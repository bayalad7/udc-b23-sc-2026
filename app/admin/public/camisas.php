<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}
require __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../camisas/includes/costo.php';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// Cobranza de las camisas del alumnado, vista desde el staff: el costo (que se
// fija aquí y rige para todos los grupos), quién es el jefe de cada grado+grupo
// y cuánto lleva juntado cada uno. La captura del día a día NO se hace en esta
// pantalla — la hace cada jefe desde app/camisas sobre su propio grupo; aquí se
// consulta el total y se corrige de forma puntual desde la ficha del alumno.
//
// El personal (`trabajadores`) no aparece: su registro existe solo para el
// pedido de tallas y no lleva control de pago (ver schema.sql).

$costo = camisaCosto($pdo);

$mensajesExito = ['costo_guardado' => 'Costo de la camisa actualizado.'];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;

$mensajesError = [
    'costo_invalido' => 'El costo no es válido: escribe una cantidad mayor a cero, como 150.',
    'costo_menor_a_pagos' => 'No se puede bajar el costo a menos de lo que alguien ya pagó ('
        . htmlspecialchars((string) ($_GET['detalle'] ?? ''), ENT_QUOTES, 'UTF-8')
        . '). Ajusta primero esos pagos desde la ficha del alumno.',
];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

// --- Corte por grado y grupo -------------------------------------------------
// El costo entra como parámetro (y no como literal) para que "liquidados"
// signifique lo mismo aquí que en la pantalla del jefe.
$consultaGrupos = $pdo->prepare(
    'SELECT grado, grupo,
            COUNT(*) AS total,
            SUM(camisa_pedir = 1) AS piden,
            SUM(camisa_pedir = 1 AND camisa_pago > 0 AND camisa_pago < :costo1) AS abonan,
            SUM(camisa_pedir = 1 AND camisa_pago >= :costo2) AS liquidados,
            SUM(CASE WHEN camisa_pedir = 1 THEN camisa_pago ELSE 0 END) AS recaudado
     FROM alumnos GROUP BY grado, grupo ORDER BY grado, grupo'
);
$consultaGrupos->execute(['costo1' => $costo, 'costo2' => $costo]);
$filasGrupo = $consultaGrupos->fetchAll();

$jefesPorGrupo = [];
foreach ($pdo->query('SELECT id, nombre_completo, grado, grupo FROM alumnos WHERE es_jefe = 1')->fetchAll() as $jefe) {
    $jefesPorGrupo[$jefe['grado'] . $jefe['grupo']] = $jefe;
}

$grupos = [];
$totales = ['piden' => 0, 'abonan' => 0, 'liquidados' => 0, 'recaudado' => 0.0, 'esperado' => 0.0, 'pendiente' => 0.0, 'sin_jefe' => 0];
foreach ($filasGrupo as $fila) {
    $piden = (int) $fila['piden'];
    $recaudado = (float) $fila['recaudado'];
    $esperado = $piden * $costo;
    $clave = $fila['grado'] . $fila['grupo'];

    $grupos[] = [
        'etiqueta' => $fila['grado'] . '°' . $fila['grupo'],
        'jefe' => $jefesPorGrupo[$clave] ?? null,
        'total' => (int) $fila['total'],
        'piden' => $piden,
        'abonan' => (int) $fila['abonan'],
        'liquidados' => (int) $fila['liquidados'],
        'recaudado' => $recaudado,
        'esperado' => $esperado,
        'pendiente' => max(0.0, $esperado - $recaudado),
    ];

    $totales['piden'] += $piden;
    $totales['abonan'] += (int) $fila['abonan'];
    $totales['liquidados'] += (int) $fila['liquidados'];
    $totales['recaudado'] += $recaudado;
    $totales['esperado'] += $esperado;
    if (!isset($jefesPorGrupo[$clave])) {
        $totales['sin_jefe']++;
    }
}
$totales['pendiente'] = max(0.0, $totales['esperado'] - $totales['recaudado']);

// --- Listado de alumnos ------------------------------------------------------
$grado = trim((string) ($_GET['grado'] ?? ''));
$grupo = trim((string) ($_GET['grupo'] ?? ''));
$estado = trim((string) ($_GET['estado'] ?? ''));
$buscar = trim((string) ($_GET['buscar'] ?? ''));

