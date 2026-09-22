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

require_once __DIR__ . '/sin-inscripcion.php';

$formato = trim((string) ($_GET['formato'] ?? ''));
if (!in_array($formato, ['xlsx', 'pdf'], true)) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

// Tres alcances, los mismos que ofrece la tabla del dashboard: sin parámetros,
// el reporte completo; `bloque=academico-0900-1000`, una sola franja horaria;
// `dia=academico`, quienes no se inscribieron a NADA de ese día.
$bloqueSolicitado = trim((string) ($_GET['bloque'] ?? ''));
$diaSolicitado = trim((string) ($_GET['dia'] ?? ''));
if ($bloqueSolicitado !== '' && $diaSolicitado !== '') {
    http_response_code(400);
    exit('Pide un bloque o un día, no los dos.');
}

$reporte = alumnosSinInscripcion($pdo);

// Cada "listado" es un encabezado más su lista de alumnos; el archivo trae uno
// (bloque o día) o todos, y de ahí en adelante Excel y PDF los recorren igual.
// Se arman día por día —sus bloques y luego su renglón de cierre— para que el
// archivo se lea en el mismo orden que la tabla del dashboard.
$listados = [];
foreach ($reporte['dias'] as $dia) {
    foreach ($reporte['bloques'] as $bloque) {
        if ($bloque['dia'] !== $dia['dia']) {
            continue;
        }
        $listados[] = [
            'clave' => $bloque['clave'],
            'dia' => $bloque['dia'],
            'dia_label' => $bloque['dia_label'],
            'bloque' => $bloque['horario'],
            'detalle' => $bloque['actividades'] . ' actividad' . ($bloque['actividades'] === 1 ? '' : 'es') . ': ' . $bloque['nombres'],
            'alumnos' => $bloque['alumnos'],
        ];
    }
    $listados[] = [
        'clave' => 'dia-' . $dia['dia'],
        'dia' => $dia['dia'],
        'dia_label' => $dia['dia_label'],
        'bloque' => 'En todo el día',
        'detalle' => 'No se inscribieron a ninguna actividad del día',
        'alumnos' => $dia['alumnos'],
    ];
}

$claveSolicitada = null;
if ($bloqueSolicitado !== '') {
    $claveSolicitada = $bloqueSolicitado;
} elseif ($diaSolicitado !== '') {
    $claveSolicitada = 'dia-' . $diaSolicitado;
}

if ($claveSolicitada !== null) {
    $listados = array_values(array_filter(
        $listados,
        static fn(array $listado): bool => $listado['clave'] === $claveSolicitada
    ));
    if ($listados === []) {
        http_response_code(404);
        exit('Ese bloque no existe en el itinerario.');
    }
}

$nombreBase = 'sin_inscripcion'
    . ($claveSolicitada !== null ? '_' . str_replace('-', '_', $claveSolicitada) : '')
    . '_' . date('Y-m-d_His');

// --- Excel -----------------------------------------------------------------
// Dos hojas: "Resumen" es un renglón por bloque (cuánta gente se está quedando
// fuera y de qué grupos) y "Alumnos" la lista nominal corrida, con el bloque en
// una columna para poder filtrarla en Excel.

if ($formato === 'xlsx') {
    $hoja = new Spreadsheet();

    $hojaResumen = $hoja->getActiveSheet();
    $hojaResumen->setTitle('Resumen');
    $hojaResumen->fromArray(
        ['Día', 'Bloque', 'Actividades', 'Sin inscripción', 'Padrón', '% del padrón'],
        null,
        'A1'
    );
    $hojaResumen->getStyle('A1:F1')->getFont()->setBold(true);

    $hojaAlumnos = $hoja->createSheet();
    $hojaAlumnos->setTitle('Alumnos');
    $hojaAlumnos->fromArray(
        ['Día', 'Bloque', 'No. cuenta', 'Nombre completo', 'Grado', 'Grupo', 'Correo institucional'],
        null,
        'A1'
    );
    $hojaAlumnos->getStyle('A1:G1')->getFont()->setBold(true);

    $filaResumen = 2;
    $filaAlumnos = 2;
    foreach ($listados as $listado) {
        $total = count($listado['alumnos']);
        $hojaResumen->fromArray([
            $listado['dia_label'],
            $listado['bloque'],
            $listado['detalle'],
            $total,
            $reporte['total_alumnos'],
            ($reporte['total_alumnos'] > 0 ? (int) round($total / $reporte['total_alumnos'] * 100) : 0) . '%',
        ], null, 'A' . $filaResumen);
        $filaResumen++;

        foreach ($listado['alumnos'] as $alumno) {
            $hojaAlumnos->fromArray([
                $listado['dia_label'],
                $listado['bloque'],
                $alumno['numero_cuenta'],
                $alumno['nombre_completo'],
                $alumno['grado'] . '°',
                $alumno['grupo'],
                $alumno['correo_institucional'],
            ], null, 'A' . $filaAlumnos);
            $filaAlumnos++;
        }
    }

    foreach (range('A', 'F') as $columna) {
        $hojaResumen->getColumnDimension($columna)->setAutoSize(true);
    }
    foreach (range('A', 'G') as $columna) {
        $hojaAlumnos->getColumnDimension($columna)->setAutoSize(true);
    }
    $hoja->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreBase . '.xlsx"');
    header('Cache-Control: max-age=0');

    $escritor = new Xlsx($hoja);
    $escritor->save('php://output');
    exit;
}

