<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

// Abre o cierra las inscripciones al alumnado (sistema.liberar_inscripciones)
// desde la sección "Inscripciones" del dashboard. La bandera la leen
// app/inscripciones/public/*.php y, sobre todo, sus endpoints de escritura —
// ver app/inscripciones/includes/estado.php.
//
// A diferencia de clave_acceso/clave_admin, que solo se escriben si están en
// NULL (son de un solo uso), aquí SÍ se sobreescribe siempre: es un
// interruptor que el staff necesita poder mover en los dos sentidos las veces
// que haga falta. Se usa INSERT ... ON DUPLICATE KEY porque la fila de
// `sistema` puede no existir todavía si nadie ha configurado una contraseña.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}

$liberar = ($_POST['liberar'] ?? '') === '1' ? 1 : 0;

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$guardar = $pdo->prepare(
    'INSERT INTO sistema (id, liberar_inscripciones) VALUES (1, :liberar)
     ON DUPLICATE KEY UPDATE liberar_inscripciones = VALUES(liberar_inscripciones)'
);
$guardar->execute(['liberar' => $liberar]);

header('Location: ' . BASE_URL . '/admin/public/index.php?msg=' . ($liberar === 1 ? 'inscripciones_abiertas' : 'inscripciones_cerradas'));
exit;
