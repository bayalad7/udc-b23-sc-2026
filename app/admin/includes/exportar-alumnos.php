<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$grado = trim((string) ($_GET['grado'] ?? ''));
$grupo = trim((string) ($_GET['grupo'] ?? ''));
$buscar = trim((string) ($_GET['buscar'] ?? ''));

$condiciones = [];
$parametros = [];
if (in_array($grado, ['1', '3', '5'], true)) {
    $condiciones[] = 'grado = :grado';
    $parametros['grado'] = $grado;
}
if (in_array($grupo, ['A', 'B', 'C'], true)) {
    $condiciones[] = 'grupo = :grupo';
    $parametros['grupo'] = $grupo;
}
if ($buscar !== '') {
    $condiciones[] = '(nombre_completo LIKE :buscar OR numero_cuenta LIKE :buscar)';
    $parametros['buscar'] = '%' . $buscar . '%';
}
$whereSql = $condiciones !== [] ? 'WHERE ' . implode(' AND ', $condiciones) : '';

$consulta = $pdo->prepare(
    "SELECT numero_cuenta, nombre_completo, grado, grupo, correo_institucional, camisa_corte, camisa_talla,
            credencial_generada, fecha_registro
     FROM alumnos $whereSql ORDER BY grado, grupo, nombre_completo"
);
$consulta->execute($parametros);
$alumnos = $consulta->fetchAll();

$hoja = new Spreadsheet();
$activa = $hoja->getActiveSheet();
$activa->setTitle('Alumnos');

$encabezados = ['Número de cuenta', 'Nombre completo', 'Grado', 'Grupo', 'Correo institucional', 'Corte de camisa', 'Talla de camisa', 'Credencial generada', 'Fecha de registro'];
$activa->fromArray($encabezados, null, 'A1');
$activa->getStyle('A1:I1')->getFont()->setBold(true);

$fila = 2;
foreach ($alumnos as $alumnoFila) {
    $activa->fromArray([
        $alumnoFila['numero_cuenta'],
        $alumnoFila['nombre_completo'],
        $alumnoFila['grado'] . '°',
        $alumnoFila['grupo'],
        $alumnoFila['correo_institucional'],
        $alumnoFila['camisa_corte'],
        $alumnoFila['camisa_talla'],
        $alumnoFila['credencial_generada'] ? 'Sí' : 'No',
        $alumnoFila['fecha_registro'],
    ], null, 'A' . $fila);
    $fila++;
}

foreach (range('A', 'I') as $columna) {
    $activa->getColumnDimension($columna)->setAutoSize(true);
}

$nombreArchivo = 'alumnos_' . date('Y-m-d_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$escritor = new Xlsx($hoja);
$escritor->save('php://output');
exit;
