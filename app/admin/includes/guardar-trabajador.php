<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require_once __DIR__ . '/../../trabajadores/includes/catalogo.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/trabajadores.php');
    exit;
}

function volverConError(?int $id, string $codigo): never
{
    $destino = $id !== null
        ? BASE_URL . '/admin/public/trabajador.php?id=' . $id
        : BASE_URL . '/admin/public/trabajador.php?nuevo=1';
    header('Location: ' . $destino . '&error=' . urlencode($codigo));
    exit;
}

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

$tipo = trim((string) ($_POST['tipo'] ?? ''));
$numeroTrabajador = strtoupper(trim((string) ($_POST['numero_trabajador'] ?? '')));
$nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
$camisaCorte = trim((string) ($_POST['camisa_corte'] ?? ''));
$camisaTalla = trim((string) ($_POST['camisa_talla'] ?? ''));

if ($nombreCompleto === '' || mb_strlen($nombreCompleto) > 150) {
    volverConError($id, 'campos_incompletos');
}
if (!in_array($tipo, TRABAJADOR_TIPOS, true)) {
    volverConError($id, 'campos_incompletos');
}
// Mismo criterio que el formulario público (ver
// trabajadores/includes/guardar-registro.php): el número de trabajador no
// tiene longitud fija institucional, solo se acota el formato.
if (!preg_match('/^[A-Z0-9-]{1,20}$/', $numeroTrabajador)) {
    volverConError($id, 'numero_invalido');
}
if (!in_array($camisaCorte, TRABAJADOR_CAMISA_CORTES, true) || !isset(TRABAJADOR_CAMISA_TALLAS[$camisaTalla])) {
    volverConError($id, 'campos_incompletos');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$campos = [
    'tipo' => $tipo,
    'numero_trabajador' => $numeroTrabajador,
    'nombre_completo' => $nombreCompleto,
    'camisa_corte' => $camisaCorte,
    'camisa_talla' => $camisaTalla,
];

if ($id === null) {
    // --- Alta manual desde el panel ----------------------------------------
    try {
        $insertar = $pdo->prepare(
            'INSERT INTO trabajadores (tipo, numero_trabajador, nombre_completo, camisa_corte, camisa_talla)
             VALUES (:tipo, :numero_trabajador, :nombre_completo, :camisa_corte, :camisa_talla)'
        );
        $insertar->execute($campos);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            volverConError(null, 'trabajador_duplicado');
        }
        throw $e;
    }

    header('Location: ' . BASE_URL . '/admin/public/trabajador.php?id=' . (int) $pdo->lastInsertId() . '&msg=creado');
    exit;
}

// --- Editar registro existente ---------------------------------------------

$existe = $pdo->prepare('SELECT id FROM trabajadores WHERE id = :id');
$existe->execute(['id' => $id]);
if ($existe->fetch() === false) {
    header('Location: ' . BASE_URL . '/admin/public/trabajadores.php?error=no_encontrado');
    exit;
}

try {
    $actualizar = $pdo->prepare(
        'UPDATE trabajadores SET tipo = :tipo, numero_trabajador = :numero_trabajador,
            nombre_completo = :nombre_completo, camisa_corte = :camisa_corte, camisa_talla = :camisa_talla
         WHERE id = :id'
    );
    $actualizar->execute($campos + ['id' => $id]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        volverConError($id, 'trabajador_duplicado');
    }
    throw $e;
}

header('Location: ' . BASE_URL . '/admin/public/trabajador.php?id=' . $id . '&msg=actualizado');
exit;
