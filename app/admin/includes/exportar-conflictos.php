<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

require __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/conflictos.php';

// Descarga del reporte de conflictos de inscripción (ver includes/conflictos.php
// para la regla de qué cuenta como problema). Dos vistas en el mismo archivo,
// las mismas de la pantalla: por pareja de actividades —para decidir qué mover—
// y alumno por alumno —para avisarle a cada quien—.

$formato = trim((string) ($_GET['formato'] ?? ''));
if (!in_array($formato, ['xlsx', 'pdf'], true)) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

$reporte = conflictosDeInscripcion($pdo);
$porActividad = conflictosPorActividad($reporte);
$nombreBase = 'conflictos_inscripcion_' . date('Y-m-d_His');

/** Una actividad en una celda: su nombre y, debajo, qué es y a qué hora. */
function conflictoTextoCompromiso(array $compromiso): string
{
    return $compromiso['nombre'] . ' (' . ucfirst($compromiso['tipo']) . ' · '
        . $compromiso['horario'] . ' · ' . $compromiso['detalle'] . ')';
}

if ($formato === 'xlsx') {
    $hoja = new Spreadsheet();

    $hojaPares = $hoja->getActiveSheet();
    $hojaPares->setTitle('Qué se estorba');
    $hojaPares->fromArray(
        ['Día', 'Horario', 'Actividad', 'Se estorba con', 'Motivo', 'Alumnos afectados'],
        null,
        'A1'
    );
    $hojaPares->getStyle('A1:F1')->getFont()->setBold(true);

    $fila = 2;
    foreach ($porActividad as $par) {
        $hojaPares->fromArray([
            $par['dia_label'],
            $par['horario'],
            conflictoTextoCompromiso($par['a']),
            conflictoTextoCompromiso($par['b']),
            $par['etiqueta'],
            $par['alumnos'],
        ], null, 'A' . $fila);
        $fila++;
    }
    foreach (range('A', 'F') as $columna) {
        $hojaPares->getColumnDimension($columna)->setAutoSize(true);
    }

    $hojaAlumnos = $hoja->createSheet();
    $hojaAlumnos->setTitle('Alumnos');
    $hojaAlumnos->fromArray(
        ['No. cuenta', 'Alumno', 'Grado y grupo', 'Correo institucional', 'Día', 'Horario',
            'Tiene esto', 'Y también esto', 'Motivo'],
        null,
        'A1'
    );
    $hojaAlumnos->getStyle('A1:I1')->getFont()->setBold(true);

    $fila = 2;
    foreach ($reporte['alumnos'] as $alumno) {
        foreach ($alumno['conflictos'] as $conflicto) {
            $hojaAlumnos->fromArray([
                $alumno['numero_cuenta'],
                $alumno['nombre_completo'],
                $alumno['grado_grupo'],
                $alumno['correo_institucional'],
                $conflicto['dia_label'],
                $conflicto['horario'],
                conflictoTextoCompromiso($conflicto['a']),
                conflictoTextoCompromiso($conflicto['b']),
                $conflicto['etiqueta'],
            ], null, 'A' . $fila);
            $fila++;
        }
    }
    foreach (range('A', 'I') as $columna) {
        $hojaAlumnos->getColumnDimension($columna)->setAutoSize(true);
    }
    $hoja->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreBase . '.xlsx"');
    header('Cache-Control: max-age=0');

    (new Xlsx($hoja))->save('php://output');
    exit;
}

// --- PDF -------------------------------------------------------------------
// Horizontal: cada renglón lleva dos actividades con su nombre completo y en
// vertical se parten en tiras ilegibles.

$escapar = static fn(string $texto): string => htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

$estilos = '<style>
    body { font-family: sans-serif; font-size: 10px; color: #0f172a; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    h2 { font-size: 13px; margin: 16px 0 4px; }
    p.resumen { margin: 0 0 10px; color: #475569; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 5px; vertical-align: top; }
    th { background: #f1f5f9; text-align: left; font-size: 9px; text-transform: uppercase; }
    td.centro, th.centro { text-align: center; }
    span.detalle { display: block; color: #64748b; font-size: 9px; }
    p.pie { margin-top: 10px; color: #64748b; font-size: 9px; }
    p.limpio { border: 1px solid #cbd5e1; padding: 10px; font-size: 12px; }
</style>';

$html = $estilos . '<h1>Conflictos de inscripción</h1>'
    . '<p class="resumen">' . count($reporte['alumnos']) . ' alumnos con choque, de un padrón de '
    . $reporte['padron'] . ' · ' . $reporte['total_conflictos'] . ' choques en '
    . count($porActividad) . ' parejas de actividades. Impreso el ' . date('d/m/Y H:i') . '.</p>';

if ($reporte['alumnos'] === []) {
    $html .= '<p class="limpio">Ningún alumno tiene dos inscripciones encimadas. Si acabas de cambiar un '
        . 'evento de día, revisa que el cambio ya esté guardado: el reporte compara contra el catálogo '
        . 'actual.</p>';
} else {
    $html .= '<h2>Qué se estorba con qué</h2>'
        . '<table><thead><tr><th>Día y horario</th><th>Actividad</th><th>Se estorba con</th>'
        . '<th>Motivo</th><th class="centro">Alumnos</th></tr></thead><tbody>';
    foreach ($porActividad as $par) {
        $html .= '<tr>'
            . '<td>' . $escapar($par['dia_label']) . '<span class="detalle">' . $escapar($par['horario']) . '</span></td>'
            . '<td>' . $escapar($par['a']['nombre']) . '<span class="detalle">' . $escapar($par['a']['detalle']) . '</span></td>'
            . '<td>' . $escapar($par['b']['nombre']) . '<span class="detalle">' . $escapar($par['b']['detalle']) . '</span></td>'
            . '<td>' . $escapar($par['etiqueta']) . '</td>'
            . '<td class="centro">' . $par['alumnos'] . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table>';

    $html .= '<h2>Alumno por alumno</h2>'
        . '<table><thead><tr><th>Alumno</th><th class="centro">Grupo</th><th>Día y horario</th>'
        . '<th>Tiene esto…</th><th>…y también esto</th></tr></thead><tbody>';
    foreach ($reporte['alumnos'] as $alumno) {
        foreach ($alumno['conflictos'] as $conflicto) {
            $html .= '<tr>'
                . '<td>' . $escapar((string) $alumno['nombre_completo'])
                . '<span class="detalle">' . $escapar((string) $alumno['numero_cuenta']) . '</span></td>'
                . '<td class="centro">' . $escapar((string) $alumno['grado_grupo']) . '</td>'
                . '<td>' . $escapar($conflicto['dia_label'])
                . '<span class="detalle">' . $escapar($conflicto['horario']) . '</span></td>'
                . '<td>' . $escapar($conflicto['a']['nombre'])
                . '<span class="detalle">' . $escapar($conflicto['a']['detalle']) . '</span></td>'
                . '<td>' . $escapar($conflicto['b']['nombre'])
                . '<span class="detalle">' . $escapar($conflicto['b']['detalle']) . '</span></td>'
                . '</tr>';
        }
    }
    $html .= '</tbody></table>';
}

$html .= '<p class="pie">El reporte compara el catálogo de eventos y competiciones como está en este '
    . 'momento: si todavía falta mover un evento de día, ese choque aún no aparece aquí.</p>';

$opciones = new Options();
$opciones->set('isRemoteEnabled', false);

$dompdf = new Dompdf($opciones);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream($nombreBase . '.pdf', ['Attachment' => true]);
exit;
