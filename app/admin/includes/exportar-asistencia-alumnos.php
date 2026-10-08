<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

require __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/asistencia-alumnos.php';

$formato = trim((string) ($_GET['formato'] ?? ''));
if (!in_array($formato, ['xlsx', 'pdf'], true)) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

$reporte = asistenciaAlumnos($pdo);
$columnas = $reporte['columnas'];
$nombreBase = 'asistencia_alumnos_' . date('Y-m-d_His');

// --- Excel -----------------------------------------------------------------
// Hoja "Asistencia": un renglón por alumno y, por cada columna del reporte,
// sus horas en celdas separadas (Entrada / Salida, más la Actividad en los
// bloques) para que se puedan filtrar y ordenar. Las horas van como texto
// "08:02": son la hora del escaneo, no una duración que alguien vaya a sumar.
// Hoja "Resumen": cuántos debían presentarse y cuántos se presentaron por
// columna.

if ($formato === 'xlsx') {
    $libro = new Spreadsheet();
    $hoja = $libro->getActiveSheet();
    $hoja->setTitle('Asistencia');

    // Encabezado de dos pisos: arriba el día y la columna (combinadas sobre
    // sus sub-columnas), abajo Actividad / Entrada / Salida.
    $hoja->fromArray(['Grado y grupo', 'No. cuenta', 'Nombre completo'], null, 'A1');
    foreach (['A', 'B', 'C'] as $letra) {
        $hoja->mergeCells($letra . '1:' . $letra . '2');
    }
    $indice = 4;
    foreach ($columnas as $columna) {
        $subcolumnas = $columna['tipo'] === 'plantel' ? ['Entrada', 'Salida'] : ['Actividad', 'Entrada', 'Salida'];
        $inicio = Coordinate::stringFromColumnIndex($indice);
        $fin = Coordinate::stringFromColumnIndex($indice + count($subcolumnas) - 1);
        $hoja->setCellValue($inicio . '1', $columna['dia_label'] . ' · ' . $columna['etiqueta'] . ' (' . $columna['horario'] . ')');
        $hoja->mergeCells($inicio . '1:' . $fin . '1');
        $hoja->fromArray($subcolumnas, null, $inicio . '2');
        $indice += count($subcolumnas);
    }
    $ultima = Coordinate::stringFromColumnIndex($indice);
    $hoja->setCellValue($ultima . '1', 'Asistencias');
    $hoja->mergeCells($ultima . '1:' . $ultima . '2');
    $hoja->getStyle('A1:' . $ultima . '2')->getFont()->setBold(true);
    $hoja->getStyle('A1:' . $ultima . '2')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);

    $fila = 3;
    foreach ($reporte['grupos'] as $grupoEtiqueta => $alumnos) {
        foreach ($alumnos as $alumno) {
            $renglon = [$grupoEtiqueta, $alumno['numero_cuenta'], $alumno['nombre_completo']];
            foreach ($columnas as $columna) {
                $registros = $alumno['celdas'][$columna['clave']]['registros'];
                $entradas = implode(' · ', array_map(static fn(array $r): string => $r['entrada'] ?? '', $registros));
                $salidas = implode(' · ', array_map(static fn(array $r): string => $r['salida'] ?? '', $registros));
                if ($columna['tipo'] === 'bloque') {
                    $renglon[] = implode(' · ', array_column($registros, 'nombre'));
                }
                // Con una sola actividad (lo normal) los separadores sobran:
                // una celda " · " vacía se filtraría como si trajera algo.
                $renglon[] = trim($entradas, ' ·');
                $renglon[] = trim($salidas, ' ·');
            }
            $renglon[] = $alumno['asistidas'] . ' de ' . $alumno['esperadas'];
            // Columna por columna en texto explícito: un número de cuenta o una
            // hora "08:02" no deben convertirse en número ni en fracción de día.
            foreach ($renglon as $posicion => $valor) {
                $hoja->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($posicion + 1) . $fila,
                    (string) $valor,
                    DataType::TYPE_STRING
                );
            }
            $fila++;
        }
    }

    for ($i = 1; $i <= $indice; $i++) {
        $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }
    $hoja->freezePane('D3');

    $resumen = $libro->createSheet();
    $resumen->setTitle('Resumen');
    $resumen->fromArray(['Día', 'Columna', 'Horario', 'Debían presentarse', 'Se presentaron', '%'], null, 'A1');
    $resumen->getStyle('A1:F1')->getFont()->setBold(true);
    $filaResumen = 2;
    foreach ($columnas as $columna) {
        $total = $reporte['totales'][$columna['clave']];
        $resumen->fromArray([
            $columna['dia_label'],
            $columna['etiqueta'],
            $columna['horario'],
            $total['esperados'],
            $total['asistieron'],
            ($total['esperados'] > 0 ? (int) round($total['asistieron'] / $total['esperados'] * 100) : 0) . '%',
        ], null, 'A' . $filaResumen);
        $filaResumen++;
    }
    foreach (range('A', 'F') as $letra) {
        $resumen->getColumnDimension($letra)->setAutoSize(true);
    }
    $libro->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreBase . '.xlsx"');
    header('Cache-Control: max-age=0');

    (new Xlsx($libro))->save('php://output');
    exit;
}

