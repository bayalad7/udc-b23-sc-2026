<?php
declare(strict_types=1);

// Datos del pedido de camisas del aniversario, compartidos por la sección
// "Tallas de camisa solicitadas" del dashboard (admin/public/index.php) y por
// su exportación a Excel/PDF (admin/includes/exportar-tallas-camisa.php).
//
// Viven aquí y no en cada archivo porque el pedido al proveedor sale de estos
// números: si la tabla en pantalla y el Excel se calcularan por separado,
// bastaría con tocar uno de los dos para que dejaran de cuadrar.
//
// El pedido cubre a DOS poblaciones que se guardan en tablas distintas y sin
// relación entre sí (ver schema.sql): `alumnos`, que se desglosa por grado y
// grupo, y `trabajadores` (personal administrativo y docente), que no tiene
// grado/grupo y se cuenta como una columna más. Para el proveedor son la
// misma camisa, así que el total de cada talla suma las dos.
//
// Incluir SIEMPRE con require_once: son constantes y funciones de archivo.

/** Orden en que se muestran las tallas — el mismo del ENUM camisa_talla. */
const CAMISA_TALLAS_ORDEN = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];

/** Etiqueta de la columna del personal en el resumen (no es un grupo escolar). */
const CAMISA_COLUMNA_PERSONAL = 'Personal';

/**
 * Resumen por talla: una fila por talla y una columna por grado/grupo, más la
 * columna del personal y el total.
 *
 * Devuelve ['tallas' => string[], 'columnas' => string[], 'pivote' =>
 * array<string, array<string, int>>, 'totales' => array<string, int>].
 */
function tallasCamisaResumen(PDO $pdo): array
{
    $pivote = [];
    $columnasGrupo = [];

    $porGradoGrupo = $pdo->query(
        'SELECT camisa_talla, grado, grupo, COUNT(*) AS total FROM alumnos
         GROUP BY camisa_talla, grado, grupo'
    )->fetchAll();
    foreach ($porGradoGrupo as $fila) {
        $columna = $fila['grado'] . '°' . $fila['grupo'];
        $columnasGrupo[$columna] = true;
        $pivote[$fila['camisa_talla']][$columna] = (int) $fila['total'];
    }

    $porPersonal = $pdo->query(
        'SELECT camisa_talla, COUNT(*) AS total FROM trabajadores GROUP BY camisa_talla'
    )->fetchAll();
    foreach ($porPersonal as $fila) {
        $pivote[$fila['camisa_talla']][CAMISA_COLUMNA_PERSONAL] = (int) $fila['total'];
    }

    $columnasGrupo = array_keys($columnasGrupo);
    sort($columnasGrupo);
    // El personal siempre va al final, después de los grupos escolares.
    $columnas = array_merge($columnasGrupo, [CAMISA_COLUMNA_PERSONAL]);

    // Las filas salen de las tallas que alguien pidió — en cualquiera de las
    // dos tablas. Recorrer el orden canónico y no las llaves del pivote evita
    // dos errores: que una talla pedida SOLO por el personal se quedara fuera
    // (antes las filas salían de un GROUP BY sobre alumnos), y que el orden
    // dependa de en qué momento se insertó cada fila.
    $tallas = [];
    $totales = [];
    foreach (CAMISA_TALLAS_ORDEN as $talla) {
        if (!isset($pivote[$talla])) {
            continue;
        }
        $tallas[] = $talla;
        $totales[$talla] = array_sum($pivote[$talla]);
    }

    return ['tallas' => $tallas, 'columnas' => $columnas, 'pivote' => $pivote, 'totales' => $totales];
}

/** Detalle de alumnos para la lista de entrega, agrupado por grado y grupo. */
function tallasCamisaDetalleAlumnos(PDO $pdo): array
{
    $filas = $pdo->query(
        'SELECT numero_cuenta, nombre_completo, grado, grupo, camisa_corte, camisa_talla
         FROM alumnos ORDER BY grado, grupo, nombre_completo'
    )->fetchAll();

    $porGrupo = [];
    foreach ($filas as $fila) {
        $porGrupo[$fila['grado'] . '°' . $fila['grupo']][] = $fila;
    }

    return $porGrupo;
}

/** Detalle del personal para la lista de entrega, agrupado por tipo. */
function tallasCamisaDetallePersonal(PDO $pdo): array
{
    $filas = $pdo->query(
        'SELECT numero_trabajador, nombre_completo, tipo, camisa_corte, camisa_talla
         FROM trabajadores ORDER BY tipo, nombre_completo'
    )->fetchAll();

    $porTipo = [];
    foreach ($filas as $fila) {
        $porTipo[$fila['tipo']][] = $fila;
    }

    return $porTipo;
}

/** Conteo por talla de una sola población, listo para las series de la gráfica. */
function tallasCamisaSerie(PDO $pdo, string $tabla, array $tallas): array
{
    // $tabla no viene del cliente nunca — se llama con literales desde el
    // dashboard — pero se valida igual porque va concatenada a la consulta.
    if (!in_array($tabla, ['alumnos', 'trabajadores'], true)) {
        throw new InvalidArgumentException('Tabla no permitida: ' . $tabla);
    }

    $conteo = [];
    foreach ($pdo->query("SELECT camisa_talla, COUNT(*) AS total FROM $tabla GROUP BY camisa_talla")->fetchAll() as $fila) {
        $conteo[$fila['camisa_talla']] = (int) $fila['total'];
    }

    return array_map(static fn(string $talla): int => $conteo[$talla] ?? 0, $tallas);
}
