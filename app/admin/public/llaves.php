<?php
declare(strict_types=1);

// Listado de llaves: una fila por competición, con cuántos equipos tiene
// inscritos y en qué va su llave. Es la puerta de entrada al módulo — armar
// y resolver la llave de una competición concreta vive en llave.php.
//
// Se listan TODAS las competiciones, no solo los torneos del Día Deportivo:
// la eliminación directa es un formato, no una propiedad del día (el
// Concurso del Conocimiento podría usarla igual). El Día Deportivo va
// primero en el orden porque es el caso para el que se construyó esto.

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}
require __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/llaves.php';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$competiciones = $pdo->query(
    "SELECT c.id, c.dia, c.tipo, c.nombre, c.hora_inicio, c.hora_fin, c.max_equipos,
            (SELECT COUNT(*) FROM equipos e WHERE e.id_competicion = c.id) AS total_equipos,
            (SELECT COUNT(*) FROM partidos p WHERE p.id_competicion = c.id) AS total_partidos,
            (SELECT COUNT(*) FROM partidos p WHERE p.id_competicion = c.id AND p.id_equipo_ganador IS NOT NULL) AS partidos_resueltos
     FROM competiciones c
     ORDER BY FIELD(c.dia, 'deportivo', 'academico', 'cultural'), c.hora_inicio, c.nombre"
)->fetchAll();

$diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural', 'deportivo' => 'Día Deportivo'];
$diasIcono = ['academico' => 'academico', 'cultural' => 'cultural', 'deportivo' => 'deportivo'];

$mensajesExito = ['llave_eliminada' => 'Llave eliminada.'];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;
$mensajesError = ['no_encontrado' => 'No se encontró la competición.'];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir('Llaves', 'llaves');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<p class="mb-6 flex items-start gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
    <?= icono('llaves', 'mt-0.5 h-4 w-4 shrink-0 text-slate-400') ?>
    <span>
        Las llaves son de <strong>eliminación directa</strong>: quien pierde queda fuera.
        No hace falta que se llene el tope de equipos de la competición — si el número de equipos
        inscritos no es potencia de 2 (2, 4, 8, 16…), los equipos que sobran reciben un
        <strong>pase directo (bye)</strong> a la segunda ronda y el sistema los reparte solo.
    </span>
</p>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">Competición</th>
                <th class="px-4 py-3">Día</th>
                <th class="px-4 py-3 text-center">Equipos</th>
                <th class="px-4 py-3 text-center">Llave</th>
                <th class="px-4 py-3 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($competiciones === []): ?>
            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">
                <span class="flex flex-col items-center gap-2">
                    <?= icono('trofeo', 'h-6 w-6 text-slate-300') ?>
                    Todavía no hay competiciones registradas.
                </span>
            </td></tr>
            <?php endif; ?>
            <?php foreach ($competiciones as $competicion):
                $totalEquipos = (int) $competicion['total_equipos'];
                $totalPartidos = (int) $competicion['total_partidos'];
                $resueltos = (int) $competicion['partidos_resueltos'];
                $maxEquipos = $competicion['max_equipos'] !== null ? (int) $competicion['max_equipos'] : null;
            ?>
            <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                <td class="px-4 py-3">
                    <span class="font-medium"><?= htmlspecialchars($competicion['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="block text-xs text-slate-400"><?= substr((string) $competicion['hora_inicio'], 0, 5) ?>–<?= substr((string) $competicion['hora_fin'], 0, 5) ?></span>
                </td>
                <td class="px-4 py-3 text-slate-500">
                    <span class="flex items-center gap-1.5">
                        <?= icono($diasIcono[$competicion['dia']] ?? 'calendario', 'h-3.5 w-3.5 text-slate-400') ?>
                        <?= $diasLabel[$competicion['dia']] ?? $competicion['dia'] ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-center text-slate-500">
                    <?= $totalEquipos ?><?= $maxEquipos !== null ? '/' . $maxEquipos : '' ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <?php if ($totalPartidos === 0): ?>
                    <span class="text-slate-400">Sin generar</span>
                    <?php elseif ($resueltos >= $totalPartidos): ?>
                    <span class="flex items-center justify-center gap-1 font-medium text-emerald-700">
                        <?= icono('trofeo', 'h-3.5 w-3.5') ?>
                        Terminada
                    </span>
                    <?php else: ?>
                    <span class="text-slate-500"><?= $resueltos ?>/<?= $totalPartidos ?> partidos resueltos</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="flex items-center justify-center gap-1">
                        <a href="<?= BASE_URL ?>/admin/public/llave.php?id=<?= (int) $competicion['id'] ?>"
                           title="<?= $totalPartidos === 0 ? 'Armar la llave' : 'Ver / editar la llave' ?>"
                           class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                            <?= icono($totalPartidos === 0 ? 'agregar' : 'llaves', 'h-4 w-4') ?>
                        </a>
                        <?php if ($totalPartidos > 0): ?>
                        <a href="<?= BASE_URL ?>/admin/includes/exportar-llave.php?id=<?= (int) $competicion['id'] ?>"
                           title="Descargar la llave en PDF"
                           class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                            <?= icono('descargar', 'h-4 w-4') ?>
                        </a>
                        <?php endif; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php layoutAdminCerrar(); ?>
