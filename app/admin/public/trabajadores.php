<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
require_once __DIR__ . '/../../trabajadores/includes/catalogo.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}
require __DIR__ . '/../includes/layout.php';

// Listado del personal administrativo y docente registrado en
// app/trabajadores. Ese módulo solo levanta el pedido de camisas (sin
// credencial, sin asistencia, sin inscripciones), así que esta página se
// centra en lo mismo: cuántas camisas de cada talla hay que encargar y quién
// pidió cada una.

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$tipo = trim((string) ($_GET['tipo'] ?? ''));
$talla = trim((string) ($_GET['talla'] ?? ''));
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 30;

$condiciones = [];
$parametros = [];
if (in_array($tipo, TRABAJADOR_TIPOS, true)) {
    $condiciones[] = 'tipo = :tipo';
    $parametros['tipo'] = $tipo;
}
if (isset(TRABAJADOR_CAMISA_TALLAS[$talla])) {
    $condiciones[] = 'camisa_talla = :talla';
    $parametros['talla'] = $talla;
}
if ($buscar !== '') {
    // Placeholders separados (:buscar1/:buscar2) porque PDO::ATTR_EMULATE_PREPARES
    // está desactivado (ver config/db.php) — con prepares nativos, MySQL no
    // permite el mismo parámetro nombrado repetido dos veces en la misma query.
    $condiciones[] = '(nombre_completo LIKE :buscar1 OR numero_trabajador LIKE :buscar2)';
    $parametros['buscar1'] = '%' . $buscar . '%';
    $parametros['buscar2'] = '%' . $buscar . '%';
}
$whereSql = $condiciones !== [] ? 'WHERE ' . implode(' AND ', $condiciones) : '';

