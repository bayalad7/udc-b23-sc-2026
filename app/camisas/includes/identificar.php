<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionCamisas();

// Identificación del jefe de grupo: numero_cuenta + correo_institucional,
// exactamente el mismo par que pide app/inscripciones/includes/identificar.php
// (pedir los dos, y no solo el numero_cuenta que ya viaja en el QR de la
// credencial, sube la barrera para hacerse pasar por alguien más) más la
// condición de ser jefe.
//
// El mensaje de error es el mismo para "no existe", "no coinciden" y "no es
// jefe": distinguirlos dejaría averiguar, probando cuentas, quién es el jefe de
// cada grupo — que es justo la cuenta que valdría la pena suplantar.

function volverConError(string $codigo): never
{
    header('Location: ' . BASE_URL . '/camisas/public/index.php?error=' . urlencode($codigo));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/camisas/public/index.php');
    exit;
}

$numeroCuenta = strtoupper(trim((string) ($_POST['numero_cuenta'] ?? '')));
$correoInstitucional = trim((string) ($_POST['correo_institucional'] ?? ''));

if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
    volverConError('credenciales_invalidas');
}
if (!filter_var($correoInstitucional, FILTER_VALIDATE_EMAIL)) {
    volverConError('credenciales_invalidas');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare(
    'SELECT id FROM alumnos
     WHERE numero_cuenta = :cuenta AND correo_institucional = :correo AND es_jefe = 1'
);
$consulta->execute(['cuenta' => $numeroCuenta, 'correo' => $correoInstitucional]);
$jefe = $consulta->fetch();

if ($jefe === false) {
    volverConError('credenciales_invalidas');
}

// Sesión nueva al identificarse: evita que una cookie de sesión previa (por
// ejemplo la del jefe anterior en el celular prestado) se reutilice.
session_regenerate_id(true);
$_SESSION['jefe_id'] = (int) $jefe['id'];

header('Location: ' . BASE_URL . '/camisas/public/index.php');
exit;
