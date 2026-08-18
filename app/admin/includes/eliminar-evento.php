<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/eventos.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/public/eventos.php?error=no_encontrado');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare('SELECT COUNT(*) AS n FROM inscripciones WHERE id_evento = :id');
$consulta->execute(['id' => $id]);
if ((int) $consulta->fetch()['n'] > 0) {
    header('Location: ' . BASE_URL . '/admin/public/evento.php?id=' . $id . '&error=tiene_dependientes');
    exit;
}

$eliminar = $pdo->prepare('DELETE FROM eventos WHERE id = :id');
$eliminar->execute(['id' => $id]);

header('Location: ' . BASE_URL . '/admin/public/eventos.php?msg=eliminado');
exit;
