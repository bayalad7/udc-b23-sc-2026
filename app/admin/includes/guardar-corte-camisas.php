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

$grado = trim((string) ($_POST['grado'] ?? ''));
$grupo = trim((string) ($_POST['grupo'] ?? ''));

function volverConError(string $grado, string $grupo, string $codigo): never
{
    header('Location: ' . BASE_URL . '/admin/public/corte-camisas.php?grado=' . urlencode($grado)
        . '&grupo=' . urlencode($grupo) . '&error=' . urlencode($codigo));
    exit;
}

if (!in_array($grado, ['1', '3', '5'], true) || !in_array($grupo, ['A', 'B', 'C'], true)) {
    header('Location: ' . BASE_URL . '/admin/public/camisas.php?error=grupo_invalido');
    exit;
}

// El monto es lo que el jefe entrega FÍSICAMENTE — a propósito NO se topa
// contra lo recaudado según el sistema (ver camisa_cortes en schema.sql): la
// única regla es que sea un número válido y mayor a cero.
$monto = camisaMontoDesdeTexto((string) ($_POST['monto'] ?? ''));
if ($monto === null || $monto <= 0) {
    volverConError($grado, $grupo, 'monto_invalido');
}

$entregadoPor = trim((string) ($_POST['entregado_por'] ?? ''));
$recibidoPor = trim((string) ($_POST['recibido_por'] ?? ''));
if ($entregadoPor === '' || mb_strlen($entregadoPor) > 150 || $recibidoPor === '' || mb_strlen($recibidoPor) > 150) {
    volverConError($grado, $grupo, 'campos_incompletos');
}

$fechaMovimientoTexto = trim((string) ($_POST['fecha_movimiento'] ?? ''));
$fechaMovimiento = DateTime::createFromFormat('Y-m-d', $fechaMovimientoTexto);
if ($fechaMovimiento === false) {
    volverConError($grado, $grupo, 'fecha_invalida');
}
// createFromFormat('Y-m-d', ...) sin componente de hora toma la hora ACTUAL
// del reloj (no medianoche) — sin este setTime(), comparar contra "today"
// (que sí es medianoche) marcaría el día de hoy como "futuro" en cuanto
// pasara la medianoche, es decir, casi siempre.
$fechaMovimiento->setTime(0, 0, 0);

// No puede ser una entrega que "todavía no ocurre" — resguardo contra un
// error de captura en el date-picker (ej. teclear el año equivocado).
$hoy = new DateTime('today');
if ($fechaMovimiento > $hoy) {
    volverConError($grado, $grupo, 'fecha_futura');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$insertar = $pdo->prepare(
    'INSERT INTO camisa_cortes (grado, grupo, monto, entregado_por, recibido_por, fecha_movimiento)
     VALUES (:grado, :grupo, :monto, :entregado_por, :recibido_por, :fecha_movimiento)'
);
$insertar->execute([
    'grado' => $grado,
    'grupo' => $grupo,
    'monto' => number_format($monto, 2, '.', ''),
    'entregado_por' => $entregadoPor,
    'recibido_por' => $recibidoPor,
    'fecha_movimiento' => $fechaMovimiento->format('Y-m-d'),
]);
$idNuevo = (int) $pdo->lastInsertId();

header('Location: ' . BASE_URL . '/admin/public/corte-camisas.php?grado=' . urlencode($grado)
    . '&grupo=' . urlencode($grupo) . '&msg=corte_guardado&id=' . $idNuevo);
exit;
