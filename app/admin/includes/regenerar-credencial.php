<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require __DIR__ . '/../../registro/includes/generar-credencial.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/alumnos.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare('SELECT numero_cuenta, token_descarga FROM alumnos WHERE id = :id');
$consulta->execute(['id' => $id]);
$alumno = $consulta->fetch();

if ($alumno === false) {
    header('Location: ' . BASE_URL . '/admin/public/alumnos.php?error=no_encontrado');
    exit;
}

try {
    generarCredencial($pdo, $alumno['numero_cuenta'], $alumno['token_descarga']);
    header('Location: ' . BASE_URL . '/admin/public/alumno.php?id=' . $id . '&msg=credencial_regenerada');
} catch (Throwable $e) {
    error_log('Error regenerando credencial (admin) para alumno id ' . $id . ': ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/admin/public/alumno.php?id=' . $id . '&error=error_servidor');
}
exit;