// --- PDF -------------------------------------------------------------------
// Hoja 1 el resumen por columna y luego una hoja por grado y grupo —así se le
// entrega a cada maestro la de su grupo—, en horizontal porque son 3 columnas
// de datos más una por cada toma de asistencia.

$e = static fn(string $texto): string => htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

$html = '<style>
    body { font-family: sans-serif; font-size: 8px; color: #1e293b; }
    h1 { font-size: 13px; margin-bottom: 2px; }
    p.resumen { margin: 0 0 8px; color: #64748b; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 3px 4px; vertical-align: top; }
    th { background: #f1f5f9; text-align: center; font-size: 8px; }
    th.izquierda { text-align: left; }
    td.centro { text-align: center; }
    span.actividad { display: block; color: #64748b; font-size: 7px; }
    span.hora { display: block; }
    .asistio { color: #047857; }
    .falto { color: #b91c1c; }
    .sin { color: #94a3b8; text-align: center; }
    p.pie { margin-top: 8px; color: #64748b; font-size: 8px; }
    div.hoja { page-break-before: always; }
    div.hoja:first-of-type { page-break-before: avoid; }
</style>';

$html .= '<div class="hoja"><h1>Asistencia por alumno — resumen</h1>'
    . '<p class="resumen">Padrón de ' . $reporte['total_alumnos'] . ' alumnos. "Plantel" es la asistencia general del día '
    . '(se le pide a todo el padrón); cada bloque cuenta solo a los inscritos a alguna de sus actividades. '
    . 'Las hojas siguientes traen el detalle de cada grado y grupo.</p>'
    . '<table><thead><tr><th class="izquierda">Día</th><th class="izquierda">Columna</th><th>Horario</th>'
    . '<th>Debían presentarse</th><th>Se presentaron</th><th>%</th></tr></thead><tbody>';
foreach ($columnas as $columna) {
    $total = $reporte['totales'][$columna['clave']];
    $porcentaje = $total['esperados'] > 0 ? (int) round($total['asistieron'] / $total['esperados'] * 100) : 0;
    $html .= '<tr><td>' . $e($columna['dia_label']) . '</td><td>' . $e($columna['etiqueta']) . '</td>'
        . '<td class="centro">' . $e($columna['horario']) . '</td>'
        . '<td class="centro">' . $total['esperados'] . '</td>'
        . '<td class="centro">' . $total['asistieron'] . '</td>'
        . '<td class="centro">' . $porcentaje . '%</td></tr>';
}
$html .= '</tbody></table>'
    . '<p class="pie">Generado el ' . date('d/m/Y H:i') . ' desde el panel de administración.</p></div>';

foreach ($reporte['grupos'] as $grupoEtiqueta => $alumnos) {
    $html .= '<div class="hoja">'
        . '<h1>Asistencia por alumno — ' . $e((string) $grupoEtiqueta) . '</h1>'
        . '<p class="resumen">' . count($alumnos) . ' alumnos. Cada celda trae la hora de entrada y la de salida; '
        . '"Sin entrada" = estaba inscrito (o se le esperaba en el plantel) y no se le escaneó; '
        . 'guion = no estaba inscrito a nada de ese bloque.</p>'
        . '<table><thead><tr>'
        . '<th rowspan="2" class="izquierda">No. cuenta</th><th rowspan="2" class="izquierda">Alumno</th>';
    foreach ($reporte['dias'] as $dia) {
        $html .= '<th colspan="' . $dia['columnas'] . '">' . $e($dia['dia_label']) . '</th>';
    }
    $html .= '<th rowspan="2">Asistió</th></tr><tr>';
    foreach ($columnas as $columna) {
        $html .= '<th>' . $e($columna['etiqueta']) . '<br>' . $e($columna['horario']) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($alumnos as $alumno) {
        $html .= '<tr><td>' . $e((string) $alumno['numero_cuenta']) . '</td>'
            . '<td>' . $e((string) $alumno['nombre_completo']) . '</td>';
        foreach ($columnas as $columna) {
            $celda = $alumno['celdas'][$columna['clave']];
            if ($celda['estado'] === 'sin') {
                $html .= '<td class="sin">—</td>';
                continue;
            }
            $html .= '<td>';
            foreach ($celda['registros'] as $registro) {
                if ($registro['nombre'] !== '') {
                    $html .= '<span class="actividad">' . $e($registro['nombre']) . '</span>';
                }
                $html .= '<span class="hora ' . ($registro['entrada'] !== null ? 'asistio' : 'falto') . '">'
                    . $e(asistenciaTexto($registro)) . '</span>';
            }
            $html .= '</td>';
        }
        $html .= '<td class="centro">' . $alumno['asistidas'] . ' de ' . $alumno['esperadas'] . '</td></tr>';
    }

    $html .= '</tbody></table>'
        . '<p class="pie">Generado el ' . date('d/m/Y H:i') . ' desde el panel de administración.</p></div>';
}

$opciones = new Options();
$opciones->set('isRemoteEnabled', false);

$dompdf = new Dompdf($opciones);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream($nombreBase . '.pdf', ['Attachment' => true]);
exit;
