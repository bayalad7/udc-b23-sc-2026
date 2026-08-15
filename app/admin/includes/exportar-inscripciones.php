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

$idEvento = (int) ($_GET['id'] ?? 0);
if ($idEvento <= 0) {
    http_response_code(400);
    exit('Falta el id del evento.');
}

$consultaEvento = $pdo->prepare('SELECT nombre FROM eventos WHERE id = :id');
$consultaEvento->execute(['id' => $idEvento]);
$evento = $consultaEvento->fetch();
if ($evento === false) {
    http_response_code(404);
    exit('Evento no encontrado.');
}

$consulta = $pdo->prepare(
    'SELECT a.numero_cuenta, a.nombre_completo, a.grado, a.grupo, i.origen, i.registrado_por,
            i.hora_entrada, i.punto_control_entrada, i.escaneado_por_entrada,
            i.hora_salida, i.punto_control_salida, i.escaneado_por_salida
     FROM inscripciones i
     JOIN alumnos a ON a.id = i.id_alumno
     WHERE i.id_evento = :id
     ORDER BY a.nombre_completo'
);
$consulta->execute(['id' => $idEvento]);
$inscripciones = $consulta->fetchAll();

$hoja = new Spreadsheet();
$activa = $hoja->getActiveSheet();
$activa->setTitle('Inscripciones');

$encabezados = [
    'No. cuenta', 'Nombre completo', 'Grado', 'Grupo', 'Origen', 'Registró',
    'Estado', 'Entrada', 'Punto (entrada)', 'Escaneó (entrada)',
    'Salida', 'Punto (salida)', 'Escaneó (salida)',
];
$activa->fromArray($encabezados, null, 'A1');
$activa->getStyle('A1:M1')->getFont()->setBold(true);

$fila = 2;
foreach ($inscripciones as $inscripcion) {
    if ($inscripcion['hora_entrada'] === null) {
        $estado = 'Sin llegar';
    } elseif ($inscripcion['hora_salida'] === null) {
        $estado = 'Presente';
    } else {
        $estado = 'Salió';
    }

    $activa->fromArray([
        $inscripcion['numero_cuenta'],
        $inscripcion['nombre_completo'],
        $inscripcion['grado'] . '°',
        $inscripcion['grupo'],
        $inscripcion['origen'] === 'previo' ? 'Previo' : 'Orden de llegada',
        $inscripcion['registrado_por'],
        $estado,
        $inscripcion['hora_entrada'] ?? '',
        $inscripcion['punto_control_entrada'] ?? '',
        $inscripcion['escaneado_por_entrada'] ?? '',
        $inscripcion['hora_salida'] ?? '',
        $inscripcion['punto_control_salida'] ?? '',
        $inscripcion['escaneado_por_salida'] ?? '',
    ], null, 'A' . $fila);
    $fila++;
}

foreach (range('A', 'M') as $columna) {
    $activa->getColumnDimension($columna)->setAutoSize(true);
}

$nombreArchivo = 'inscripciones_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $evento['nombre']) . '_' . date('Y-m-d_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$escritor = new Xlsx($hoja);
$escritor->save('php://output');
exit;
