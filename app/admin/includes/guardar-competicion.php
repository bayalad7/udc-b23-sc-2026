<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/public/competiciones.php');
    exit;
}

function volverConError(?int $id, string $codigo): never
{
    $destino = $id !== null ? '/admin/public/competicion.php?id=' . $id : '/admin/public/competicion.php?nuevo=1';
    header('Location: ' . $destino . '&error=' . urlencode($codigo));
    exit;
}

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

$dia = trim((string) ($_POST['dia'] ?? ''));
$tipo = trim((string) ($_POST['tipo'] ?? ''));
$horaInicio = trim((string) ($_POST['hora_inicio'] ?? ''));
$horaFin = trim((string) ($_POST['hora_fin'] ?? ''));
$nombre = trim((string) ($_POST['nombre'] ?? ''));
$fechaLimite = trim((string) ($_POST['fecha_limite'] ?? ''));

if (!in_array($dia, ['academico', 'cultural', 'deportivo'], true) || !in_array($tipo, ['concurso', 'torneo'], true)) {
    volverConError($id, 'campos_incompletos');
}
if ($nombre === '' || mb_strlen($nombre) > 150) {
    volverConError($id, 'campos_incompletos');
}
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaInicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaFin)) {
    volverConError($id, 'campos_incompletos');
}
if ($horaFin <= $horaInicio) {
    volverConError($id, 'horario_invalido');
}

$fechaLimiteSql = DateTime::createFromFormat('Y-m-d\TH:i', $fechaLimite);
if ($fechaLimiteSql === false) {
    volverConError($id, 'campos_incompletos');
}
$fechaLimiteSql = $fechaLimiteSql->format('Y-m-d H:i:s');

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

if ($id === null) {
    $insertar = $pdo->prepare(
        'INSERT INTO competiciones (dia, tipo, hora_inicio, hora_fin, nombre, fecha_limite)
         VALUES (:dia, :tipo, :hora_inicio, :hora_fin, :nombre, :fecha_limite)'
    );
    $insertar->execute([
        'dia' => $dia, 'tipo' => $tipo, 'hora_inicio' => $horaInicio, 'hora_fin' => $horaFin,
        'nombre' => $nombre, 'fecha_limite' => $fechaLimiteSql,
    ]);
    $idNuevo = (int) $pdo->lastInsertId();
    header('Location: /admin/public/competicion.php?id=' . $idNuevo . '&msg=creado');
    exit;
}

$actualizar = $pdo->prepare(
    'UPDATE competiciones SET dia = :dia, tipo = :tipo, hora_inicio = :hora_inicio, hora_fin = :hora_fin,
        nombre = :nombre, fecha_limite = :fecha_limite
     WHERE id = :id'
);
$actualizar->execute([
    'dia' => $dia, 'tipo' => $tipo, 'hora_inicio' => $horaInicio, 'hora_fin' => $horaFin,
    'nombre' => $nombre, 'fecha_limite' => $fechaLimiteSql, 'id' => $id,
]);

header('Location: /admin/public/competicion.php?id=' . $id . '&msg=actualizado');
exit;
