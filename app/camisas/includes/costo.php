<?php
declare(strict_types=1);

// Costo de la camisa y utilidades de cobranza, compartidas por app/camisas (el
// jefe de grupo que cobra) y app/admin (el staff que fija el costo y ve los
// reportes) — mismo criterio que app/trabajadores/includes/catalogo.php, que
// también lo reusa el CRUD del panel, y que app/admin/includes/tallas-camisa.php:
// si cada pantalla calculara por su cuenta lo recaudado y lo pendiente,
// bastaría con tocar una para que dejaran de cuadrar.
//
// Incluir SIEMPRE con require_once: son constantes y funciones de archivo.

/**
 * Costo que se usa mientras nadie lo haya configurado.
 *
 * La fila de `sistema` no existe desde el principio: nace con la primera
 * contraseña que alguien registra (ver app/admin/includes/registrar-clave.php)
 * y hasta entonces cualquier SELECT sobre ella no devuelve nada. Sin este
 * respaldo, el módulo del jefe mostraría un costo de $0 en una instalación
 * recién levantada.
 */
const CAMISA_COSTO_DEFECTO = 150.00;

/** Costo unitario vigente de la camisa oficial. */
function camisaCosto(PDO $pdo): float
{
    $fila = $pdo->query('SELECT camisa_costo FROM sistema WHERE id = 1')->fetch();

    return $fila === false ? CAMISA_COSTO_DEFECTO : (float) $fila['camisa_costo'];
}

/** Un solo formato de dinero en toda la app: "$150.00". */
function camisaMoneda(float $monto): string
{
    return '$' . number_format($monto, 2);
}

/**
 * Interpreta un monto tecleado por una persona y devuelve el float, o null si
 * no es un monto válido. Acepta "150", "150.5", "1,350.00" y "$150" porque es
 * lo que sale de teclear en un celular mientras se cobra en efectivo; rechaza
 * negativos, texto y más de dos decimales.
 */
function camisaMontoDesdeTexto(string $texto): ?float
{
    $limpio = str_replace(['$', ',', ' '], '', trim($texto));

    if ($limpio === '') {
        return 0.0;
    }
    if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $limpio)) {
        return null;
    }

    return (float) $limpio;
}

/**
 * Cifras de cobranza de un conjunto de alumnos ya consultados (cada uno con
 * camisa_pedir y camisa_pago). Se calcula en PHP y no en SQL porque las mismas
 * filas ya se traen para pintar el listado: una segunda consulta de agregados
 * podría dar un número distinto al de la tabla que el jefe tiene enfrente.
 *
 * Devuelve ['piden' => int, 'no_piden' => int, 'esperado' => float,
 *           'recaudado' => float, 'pendiente' => float,
 *           'liquidados' => int, 'sin_pagar' => int].
 */
function camisaResumen(array $alumnos, float $costo): array
{
    $resumen = [
        'piden' => 0,
        'no_piden' => 0,
        'esperado' => 0.0,
        'recaudado' => 0.0,
        'pendiente' => 0.0,
        'liquidados' => 0,
        'sin_pagar' => 0,
    ];

    foreach ($alumnos as $alumno) {
        if ((int) $alumno['camisa_pedir'] !== 1) {
            $resumen['no_piden']++;
            continue;
        }

        $pago = (float) $alumno['camisa_pago'];
        $resumen['piden']++;
        $resumen['esperado'] += $costo;
        $resumen['recaudado'] += $pago;

        if ($pago >= $costo) {
            $resumen['liquidados']++;
        } elseif ($pago <= 0) {
            $resumen['sin_pagar']++;
        }
    }

    // Nunca negativo: si alguien liquidó y después el staff bajó el costo, lo
    // pagado de más no se convierte en "pendiente en contra".
    $resumen['pendiente'] = max(0.0, $resumen['esperado'] - $resumen['recaudado']);

    return $resumen;
}

/** Etiqueta y color del estado de pago de un alumno, para pintar la insignia. */
function camisaEstadoPago(array $alumno, float $costo): array
{
    if ((int) $alumno['camisa_pedir'] !== 1) {
        return ['etiqueta' => 'No pide camisa', 'clases' => 'bg-slate-100 text-slate-600'];
    }

    $pago = (float) $alumno['camisa_pago'];

    if ($pago >= $costo) {
        return ['etiqueta' => 'Liquidada', 'clases' => 'bg-emerald-50 text-emerald-700'];
    }
    if ($pago > 0) {
        return ['etiqueta' => 'Abonó ' . camisaMoneda($pago), 'clases' => 'bg-amber-50 text-amber-700'];
    }

    return ['etiqueta' => 'Sin pagar', 'clases' => 'bg-red-50 text-red-700'];
}
