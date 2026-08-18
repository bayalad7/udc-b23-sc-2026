<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}

$clave = (string) ($_POST['clave'] ?? '');
$claveConfirmar = (string) ($_POST['clave_confirmar'] ?? '');

if (mb_strlen($clave) < 8) {
    header('Location: ' . BASE_URL . '/admin/public/index.php?error=clave_muy_corta');
    exit;
}

if (!hash_equals($clave, $claveConfirmar)) {
    header('Location: ' . BASE_URL . '/admin/public/index.php?error=claves_no_coinciden');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// La tabla sistema solo admite una fila (id fijo en 1, ver schema.sql) pero
// clave_admin y clave_acceso (app/asistencias) se configuran por separado —
// ver la misma nota en app/asistencias/includes/registrar-clave.php. El
// INSERT solo toca su propia columna vía ON DUPLICATE KEY UPDATE, sin pisar
// una clave_admin que ya tuviera valor.
$registrar = $pdo->prepare(
    'INSERT INTO sistema (id, clave_admin) VALUES (1, :clave)
     ON DUPLICATE KEY UPDATE clave_admin = IF(clave_admin IS NULL, VALUES(clave_admin), clave_admin)'
);
$registrar->execute(['clave' => password_hash($clave, PASSWORD_DEFAULT)]);

if ($registrar->rowCount() === 0) {
    header('Location: ' . BASE_URL . '/admin/public/index.php?error=ya_registrada');
    exit;
}

$_SESSION['admin_autorizado'] = true;

header('Location: ' . BASE_URL . '/admin/public/index.php');
exit;
