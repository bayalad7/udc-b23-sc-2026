<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require_once __DIR__ . '/../../camisas/includes/costo.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/camisas.php');
    exit;
}

function volverConError(string $codigo, array $extra = []): never
{
    header('Location: ' . BASE_URL . '/admin/public/camisas.php?' . http_build_query(['error' => $codigo] + $extra));
    exit;
}

$costo = camisaMontoDesdeTexto((string) ($_POST['camisa_costo'] ?? ''));

if ($costo === null || $costo <= 0) {
    volverConError('costo_invalido');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// Bajar el costo por debajo de lo que alguien ya pagó dejaría filas violando el
// tope (camisa_pago <= sistema.camisa_costo) sin que ningún trigger se entere:
// trg_alumnos_camisa_pago_* solo mira escrituras sobre `alumnos`, no sobre
// `sistema`. Se rechaza aquí, diciendo cuál es el pago más alto ya registrado.
$maximo = (float) ($pdo->query('SELECT COALESCE(MAX(camisa_pago), 0) AS maximo FROM alumnos')->fetch()['maximo']);

if ($costo < $maximo) {
    volverConError('costo_menor_a_pagos', ['detalle' => camisaMoneda($maximo)]);
}

// La fila de sistema puede no existir todavía (nace con la primera contraseña
// que se registra, ver registrar-clave.php). Mismo INSERT ... ON DUPLICATE KEY
// de allá, tocando solo su propia columna para no pisar las claves.
$guardar = $pdo->prepare(
    'INSERT INTO sistema (id, camisa_costo) VALUES (1, :costo)
     ON DUPLICATE KEY UPDATE camisa_costo = VALUES(camisa_costo)'
);
$guardar->execute(['costo' => number_format($costo, 2, '.', '')]);

header('Location: ' . BASE_URL . '/admin/public/camisas.php?msg=costo_guardado');
exit;
