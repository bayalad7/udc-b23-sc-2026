<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionInscripciones();

$_SESSION = [];
session_destroy();

header('Location: /inscripciones/public/index.php');
exit;
