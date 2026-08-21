<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';
require_once __DIR__ . '/catalogo.php';
require __DIR__ . '/sesion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/trabajadores/public/index.php');
    exit;
}

$tipo = trim((string) ($_POST['tipo'] ?? ''));
$numeroTrabajador = strtoupper(trim((string) ($_POST['numero_trabajador'] ?? '')));
$nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
$camisaCorte = trim((string) ($_POST['camisa_corte'] ?? ''));
$camisaTalla = trim((string) ($_POST['camisa_talla'] ?? ''));

/**
 * Regresa al formulario con el error y los datos ya capturados, para no
 * obligar a la persona a escribirlo todo de nuevo.
 */
function volverConError(string $codigo): never
{
    global $tipo, $numeroTrabajador, $nombreCompleto, $camisaTalla;

    $parametros = http_build_query([
        'error' => $codigo,
        'tipo' => $tipo,
        'numero_trabajador' => $numeroTrabajador,
        'nombre_completo' => $nombreCompleto,
        'camisa_talla' => $camisaTalla,
    ]);

    header('Location: ' . BASE_URL . '/trabajadores/public/index.php?' . $parametros);
    exit;
}

if (!in_array($tipo, TRABAJADOR_TIPOS, true)) {
    volverConError('campos_incompletos');
}

// El número de trabajador no tiene una longitud fija institucional (a
// diferencia de los 8 caracteres del número de cuenta del alumnado), así que
// solo se acota el formato: alfanumérico, sin espacios, dentro del
// VARCHAR(20) de la tabla.
if (!preg_match('/^[A-Z0-9-]{1,20}$/', $numeroTrabajador)) {
    volverConError('campos_incompletos');
}

if ($nombreCompleto === '' || mb_strlen($nombreCompleto) > 150) {
    volverConError('campos_incompletos');
}

if (!in_array($camisaCorte, TRABAJADOR_CAMISA_CORTES, true) || !isset(TRABAJADOR_CAMISA_TALLAS[$camisaTalla])) {
    volverConError('campos_incompletos');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

try {
    $insertar = $pdo->prepare(
        'INSERT INTO trabajadores (tipo, numero_trabajador, nombre_completo, camisa_corte, camisa_talla)
         VALUES (:tipo, :numero_trabajador, :nombre_completo, :camisa_corte, :camisa_talla)'
    );
    $insertar->execute([
        'tipo' => $tipo,
        'numero_trabajador' => $numeroTrabajador,
        'nombre_completo' => $nombreCompleto,
        'camisa_corte' => $camisaCorte,
        'camisa_talla' => $camisaTalla,
    ]);
} catch (PDOException $e) {
    error_log('Error al insertar trabajador ' . $numeroTrabajador . ': ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        volverConError('trabajador_duplicado');
    }
    volverConError('error_servidor');
}

iniciarSesionTrabajadores();
guardarConfirmacionTrabajador([
    'tipo' => $tipo,
    'numero_trabajador' => $numeroTrabajador,
    'nombre_completo' => $nombreCompleto,
    'camisa_corte' => $camisaCorte,
    'camisa_talla' => $camisaTalla,
]);

header('Location: ' . BASE_URL . '/trabajadores/public/exito.php');
exit;
