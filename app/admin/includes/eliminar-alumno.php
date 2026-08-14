<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/public/alumnos.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: /admin/public/alumnos.php?error=no_encontrado');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// Ninguna FK del esquema tiene ON DELETE CASCADE (ver schema.sql) — se
// verifica aquí, antes de intentar el DELETE, para mostrar un aviso claro en
// vez de dejar que truene el error crudo de restricción de llave foránea.
$dependientes = [];

$consultaInscripciones = $pdo->prepare('SELECT COUNT(*) AS n FROM inscripciones WHERE id_alumno = :id');
$consultaInscripciones->execute(['id' => $id]);
if ((int) $consultaInscripciones->fetch()['n'] > 0) {
    $dependientes[] = 'inscripciones a ponencias/talleres';
}

$consultaIntegrantes = $pdo->prepare('SELECT COUNT(*) AS n FROM integrantes WHERE id_alumno = :id');
$consultaIntegrantes->execute(['id' => $id]);
if ((int) $consultaIntegrantes->fetch()['n'] > 0) {
    $dependientes[] = 'membresías de equipo';
}

$consultaAsistencias = $pdo->prepare('SELECT COUNT(*) AS n FROM asistencias_generales WHERE id_alumno = :id');
$consultaAsistencias->execute(['id' => $id]);
if ((int) $consultaAsistencias->fetch()['n'] > 0) {
    $dependientes[] = 'asistencia general registrada';
}

$consultaCapitan = $pdo->prepare('SELECT COUNT(*) AS n FROM equipos WHERE id_alumno_capitan = :id');
$consultaCapitan->execute(['id' => $id]);
if ((int) $consultaCapitan->fetch()['n'] > 0) {
    $dependientes[] = 'capitanía de un equipo';
}

if ($dependientes !== []) {
    header('Location: /admin/public/alumno.php?id=' . $id . '&error=tiene_dependientes&detalle=' . urlencode(implode(', ', $dependientes)));
    exit;
}

$consulta = $pdo->prepare('SELECT foto_path, credencial_path FROM alumnos WHERE id = :id');
$consulta->execute(['id' => $id]);
$alumno = $consulta->fetch();
if ($alumno === false) {
    header('Location: /admin/public/alumnos.php?error=no_encontrado');
    exit;
}

$eliminar = $pdo->prepare('DELETE FROM alumnos WHERE id = :id');
$eliminar->execute(['id' => $id]);

$directorioUploads = __DIR__ . '/../../registro/public/uploads';
$directorioCredenciales = __DIR__ . '/../../registro/public/credenciales';

if ($alumno['foto_path']) {
    $ruta = $directorioUploads . '/' . basename($alumno['foto_path']);
    if (is_file($ruta)) {
        unlink($ruta);
    }
}
if ($alumno['credencial_path']) {
    $ruta = $directorioCredenciales . '/' . basename($alumno['credencial_path']);
    if (is_file($ruta)) {
        unlink($ruta);
    }
}

header('Location: /admin/public/alumnos.php?msg=eliminado');
exit;
