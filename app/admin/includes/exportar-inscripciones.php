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

require_once __DIR__ . '/cupo-eventos.php';

// Sin `id` sale el reporte general —el cupo de todas las ponencias y talleres
// con sus inscritos— desde el encabezado del modal del dashboard; con `id`,
// solo ese evento (los botones de su renglón y la ficha del evento).
//
// `formato` se quedó opcional a propósito: el botón "Exportar a Excel" de
// app/admin/public/evento.php llama a este archivo con solo el id, de cuando
// esta descarga era únicamente de Excel.
$idEvento = isset($_GET['id']) ? (int) $_GET['id'] : null;
if ($idEvento !== null && $idEvento <= 0) {
    http_response_code(400);
    exit('Falta el id del evento.');
}

$formato = trim((string) ($_GET['formato'] ?? 'xlsx'));
if (!in_array($formato, ['xlsx', 'pdf'], true)) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

$eventos = eventosConCupo($pdo, $idEvento);
if ($eventos === []) {
    http_response_code(404);
    exit($idEvento !== null ? 'Evento no encontrado.' : 'No hay eventos que reportar.');
}

$inscritosPorEvento = inscritosDeEventos($pdo, $idEvento);

// --- Nombre del archivo ----------------------------------------------------
// Sin acentos ni espacios: viaja en una cabecera HTTP y lo abre gente en
// Windows, Android y iOS. El strtr explícito, en vez de
// iconv(..., 'ASCII//TRANSLIT'), porque el resultado de iconv depende de la
// libc (ver includes/exportar-llave.php).

$nombreBase = 'inscripciones';
if ($idEvento !== null) {
    $sinAcentos = strtr((string) $eventos[0]['nombre'], [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $sufijo = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $sinAcentos), '_'));
    $nombreBase = 'inscripciones_' . ($sufijo !== '' ? $sufijo : 'evento_' . $idEvento);
}
$nombreBase .= '_' . date('Y-m-d_His');

// --- Excel -----------------------------------------------------------------
// Dos hojas: "Cupo" es un renglón por evento (lo que se revisa para saber qué
// falta por llenar) e "Inscritos" el detalle alumno por alumno, como tabla
// corrida para poder filtrarla y ordenarla en Excel. Con un solo evento la
// hoja de cupo trae ese único renglón, así que el archivo individual sigue
// diciendo de qué evento se trata sin abrir la otra pestaña.

