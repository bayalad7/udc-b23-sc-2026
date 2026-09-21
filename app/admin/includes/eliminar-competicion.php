<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/competiciones.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/public/competiciones.php?error=no_encontrado');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare('SELECT COUNT(*) AS n FROM equipos WHERE id_competicion = :id');
$consulta->execute(['id' => $id]);
if ((int) $consulta->fetch()['n'] > 0) {
    header('Location: ' . BASE_URL . '/admin/public/competicion.php?id=' . $id . '&error=tiene_dependientes');
    exit;
}

// Misma lógica para la llave de enfrentamientos: ninguna FK del esquema usa
// ON DELETE CASCADE, y además borrar en silencio una llave ya armada (con sus
// resultados) sería una sorpresa desagradable. Se borra desde llave.php.
$consultaLlave = $pdo->prepare('SELECT COUNT(*) AS n FROM partidos WHERE id_competicion = :id');
$consultaLlave->execute(['id' => $id]);
if ((int) $consultaLlave->fetch()['n'] > 0) {
    header('Location: ' . BASE_URL . '/admin/public/competicion.php?id=' . $id . '&error=tiene_llave');
    exit;
}

$consultaConvocatoria = $pdo->prepare('SELECT convocatoria FROM competiciones WHERE id = :id');
$consultaConvocatoria->execute(['id' => $id]);
$convocatoria = $consultaConvocatoria->fetch()['convocatoria'] ?? null;

$eliminar = $pdo->prepare('DELETE FROM competiciones WHERE id = :id');
$eliminar->execute(['id' => $id]);

if ($convocatoria) {
    $ruta = __DIR__ . '/../../assets/img/convocatorias/' . basename($convocatoria);
    if (is_file($ruta)) {
        unlink($ruta);
    }
}

header('Location: ' . BASE_URL . '/admin/public/competiciones.php?msg=eliminado');
exit;
