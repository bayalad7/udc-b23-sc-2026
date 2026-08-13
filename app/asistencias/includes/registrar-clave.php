<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAsistencias();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /asistencias/public/evento.php');
    exit;
}

$clave = (string) ($_POST['clave'] ?? '');
$claveConfirmar = (string) ($_POST['clave_confirmar'] ?? '');

if (mb_strlen($clave) < 8) {
    header('Location: /asistencias/public/evento.php?error=clave_muy_corta');
    exit;
}

if (!hash_equals($clave, $claveConfirmar)) {
    header('Location: /asistencias/public/evento.php?error=claves_no_coinciden');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// La tabla sistema solo admite una fila (id fijo en 1, ver schema.sql). Si ya
// existe (alguien más la registró justo antes), no la pisamos: el que llega
// después debe usar el formulario normal de acceso con la clave ya definida.
try {
    $insertar = $pdo->prepare('INSERT INTO sistema (clave_acceso) VALUES (:clave)');
    $insertar->execute(['clave' => password_hash($clave, PASSWORD_DEFAULT)]);
} catch (PDOException $e) {
    if ($e->getCode() !== '23000') {
        throw $e;
    }
    header('Location: /asistencias/public/evento.php?error=ya_registrada');
    exit;
}

$_SESSION['autorizado'] = true;

header('Location: /asistencias/public/evento.php');
exit;
