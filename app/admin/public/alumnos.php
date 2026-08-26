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

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$grado = trim((string) ($_GET['grado'] ?? ''));
$grupo = trim((string) ($_GET['grupo'] ?? ''));
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 30;

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
    // Placeholders separados (:buscar1/:buscar2) porque PDO::ATTR_EMULATE_PREPARES
    // está desactivado (ver config/db.php) — con prepares nativos, MySQL no
    // permite el mismo parámetro nombrado repetido dos veces en la misma query.
    $condiciones[] = '(nombre_completo LIKE :buscar1 OR numero_cuenta LIKE :buscar2)';
    $parametros['buscar1'] = '%' . $buscar . '%';
    $parametros['buscar2'] = '%' . $buscar . '%';
}
$whereSql = $condiciones !== [] ? 'WHERE ' . implode(' AND ', $condiciones) : '';

$consultaTotal = $pdo->prepare("SELECT COUNT(*) AS n FROM alumnos $whereSql");
$consultaTotal->execute($parametros);
$total = (int) $consultaTotal->fetch()['n'];
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$consulta = $pdo->prepare(
    "SELECT id, numero_cuenta, nombre_completo, grado, grupo, correo_institucional, credencial_generada, foto_path, es_jefe
     FROM alumnos $whereSql ORDER BY nombre_completo LIMIT :limite OFFSET :offset"
);
foreach ($parametros as $clave => $valor) {
    $consulta->bindValue($clave, $valor);
}
$consulta->bindValue('limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue('offset', $offset, PDO::PARAM_INT);
$consulta->execute();
$alumnos = $consulta->fetchAll();

$filtrosActuales = array_filter(['buscar' => $buscar, 'grado' => $grado, 'grupo' => $grupo]);

$mensajesExito = [
    'creado' => 'Alumno registrado correctamente.',
    'eliminado' => 'Alumno eliminado.',
];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;
$mensajesError = ['no_encontrado' => 'No se encontró el alumno.'];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir('Alumnos', 'alumnos');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('buscar', 'h-4 w-4') ?></span>
                <input type="text" name="buscar" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o número de cuenta"
                       class="w-56 rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Grado</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                <select name="grado" class="rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Todos</option>
                    <?php foreach (['1', '3', '5'] as $g): ?>
                    <option value="<?= $g ?>" <?= $grado === $g ? 'selected' : '' ?>><?= $g ?>°</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Grupo</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                <select name="grupo" class="rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Todos</option>
                    <?php foreach (['A', 'B', 'C'] as $gr): ?>
                    <option value="<?= $gr ?>" <?= $grupo === $gr ? 'selected' : '' ?>><?= $gr ?></option>
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
        <a href="<?= BASE_URL ?>/admin/includes/exportar-alumnos.php?<?= htmlspecialchars(http_build_query($filtrosActuales), ENT_QUOTES, 'UTF-8') ?>"
           class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            <?= icono('descargar', 'h-4 w-4') ?>
            Exportar a Excel
        </a>
        <a href="<?= BASE_URL ?>/admin/public/alumno.php?nuevo=1"
           class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
            <?= icono('agregar', 'h-4 w-4') ?>
            Nuevo alumno
        </a>
    </div>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3"></th>
                <th class="px-4 py-3">Alumno</th>
                <th class="px-4 py-3 text-center">No. cuenta</th>
                <th class="px-4 py-3 text-center">Grado/Grupo</th>
                <th class="px-4 py-3">Correo institucional</th>
                <th class="px-4 py-3 text-center">Credencial</th>
                <th class="px-4 py-3 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($alumnos === []): ?>
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">
                <span class="flex flex-col items-center gap-2">
                    <?= icono('buscar', 'h-6 w-6 text-slate-300') ?>
                    No se encontraron alumnos con esos filtros.
                </span>
            </td></tr>
            <?php endif; ?>
            <?php foreach ($alumnos as $alumno): ?>
            <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                <td class="px-4 py-3">
                    <?php fotoMiniatura($alumno['foto_path'], $alumno['nombre_completo']); ?>
                </td>
                <td class="px-4 py-3 font-medium">
                    <?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ((int) $alumno['es_jefe'] === 1): ?>
                    <span class="ml-1.5 inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700"
                          title="Lleva el control de camisas de su grado y grupo"><?= icono('camisa', 'h-3 w-3') ?> Jefe</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars($alumno['grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars($alumno['grupo'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($alumno['correo_institucional'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-center">
                    <?php if ($alumno['credencial_generada']): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"><?= icono('exito', 'h-3 w-3') ?> Generada</span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700"><?= icono('alerta', 'h-3 w-3') ?> Pendiente</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <a href="<?= BASE_URL ?>/admin/public/alumno.php?id=<?= (int) $alumno['id'] ?>" title="Ver / editar alumno"
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
    <span><?= number_format($total) ?> alumnos · página <?= $pagina ?> de <?= $totalPaginas ?></span>
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
