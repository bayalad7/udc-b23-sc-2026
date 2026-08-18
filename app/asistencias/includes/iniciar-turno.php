<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAsistencias();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !turnoAutorizado()) {
    header('Location: ' . BASE_URL . '/asistencias/public/evento.php');
    exit;
}

$evento = (string) ($_POST['evento'] ?? '');
$operador = trim((string) ($_POST['operador'] ?? ''));
$puntoControl = trim((string) ($_POST['punto_control'] ?? ''));

if (
    !in_array($evento, ['academico', 'cultural', 'deportivo'], true)
    || $operador === '' || mb_strlen($operador) > 100
    || $puntoControl === '' || mb_strlen($puntoControl) > 100
) {
    header('Location: ' . BASE_URL . '/asistencias/public/evento.php?error=campos_incompletos');
    exit;
}

$_SESSION['evento'] = $evento;
$_SESSION['operador'] = $operador;
$_SESSION['punto_control'] = $puntoControl;

header('Location: ' . BASE_URL . '/asistencias/public/escaneo.php');
exit;
