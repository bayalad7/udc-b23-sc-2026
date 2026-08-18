<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAsistencias();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/asistencias/public/evento.php');
    exit;
}

$clave = (string) ($_POST['clave'] ?? '');
$claveConfirmar = (string) ($_POST['clave_confirmar'] ?? '');

if (mb_strlen($clave) < 8) {
    header('Location: ' . BASE_URL . '/asistencias/public/evento.php?error=clave_muy_corta');
    exit;
}

if (!hash_equals($clave, $claveConfirmar)) {
    header('Location: ' . BASE_URL . '/asistencias/public/evento.php?error=claves_no_coinciden');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// La tabla sistema solo admite una fila (id fijo en 1, ver schema.sql) pero
// clave_acceso y clave_admin (app/admin) se configuran por separado, en
// momentos distintos e independientes — así que la fila puede ya existir con
// clave_admin definida y clave_acceso todavía en NULL. Por eso el INSERT solo
// toca su propia columna vía ON DUPLICATE KEY UPDATE, con un IF() que nunca
// pisa una clave_acceso que ya tuviera valor. Si el UPDATE no cambió nada
// (rowCount 0), es que alguien más ya la había registrado justo antes.
$registrar = $pdo->prepare(
    'INSERT INTO sistema (id, clave_acceso) VALUES (1, :clave)
     ON DUPLICATE KEY UPDATE clave_acceso = IF(clave_acceso IS NULL, VALUES(clave_acceso), clave_acceso)'
);
$registrar->execute(['clave' => password_hash($clave, PASSWORD_DEFAULT)]);

if ($registrar->rowCount() === 0) {
    header('Location: ' . BASE_URL . '/asistencias/public/evento.php?error=ya_registrada');
    exit;
}

$_SESSION['autorizado'] = true;

header('Location: ' . BASE_URL . '/asistencias/public/evento.php');
exit;
