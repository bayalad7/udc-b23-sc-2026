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

$evento = [
    'id' => null, 'dia' => '', 'tipo' => '', 'hora_inicio' => '', 'hora_fin' => '',
    'facilitador' => '', 'nombre' => '', 'descripcion' => '', 'espacio' => '',
    'cupo_maximo' => 30, 'cupo_disponible' => 30, 'responsable' => '',
];
$inscritos = [];

if (!$esNuevo) {
    if ($id === null || $id <= 0) {
        header('Location: /admin/public/eventos.php?error=no_encontrado');
        exit;
    }
    $consulta = $pdo->prepare('SELECT * FROM eventos WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $fila = $consulta->fetch();
    if ($fila === false) {
        header('Location: /admin/public/eventos.php?error=no_encontrado');
        exit;
    }
    $evento = $fila;

    $consultaInscritos = $pdo->prepare(
        'SELECT i.origen, i.hora_entrada, i.hora_salida, a.nombre_completo, a.numero_cuenta, a.grado, a.grupo
         FROM inscripciones i
         JOIN alumnos a ON a.id = i.id_alumno
         WHERE i.id_evento = :id
         ORDER BY a.nombre_completo'
    );
    $consultaInscritos->execute(['id' => $id]);
    $inscritos = $consultaInscritos->fetchAll();
}

require __DIR__ . '/../includes/layout.php';

$mensajesExito = ['creado' => 'Evento creado.', 'actualizado' => 'Cambios guardados.'];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;
$mensajesError = [
    'campos_incompletos' => 'Revisa que todos los campos estén completos y con formato válido.',
    'horario_invalido' => 'La hora de fin debe ser posterior a la hora de inicio.',
    'cupo_invalido' => 'El cupo máximo debe ser al menos 1.',
    'cupo_menor_a_inscritos' => 'El cupo máximo no puede ser menor que el número de alumnos ya inscritos (' . htmlspecialchars((string) count($inscritos), ENT_QUOTES, 'UTF-8') . ').',
    'tiene_dependientes' => 'No se puede eliminar: el evento todavía tiene alumnos inscritos.',
];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir($esNuevo ? 'Nuevo evento' : 'Evento', 'eventos');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<a href="/admin/public/eventos.php" class="mb-4 inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
    <?= icono('atras', 'h-3.5 w-3.5') ?>
    Volver al listado
</a>

<div class="grid grid-cols-1 gap-6 <?= $esNuevo ? '' : 'lg:grid-cols-3' ?>">

    <div class="<?= $esNuevo ? '' : 'lg:col-span-1' ?>">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-base font-semibold"><?= $esNuevo ? 'Datos del evento' : 'Editar evento' ?></h2>
            <form action="/admin/includes/guardar-evento.php" method="post" class="grid grid-cols-1 gap-4">
                <?php if (!$esNuevo): ?>
                <input type="hidden" name="id" value="<?= (int) $evento['id'] ?>">
                <?php endif; ?>

                <div>
                    <label for="nombre" class="mb-1 block text-sm font-medium">Nombre</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('lista', 'h-4 w-4') ?></span>
                        <input type="text" id="nombre" name="nombre" required maxlength="150"
                               value="<?= htmlspecialchars((string) $evento['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label for="descripcion" class="mb-1 block text-sm font-medium">Descripción breve</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('descripcion', 'h-4 w-4') ?></span>
                        <input type="text" id="descripcion" name="descripcion" required maxlength="150"
                               value="<?= htmlspecialchars((string) $evento['descripcion'], ENT_QUOTES, 'UTF-8') ?>"
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
                                <option value="academico" <?= $evento['dia'] === 'academico' ? 'selected' : '' ?>>Académico</option>
                                <option value="cultural" <?= $evento['dia'] === 'cultural' ? 'selected' : '' ?>>Cultural</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="tipo" class="mb-1 block text-sm font-medium">Tipo</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                            <select id="tipo" name="tipo" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                                <option value="">Elige...</option>
                                <option value="ponencia" <?= $evento['tipo'] === 'ponencia' ? 'selected' : '' ?>>Ponencia</option>
                                <option value="taller" <?= $evento['tipo'] === 'taller' ? 'selected' : '' ?>>Taller</option>
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
                                   value="<?= htmlspecialchars(substr((string) $evento['hora_inicio'], 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label for="hora_fin" class="mb-1 block text-sm font-medium">Hora de fin</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('reloj', 'h-4 w-4') ?></span>
                            <input type="time" id="hora_fin" name="hora_fin" required
                                   value="<?= htmlspecialchars(substr((string) $evento['hora_fin'], 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="espacio" class="mb-1 block text-sm font-medium">Espacio</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('espacio', 'h-4 w-4') ?></span>
                        <input type="text" id="espacio" name="espacio" required maxlength="100"
                               value="<?= htmlspecialchars((string) $evento['espacio'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label for="facilitador" class="mb-1 block text-sm font-medium">Facilitador</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('persona', 'h-4 w-4') ?></span>
                        <input type="text" id="facilitador" name="facilitador" required maxlength="150"
                               value="<?= htmlspecialchars((string) $evento['facilitador'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label for="responsable" class="mb-1 block text-sm font-medium">Responsable (staff/maestro)</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('persona', 'h-4 w-4') ?></span>
                        <input type="text" id="responsable" name="responsable" required maxlength="150"
                               value="<?= htmlspecialchars((string) $evento['responsable'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label for="cupo_maximo" class="mb-1 block text-sm font-medium">Cupo máximo</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('numeral', 'h-4 w-4') ?></span>
                        <input type="number" id="cupo_maximo" name="cupo_maximo" required min="1"
                               value="<?= (int) $evento['cupo_maximo'] ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                    <?php if (!$esNuevo): ?>
                    <p class="mt-1 text-xs text-slate-500"><?= count($inscritos) ?> inscritos actualmente — el cupo disponible se recalcula solo.</p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white active:bg-slate-700">
                    <?= icono('verificado', 'h-4 w-4') ?>
                    <?= $esNuevo ? 'Crear evento' : 'Guardar cambios' ?>
                </button>
            </form>

            <?php if (!$esNuevo): ?>
            <form action="/admin/includes/eliminar-evento.php" method="post" class="mt-6 border-t border-slate-100 pt-4"
                  onsubmit="return confirm('¿Eliminar este evento de forma permanente?');">
                <input type="hidden" name="id" value="<?= (int) $evento['id'] ?>">
                <button type="submit" class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                    <?= icono('eliminar', 'h-4 w-4') ?>
                    Eliminar evento
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$esNuevo): ?>
    <div class="lg:col-span-2">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 flex items-center justify-between text-base font-semibold">
                <span>Inscritos (<?= count($inscritos) ?>/<?= (int) $evento['cupo_maximo'] ?>)</span>
            </h2>
            <?php if ($inscritos === []): ?>
            <p class="flex items-center gap-2 text-sm text-slate-500">
                <?= icono('usuarios', 'h-4 w-4 text-slate-300') ?>
                Todavía no hay nadie inscrito.
            </p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                            <th class="py-2 pr-3">Alumno</th>
                            <th class="py-2 pr-3 text-center">No. cuenta</th>
                            <th class="py-2 pr-3 text-center">Grado/Grupo</th>
                            <th class="py-2 pr-3 text-center">Origen</th>
                            <th class="py-2 pr-3 text-center">Entrada</th>
                            <th class="py-2 text-center">Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inscritos as $inscrito): ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($inscrito['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 pr-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($inscrito['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 pr-3 text-center text-slate-500"><?= htmlspecialchars($inscrito['grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars($inscrito['grupo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 pr-3 text-center text-slate-500"><?= $inscrito['origen'] === 'previo' ? 'Previo' : 'Orden de llegada' ?></td>
                            <td class="py-2 pr-3 text-center text-slate-500"><?= $inscrito['hora_entrada'] ? htmlspecialchars((string) $inscrito['hora_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            <td class="py-2 text-center text-slate-500"><?= $inscrito['hora_salida'] ? htmlspecialchars((string) $inscrito['hora_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php layoutAdminCerrar(); ?>