$condiciones = [];
$parametros = [];
if (in_array($grado, ['1', '3', '5'], true)) {
    $condiciones[] = 'grado = :grado';
    $parametros['grado'] = $grado;
}
if (in_array($grupo, ['A', 'B', 'C'], true)) {
    $condiciones[] = 'grupo = :grupo';
    $parametros['grupo'] = $grupo;
}
if ($buscar !== '') {
    // Placeholders separados porque PDO::ATTR_EMULATE_PREPARES está desactivado
    // (ver config/db.php) — mismo motivo que en admin/public/alumnos.php.
    $condiciones[] = '(nombre_completo LIKE :buscar1 OR numero_cuenta LIKE :buscar2)';
    $parametros['buscar1'] = '%' . $buscar . '%';
    $parametros['buscar2'] = '%' . $buscar . '%';
}
if ($estado === 'pendientes') {
    $condiciones[] = 'camisa_pedir = 1 AND camisa_pago < :costo_estado';
    $parametros['costo_estado'] = $costo;
} elseif ($estado === 'liquidados') {
    $condiciones[] = 'camisa_pedir = 1 AND camisa_pago >= :costo_estado';
    $parametros['costo_estado'] = $costo;
} elseif ($estado === 'sin_pagar') {
    $condiciones[] = 'camisa_pedir = 1 AND camisa_pago <= 0';
} elseif ($estado === 'no_piden') {
    $condiciones[] = 'camisa_pedir = 0';
}
$whereSql = $condiciones !== [] ? 'WHERE ' . implode(' AND ', $condiciones) : '';

$consultaAlumnos = $pdo->prepare(
    "SELECT id, numero_cuenta, nombre_completo, grado, grupo, camisa_talla, camisa_pedir, camisa_pago, es_jefe
     FROM alumnos $whereSql ORDER BY grado, grupo, nombre_completo LIMIT 300"
);
$consultaAlumnos->execute($parametros);
$alumnos = $consultaAlumnos->fetchAll();

layoutAdminAbrir('Camisas', 'camisas');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="flex items-center gap-3 rounded-lg border-l-4 border-emerald-500 bg-emerald-50 p-3 shadow-sm">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><?= icono('dinero', 'h-5 w-5') ?></span>
        <div class="min-w-0">
            <span class="block truncate text-xs font-medium text-emerald-700">Recaudado</span>
            <span class="block text-xl font-bold text-emerald-900"><?= camisaMoneda($totales['recaudado']) ?></span>
            <span class="block truncate text-[11px] text-emerald-600">de <?= camisaMoneda($totales['esperado']) ?> esperados</span>
        </div>
    </div>

    <div class="flex items-center gap-3 rounded-lg border-l-4 p-3 shadow-sm <?= $totales['pendiente'] > 0 ? 'border-amber-500 bg-amber-50' : 'border-slate-200 bg-white' ?>">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full <?= $totales['pendiente'] > 0 ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-500' ?>"><?= icono('alerta', 'h-5 w-5') ?></span>
        <div class="min-w-0">
            <span class="block truncate text-xs font-medium <?= $totales['pendiente'] > 0 ? 'text-amber-700' : 'text-slate-500' ?>">Por cobrar</span>
            <span class="block text-xl font-bold <?= $totales['pendiente'] > 0 ? 'text-amber-900' : 'text-slate-900' ?>"><?= camisaMoneda($totales['pendiente']) ?></span>
            <span class="block truncate text-[11px] <?= $totales['pendiente'] > 0 ? 'text-amber-600' : 'text-slate-400' ?>"><?= number_format($totales['liquidados']) ?> de <?= number_format($totales['piden']) ?> liquidaron</span>
        </div>
    </div>

    <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500"><?= icono('camisa', 'h-5 w-5') ?></span>
        <div class="min-w-0">
            <span class="block truncate text-xs text-slate-500">Camisas encargadas</span>
            <span class="block text-xl font-bold text-slate-900"><?= number_format($totales['piden']) ?></span>
            <span class="block truncate text-[11px] text-slate-400">alumnos (el personal va aparte)</span>
        </div>
    </div>

    <div class="flex items-center gap-3 rounded-lg border-l-4 p-3 shadow-sm <?= $totales['sin_jefe'] > 0 ? 'border-red-500 bg-red-50' : 'border-slate-200 bg-white' ?>">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full <?= $totales['sin_jefe'] > 0 ? 'bg-red-100 text-red-600' : 'bg-slate-100 text-slate-500' ?>"><?= icono('usuarios', 'h-5 w-5') ?></span>
        <div class="min-w-0">
            <span class="block truncate text-xs font-medium <?= $totales['sin_jefe'] > 0 ? 'text-red-700' : 'text-slate-500' ?>">Grupos sin jefe</span>
            <span class="block text-xl font-bold <?= $totales['sin_jefe'] > 0 ? 'text-red-900' : 'text-slate-900' ?>"><?= $totales['sin_jefe'] ?></span>
            <span class="block truncate text-[11px] <?= $totales['sin_jefe'] > 0 ? 'text-red-600' : 'text-slate-400' ?>">de <?= count($grupos) ?> grupos con alumnos</span>
        </div>
    </div>
