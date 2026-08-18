<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require __DIR__ . '/../../registro/includes/generar-credencial.php';
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

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

$nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
$numeroCuenta = strtoupper(trim((string) ($_POST['numero_cuenta'] ?? '')));
$grado = trim((string) ($_POST['grado'] ?? ''));
$grupo = trim((string) ($_POST['grupo'] ?? ''));
$correoInstitucional = trim((string) ($_POST['correo_institucional'] ?? ''));
$camisaCorte = trim((string) ($_POST['camisa_corte'] ?? ''));
$camisaTalla = trim((string) ($_POST['camisa_talla'] ?? ''));

if ($nombreCompleto === '' || $correoInstitucional === '' || mb_strlen($nombreCompleto) > 150 || mb_strlen($correoInstitucional) > 150) {
    volverConError($id, 'campos_incompletos');
}
if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
    volverConError($id, 'numero_cuenta_invalido');
}
if (!in_array($grado, ['1', '3', '5'], true) || !in_array($grupo, ['A', 'B', 'C'], true)) {
    volverConError($id, 'campos_incompletos');
}
if (!in_array($camisaCorte, ['Unisex'], true) || !in_array($camisaTalla, ['XS', 'S', 'M', 'L', 'XL', '2XL'], true)) {
    volverConError($id, 'campos_incompletos');
}
if (!filter_var($correoInstitucional, FILTER_VALIDATE_EMAIL)) {
    volverConError($id, 'campos_incompletos');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

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
                (numero_cuenta, nombre_completo, grado, grupo, correo_institucional, foto_path, camisa_corte, camisa_talla, token_descarga)
             VALUES
                (:numero_cuenta, :nombre_completo, :grado, :grupo, :correo_institucional, :foto_path, :camisa_corte, :camisa_talla, :token_descarga)'
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
            'token_descarga' => $token,
        ]);
    } catch (PDOException $e) {
        unlink($rutaFotoAbsoluta);
        if ($e->getCode() === '23000') {
            volverConError(null, 'cuenta_duplicada');
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
    'id' => $id,
];

try {
    if ($fotoNueva) {
        $actualizar = $pdo->prepare(
            'UPDATE alumnos SET numero_cuenta = :numero_cuenta, nombre_completo = :nombre_completo, grado = :grado, grupo = :grupo,
                correo_institucional = :correo_institucional, camisa_corte = :camisa_corte, camisa_talla = :camisa_talla,
                foto_path = :foto_path
             WHERE id = :id'
        );
        $actualizar->execute($camposComunes + ['foto_path' => $rutaFotoRelativa]);
    } else {
        $actualizar = $pdo->prepare(
            'UPDATE alumnos SET numero_cuenta = :numero_cuenta, nombre_completo = :nombre_completo, grado = :grado, grupo = :grupo,
                correo_institucional = :correo_institucional, camisa_corte = :camisa_corte, camisa_talla = :camisa_talla
             WHERE id = :id'
        );
        $actualizar->execute($camposComunes);
    }
} catch (PDOException $e) {
    if ($fotoNueva) {
        unlink($rutaFotoAbsoluta);
    }
    if ($e->getCode() === '23000') {
        volverConError($id, 'cuenta_duplicada');
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
