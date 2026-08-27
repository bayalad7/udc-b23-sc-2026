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
// Son DOS pedidos INDEPENDIENTES, uno por cada población, y las poblaciones
// viven en tablas distintas y sin relación entre sí (ver schema.sql):
// `alumnos`, que se desglosa por grado y grupo, y `trabajadores` (personal
// administrativo y docente), que se desglosa por tipo. Cada uno se cotiza y se
// encarga por separado, así que sus resúmenes NO se suman en un total común:
// un gran total mezclado invitaría a levantar un solo pedido con la suma, que
// es justo lo que no se quiere. De ahí que haya dos funciones de resumen
// simétricas en vez de una sola tabla con el personal como columna extra.
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

/**
 * Arma un resumen a partir de un pivote ya llenado: descarta las tallas que
 * nadie pidió y devuelve la estructura en el orden canónico de tallas.
 *
 * Las dos funciones de resumen la comparten para que la tabla de alumnos y la
 * del personal se lean igual y se puedan pintar con el mismo bloque de HTML.
 * Recorrer CAMISA_TALLAS_ORDEN y no las llaves del pivote deja el orden fijo
 * en vez de depender de en qué momento se insertó cada fila.
 *
 * Devuelve ['tallas' => string[], 'columnas' => string[], 'pivote' =>
 * array<string, array<string, int>>, 'totales' => array<string, int>].
 */
function camisaResumenDesdePivote(array $pivote, array $columnas): array
{
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
 * Pedido de los alumnos: una fila por talla, una columna por grado/grupo y el
 * total de la talla. No incluye al personal — es un pedido aparte, ver
 * tallasCamisaResumenPersonal().
 */
function tallasCamisaResumenAlumnos(PDO $pdo): array
{
    $pivote = [];
    $columnas = [];

    $porGradoGrupo = $pdo->query(
        'SELECT camisa_talla, grado, grupo, COUNT(*) AS total FROM alumnos
         WHERE ' . CAMISA_ALUMNOS_FILTRO . '
         GROUP BY camisa_talla, grado, grupo'
    )->fetchAll();
    foreach ($porGradoGrupo as $fila) {
        $columna = $fila['grado'] . '°' . $fila['grupo'];
        $columnas[$columna] = true;
        $pivote[$fila['camisa_talla']][$columna] = (int) $fila['total'];
    }

    $columnas = array_keys($columnas);
    sort($columnas);

    return camisaResumenDesdePivote($pivote, $columnas);
}

/**
 * Pedido del personal: una fila por talla, una columna por tipo
 * (Administrativo/Docente) y el total de la talla. Mismo formato que el de
 * alumnos, pero contado y encargado por separado.
 */
function tallasCamisaResumenPersonal(PDO $pdo): array
{
    require_once __DIR__ . '/../../trabajadores/includes/catalogo.php';

    $pivote = [];
    $presentes = [];

    $porTipo = $pdo->query(
        'SELECT camisa_talla, tipo, COUNT(*) AS total FROM trabajadores GROUP BY camisa_talla, tipo'
    )->fetchAll();
    foreach ($porTipo as $fila) {
        $presentes[$fila['tipo']] = true;
        $pivote[$fila['camisa_talla']][$fila['tipo']] = (int) $fila['total'];
    }

    // Se ordenan por el catálogo y no alfabéticamente: si mañana se agrega un
    // tipo nuevo al ENUM, la columna aparece donde el catálogo la ponga.
    $columnas = array_values(array_filter(
        TRABAJADOR_TIPOS,
        static fn(string $tipo): bool => isset($presentes[$tipo])
    ));

    return camisaResumenDesdePivote($pivote, $columnas);
}

/**
 * Tallas que aparecen en cualquiera de los dos pedidos, en el orden canónico.
 * Sirve para el eje de la gráfica, que sí muestra las dos poblaciones juntas
 * —una barra al lado de la otra, nunca apiladas— y necesita un eje común.
 */
function camisaTallasUnion(array ...$listasDeTallas): array
{
    $union = array_unique(array_merge(...$listasDeTallas));

    return array_values(array_filter(
        CAMISA_TALLAS_ORDEN,
        static fn(string $talla): bool => in_array($talla, $union, true)
    ));
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
