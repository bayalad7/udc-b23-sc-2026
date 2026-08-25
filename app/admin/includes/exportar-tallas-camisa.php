<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

require __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$vista = trim((string) ($_GET['vista'] ?? ''));
$formato = trim((string) ($_GET['formato'] ?? ''));

if (!in_array($vista, ['resumen', 'detalle', 'detalle_personal'], true) || !in_array($formato, ['xlsx', 'pdf'], true)) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

// --- Datos -------------------------------------------------------------
// Salen del mismo include que la sección del dashboard (ver
// includes/tallas-camisa.php): el pedido al proveedor se arma con estos
// números y el archivo descargado tiene que decir exactamente lo mismo que
// la pantalla.

require_once __DIR__ . '/tallas-camisa.php';

if ($vista === 'resumen') {
    $resumenCamisa = tallasCamisaResumen($pdo);
    $tallasCamisa = $resumenCamisa['tallas'];
    $columnasCamisa = $resumenCamisa['columnas'];
    $tallasCamisaPivote = $resumenCamisa['pivote'];
    $tallasCamisaTotales = $resumenCamisa['totales'];
} elseif ($vista === 'detalle') {
    $alumnosCamisaPorGrupo = tallasCamisaDetalleAlumnos($pdo);
} else {
    $personalCamisaPorTipo = tallasCamisaDetallePersonal($pdo);
}

$nombreBase = 'tallas_camisa_' . $vista . '_' . date('Y-m-d_His');

// --- Excel ---------------------------------------------------------------

if ($formato === 'xlsx') {
    $hoja = new Spreadsheet();
    $activa = $hoja->getActiveSheet();

    if ($vista === 'resumen') {
        $activa->setTitle('Resumen por talla');
        $encabezados = array_merge(['Talla'], $columnasCamisa, ['Total']);
        $ultimaColumna = Coordinate::stringFromColumnIndex(count($encabezados));
        $activa->fromArray($encabezados, null, 'A1');
        $activa->getStyle('A1:' . $ultimaColumna . '1')->getFont()->setBold(true);

        $fila = 2;
        foreach ($tallasCamisa as $talla) {
            $renglon = [$talla];
            foreach ($columnasCamisa as $columna) {
                $renglon[] = $tallasCamisaPivote[$talla][$columna] ?? 0;
            }
            $renglon[] = $tallasCamisaTotales[$talla];
            $activa->fromArray($renglon, null, 'A' . $fila);
            $fila++;
        }
        foreach (range('A', $ultimaColumna) as $columna) {
            $activa->getColumnDimension($columna)->setAutoSize(true);
        }
    } elseif ($vista === 'detalle_personal') {
        $activa->setTitle('Detalle por trabajador');
        $activa->fromArray(['#', 'No. trabajador', 'Nombre completo', 'Corte', 'Talla'], null, 'A1');
        $activa->getStyle('A1:E1')->getFont()->setBold(true);

        $fila = 2;
        foreach ($personalCamisaPorTipo as $tipoEtiqueta => $personalDelTipo) {
            $activa->setCellValue('A' . $fila, $tipoEtiqueta);
            $activa->mergeCells('A' . $fila . ':E' . $fila);
            $activa->getStyle('A' . $fila)->getFont()->setBold(true);
            $fila++;
            foreach ($personalDelTipo as $indice => $trabajadorFila) {
                $activa->fromArray([
                    $indice + 1,
                    $trabajadorFila['numero_trabajador'],
                    $trabajadorFila['nombre_completo'],
                    $trabajadorFila['camisa_corte'],
                    $trabajadorFila['camisa_talla'],
                ], null, 'A' . $fila);
                $fila++;
            }
        }
        foreach (range('A', 'E') as $columna) {
            $activa->getColumnDimension($columna)->setAutoSize(true);
        }
    } else {
        $activa->setTitle('Detalle por alumno');
        $encabezados = ['#', 'No. cuenta', 'Nombre completo', 'Corte', 'Talla'];
        $activa->fromArray($encabezados, null, 'A1');
        $activa->getStyle('A1:E1')->getFont()->setBold(true);

        $fila = 2;
        foreach ($alumnosCamisaPorGrupo as $grupoEtiqueta => $alumnosDelGrupo) {
            $activa->setCellValue('A' . $fila, $grupoEtiqueta);
            $activa->mergeCells('A' . $fila . ':E' . $fila);
            $activa->getStyle('A' . $fila)->getFont()->setBold(true);
            $fila++;
            foreach ($alumnosDelGrupo as $indice => $alumnoFila) {
                $activa->fromArray([
                    $indice + 1,
                    $alumnoFila['numero_cuenta'],
                    $alumnoFila['nombre_completo'],
                    $alumnoFila['camisa_corte'],
                    $alumnoFila['camisa_talla'],
                ], null, 'A' . $fila);
                $fila++;
            }
        }
        foreach (range('A', 'E') as $columna) {
            $activa->getColumnDimension($columna)->setAutoSize(true);
        }
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreBase . '.xlsx"');
    header('Cache-Control: max-age=0');

    $escritor = new Xlsx($hoja);
    $escritor->save('php://output');
    exit;
}

