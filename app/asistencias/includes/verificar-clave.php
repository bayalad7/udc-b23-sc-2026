<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAsistencias();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /asistencias/public/evento.php');
    exit;
}

$clave = (string) ($_POST['clave'] ?? '');

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$fila = $pdo->query('SELECT clave_acceso FROM sistema ORDER BY id LIMIT 1')->fetch();

if ($clave === '' || $fila === false || $fila['clave_acceso'] === null || !password_verify($clave, $fila['clave_acceso'])) {
    header('Location: /asistencias/public/evento.php?error=clave_incorrecta');
    exit;
}

$_SESSION['autorizado'] = true;

header('Location: /asistencias/public/evento.php');
exit;
