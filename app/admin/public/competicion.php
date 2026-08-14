<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: /admin/public/index.php');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$esNuevo = isset($_GET['nuevo']);
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

$competicion = [
    'id' => null, 'dia' => '', 'tipo' => '', 'hora_inicio' => '', 'hora_fin' => '',
    'nombre' => '', 'fecha_limite' => '',
];
$equipos = [];

if (!$esNuevo) {
    if ($id === null || $id <= 0) {
        header('Location: /admin/public/competiciones.php?error=no_encontrado');
        exit;
    }
    $consulta = $pdo->prepare('SELECT * FROM competiciones WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $fila = $consulta->fetch();
    if ($fila === false) {
        header('Location: /admin/public/competiciones.php?error=no_encontrado');
        exit;
    }
    $competicion = $fila;

    $consultaEquipos = $pdo->prepare(
        'SELECT eq.id, eq.nombre, eq.color_camisa, a.nombre_completo AS capitan, a.numero_cuenta AS capitan_cuenta
         FROM equipos eq
         JOIN alumnos a ON a.id = eq.id_alumno_capitan
         WHERE eq.id_competicion = :id
         ORDER BY eq.nombre'
    );
    $consultaEquipos->execute(['id' => $id]);
    $equiposFilas = $consultaEquipos->fetchAll();

    $consultaIntegrantes = $pdo->prepare(
        'SELECT id_equipo, tipo, nombre, codigo_participante, hora_entrada, hora_salida FROM integrantes WHERE id_equipo = :id ORDER BY FIELD(tipo, "alumno", "padre", "madre")'
    );
    foreach ($equiposFilas as $equipoFila) {
        $consultaIntegrantes->execute(['id' => $equipoFila['id']]);
        $equipoFila['integrantes'] = $consultaIntegrantes->fetchAll();
        $equipos[] = $equipoFila;
    }
}

$esConocimiento = $competicion['dia'] === 'academico' && $competicion['tipo'] === 'concurso';

require __DIR__ . '/../includes/layout.php';

$mensajesExito = ['creado' => 'Competición creada.', 'actualizado' => 'Cambios guardados.'];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;
$mensajesError = [
    'campos_incompletos' => 'Revisa que todos los campos estén completos y con formato válido.',
    'horario_invalido' => 'La hora de fin debe ser posterior a la hora de inicio.',
    'tiene_dependientes' => 'No se puede eliminar: la competición todavía tiene equipos inscritos.',
];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir($esNuevo ? 'Nueva competición' : 'Competición', 'competiciones');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<a href="/admin/public/competiciones.php" class="mb-4 inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
    <?= icono('atras', 'h-3.5 w-3.5') ?>
    Volver al listado
</a>

<div class="grid grid-cols-1 gap-6 <?= $esNuevo ? '' : 'lg:grid-cols-3' ?>">

    <div class="<?= $esNuevo ? '' : 'lg:col-span-1' ?>">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-base font-semibold"><?= $esNuevo ? 'Datos de la competición' : 'Editar competición' ?></h2>
            <form action="/admin/includes/guardar-competicion.php" method="post" class="grid grid-cols-1 gap-4">
                <?php if (!$esNuevo): ?>
                <input type="hidden" name="id" value="<?= (int) $competicion['id'] ?>">
                <?php endif; ?>

                <div>
                    <label for="nombre" class="mb-1 block text-sm font-medium">Nombre</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('trofeo', 'h-4 w-4') ?></span>
                        <input type="text" id="nombre" name="nombre" required maxlength="150"
                               value="<?= htmlspecialchars((string) $competicion['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="dia" class="mb-1 block text-sm font-medium">Día</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                            <select id="dia" name="dia" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                                <option value="">Elige...</option>
                                <option value="academico" <?= $competicion['dia'] === 'academico' ? 'selected' : '' ?>>Académico</option>
                                <option value="cultural" <?= $competicion['dia'] === 'cultural' ? 'selected' : '' ?>>Cultural</option>
                                <option value="deportivo" <?= $competicion['dia'] === 'deportivo' ? 'selected' : '' ?>>Deportivo</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="tipo" class="mb-1 block text-sm font-medium">Tipo</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                            <select id="tipo" name="tipo" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                                <option value="">Elige...</option>
                                <option value="concurso" <?= $competicion['tipo'] === 'concurso' ? 'selected' : '' ?>>Concurso</option>
                                <option value="torneo" <?= $competicion['tipo'] === 'torneo' ? 'selected' : '' ?>>Torneo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="hora_inicio" class="mb-1 block text-sm font-medium">Hora de inicio</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('reloj', 'h-4 w-4') ?></span>
                            <input type="time" id="hora_inicio" name="hora_inicio" required
                                   value="<?= htmlspecialchars(substr((string) $competicion['hora_inicio'], 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label for="hora_fin" class="mb-1 block text-sm font-medium">Hora de fin</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('reloj', 'h-4 w-4') ?></span>
                            <input type="time" id="hora_fin" name="hora_fin" required
                                   value="<?= htmlspecialchars(substr((string) $competicion['hora_fin'], 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="fecha_limite" class="mb-1 block text-sm font-medium">Fecha límite de inscripción</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('calendario', 'h-4 w-4') ?></span>
                        <input type="datetime-local" id="fecha_limite" name="fecha_limite" required
                               value="<?= htmlspecialchars(str_replace(' ', 'T', (string) $competicion['fecha_limite']), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <button type="submit" class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white active:bg-slate-700">
                    <?= icono('verificado', 'h-4 w-4') ?>
                    <?= $esNuevo ? 'Crear competición' : 'Guardar cambios' ?>
                </button>
            </form>

            <?php if (!$esNuevo): ?>
            <form action="/admin/includes/eliminar-competicion.php" method="post" class="mt-6 border-t border-slate-100 pt-4"
                  onsubmit="return confirm('¿Eliminar esta competición de forma permanente?');">
                <input type="hidden" name="id" value="<?= (int) $competicion['id'] ?>">
                <button type="submit" class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                    <?= icono('eliminar', 'h-4 w-4') ?>
                    Eliminar competición
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$esNuevo): ?>
    <div class="lg:col-span-2">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 flex items-center justify-between text-base font-semibold">
                <span>Equipos (<?= count($equipos) ?><?= $esConocimiento ? '/12' : '' ?>)</span>
                <?php if ($esConocimiento && count($equipos) >= 10): ?>
                <span class="flex items-center gap-1 text-xs font-medium <?= count($equipos) >= 12 ? 'text-red-700' : 'text-amber-700' ?>">
                    <?= icono('alerta', 'h-3.5 w-3.5') ?>
                    <?= count($equipos) >= 12 ? 'Límite alcanzado' : 'Cerca del límite de 12' ?>
                </span>
                <?php endif; ?>
            </h2>
            <?php if ($equipos === []): ?>
            <p class="flex items-center gap-2 text-sm text-slate-500">
                <?= icono('trofeo', 'h-4 w-4 text-slate-300') ?>
                Todavía no hay equipos inscritos.
            </p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($equipos as $equipo): ?>
                <details class="rounded-lg border border-slate-200 p-3">
                    <summary class="flex cursor-pointer items-center justify-between text-sm font-medium">
                        <span><?= htmlspecialchars($equipo['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="flex items-center gap-3 text-xs font-normal text-slate-500">
                            <span>Capitán: <?= htmlspecialchars($equipo['capitan'], ENT_QUOTES, 'UTF-8') ?> <span class="font-mono text-slate-400">(<?= htmlspecialchars($equipo['capitan_cuenta'], ENT_QUOTES, 'UTF-8') ?>)</span></span>
                            <?php if ($equipo['color_camisa']): ?>
                            <span>Color: <?= htmlspecialchars($equipo['color_camisa'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </span>
                    </summary>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-left uppercase text-slate-400">
                                    <th class="py-1.5 pr-3">Nombre</th>
                                    <th class="py-1.5 pr-3 text-center">Código participante</th>
                                    <th class="py-1.5 pr-3 text-center">Tipo</th>
                                    <th class="py-1.5 pr-3 text-center">Entrada</th>
                                    <th class="py-1.5 text-center">Salida</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipo['integrantes'] as $integrante): ?>
                                <tr class="border-b border-slate-50 last:border-0">
                                    <td class="py-1.5 pr-3"><?= htmlspecialchars($integrante['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-1.5 pr-3 text-center font-mono text-slate-500"><?= htmlspecialchars($integrante['codigo_participante'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-1.5 pr-3 text-center capitalize text-slate-500"><?= htmlspecialchars($integrante['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-1.5 pr-3 text-center text-slate-500"><?= $integrante['hora_entrada'] ? htmlspecialchars((string) $integrante['hora_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                    <td class="py-1.5 text-center text-slate-500"><?= $integrante['hora_salida'] ? htmlspecialchars((string) $integrante['hora_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php layoutAdminCerrar(); ?>
