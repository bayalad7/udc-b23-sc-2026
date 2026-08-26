<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionCamisas();

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . '/camisas/public/index.php');
exit;
