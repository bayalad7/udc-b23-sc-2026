<?php
declare(strict_types=1);

// Borra la llave completa de una competición (sus partidos y los resultados
// capturados). Los equipos y sus integrantes NO se tocan: lo que se borra es
// el cuadro de enfrentamientos, no la inscripción.
//
// La confirmación es el confirm() de la zona roja de llave.php, igual que en
// los otros borrados del panel — no se pide la contraseña de nuevo porque,
// a diferencia del reseteo del padrón, esto se rehace con un clic en
// "Generar la llave".

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php');
    exit;
}

$idCompeticion = (int) ($_POST['id_competicion'] ?? 0);
if ($idCompeticion <= 0) {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php?error=no_encontrado');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$borrar = $pdo->prepare('DELETE FROM partidos WHERE id_competicion = :id');
$borrar->execute(['id' => $idCompeticion]);

header('Location: ' . BASE_URL . '/admin/public/llaves.php?msg=llave_eliminada');
exit;
