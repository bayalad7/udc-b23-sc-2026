<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/public/index.php');
    exit;
}

$clave = (string) ($_POST['clave'] ?? '');

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$fila = $pdo->query('SELECT clave_admin FROM sistema ORDER BY id LIMIT 1')->fetch();

if ($clave === '' || $fila === false || $fila['clave_admin'] === null || !password_verify($clave, $fila['clave_admin'])) {
    header('Location: /admin/public/index.php?error=clave_incorrecta');
    exit;
}

$_SESSION['admin_autorizado'] = true;

header('Location: /admin/public/index.php');
exit;