</div>

<section class="mt-6 rounded-xl bg-white p-5 shadow-sm">
    <h2 class="mb-2 flex items-center gap-2 text-base font-semibold">
        <?= icono('dinero', 'h-4 w-4 text-slate-400') ?>
        Costo de la camisa
    </h2>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <p class="min-w-0 text-sm text-slate-600">
            Rige para todos los grupos y es el tope de lo que un jefe puede registrar como pagado.
            Cambiarlo <strong>no</strong> modifica los pagos ya capturados: solo cambia cuánto falta por cobrar.
        </p>
        <form action="<?= BASE_URL ?>/admin/includes/guardar-camisa-costo.php" method="post" novalidate class="flex shrink-0 items-end gap-2">
            <div>
                <label for="camisa_costo" class="mb-1 block text-xs font-medium text-slate-500">Costo unitario</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-sm text-slate-400">$</span>
                    <input type="text" inputmode="decimal" id="camisa_costo" name="camisa_costo"
                           value="<?= number_format($costo, 2, '.', '') ?>"
                           class="w-32 rounded-lg border border-slate-300 py-2 pl-6 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                </div>
            </div>
            <button type="submit" class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                <?= icono('verificado', 'h-4 w-4') ?>
                Guardar
            </button>
        </form>
    </div>
</section>

