<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionInscripciones();

// Autoservicio: cualquier persona puede identificarse con numero_cuenta +
// correo_institucional — no hay contraseña. Pedir las dos cosas (en vez de
// solo numero_cuenta, que ya viaja en el QR de la credencial y por tanto es
// más fácil de ver por encima) sube un poco la barrera para identificarse
// como otra persona sin dejar de ser autoservicio.

function destinoSeguro(string $destino): string
{
    return str_starts_with($destino, '/inscripciones/public/') ? $destino : '/inscripciones/public/index.php';
}

function volverConError(string $destino, string $codigo): never
{
    $separador = str_contains($destino, '?') ? '&' : '?';
    header('Location: ' . $destino . $separador . 'error=' . urlencode($codigo));
    exit;
}

$destino = destinoSeguro((string) ($_POST['volver'] ?? '/inscripciones/public/index.php'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $destino);
    exit;
}

$numeroCuenta = strtoupper(trim((string) ($_POST['numero_cuenta'] ?? '')));
$correoInstitucional = trim((string) ($_POST['correo_institucional'] ?? ''));

if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
    volverConError($destino, 'numero_cuenta_invalido');
}

if (!filter_var($correoInstitucional, FILTER_VALIDATE_EMAIL)) {
    volverConError($destino, 'correo_invalido');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare('SELECT id FROM alumnos WHERE numero_cuenta = :cuenta AND correo_institucional = :correo');
$consulta->execute(['cuenta' => $numeroCuenta, 'correo' => $correoInstitucional]);
$alumno = $consulta->fetch();

if ($alumno === false) {
    volverConError($destino, 'no_encontrado');
}

$_SESSION['alumno_id'] = (int) $alumno['id'];

header('Location: ' . $destino);
exit;