// --- PDF -------------------------------------------------------------------
// Un bloque por hoja y, dentro, los alumnos agrupados por grado y grupo: así es
// como se le entrega la lista a cada maestro de grupo para ir a buscarlos.

$estilos = '<style>
    body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 14px; margin-bottom: 2px; }
    p.resumen { margin: 0 0 10px; color: #64748b; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; }
    th { background: #f1f5f9; text-align: center; }
    td.centro, th.centro { text-align: center; }
    tr.grupo td { background: #e2e8f0; font-weight: bold; }
    tr.vacio td { color: #64748b; }
    p.pie { margin-top: 10px; color: #64748b; font-size: 10px; }
    div.hoja { page-break-before: always; }
    div.hoja:first-of-type { page-break-before: avoid; }
</style>';

$html = $estilos;

if ($claveSolicitada === null) {
    $html .= '<div class="hoja"><h1>Alumnos sin inscripción</h1>'
        . '<p class="resumen">Padrón de ' . $reporte['total_alumnos'] . ' alumnos. Un renglón por bloque '
        . '(franja horaria con actividad) y, al cerrar cada día, quienes no se inscribieron a nada de ese día. '
        . 'El Escenario de Talentos no cuenta: no reparte cupo y un alumno puede tener varias participaciones.</p>'
        . '<table><thead><tr><th>Día</th><th>Bloque</th><th class="centro">Sin inscripción</th>'
        . '<th class="centro">% del padrón</th></tr></thead><tbody>';

    foreach ($listados as $listado) {
        $total = count($listado['alumnos']);
        $porcentaje = $reporte['total_alumnos'] > 0 ? (int) round($total / $reporte['total_alumnos'] * 100) : 0;
        $html .= '<tr>'
            . '<td>' . htmlspecialchars((string) $listado['dia_label'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars((string) $listado['bloque'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="centro">' . $total . '</td>'
            . '<td class="centro">' . $porcentaje . '%</td>'
            . '</tr>';
    }

    $html .= '</tbody></table>'
        . '<p class="pie">Generado el ' . date('d/m/Y H:i') . ' desde el panel de administración. '
        . 'Las hojas siguientes traen la lista nominal de cada bloque.</p></div>';
}

foreach ($listados as $listado) {
    $total = count($listado['alumnos']);
    $porcentaje = $reporte['total_alumnos'] > 0 ? (int) round($total / $reporte['total_alumnos'] * 100) : 0;

    $html .= '<div class="hoja">'
        . '<h1>' . htmlspecialchars((string) $listado['dia_label'] . ' · ' . $listado['bloque'], ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<p class="resumen">' . $total . ' de ' . $reporte['total_alumnos'] . ' alumnos sin inscripción ('
        . $porcentaje . '%) · ' . htmlspecialchars((string) $listado['detalle'], ENT_QUOTES, 'UTF-8') . '</p>'
        . '<table><thead><tr><th class="centro">#</th><th>Alumno</th><th class="centro">No. cuenta</th>'
        . '<th>Correo institucional</th></tr></thead><tbody>';

    if ($listado['alumnos'] === []) {
        $html .= '<tr class="vacio"><td colspan="4">Todos los alumnos del padrón tienen al menos una inscripción en este bloque.</td></tr>';
    }

    foreach (sinInscripcionAgrupado($listado['alumnos']) as $grupoEtiqueta => $alumnosDelGrupo) {
        $html .= '<tr class="grupo"><td colspan="4">' . htmlspecialchars((string) $grupoEtiqueta, ENT_QUOTES, 'UTF-8')
            . ' — ' . count($alumnosDelGrupo) . '</td></tr>';
        foreach ($alumnosDelGrupo as $indice => $alumno) {
            $html .= '<tr>'
                . '<td class="centro">' . ($indice + 1) . '</td>'
                . '<td>' . htmlspecialchars((string) $alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="centro">' . htmlspecialchars((string) $alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) $alumno['correo_institucional'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
    }

    $html .= '</tbody></table>'
        . '<p class="pie">Generado el ' . date('d/m/Y H:i') . ' desde el panel de administración. '
        . 'Es una foto del momento: quien se inscriba después deja de aparecer en esta lista.</p>'
        . '</div>';
}

$opciones = new Options();
$opciones->set('isRemoteEnabled', false);

$dompdf = new Dompdf($opciones);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream($nombreBase . '.pdf', ['Attachment' => true]);
exit;
