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

$condiciones = [];
$parametros = [];
if ($buscar !== '') {
    $condiciones[] = '(e.nombre LIKE :buscar OR e.facilitador LIKE :buscar)';
    $parametros['buscar'] = '%' . $buscar . '%';
}
$whereSql = $condiciones !== [] ? 'WHERE ' . implode(' AND ', $condiciones) : '';

// Inscritos/entrada/salida se calculan desde inscripciones (no desde
// cupo_disponible) para que reflejen la realidad aunque cupo y conteo real
// llegaran a desalinearse.
$eventos = $pdo->prepare(
    "SELECT e.id, e.dia, e.tipo, e.hora_inicio, e.hora_fin, e.nombre, e.espacio, e.facilitador,
            e.cupo_maximo, e.cupo_disponible,
            COUNT(i.id_alumno) AS total_inscritos,
            COUNT(i.hora_entrada) AS total_entrada,
            COUNT(i.hora_salida) AS total_salida
     FROM eventos e
     LEFT JOIN inscripciones i ON i.id_evento = e.id
     $whereSql
     GROUP BY e.id
     ORDER BY e.dia, e.hora_inicio, e.nombre"
);
$eventos->execute($parametros);
$eventos = $eventos->fetchAll();

$diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural'];
$eventosPorDia = ['academico' => [], 'cultural' => []];
foreach ($eventos as $evento) {
    $eventosPorDia[$evento['dia']][] = $evento;
}

$mensajesExito = ['creado' => 'Evento creado.', 'actualizado' => 'Cambios guardados.', 'eliminado' => 'Evento eliminado.'];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;
$mensajesError = ['no_encontrado' => 'No se encontró el evento.'];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir('Eventos', 'eventos');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <form action="<?= BASE_URL ?>/admin/public/eventos.php" method="get" class="flex-1">
        <div class="relative max-w-sm">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('buscar', 'h-4 w-4') ?></span>
            <input type="search" name="buscar" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Buscar por nombre o facilitador..."
                   class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
        </div>
    </form>
    <a href="<?= BASE_URL ?>/admin/public/evento.php?nuevo=1" class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
        <?= icono('agregar', 'h-4 w-4') ?>
        Nuevo evento
    </a>
</div>

<?php foreach ($diasLabel as $diaClave => $diaLabel): ?>
<section class="mb-8">
    <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
        <?= icono($diaClave, 'h-4 w-4 text-slate-400') ?>
        <?= $diaLabel ?>
    </h2>
    <?php if ($eventosPorDia[$diaClave] === []): ?>
    <p class="flex items-center gap-2 rounded-xl bg-white p-5 text-sm text-slate-500 shadow-sm">
        <?= icono('lista', 'h-4 w-4 text-slate-300') ?>
        Sin eventos registrados todavía.
    </p>
    <?php else: ?>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3 text-center">Horario</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3 text-center">Tipo</th>
                    <th class="px-4 py-3">Espacio</th>
                    <th class="px-4 py-3 text-center">Cupo</th>
                    <th class="px-4 py-3 text-center">Inscritos</th>
                    <th class="px-4 py-3 text-center">Entrada</th>
                    <th class="px-4 py-3 text-center">Salida</th>
                    <th class="px-4 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eventosPorDia[$diaClave] as $evento):
                    $ocupados = (int) $evento['cupo_maximo'] - (int) $evento['cupo_disponible'];
                    $lleno = (int) $evento['cupo_disponible'] <= 0;
                ?>
                <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs text-slate-400">#<?= (int) $evento['id'] ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= substr((string) $evento['hora_inicio'], 0, 5) ?>–<?= substr((string) $evento['hora_fin'], 0, 5) ?></td>
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($evento['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center capitalize text-slate-500"><?= htmlspecialchars($evento['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($evento['espacio'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="<?= $lleno ? 'text-red-600 font-medium' : 'text-slate-500' ?>"><?= $ocupados ?>/<?= $evento['cupo_maximo'] ?></span>
                    </td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= (int) $evento['total_inscritos'] ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= (int) $evento['total_entrada'] ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= (int) $evento['total_salida'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <a href="<?= BASE_URL ?>/admin/public/evento.php?id=<?= (int) $evento['id'] ?>" title="Ver / editar evento"
                           class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                            <?= icono('editar', 'h-4 w-4') ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<?php layoutAdminCerrar(); ?>
