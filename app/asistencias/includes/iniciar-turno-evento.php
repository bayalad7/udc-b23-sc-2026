<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAsistencias();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !turnoAutorizado()) {
    header('Location: ' . BASE_URL . '/asistencias/public/evento.php');
    exit;
}

$idEvento = (int) ($_POST['id_evento'] ?? 0);
$operador = trim((string) ($_POST['operador'] ?? ''));
$puntoControl = trim((string) ($_POST['punto_control'] ?? ''));

if (
    $idEvento <= 0
    || $operador === '' || mb_strlen($operador) > 100
    || $puntoControl === '' || mb_strlen($puntoControl) > 100
) {
    header('Location: ' . BASE_URL . '/asistencias/public/turno-evento.php?error=evento_no_valido');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// Solo lectura sobre eventos, únicamente para confirmar que el id elegido
// existe de verdad (nunca se crea/edita nada aquí).
$consulta = $pdo->prepare('SELECT 1 FROM eventos WHERE id = :id');
$consulta->execute(['id' => $idEvento]);
if ($consulta->fetch() === false) {
    header('Location: ' . BASE_URL . '/asistencias/public/turno-evento.php?error=evento_no_valido');
    exit;
}

$_SESSION['id_evento'] = $idEvento;
$_SESSION['operador_evento'] = $operador;
$_SESSION['punto_control_evento'] = $puntoControl;

header('Location: ' . BASE_URL . '/asistencias/public/escaneo-evento.php');
exit;