$consultaTotal = $pdo->prepare("SELECT COUNT(*) AS n FROM trabajadores $whereSql");
$consultaTotal->execute($parametros);
$total = (int) $consultaTotal->fetch()['n'];
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$consulta = $pdo->prepare(
    "SELECT id, tipo, numero_trabajador, nombre_completo, camisa_corte, camisa_talla, fecha_registro
     FROM trabajadores $whereSql ORDER BY tipo, nombre_completo LIMIT :limite OFFSET :offset"
);
foreach ($parametros as $clave => $valor) {
    $consulta->bindValue($clave, $valor);
}
$consulta->bindValue('limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue('offset', $offset, PDO::PARAM_INT);
$consulta->execute();
$trabajadores = $consulta->fetchAll();

// Resumen de camisas: siempre sobre el total del personal, no sobre el
// filtro — es la cifra que se manda al proveedor.
$resumenTallas = [];
foreach (array_keys(TRABAJADOR_CAMISA_TALLAS) as $tallaClave) {
    $resumenTallas[$tallaClave] = 0;
}
$conteoTallas = $pdo->query('SELECT camisa_talla, COUNT(*) AS n FROM trabajadores GROUP BY camisa_talla')->fetchAll();
foreach ($conteoTallas as $filaConteo) {
    $resumenTallas[$filaConteo['camisa_talla']] = (int) $filaConteo['n'];
}
$totalCamisas = array_sum($resumenTallas);

$filtrosActuales = array_filter(['buscar' => $buscar, 'tipo' => $tipo, 'talla' => $talla]);

$mensajesExito = [
    'creado' => 'Personal registrado correctamente.',
    'eliminado' => 'Registro de personal eliminado.',
];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;
$mensajesError = ['no_encontrado' => 'No se encontró ese registro de personal.'];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir('Personal', 'trabajadores');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<div class="mb-6 rounded-xl bg-white p-5 shadow-sm">
    <h2 class="mb-4 flex items-center gap-1.5 text-base font-semibold">
        <?= icono('camisa', 'h-4 w-4 text-slate-400') ?>
        Camisas del personal por talla
    </h2>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <?php foreach (TRABAJADOR_CAMISA_TALLAS as $tallaClave => $etiqueta): ?>
        <div class="rounded-lg border border-slate-200 px-3 py-2 text-center">
            <span class="block text-xs text-slate-500"><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="block text-xl font-bold"><?= $resumenTallas[$tallaClave] ?></span>
            <span class="block text-xs text-slate-400"><?= $tallaClave ?></span>
        </div>
        <?php endforeach; ?>
        <div class="rounded-lg bg-slate-900 px-3 py-2 text-center text-white">
            <span class="block text-xs text-slate-300">Total</span>
            <span class="block text-xl font-bold"><?= $totalCamisas ?></span>
            <span class="block text-xs text-slate-400">camisas</span>
        </div>
    </div>
</div>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('buscar', 'h-4 w-4') ?></span>
                <input type="text" name="buscar" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o número de trabajador"
                       class="w-56 rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Tipo</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                <select name="tipo" class="rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Todos</option>
                    <?php foreach (TRABAJADOR_TIPOS as $t): ?>
                    <option value="<?= $t ?>" <?= $tipo === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Talla</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                <select name="talla" class="rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Todas</option>
                    <?php foreach (TRABAJADOR_CAMISA_TALLAS as $tallaClave => $etiqueta): ?>
                    <option value="<?= $tallaClave ?>" <?= $talla === $tallaClave ? 'selected' : '' ?>><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
            <?= icono('filtro', 'h-4 w-4') ?>
            Filtrar
        </button>
    </form>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/admin/includes/exportar-trabajadores.php?<?= htmlspecialchars(http_build_query($filtrosActuales), ENT_QUOTES, 'UTF-8') ?>"
           class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            <?= icono('descargar', 'h-4 w-4') ?>
            Exportar a Excel
        </a>
        <a href="<?= BASE_URL ?>/admin/public/trabajador.php?nuevo=1"
           class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
            <?= icono('agregar', 'h-4 w-4') ?>
            Nuevo registro
        </a>
    </div>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">Nombre completo</th>
                <th class="px-4 py-3 text-center">Tipo</th>
                <th class="px-4 py-3 text-center">No. trabajador</th>
                <th class="px-4 py-3 text-center">Corte</th>
                <th class="px-4 py-3 text-center">Talla</th>
                <th class="px-4 py-3 text-center">Registro</th>
                <th class="px-4 py-3 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($trabajadores === []): ?>
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">
                <span class="flex flex-col items-center gap-2">
                    <?= icono('buscar', 'h-6 w-6 text-slate-300') ?>
                    No se encontró personal con esos filtros.
                </span>
            </td></tr>
            <?php endif; ?>
            <?php foreach ($trabajadores as $trabajador): ?>
            <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($trabajador['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                        <?= icono('maletin', 'h-3 w-3') ?>
                        <?= htmlspecialchars($trabajador['tipo'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($trabajador['numero_trabajador'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars($trabajador['camisa_corte'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-900 px-2 py-0.5 text-xs font-medium text-white"><?= htmlspecialchars($trabajador['camisa_talla'], ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td class="px-4 py-3 text-center text-xs text-slate-400"><?= htmlspecialchars((string) $trabajador['fecha_registro'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-center">
                    <a href="<?= BASE_URL ?>/admin/public/trabajador.php?id=<?= (int) $trabajador['id'] ?>" title="Ver / editar registro"
                       class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                        <?= icono('editar', 'h-4 w-4') ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPaginas > 1): ?>
<div class="mt-4 flex items-center justify-between text-sm text-slate-500">
    <span><?= number_format($total) ?> registros · página <?= $pagina ?> de <?= $totalPaginas ?></span>
    <div class="flex gap-2">
        <?php if ($pagina > 1): ?>
        <a href="?<?= htmlspecialchars(http_build_query($filtrosActuales + ['pagina' => $pagina - 1]), ENT_QUOTES, 'UTF-8') ?>" class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">Anterior</a>
        <?php endif; ?>
        <?php if ($pagina < $totalPaginas): ?>
        <a href="?<?= htmlspecialchars(http_build_query($filtrosActuales + ['pagina' => $pagina + 1]), ENT_QUOTES, 'UTF-8') ?>" class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">Siguiente</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php layoutAdminCerrar(); ?>
