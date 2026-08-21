<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

// A diferencia de eliminar-alumno.php / eliminar-evento.php /
// eliminar-competicion.php, aquí no hay chequeo de dependientes: ninguna
// tabla del esquema referencia a `trabajadores` (ver schema.sql), porque el
// personal solo existe para el control de camisas — no se inscribe a eventos
// ni forma equipos. Tampoco hay archivos que borrar (no tiene foto ni
// credencial).

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/trabajadores.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/public/trabajadores.php?error=no_encontrado');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$eliminar = $pdo->prepare('DELETE FROM trabajadores WHERE id = :id');
$eliminar->execute(['id' => $id]);

if ($eliminar->rowCount() === 0) {
    header('Location: ' . BASE_URL . '/admin/public/trabajadores.php?error=no_encontrado');
    exit;
}

header('Location: ' . BASE_URL . '/admin/public/trabajadores.php?msg=eliminado');
exit;
