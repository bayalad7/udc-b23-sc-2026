<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require __DIR__ . '/../../registro/includes/generar-credencial.php';
// Costo de la camisa y validación de montos: mismas funciones que usa el jefe
// de grupo en app/camisas, para que el panel no acepte un pago que aquel no
// podría haber capturado (ver app/camisas/includes/costo.php).
require_once __DIR__ . '/../../camisas/includes/costo.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/alumnos.php');
    exit;
}

function volverConError(?int $id, string $codigo): never
{
    $destino = $id !== null ? BASE_URL . '/admin/public/alumno.php?id=' . $id : BASE_URL . '/admin/public/alumno.php?nuevo=1';
    header('Location: ' . $destino . '&error=' . urlencode($codigo));
    exit;
}

/**
 * Traduce un error 23000 de MariaDB al código de mensaje que le toca.
 *
 * Bajo ese mismo SQLSTATE caen tres cosas distintas: el número de cuenta
 * repetido (uq_alumnos_numero_cuenta), el segundo jefe del mismo grupo
 * (uq_alumnos_jefe_grupo) y el CHECK de coherencia del pago
 * (chk_alumnos_camisa_pago, que llega como error 4025). Sin mirar el nombre de
 * la restricción en el mensaje del driver, las tres se le mostrarían al staff
 * como "ese número de cuenta ya está registrado". Todas se validan antes de
 * llegar aquí; esto cubre la carrera entre dos pestañas del panel.
 */
function codigoDeErrorDeIntegridad(PDOException $e): string
{
    $mensaje = (string) ($e->errorInfo[2] ?? '');

    if (str_contains($mensaje, 'uq_alumnos_jefe_grupo')) {
        return 'jefe_duplicado';
    }
    if (str_contains($mensaje, 'chk_alumnos_camisa_pago')) {
        return 'pago_sin_pedido';
    }

    return 'cuenta_duplicada';
}

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

$nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
$numeroCuenta = strtoupper(trim((string) ($_POST['numero_cuenta'] ?? '')));
$grado = trim((string) ($_POST['grado'] ?? ''));
$grupo = trim((string) ($_POST['grupo'] ?? ''));
$correoInstitucional = trim((string) ($_POST['correo_institucional'] ?? ''));
$camisaCorte = trim((string) ($_POST['camisa_corte'] ?? ''));
$camisaTalla = trim((string) ($_POST['camisa_talla'] ?? ''));
// Casillas: si no vienen marcadas el navegador no manda el campo.
$camisaPedir = ($_POST['camisa_pedir'] ?? '') === '1' ? 1 : 0;
$esJefe = ($_POST['es_jefe'] ?? '') === '1' ? 1 : 0;