<section class="mt-6 rounded-xl bg-white p-5 shadow-sm">
    <h2 class="mb-4 flex items-center gap-2 text-base font-semibold">
        <?= icono('usuarios', 'h-4 w-4 text-slate-400') ?>
        Cobranza por grado y grupo
    </h2>
    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <th class="px-3 py-2">Grupo</th>
                    <th class="px-3 py-2">Jefe de grupo</th>
                    <th class="px-3 py-2 text-center">Encargan</th>
                    <th class="px-3 py-2 text-center">Abonan</th>
                    <th class="px-3 py-2 text-center">Liquidados</th>
                    <th class="px-3 py-2 text-right">Recaudado</th>
                    <th class="px-3 py-2 text-right">Por cobrar</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($grupos === []): ?>
                <tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">Todavía no hay alumnos registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($grupos as $g): ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-3 py-2 font-medium"><?= htmlspecialchars($g['etiqueta'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-3 py-2">
                        <?php if ($g['jefe'] === null): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700"><?= icono('alerta', 'h-3 w-3') ?> Sin jefe asignado</span>
                        <?php else: ?>
                        <a href="<?= BASE_URL ?>/admin/public/alumno.php?id=<?= (int) $g['jefe']['id'] ?>" class="text-slate-700 underline hover:text-slate-900">
                            <?= htmlspecialchars($g['jefe']['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 text-center text-slate-500"><?= $g['piden'] ?> <span class="text-xs text-slate-400">de <?= $g['total'] ?></span></td>
                    <td class="px-3 py-2 text-center text-slate-500"><?= $g['abonan'] ?></td>
                    <td class="px-3 py-2 text-center text-slate-500"><?= $g['liquidados'] ?></td>
                    <td class="px-3 py-2 text-right font-medium text-emerald-700"><?= camisaMoneda($g['recaudado']) ?></td>
                    <td class="px-3 py-2 text-right font-medium <?= $g['pendiente'] > 0 ? 'text-amber-600' : 'text-slate-400' ?>"><?= camisaMoneda($g['pendiente']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($grupos !== []): ?>
            <tfoot>
                <tr class="border-t border-slate-200 bg-slate-50 text-sm font-semibold">
                    <td class="px-3 py-2" colspan="2">Total</td>
                    <td class="px-3 py-2 text-center"><?= number_format($totales['piden']) ?></td>
                    <td class="px-3 py-2 text-center"><?= number_format($totales['abonan']) ?></td>
                    <td class="px-3 py-2 text-center"><?= number_format($totales['liquidados']) ?></td>
                    <td class="px-3 py-2 text-right text-emerald-700"><?= camisaMoneda($totales['recaudado']) ?></td>
                    <td class="px-3 py-2 text-right <?= $totales['pendiente'] > 0 ? 'text-amber-600' : 'text-slate-400' ?>"><?= camisaMoneda($totales['pendiente']) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</section>

<section class="mt-6 rounded-xl bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
        <h2 class="flex items-center gap-2 text-base font-semibold">
            <?= icono('camisa', 'h-4 w-4 text-slate-400') ?>
            Detalle por alumno
        </h2>
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('buscar', 'h-4 w-4') ?></span>
                    <input type="text" name="buscar" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o número de cuenta"
                           class="w-52 rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Grado</label>
                <select name="grado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Todos</option>
                    <?php foreach (['1', '3', '5'] as $g): ?>
                    <option value="<?= $g ?>" <?= $grado === $g ? 'selected' : '' ?>><?= $g ?>°</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Grupo</label>
                <select name="grupo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Todos</option>
                    <?php foreach (['A', 'B', 'C'] as $gr): ?>
                    <option value="<?= $gr ?>" <?= $grupo === $gr ? 'selected' : '' ?>><?= $gr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Estado</label>
                <select name="estado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    <?php foreach ([
                        '' => 'Todos',
                        'pendientes' => 'Deben algo',
                        'sin_pagar' => 'Sin pagar nada',
                        'liquidados' => 'Ya liquidaron',
                        'no_piden' => 'No piden camisa',
                    ] as $valor => $etiqueta): ?>
                    <option value="<?= $valor ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                <?= icono('filtro', 'h-4 w-4') ?>
                Filtrar
            </button>
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <th class="px-3 py-2">Alumno</th>
                    <th class="px-3 py-2 text-center">Grupo</th>
                    <th class="px-3 py-2 text-center">Talla</th>
                    <th class="px-3 py-2 text-right">Pagado</th>
                    <th class="px-3 py-2 text-center">Estado</th>
                    <th class="px-3 py-2 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($alumnos === []): ?>
                <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">No se encontraron alumnos con esos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($alumnos as $a): $estadoPago = camisaEstadoPago($a, $costo); ?>
                <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium">
                        <?= htmlspecialchars($a['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ((int) $a['es_jefe'] === 1): ?>
                        <span class="ml-1.5 inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700"><?= icono('camisa', 'h-3 w-3') ?> Jefe</span>
                        <?php endif; ?>
                        <span class="block font-mono text-xs font-normal text-slate-400"><?= htmlspecialchars($a['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td class="px-3 py-2 text-center text-slate-500"><?= htmlspecialchars($a['grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars($a['grupo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-3 py-2 text-center text-slate-500"><?= htmlspecialchars($a['camisa_talla'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-3 py-2 text-right"><?= camisaMoneda((float) $a['camisa_pago']) ?></td>
                    <td class="px-3 py-2 text-center">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?= $estadoPago['clases'] ?>">
                            <?= htmlspecialchars($estadoPago['etiqueta'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <a href="<?= BASE_URL ?>/admin/public/alumno.php?id=<?= (int) $a['id'] ?>" title="Ver / editar alumno"
                           class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                            <?= icono('editar', 'h-4 w-4') ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($alumnos) === 300): ?>
    <p class="mt-3 text-xs text-slate-400">Se muestran los primeros 300 alumnos: afina los filtros para ver el resto.</p>
    <?php endif; ?>
</section>

<?php layoutAdminCerrar(); ?>