// --- PDF -------------------------------------------------------------------

$estilos = '<style>
    body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 14px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; }
    th { background: #f1f5f9; text-align: center; }
    td.centro, th.centro { text-align: center; }
    tr.grupo td { background: #e2e8f0; font-weight: bold; }
</style>';

if ($vista === 'resumen') {
    $html = $estilos . '<h1>Tallas de camisa solicitadas — Resumen por talla</h1><table><thead><tr><th>Talla</th>';
    foreach ($columnasCamisa as $columna) {
        $html .= '<th>' . htmlspecialchars($columna, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '<th>Total</th></tr></thead><tbody>';
    foreach ($tallasCamisa as $talla) {
        $html .= '<tr><td class="centro">' . htmlspecialchars($talla, ENT_QUOTES, 'UTF-8') . '</td>';
        foreach ($columnasCamisa as $columna) {
            $html .= '<td class="centro">' . ($tallasCamisaPivote[$talla][$columna] ?? 0) . '</td>';
        }
        $html .= '<td class="centro"><strong>' . $tallasCamisaTotales[$talla] . '</strong></td></tr>';
    }
    $html .= '</tbody></table>';
} elseif ($vista === 'detalle_personal') {
    $html = $estilos . '<h1>Tallas de camisa solicitadas — Detalle por trabajador</h1><table><thead><tr>'
        . '<th class="centro">#</th><th>No. trabajador</th><th>Nombre completo</th><th class="centro">Corte</th><th class="centro">Talla</th>'
        . '</tr></thead><tbody>';
    foreach ($personalCamisaPorTipo as $tipoEtiqueta => $personalDelTipo) {
        $html .= '<tr class="grupo"><td colspan="5">' . htmlspecialchars($tipoEtiqueta, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        foreach ($personalDelTipo as $indice => $trabajadorFila) {
            $html .= '<tr>'
                . '<td class="centro">' . ($indice + 1) . '</td>'
                . '<td>' . htmlspecialchars($trabajadorFila['numero_trabajador'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars($trabajadorFila['nombre_completo'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="centro">' . htmlspecialchars($trabajadorFila['camisa_corte'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="centro">' . htmlspecialchars($trabajadorFila['camisa_talla'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
    }
    $html .= '</tbody></table>';
} else {
    $html = $estilos . '<h1>Tallas de camisa solicitadas — Detalle por alumno</h1><table><thead><tr>'
        . '<th class="centro">#</th><th>No. cuenta</th><th>Nombre completo</th><th class="centro">Corte</th><th class="centro">Talla</th>'
        . '</tr></thead><tbody>';
    foreach ($alumnosCamisaPorGrupo as $grupoEtiqueta => $alumnosDelGrupo) {
        $html .= '<tr class="grupo"><td colspan="5">' . htmlspecialchars($grupoEtiqueta, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        foreach ($alumnosDelGrupo as $indice => $alumnoFila) {
            $html .= '<tr>'
                . '<td class="centro">' . ($indice + 1) . '</td>'
                . '<td>' . htmlspecialchars($alumnoFila['numero_cuenta'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars($alumnoFila['nombre_completo'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="centro">' . htmlspecialchars($alumnoFila['camisa_corte'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="centro">' . htmlspecialchars($alumnoFila['camisa_talla'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
    }
    $html .= '</tbody></table>';
}

$opciones = new Options();
$opciones->set('isRemoteEnabled', false);

$dompdf = new Dompdf($opciones);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream($nombreBase . '.pdf', ['Attachment' => true]);
exit;