if ($nombreCompleto === '' || $correoInstitucional === '' || mb_strlen($nombreCompleto) > 150 || mb_strlen($correoInstitucional) > 150) {
    volverConError($id, 'campos_incompletos');
}
if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
    volverConError($id, 'numero_cuenta_invalido');
}
if (!in_array($grado, ['1', '3', '5'], true) || !in_array($grupo, ['A', 'B', 'C'], true)) {
    volverConError($id, 'campos_incompletos');
}
if (!in_array($camisaCorte, ['Unisex'], true) || !in_array($camisaTalla, ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'], true)) {
    volverConError($id, 'campos_incompletos');
}
if (!filter_var($correoInstitucional, FILTER_VALIDATE_EMAIL)) {
    volverConError($id, 'campos_incompletos');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// --- Camisa: pago y cargo de jefe de grupo ----------------------------------
// Estas validaciones van después de abrir la conexión porque el tope del pago
// es sistema.camisa_costo. En la base las respaldan chk_alumnos_camisa_pago y
// trg_alumnos_camisa_pago_* (ver schema.sql); aquí se comprueban antes para
// poder dar un mensaje entendible en vez de un error de MariaDB.
$costoCamisa = camisaCosto($pdo);
$camisaPago = camisaMontoDesdeTexto((string) ($_POST['camisa_pago'] ?? '0'));

if ($camisaPago === null) {
    volverConError($id, 'monto_invalido');
}
if ($camisaPago > $costoCamisa) {
    volverConError($id, 'pago_excede');
}
if ($camisaPedir === 0 && $camisaPago > 0) {
    volverConError($id, 'pago_sin_pedido');
}

// Un solo jefe por grado+grupo. uq_alumnos_jefe_grupo ya lo impide en la base,
// pero ahí llegaría como un 23000 indistinguible del de numero_cuenta
// duplicado; preguntando antes se puede decir a quién hay que relevar.
if ($esJefe === 1) {
    $jefeActual = $pdo->prepare(
        'SELECT nombre_completo FROM alumnos
         WHERE grado = :grado AND grupo = :grupo AND es_jefe = 1 AND id <> :id'
    );
    $jefeActual->execute(['grado' => $grado, 'grupo' => $grupo, 'id' => $id ?? 0]);
    $otroJefe = $jefeActual->fetch();

    if ($otroJefe !== false) {
        $destino = $id !== null
            ? BASE_URL . '/admin/public/alumno.php?id=' . $id
            : BASE_URL . '/admin/public/alumno.php?nuevo=1';
        header('Location: ' . $destino . '&error=jefe_duplicado&detalle=' . urlencode($otroJefe['nombre_completo']));
        exit;
    }
}

$directorioUploads = __DIR__ . '/../../registro/public/uploads';
$tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
$TAMANO_MAXIMO_FOTO = 5 * 1024 * 1024;

$fotoNueva = isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;
$rutaFotoRelativa = null;
$rutaFotoAbsoluta = null;

if ($fotoNueva) {
    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK || $_FILES['foto']['size'] > $TAMANO_MAXIMO_FOTO) {
        volverConError($id, 'foto_invalida');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $_FILES['foto']['tmp_name']);
    finfo_close($finfo);
    if (!isset($tiposPermitidos[$mimeReal])) {
        volverConError($id, 'foto_invalida');
    }
    $extension = $tiposPermitidos[$mimeReal];
    $tokenFoto = bin2hex(random_bytes(16));
    if (!is_dir($directorioUploads)) {
        mkdir($directorioUploads, 0755, true);
    }
    $rutaFotoRelativa = 'uploads/' . $tokenFoto . '.' . $extension;
    $rutaFotoAbsoluta = $directorioUploads . '/' . $tokenFoto . '.' . $extension;
    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $rutaFotoAbsoluta)) {
        volverConError($id, 'error_servidor');
    }
}

if ($id === null) {
    // --- Crear alumno nuevo (registro manual desde el panel) ----------------
    if (!$fotoNueva) {
        volverConError(null, 'foto_invalida');
    }

    $token = bin2hex(random_bytes(16));

    try {
        $insertar = $pdo->prepare(
            'INSERT INTO alumnos
                (numero_cuenta, nombre_completo, grado, grupo, correo_institucional, foto_path, camisa_corte, camisa_talla,
                 camisa_pedir, camisa_pago, es_jefe, token_descarga)
             VALUES
                (:numero_cuenta, :nombre_completo, :grado, :grupo, :correo_institucional, :foto_path, :camisa_corte, :camisa_talla,
                 :camisa_pedir, :camisa_pago, :es_jefe, :token_descarga)'
        );
        $insertar->execute([
            'numero_cuenta' => $numeroCuenta,
            'nombre_completo' => $nombreCompleto,
            'grado' => $grado,
            'grupo' => $grupo,
            'correo_institucional' => $correoInstitucional,
            'foto_path' => $rutaFotoRelativa,
            'camisa_corte' => $camisaCorte,
            'camisa_talla' => $camisaTalla,
            'camisa_pedir' => $camisaPedir,
            'camisa_pago' => number_format($camisaPago, 2, '.', ''),
            'es_jefe' => $esJefe,
            'token_descarga' => $token,
        ]);
    } catch (PDOException $e) {
        unlink($rutaFotoAbsoluta);
        if ($e->getCode() === '23000') {
            volverConError(null, codigoDeErrorDeIntegridad($e));
        }
        throw $e;
    }

    $idNuevo = (int) $pdo->lastInsertId();

    try {
        generarCredencial($pdo, $numeroCuenta, $token);
    } catch (Throwable $e) {
        error_log('Error generando credencial (admin, alta manual) para ' . $numeroCuenta . ': ' . $e->getMessage());
    }

    header('Location: ' . BASE_URL . '/admin/public/alumno.php?id=' . $idNuevo . '&msg=creado');
    exit;
}

