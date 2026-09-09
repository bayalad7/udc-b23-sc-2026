<?php
declare(strict_types=1);

// Cortes de caja (entregas de efectivo del jefe de grupo al staff) —
// compartido por app/admin (quien registra el corte y emite el recibo) y
// app/camisas (el jefe, que solo lo consulta) — mismo criterio que costo.php,
// hermano de este archivo: si cada pantalla sumara por su cuenta, bastaría
// con tocar una para que dejaran de cuadrar.
//
// Incluir SIEMPRE con require_once: son funciones de archivo.

/** Histórico de cortes de un grado+grupo, más recientes primero. */
function camisaCortesListar(PDO $pdo, string $grado, string $grupo): array
{
    $consulta = $pdo->prepare(
        'SELECT id, monto, entregado_por, recibido_por, fecha_movimiento, fecha_registro
         FROM camisa_cortes WHERE grado = :grado AND grupo = :grupo
         ORDER BY fecha_movimiento DESC, id DESC'
    );
    $consulta->execute(['grado' => $grado, 'grupo' => $grupo]);

    return $consulta->fetchAll();
}

/**
 * Total entregado — se calcula en PHP sobre un listado ya consultado (mismo
 * criterio que camisaResumen(): una segunda consulta de SUM() podría no
 * cuadrar con las filas que la persona tiene enfrente).
 */
function camisaCortesTotal(array $cortes): float
{
    $total = 0.0;
    foreach ($cortes as $corte) {
        $total += (float) $corte['monto'];
    }

    return $total;
}

/**
 * Encuadre entre lo recaudado según el sistema (camisaResumen()['recaudado'])
 * y lo entregado en cortes.
 *
 * diferencia > 0: el jefe todavía trae dinero pendiente de entregar.
 * diferencia < 0: entregó de más — señal de que algún pago de un alumno se
 * capturó mal en el sistema (el monto del corte nunca se topa contra lo
 * recaudado a propósito, ver camisa_cortes en schema.sql).
 *
 * Devuelve ['entregado' => float, 'diferencia' => float].
 */
function camisaCortesEncuadre(float $recaudado, array $cortes): array
{
    $entregado = camisaCortesTotal($cortes);

    return [
        'entregado' => $entregado,
        'diferencia' => $recaudado - $entregado,
    ];
}
