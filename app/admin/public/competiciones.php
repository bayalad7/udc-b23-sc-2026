<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: /admin/public/index.php');
    exit;
}
require __DIR__ . '/../includes/layout.php';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$competiciones = $pdo->query(
    "SELECT c.id, c.dia, c.tipo, c.hora_inicio, c.hora_fin, c.nombre, c.fecha_limite, c.max_equipos, COUNT(e.id) AS total_equipos
     FROM competiciones c
     LEFT JOIN equipos e ON e.id_competicion = c.id
     GROUP BY c.id, c.dia, c.tipo, c.hora_inicio, c.hora_fin, c.nombre, c.fecha_limite, c.max_equipos
     ORDER BY c.dia, c.hora_inicio"
)->fetchAll();

$diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural', 'deportivo' => 'Día Deportivo'];

$mensajesExito = ['creado' => 'Competición creada.', 'actualizado' => 'Cambios guardados.', 'eliminado' => 'Competición eliminada.'];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;
$mensajesError = ['no_encontrado' => 'No se encontró la competición.'];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir('Competiciones', 'competiciones');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<div class="mb-6 flex justify-end">
    <a href="/admin/public/competicion.php?nuevo=1" class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
        <?= icono('agregar', 'h-4 w-4') ?>
        Nueva competición
    </a>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Día</th>
                <th class="px-4 py-3 text-center">Horario</th>
                <th class="px-4 py-3 text-center">Fecha límite</th>
                <th class="px-4 py-3 text-center">Equipos</th>
                <th class="px-4 py-3 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($competiciones === []): ?>
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">
                <span class="flex flex-col items-center gap-2">
                    <?= icono('trofeo', 'h-6 w-6 text-slate-300') ?>
                    Todavía no hay competiciones registradas.
                </span>
            </td></tr>
            <?php endif; ?>
            <?php foreach ($competiciones as $competicion):
                $maxEquiposFila = $competicion['max_equipos'] !== null ? (int) $competicion['max_equipos'] : null;
                $total = (int) $competicion['total_equipos'];
                $cercaDelLimite = $maxEquiposFila !== null && $total >= max(0, $maxEquiposFila - 2);
            ?>
            <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                <td class="px-4 py-3 font-mono text-xs text-slate-400">#<?= (int) $competicion['id'] ?></td>
                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($competicion['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-slate-500"><?= $diasLabel[$competicion['dia']] ?? $competicion['dia'] ?></td>
                <td class="px-4 py-3 text-center text-slate-500"><?= substr((string) $competicion['hora_inicio'], 0, 5) ?>–<?= substr((string) $competicion['hora_fin'], 0, 5) ?></td>
                <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars((string) $competicion['fecha_limite'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="flex items-center justify-center gap-1 <?= $cercaDelLimite ? 'font-medium text-amber-700' : 'text-slate-500' ?>">
                        <?php if ($cercaDelLimite): ?><?= icono('alerta', 'h-3.5 w-3.5') ?><?php endif; ?>
                        <?= $total ?><?= $maxEquiposFila !== null ? '/' . $maxEquiposFila : '' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <a href="/admin/public/competicion.php?id=<?= (int) $competicion['id'] ?>" title="Ver / editar competición"
                       class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                        <?= icono('editar', 'h-4 w-4') ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php layoutAdminCerrar(); ?>