// --- Editar alumno existente ------------------------------------------------

$actual = $pdo->prepare('SELECT foto_path FROM alumnos WHERE id = :id');
$actual->execute(['id' => $id]);
$alumnoActual = $actual->fetch();
if ($alumnoActual === false) {
    if ($fotoNueva) {
        unlink($rutaFotoAbsoluta);
    }
    header('Location: ' . BASE_URL . '/admin/public/alumnos.php?error=no_encontrado');
    exit;
}

$camposComunes = [
    'numero_cuenta' => $numeroCuenta,
    'nombre_completo' => $nombreCompleto,
    'grado' => $grado,
    'grupo' => $grupo,
    'correo_institucional' => $correoInstitucional,
    'camisa_corte' => $camisaCorte,
    'camisa_talla' => $camisaTalla,
    'camisa_pedir' => $camisaPedir,
    'camisa_pago' => number_format($camisaPago, 2, '.', ''),
    'es_jefe' => $esJefe,
    'id' => $id,
];

try {
    if ($fotoNueva) {
        $actualizar = $pdo->prepare(
            'UPDATE alumnos SET numero_cuenta = :numero_cuenta, nombre_completo = :nombre_completo, grado = :grado, grupo = :grupo,
                correo_institucional = :correo_institucional, camisa_corte = :camisa_corte, camisa_talla = :camisa_talla,
                camisa_pedir = :camisa_pedir, camisa_pago = :camisa_pago, es_jefe = :es_jefe,
                foto_path = :foto_path
             WHERE id = :id'
        );
        $actualizar->execute($camposComunes + ['foto_path' => $rutaFotoRelativa]);
    } else {
        $actualizar = $pdo->prepare(
            'UPDATE alumnos SET numero_cuenta = :numero_cuenta, nombre_completo = :nombre_completo, grado = :grado, grupo = :grupo,
                correo_institucional = :correo_institucional, camisa_corte = :camisa_corte, camisa_talla = :camisa_talla,
                camisa_pedir = :camisa_pedir, camisa_pago = :camisa_pago, es_jefe = :es_jefe
             WHERE id = :id'
        );
        $actualizar->execute($camposComunes);
    }
} catch (PDOException $e) {
    if ($fotoNueva) {
        unlink($rutaFotoAbsoluta);
    }
    if ($e->getCode() === '23000') {
        // Cambiarle el grado/grupo a un jefe también puede chocar: si el grupo
        // destino ya tiene el suyo, el UNIQUE salta aquí aunque es_jefe no se
        // haya tocado en este guardado.
        volverConError($id, codigoDeErrorDeIntegridad($e));
    }
    throw $e;
}

if ($fotoNueva && $alumnoActual['foto_path']) {
    $rutaAnterior = $directorioUploads . '/' . basename($alumnoActual['foto_path']);
    if (is_file($rutaAnterior)) {
        unlink($rutaAnterior);
    }
}

header('Location: ' . BASE_URL . '/admin/public/alumno.php?id=' . $id . '&msg=actualizado');
exit;
