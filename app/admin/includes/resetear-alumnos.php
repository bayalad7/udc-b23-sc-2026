<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

// Reseteo total del padrón de alumnos (ver "Zona de peligro" en
// app/admin/public/index.php): borra alumnos y TODO lo que cuelga de ellos
// (inscripciones, asistencias generales, equipos e integrantes) y además los
// archivos físicos de fotos y credenciales, para dejar el sistema como recién
// instalado sin tener que tocar la base a mano.
//
// Es la única acción del panel que, aparte de la sesión ya autenticada, vuelve
// a pedir la contraseña de administrador (sistema.clave_admin) y una palabra
// de confirmación escrita a mano: es irreversible y no hay respaldo dentro de
// la app. El catálogo de eventos y competiciones NO se toca — solo se les
// devuelve el cupo_disponible, que quedaría descuadrado al borrar las
// inscripciones que lo habían descontado.

const RESET_PALABRA_CONFIRMACION = 'RESETEAR';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}

$clave = (string) ($_POST['clave'] ?? '');
$confirmacion = trim((string) ($_POST['confirmacion'] ?? ''));

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$fila = $pdo->query('SELECT clave_admin FROM sistema ORDER BY id LIMIT 1')->fetch();
if ($clave === '' || $fila === false || $fila['clave_admin'] === null || !password_verify($clave, $fila['clave_admin'])) {
    header('Location: ' . BASE_URL . '/admin/public/index.php?error=reset_clave');
    exit;
}

if (strcasecmp($confirmacion, RESET_PALABRA_CONFIRMACION) !== 0) {
    header('Location: ' . BASE_URL . '/admin/public/index.php?error=reset_confirmacion');
    exit;
}

$directorioRegistro = __DIR__ . '/../../registro/public';

// Se leen las rutas ANTES del borrado: una vez vaciada la tabla ya no hay
// forma de saber qué archivos le pertenecían.
$rutasArchivos = [];
foreach ($pdo->query('SELECT foto_path, credencial_path FROM alumnos')->fetchAll() as $alumno) {
    foreach ([$alumno['foto_path'], $alumno['credencial_path']] as $ruta) {
        if ($ruta !== null && $ruta !== '') {
            $rutasArchivos[] = $ruta;
        }
    }
}

$totalAlumnos = (int) $pdo->query('SELECT COUNT(*) AS n FROM alumnos')->fetch()['n'];

try {
    $pdo->beginTransaction();

    // Orden obligatorio: ninguna FK del esquema usa ON DELETE CASCADE (ver
    // schema.sql), así que hay que ir de las tablas hijas hacia alumnos.
    $pdo->exec('DELETE FROM integrantes');
    $pdo->exec('DELETE FROM equipos');
    $pdo->exec('DELETE FROM inscripciones');
    $pdo->exec('DELETE FROM asistencias_generales');
    $pdo->exec('DELETE FROM alumnos');

    // Sin inscripciones, el cupo ocupado de cada evento vuelve a ser cero.
    $pdo->exec('UPDATE eventos SET cupo_disponible = cupo_maximo');

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error reseteando el padrón de alumnos (admin): ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/admin/public/index.php?error=reset_error');
    exit;
}

// Los ALTER van fuera de la transacción (en MariaDB el DDL hace commit
// implícito) y su fallo no invalida el reseteo — es solo cosmético que el
// próximo alumno vuelva a ser el id 1.
foreach (['alumnos', 'equipos'] as $tabla) {
    try {
        $pdo->exec('ALTER TABLE ' . $tabla . ' AUTO_INCREMENT = 1');
    } catch (Throwable $e) {
        error_log('No se pudo reiniciar el AUTO_INCREMENT de ' . $tabla . ': ' . $e->getMessage());
    }
}

// --- Archivos físicos: fotos y credenciales -------------------------------
$archivosBorrados = 0;

foreach ($rutasArchivos as $rutaRelativa) {
    // basename() para que una ruta manipulada en la base no pueda salirse de
    // las carpetas de registro (mismo criterio que includes/eliminar-alumno.php).
    $carpeta = str_starts_with($rutaRelativa, 'credenciales/') ? 'credenciales' : 'uploads';
    $ruta = $directorioRegistro . '/' . $carpeta . '/' . basename($rutaRelativa);
    if (is_file($ruta) && @unlink($ruta)) {
        $archivosBorrados++;
    }
}

// Barrido de lo que haya quedado suelto en las dos carpetas: credenciales de
// regeneraciones previas, subidas a medias, o archivos de alumnos borrados
// antes sin su registro. Al ser un reseteo total, ambas carpetas deben quedar
// vacías de datos de alumnos; se respetan los archivos de infraestructura
// (.htaccess, index.html, .gitignore y demás ocultos).
foreach (['uploads', 'credenciales'] as $carpeta) {
    $directorio = $directorioRegistro . '/' . $carpeta;
    if (!is_dir($directorio)) {
        continue;
    }
    foreach (scandir($directorio) ?: [] as $nombre) {
        if (str_starts_with($nombre, '.') || str_starts_with($nombre, 'index.')) {
            continue;
        }
        $ruta = $directorio . '/' . $nombre;
        if (is_file($ruta) && @unlink($ruta)) {
            $archivosBorrados++;
        }
    }
}

error_log(sprintf(
    'Padrón de alumnos reseteado desde app/admin: %d alumnos y %d archivos eliminados.',
    $totalAlumnos,
    $archivosBorrados
));

header('Location: ' . BASE_URL . '/admin/public/index.php?msg=reseteado&alumnos=' . $totalAlumnos . '&archivos=' . $archivosBorrados);
exit;
