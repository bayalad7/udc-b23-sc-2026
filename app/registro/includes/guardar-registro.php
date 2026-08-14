<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /registro/public/index.php');
    exit;
}

function volverConError(string $codigo): never
{
    header('Location: /registro/public/index.php?error=' . urlencode($codigo));
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
    volverConError('campos_incompletos');
}

if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
    volverConError('campos_incompletos');
}

if (!in_array($grado, ['1', '3', '5'], true) || !in_array($grupo, ['A', 'B', 'C'], true)) {
    volverConError('campos_incompletos');
}

if (!in_array($camisaCorte, ['Hombre', 'Mujer'], true) || !in_array($camisaTalla, ['S', 'M', 'L', 'XL', '2XL'], true)) {
    volverConError('campos_incompletos');
}

if (!filter_var($correoInstitucional, FILTER_VALIDATE_EMAIL)) {
    volverConError('campos_incompletos');
}

if (mb_strlen($nombreCompleto) > 150 || mb_strlen($correoInstitucional) > 150) {
    volverConError('campos_incompletos');
}

// --- Validación de la fotografía --------------------------------------

if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    volverConError('foto_invalida');
}

$fotoTmp = $_FILES['foto']['tmp_name'];
$TAMANO_MAXIMO_FOTO = 5 * 1024 * 1024;

if ($_FILES['foto']['size'] > $TAMANO_MAXIMO_FOTO) {
    volverConError('foto_invalida');
}

$tiposPermitidos = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeReal = finfo_file($finfo, $fotoTmp);
finfo_close($finfo);

if (!isset($tiposPermitidos[$mimeReal])) {
    volverConError('foto_invalida');
}

$extension = $tiposPermitidos[$mimeReal];

// --- Conexión y verificación de duplicado ------------------------------

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare('SELECT id FROM alumnos WHERE numero_cuenta = :cuenta');
$consulta->execute(['cuenta' => $numeroCuenta]);
if ($consulta->fetch() !== false) {
    volverConError('cuenta_duplicada');
}

// --- Guardar fotografía --------------------------------------------------

$token = bin2hex(random_bytes(16));

$directorioUploads = __DIR__ . '/../public/uploads';
if (!is_dir($directorioUploads)) {
    mkdir($directorioUploads, 0755, true);
}

$rutaFotoRelativa = 'uploads/' . $token . '.' . $extension;
$rutaFotoAbsoluta = $directorioUploads . '/' . $token . '.' . $extension;

if (!move_uploaded_file($fotoTmp, $rutaFotoAbsoluta)) {
    volverConError('error_servidor');
}

// --- Guardar registro en la base de datos --------------------------------

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
    error_log('Error al insertar alumno ' . $numeroCuenta . ': ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        volverConError('cuenta_duplicada');
    }
    volverConError('error_servidor');
}

// --- Generar la credencial digital ---------------------------------------

require __DIR__ . '/generar-credencial.php';

try {
    generarCredencial($pdo, $numeroCuenta, $token);
} catch (Throwable $e) {
    error_log('Error generando credencial para ' . $numeroCuenta . ': ' . $e->getMessage());
    // El alumno ya quedó registrado; la credencial se puede regenerar después.
}

header('Location: /registro/public/exito.php?token=' . urlencode($token));
exit;
