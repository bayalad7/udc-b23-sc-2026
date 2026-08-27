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
// De `alumnos` cuentan SOLO los que encargaron camisa Y ya abonaron algo
// (ver CAMISA_ALUMNOS_FILTRO): el pre-registro obliga a elegir talla, así que
// tener talla no significa que el alumno vaya a encargar la camisa — eso lo
// confirma el jefe de su grupo desde app/camisas, y el abono es lo que
// respalda el pedido. Sin este filtro se le encargarían al proveedor camisas
// de alumnos que nunca la pidieron o que no han pagado un peso. El personal no
// tiene esa distinción: su registro en app/trabajadores existe únicamente para
// pedir camisa.
//
// Incluir SIEMPRE con require_once: son constantes y funciones de archivo.

/** Orden en que se muestran las tallas — el mismo del ENUM camisa_talla. */
const CAMISA_TALLAS_ORDEN = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];

/**
 * Condición SQL que define qué alumno entra al pedido: la encargó y lleva
 * pagado algo. Vive en una constante porque la usan las cuatro consultas de
 * `alumnos` de este archivo (resumen, gráfica, detalle y selector de grupos);
 * si cada una la escribiera por su cuenta, cambiar el criterio en una y no en
 * las otras dejaría el resumen sin cuadrar con el detalle.
 *
 * `camisa_pago > 0` ya descarta el NULL en SQL (NULL > 0 no es verdadero),
 * pero se deja el IS NOT NULL escrito para que la regla se lea completa.
 */
const CAMISA_ALUMNOS_FILTRO = 'camisa_pedir = 1 AND camisa_pago IS NOT NULL AND camisa_pago > 0';

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
         WHERE ' . CAMISA_ALUMNOS_FILTRO . '
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

/**
 * Código corto y estable de un grado+grupo para viajar por la URL de la
 * exportación: '1A' en vez de '1°A'. Evita tener que codificar el ° y deja
 * la validación en algo trivial de comprobar (ver camisaGruposValidos).
 */
function camisaGrupoCodigo(string $grado, string $grupo): string
{
    return $grado . $grupo;
}

/** Etiqueta legible a partir del código: '1A' => '1°A'. */
function camisaGrupoEtiqueta(string $codigo): string
{
    return substr($codigo, 0, 1) . '°' . substr($codigo, 1, 1);
}

/**
 * Grupos que hoy tienen al menos un alumno registrado, como
 * [codigo => etiqueta] y en orden de grado/grupo. Es la lista que se ofrece
 * en el selector de la exportación y, a la vez, contra la que se validan los
 * códigos que llegan por la URL.
 */
function camisaGruposValidos(PDO $pdo): array
{
    $filas = $pdo->query(
        'SELECT DISTINCT grado, grupo FROM alumnos WHERE ' . CAMISA_ALUMNOS_FILTRO . ' ORDER BY grado, grupo'
    )->fetchAll();

    $grupos = [];
    foreach ($filas as $fila) {
        $codigo = camisaGrupoCodigo($fila['grado'], $fila['grupo']);
        $grupos[$codigo] = camisaGrupoEtiqueta($codigo);
    }

    return $grupos;
}

/**
 * Detalle de alumnos para la lista de entrega, agrupado por grado y grupo.
 *
 * $codigosGrupo vacío = el padrón completo (lo que se descargaba siempre).
 * Con códigos, se limita a esos grupos — se usa para bajar la lista de un
 * grupo suelto o de varios, ver la exportación con vista=detalle.
 */
function tallasCamisaDetalleAlumnos(PDO $pdo, array $codigosGrupo = []): array
{
    $sql = 'SELECT numero_cuenta, nombre_completo, grado, grupo, camisa_corte, camisa_talla
            FROM alumnos WHERE ' . CAMISA_ALUMNOS_FILTRO;
    $parametros = [];

    if ($codigosGrupo !== []) {
        // Un par de placeholders por grupo (grado y grupo van por separado
        // porque son dos columnas); nunca se concatena el valor a la consulta.
        // Los OR van entre paréntesis para que el filtro de camisa_pedir siga
        // aplicando a todos los grupos y no solo al primero.
        $condiciones = [];
        foreach (array_values($codigosGrupo) as $i => $codigo) {
            $condiciones[] = "(grado = :grado$i AND grupo = :grupo$i)";
            $parametros["grado$i"] = substr($codigo, 0, 1);
            $parametros["grupo$i"] = substr($codigo, 1, 1);
        }
        $sql .= ' AND (' . implode(' OR ', $condiciones) . ')';
    }

    $sql .= ' ORDER BY grado, grupo, nombre_completo';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    $porGrupo = [];
    foreach ($consulta->fetchAll() as $fila) {
        $porGrupo[camisaGrupoEtiqueta(camisaGrupoCodigo($fila['grado'], $fila['grupo']))][] = $fila;
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

    // Solo `alumnos` tiene camisa_pedir/camisa_pago: el personal de
    // `trabajadores` se registra únicamente para pedir camisa, así que ahí
    // cuentan todos.
    $filtro = $tabla === 'alumnos' ? 'WHERE ' . CAMISA_ALUMNOS_FILTRO : '';

    $conteo = [];
    foreach ($pdo->query("SELECT camisa_talla, COUNT(*) AS total FROM $tabla $filtro GROUP BY camisa_talla")->fetchAll() as $fila) {
        $conteo[$fila['camisa_talla']] = (int) $fila['total'];
    }

    return array_map(static fn(string $talla): int => $conteo[$talla] ?? 0, $tallas);
}
