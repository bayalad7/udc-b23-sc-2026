<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/public/competiciones.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: /admin/public/competiciones.php?error=no_encontrado');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare('SELECT COUNT(*) AS n FROM equipos WHERE id_competicion = :id');
$consulta->execute(['id' => $id]);
if ((int) $consulta->fetch()['n'] > 0) {
    header('Location: /admin/public/competicion.php?id=' . $id . '&error=tiene_dependientes');
    exit;
}

$eliminar = $pdo->prepare('DELETE FROM competiciones WHERE id = :id');
$eliminar->execute(['id' => $id]);

header('Location: /admin/public/competiciones.php?msg=eliminado');
exit;
