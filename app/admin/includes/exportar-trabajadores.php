<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require_once __DIR__ . '/../../trabajadores/includes/catalogo.php';
iniciarSesionAdmin();
exigirAdmin();

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Exporta el pedido de camisas del personal en dos hojas: el detalle (a
// quién le toca cada camisa) respetando los filtros del listado, y el
// resumen por talla SIN filtrar — que es la cifra que se le pasa al
// proveedor. Mismo patrón que exportar-alumnos.php.

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$tipo = trim((string) ($_GET['tipo'] ?? ''));
$talla = trim((string) ($_GET['talla'] ?? ''));

$condiciones = [];
$parametros = [];
if (in_array($tipo, TRABAJADOR_TIPOS, true)) {
    $condiciones[] = 'tipo = :tipo';
    $parametros['tipo'] = $tipo;
}
if (isset(TRABAJADOR_CAMISA_TALLAS[$talla])) {
    $condiciones[] = 'camisa_talla = :talla';
    $parametros['talla'] = $talla;
}
if ($buscar !== '') {
    $condiciones[] = '(nombre_completo LIKE :buscar OR numero_trabajador LIKE :buscar)';
    $parametros['buscar'] = '%' . $buscar . '%';
}
$whereSql = $condiciones !== [] ? 'WHERE ' . implode(' AND ', $condiciones) : '';

$consulta = $pdo->prepare(
    "SELECT tipo, numero_trabajador, nombre_completo, camisa_corte, camisa_talla, fecha_registro
     FROM trabajadores $whereSql ORDER BY tipo, nombre_completo"
);
$consulta->execute($parametros);
$trabajadores = $consulta->fetchAll();

$resumenTallas = [];
foreach (array_keys(TRABAJADOR_CAMISA_TALLAS) as $tallaClave) {
    $resumenTallas[$tallaClave] = ['Administrativo' => 0, 'Docente' => 0];
}
$conteo = $pdo->query('SELECT camisa_talla, tipo, COUNT(*) AS n FROM trabajadores GROUP BY camisa_talla, tipo')->fetchAll();
foreach ($conteo as $filaConteo) {
    $resumenTallas[$filaConteo['camisa_talla']][$filaConteo['tipo']] = (int) $filaConteo['n'];
}

$hoja = new Spreadsheet();

// --- Hoja 1: detalle por persona -------------------------------------------

$detalle = $hoja->getActiveSheet();
$detalle->setTitle('Personal');

$encabezados = ['Tipo', 'Número de trabajador', 'Nombre completo', 'Corte de camisa', 'Talla de camisa', 'Fecha de registro'];
$detalle->fromArray($encabezados, null, 'A1');
$detalle->getStyle('A1:F1')->getFont()->setBold(true);

$fila = 2;
foreach ($trabajadores as $trabajador) {
    $detalle->fromArray([
        $trabajador['tipo'],
        $trabajador['numero_trabajador'],
        $trabajador['nombre_completo'],
        $trabajador['camisa_corte'],
        $trabajador['camisa_talla'],
        $trabajador['fecha_registro'],
    ], null, 'A' . $fila);
    $fila++;
}

foreach (range('A', 'F') as $columna) {
    $detalle->getColumnDimension($columna)->setAutoSize(true);
}

// --- Hoja 2: resumen por talla (todo el personal, sin filtros) --------------

$resumen = $hoja->createSheet();
$resumen->setTitle('Resumen por talla');
$resumen->fromArray(['Talla', 'Descripción', 'Administrativo', 'Docente', 'Total'], null, 'A1');
$resumen->getStyle('A1:E1')->getFont()->setBold(true);

$fila = 2;
$granTotal = 0;
foreach (TRABAJADOR_CAMISA_TALLAS as $tallaClave => $etiqueta) {
    $administrativos = $resumenTallas[$tallaClave]['Administrativo'];
    $docentes = $resumenTallas[$tallaClave]['Docente'];
    $granTotal += $administrativos + $docentes;
    $resumen->fromArray([$tallaClave, $etiqueta, $administrativos, $docentes, $administrativos + $docentes], null, 'A' . $fila);
    $fila++;
}
$resumen->fromArray(['', 'Total', '', '', $granTotal], null, 'A' . $fila);
$resumen->getStyle('A' . $fila . ':E' . $fila)->getFont()->setBold(true);

foreach (range('A', 'E') as $columna) {
    $resumen->getColumnDimension($columna)->setAutoSize(true);
}

$hoja->setActiveSheetIndex(0);

$nombreArchivo = 'camisas_personal_' . date('Y-m-d_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$escritor = new Xlsx($hoja);
$escritor->save('php://output');
exit;
