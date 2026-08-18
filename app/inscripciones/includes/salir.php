<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionInscripciones();

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . '/inscripciones/public/index.php');
exit;