if ($formato === 'xlsx') {
    $hoja = new Spreadsheet();

    $hojaCupo = $hoja->getActiveSheet();
    $hojaCupo->setTitle('Cupo');
    $hojaCupo->fromArray(
        ['Día', 'Tipo', 'Evento', 'Horario', 'Espacio', 'Facilitador', 'Responsable',
            'Cupo máximo', 'Ocupados', 'Disponibles', '% ocupado'],
        null,
        'A1'
    );
    $hojaCupo->getStyle('A1:K1')->getFont()->setBold(true);

    $hojaInscritos = $hoja->createSheet();
    $hojaInscritos->setTitle('Inscritos');
    $hojaInscritos->fromArray(
        ['Día', 'Evento', 'No. cuenta', 'Nombre completo', 'Grado', 'Grupo', 'Origen', 'Registró',
            'Estado', 'Entrada', 'Punto (entrada)', 'Escaneó (entrada)',
            'Salida', 'Punto (salida)', 'Escaneó (salida)'],
        null,
        'A1'
    );
    $hojaInscritos->getStyle('A1:O1')->getFont()->setBold(true);

    $filaCupo = 2;
    $filaInscritos = 2;
    foreach ($eventos as $evento) {
        $hojaCupo->fromArray([
            $evento['dia_label'],
            ucfirst((string) $evento['tipo']),
            $evento['nombre'],
            $evento['horario'],
            $evento['espacio'],
            $evento['facilitador'],
            $evento['responsable'],
            $evento['cupo_maximo'],
            $evento['ocupados'],
            $evento['cupo_disponible'],
            $evento['porcentaje'] . '%',
        ], null, 'A' . $filaCupo);
        $filaCupo++;

        foreach ($inscritosPorEvento[$evento['id']] ?? [] as $inscrito) {
            $hojaInscritos->fromArray([
                $evento['dia_label'],
                $evento['nombre'],
                $inscrito['numero_cuenta'],
                $inscrito['nombre_completo'],
                $inscrito['grado'] . '°',
                $inscrito['grupo'],
                $inscrito['origen_label'],
                $inscrito['registrado_por'],
                $inscrito['estado'],
                $inscrito['hora_entrada'] ?? '',
                $inscrito['punto_control_entrada'] ?? '',
                $inscrito['escaneado_por_entrada'] ?? '',
                $inscrito['hora_salida'] ?? '',
                $inscrito['punto_control_salida'] ?? '',
                $inscrito['escaneado_por_salida'] ?? '',
            ], null, 'A' . $filaInscritos);
            $filaInscritos++;
        }
    }

    foreach (range('A', 'K') as $columna) {
        $hojaCupo->getColumnDimension($columna)->setAutoSize(true);
    }
    foreach (range('A', 'O') as $columna) {
        $hojaInscritos->getColumnDimension($columna)->setAutoSize(true);
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
// Un evento por hoja: es la lista que se le entrega al ponente o tallerista
// para pasar asistencia, así que cada hoja repite su encabezado y se vale
// sola. En el reporte general va además una primera hoja con el cupo de todos
// los eventos, que es la vista que usa el staff para saber qué falta por
// llenar.

$estilos = '<style>
    body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 14px; margin-bottom: 2px; }
    p.resumen { margin: 0 0 10px; color: #64748b; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; }
    th { background: #f1f5f9; text-align: center; }
    td.centro, th.centro { text-align: center; }
    tr.dia td { background: #e2e8f0; font-weight: bold; }
    tr.vacio td { color: #64748b; }
    p.pie { margin-top: 10px; color: #64748b; font-size: 10px; }
    div.hoja { page-break-before: always; }
    div.hoja:first-of-type { page-break-before: avoid; }
</style>';

$html = $estilos;

if ($idEvento === null) {
    $html .= '<div class="hoja"><h1>Cupo ocupado por evento</h1>'
        . '<p class="resumen">' . count($eventos) . ' evento' . (count($eventos) === 1 ? '' : 's')
        . ' con inscripción individual (ponencias y talleres del Día Académico y del Día Cultural).</p>'
        . '<table><thead><tr><th>Evento</th><th class="centro">Tipo</th><th class="centro">Horario</th>'
        . '<th>Espacio</th><th class="centro">Cupo</th><th class="centro">Libres</th><th class="centro">% ocupado</th>'
        . '</tr></thead><tbody>';

    $diaImpreso = null;
    foreach ($eventos as $evento) {
        if ($evento['dia'] !== $diaImpreso) {
            $diaImpreso = $evento['dia'];
            $html .= '<tr class="dia"><td colspan="7">'
                . htmlspecialchars((string) $evento['dia_label'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $html .= '<tr>'
            . '<td>' . htmlspecialchars((string) $evento['nombre'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="centro">' . htmlspecialchars(ucfirst((string) $evento['tipo']), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="centro">' . htmlspecialchars((string) $evento['horario'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars((string) $evento['espacio'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="centro">' . $evento['ocupados'] . '/' . $evento['cupo_maximo'] . '</td>'
            . '<td class="centro">' . $evento['cupo_disponible'] . '</td>'
            . '<td class="centro">' . $evento['porcentaje'] . '%</td>'
            . '</tr>';
    }

    $html .= '</tbody></table>'
        . '<p class="pie">Generado el ' . date('d/m/Y H:i') . ' desde el panel de administración. '
        . 'Las hojas siguientes traen la lista de inscritos de cada evento.</p></div>';
}

foreach ($eventos as $evento) {
    $inscritos = $inscritosPorEvento[$evento['id']] ?? [];
    $resumen = $evento['dia_label'] . ' · ' . ucfirst((string) $evento['tipo'])
        . ' · ' . $evento['horario'] . ' · ' . $evento['espacio']
        . ' · Facilitador: ' . $evento['facilitador']
        . ' · Responsable: ' . $evento['responsable']
        . ' · ' . $evento['ocupados'] . '/' . $evento['cupo_maximo'] . ' de cupo (' . $evento['porcentaje'] . '%)';

    $html .= '<div class="hoja">'
        . '<h1>' . htmlspecialchars((string) $evento['nombre'], ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<p class="resumen">' . htmlspecialchars($resumen, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<table><thead><tr>'
        . '<th class="centro">#</th><th>Alumno</th><th class="centro">No. cuenta</th>'
        . '<th class="centro">Grado y grupo</th><th class="centro">Origen</th>'
        . '<th class="centro">Estado</th><th class="centro">Entrada</th><th class="centro">Salida</th>'
        . '</tr></thead><tbody>';

    if ($inscritos === []) {
        $html .= '<tr class="vacio"><td colspan="8">Todavía no hay nadie inscrito en este evento.</td></tr>';
    }

    foreach ($inscritos as $indice => $inscrito) {
        $html .= '<tr>'
            . '<td class="centro">' . ($indice + 1) . '</td>'
            . '<td>' . htmlspecialchars((string) $inscrito['nombre_completo'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="centro">' . htmlspecialchars((string) $inscrito['numero_cuenta'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="centro">' . htmlspecialchars((string) $inscrito['grado_grupo'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="centro">' . htmlspecialchars((string) $inscrito['origen_label'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="centro">' . htmlspecialchars((string) $inscrito['estado'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="centro">' . ($inscrito['hora_entrada'] !== null ? htmlspecialchars((string) $inscrito['hora_entrada'], ENT_QUOTES, 'UTF-8') : '—') . '</td>'
            . '<td class="centro">' . ($inscrito['hora_salida'] !== null ? htmlspecialchars((string) $inscrito['hora_salida'], ENT_QUOTES, 'UTF-8') : '—') . '</td>'
            . '</tr>';
    }

    $html .= '</tbody></table>'
        . '<p class="pie">Generado el ' . date('d/m/Y H:i') . ' desde el panel de administración. '
        . 'Es una foto del momento: quien se inscriba o llegue después no aparece en esta hoja.</p>'
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
