<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/registro/public/recuperar.php');
    exit;
}

$numeroCuenta = strtoupper(trim((string) ($_POST['numero_cuenta'] ?? '')));
$correoInstitucional = trim((string) ($_POST['correo_institucional'] ?? ''));

if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta) || !filter_var($correoInstitucional, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . BASE_URL . '/registro/public/recuperar.php?error=no_encontrado');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare(
    'SELECT token_descarga FROM alumnos WHERE numero_cuenta = :cuenta AND correo_institucional = :correo'
);
$consulta->execute(['cuenta' => $numeroCuenta, 'correo' => $correoInstitucional]);
$alumno = $consulta->fetch();

if ($alumno === false) {
    header('Location: ' . BASE_URL . '/registro/public/recuperar.php?error=no_encontrado');
    exit;
}

header('Location: ' . BASE_URL . '/registro/public/exito.php?token=' . urlencode($alumno['token_descarga']));
exit;
