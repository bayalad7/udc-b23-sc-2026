<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/registro/public/recuperar.php');
    exit;
}

$token = (string) ($_POST['token'] ?? '');

if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    header('Location: ' . BASE_URL . '/registro/public/recuperar.php?error=no_encontrado');
    exit;
}

function volverConError(string $token, string $codigo): never
{
    header('Location: ' . BASE_URL . '/registro/public/regenerar.php?token=' . urlencode($token) . '&error=' . urlencode($codigo));
    exit;
}

$nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
$numeroCuenta = strtoupper(trim((string) ($_POST['numero_cuenta'] ?? '')));
$grado = trim((string) ($_POST['grado'] ?? ''));
$grupo = trim((string) ($_POST['grupo'] ?? ''));
$correoInstitucional = trim((string) ($_POST['correo_institucional'] ?? ''));
$camisaCorte = trim((string) ($_POST['camisa_corte'] ?? ''));
$camisaTalla = trim((string) ($_POST['camisa_talla'] ?? ''));

if ($nombreCompleto === '' || $correoInstitucional === '') {
    volverConError($token, 'campos_incompletos');
}

if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
    volverConError($token, 'campos_incompletos');
}

if (!in_array($grado, ['1', '3', '5'], true) || !in_array($grupo, ['A', 'B', 'C'], true)) {
    volverConError($token, 'campos_incompletos');
}

if (!in_array($camisaCorte, ['Unisex'], true) || !in_array($camisaTalla, ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'], true)) {
    volverConError($token, 'campos_incompletos');
}

if (!filter_var($correoInstitucional, FILTER_VALIDATE_EMAIL)) {
    volverConError($token, 'campos_incompletos');
}

if (mb_strlen($nombreCompleto) > 150 || mb_strlen($correoInstitucional) > 150) {
    volverConError($token, 'campos_incompletos');
}

// --- Conexión y carga del registro actual ---------------------------------

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare('SELECT id, numero_cuenta, foto_path FROM alumnos WHERE token_descarga = :token');
$consulta->execute(['token' => $token]);
$alumnoActual = $consulta->fetch();

if ($alumnoActual === false) {
    header('Location: ' . BASE_URL . '/registro/public/recuperar.php?error=no_encontrado');
    exit;
}

// --- Verificar duplicado si el número de cuenta cambió ---------------------

if ($numeroCuenta !== $alumnoActual['numero_cuenta']) {
    $consultaDuplicado = $pdo->prepare('SELECT id FROM alumnos WHERE numero_cuenta = :cuenta AND id != :id');
    $consultaDuplicado->execute(['cuenta' => $numeroCuenta, 'id' => $alumnoActual['id']]);
    if ($consultaDuplicado->fetch() !== false) {
        volverConError($token, 'cuenta_duplicada');
    }
}

// --- Validación y guardado de la fotografía (opcional) ---------------------
// Si no se sube una foto nueva, se conserva la actual sin tocarla.

$fotoPathRelativo = $alumnoActual['foto_path'];
$fotoNuevaRuta = null;
$fotoAnteriorRutaAbsoluta = null;

$fotoSubida = isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE;

if ($fotoSubida) {
    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        volverConError($token, 'foto_invalida');
    }

    $fotoTmp = $_FILES['foto']['tmp_name'];
    $TAMANO_MAXIMO_FOTO = 5 * 1024 * 1024;

    if ($_FILES['foto']['size'] > $TAMANO_MAXIMO_FOTO) {
        volverConError($token, 'foto_invalida');
    }

    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $fotoTmp);
    finfo_close($finfo);

    if (!isset($tiposPermitidos[$mimeReal])) {
        volverConError($token, 'foto_invalida');
    }

    $extension = $tiposPermitidos[$mimeReal];

    $directorioUploads = __DIR__ . '/../public/uploads';
    if (!is_dir($directorioUploads)) {
        mkdir($directorioUploads, 0755, true);
    }

    $fotoNuevaRuta = 'uploads/' . $token . '.' . $extension;
    $fotoNuevaRutaAbsoluta = $directorioUploads . '/' . $token . '.' . $extension;

    if (!move_uploaded_file($fotoTmp, $fotoNuevaRutaAbsoluta)) {
        volverConError($token, 'error_servidor');
    }

    if ($fotoNuevaRuta !== $alumnoActual['foto_path']) {
        $fotoAnteriorRutaAbsoluta = $directorioUploads . '/' . basename($alumnoActual['foto_path']);
    }

    $fotoPathRelativo = $fotoNuevaRuta;
}

// --- Actualizar el registro en la base de datos -----------------------------

try {
    $actualizar = $pdo->prepare(
        'UPDATE alumnos SET
            numero_cuenta = :numero_cuenta,
            nombre_completo = :nombre_completo,
            grado = :grado,
            grupo = :grupo,
            correo_institucional = :correo_institucional,
            foto_path = :foto_path,
            camisa_corte = :camisa_corte,
            camisa_talla = :camisa_talla
         WHERE id = :id'
    );
    $actualizar->execute([
        'numero_cuenta' => $numeroCuenta,
        'nombre_completo' => $nombreCompleto,
        'grado' => $grado,
        'grupo' => $grupo,
        'correo_institucional' => $correoInstitucional,
        'foto_path' => $fotoPathRelativo,
        'camisa_corte' => $camisaCorte,
        'camisa_talla' => $camisaTalla,
        'id' => $alumnoActual['id'],
    ]);
} catch (PDOException $e) {
    if ($fotoNuevaRuta !== null) {
        unlink(__DIR__ . '/../public/' . $fotoNuevaRuta);
    }
    error_log('Error al regenerar credencial de ' . $numeroCuenta . ': ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        volverConError($token, 'cuenta_duplicada');
    }
    volverConError($token, 'error_servidor');
}

// La foto anterior ya no se usa en ningún lado más que en este registro, así
// que una vez que el UPDATE quedó confirmado es seguro borrarla.
if ($fotoAnteriorRutaAbsoluta !== null && is_file($fotoAnteriorRutaAbsoluta)) {
    unlink($fotoAnteriorRutaAbsoluta);
}

// --- Regenerar la credencial digital (reemplaza la imagen anterior) --------

require __DIR__ . '/generar-credencial.php';

try {
    generarCredencial($pdo, $numeroCuenta, $token);
} catch (Throwable $e) {
    error_log('Error regenerando credencial para ' . $numeroCuenta . ': ' . $e->getMessage());
}

header('Location: ' . BASE_URL . '/registro/public/exito.php?token=' . urlencode($token));
exit;
