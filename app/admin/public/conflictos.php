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
require_once __DIR__ . '/../includes/conflictos.php';

// Quién quedó inscrito a dos cosas a la vez. Pensado para después de mover un
// evento de día o de horario: la validación de cruce corre al inscribir, no
// sobre lo ya guardado (el porqué y la regla completa están en
// includes/conflictos.php).

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$reporte = conflictosDeInscripcion($pdo);
$porActividad = conflictosPorActividad($reporte);

// Resultado de quitar a un alumno de un evento (ver
// includes/eliminar-inscripcion.php).
$detalleMensaje = trim((string) ($_GET['detalle'] ?? ''));
$mensajeExito = ($_GET['msg'] ?? '') === 'inscripcion_eliminada'
    ? 'Listo: se quitó la inscripción' . ($detalleMensaje !== '' ? ' de ' . $detalleMensaje : '') . ' y el lugar volvió al evento.'
    : null;
$mensajesError = [
    'con_asistencia' => 'No se quitó: a ese alumno ya le escanearon la entrada a ese evento, y borrar la inscripción se llevaría su asistencia.',
    'no_encontrada' => 'Esa inscripción ya no existe — alguien más pudo haberla quitado.',
    'datos_invalidos' => 'Faltaron datos para quitar la inscripción.',
    'error_servidor' => 'No se pudo quitar la inscripción. Vuelve a intentarlo.',
];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;
if ($mensajeError !== null && $detalleMensaje !== '') {
    $mensajeError .= ' (' . $detalleMensaje . ')';
}

/**
 * Pinta un compromiso (evento o equipo) con su enlace a la ficha y, si es un
 * evento y se pasa el alumno, el botón para quitarlo de ahí — que es como se
 * deshace el choque: se le quita UNA de las dos inscripciones.
 *
 * De los equipos no hay botón a propósito: quitar a un integrante deja al
 * equipo incompleto y eso lo decide el capitán, no el staff (ver
 * includes/eliminar-inscripcion.php).
 */
function conflictoCompromiso(array $compromiso, ?array $alumno = null): void
{
    $esEvento = $compromiso['clase'] === 'evento';
    $url = BASE_URL . ($esEvento ? '/admin/public/evento.php?id=' : '/admin/public/competicion.php?id=') . $compromiso['id'];
    ?>
    <span class="block">
        <a href="<?= $url ?>" class="font-medium hover:underline"><?= htmlspecialchars($compromiso['nombre'], ENT_QUOTES, 'UTF-8') ?></a>
        <span class="block text-xs text-slate-400">
            <span class="capitalize"><?= htmlspecialchars($compromiso['tipo'], ENT_QUOTES, 'UTF-8') ?></span>
            · <?= htmlspecialchars($compromiso['horario'], ENT_QUOTES, 'UTF-8') ?>
            · <?= htmlspecialchars($compromiso['detalle'], ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php if ($esEvento && $alumno !== null): ?>
        <form action="<?= BASE_URL ?>/admin/includes/eliminar-inscripcion.php" method="post" class="mt-1.5"
              onsubmit="return confirm('¿Quitar a <?= htmlspecialchars(addslashes((string) $alumno['nombre_completo']), ENT_QUOTES, 'UTF-8') ?> de «<?= htmlspecialchars(addslashes((string) $compromiso['nombre']), ENT_QUOTES, 'UTF-8') ?>»? El lugar vuelve al evento y el alumno queda libre a esa hora.');">
            <input type="hidden" name="id_alumno" value="<?= (int) $alumno['id'] ?>">
            <input type="hidden" name="id_evento" value="<?= (int) $compromiso['id'] ?>">
            <button type="submit" title="Quitar al alumno de este evento"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                <?= icono('eliminar', 'h-3 w-3') ?>
                Quitar de aquí
            </button>
        </form>
        <?php endif; ?>
    </span>
    <?php
}

layoutAdminAbrir('Conflictos', 'conflictos');
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold">Conflictos de inscripción</h1>
        <p class="mt-1 max-w-3xl text-sm text-slate-500">
            Alumnos comprometidos a dos cosas a la misma hora. La app valida el cruce cuando el alumno se
            inscribe, pero si después se mueve un evento de día u horario, lo ya guardado no se revisa solo:
            este reporte lee el catálogo <strong>como está ahora</strong> y dice a quién le quedó encimado.
        </p>
    </div>
    <?php if ($reporte['alumnos'] !== []): ?>
    <div class="flex shrink-0 items-center gap-2">
        <a href="<?= BASE_URL ?>/admin/includes/exportar-conflictos.php?formato=xlsx"
           class="flex cursor-pointer items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
            <?= icono('descargar', 'h-3.5 w-3.5') ?> Excel
        </a>
        <a href="<?= BASE_URL ?>/admin/includes/exportar-conflictos.php?formato=pdf"
           class="flex cursor-pointer items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
            <?= icono('descargar', 'h-3.5 w-3.5') ?> PDF
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($mensajeExito !== null): ?>
<div class="mb-4 flex items-center gap-2 rounded-lg border-l-4 border-emerald-500 bg-emerald-50 p-3 text-sm font-medium text-emerald-800">
    <?= icono('exito', 'h-4 w-4 shrink-0') ?>
    <?= htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
<?php if ($mensajeError !== null): ?>
<div class="mb-4 flex items-center gap-2 rounded-lg border-l-4 border-red-500 bg-red-50 p-3 text-sm font-medium text-red-800">
    <?= icono('alerta', 'h-4 w-4 shrink-0') ?>
    <?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if ($reporte['alumnos'] === []): ?>
<div class="rounded-xl border-l-4 border-emerald-500 bg-white p-5 shadow-sm">
    <span class="flex items-center gap-2 text-sm font-medium text-emerald-700">
        <?= icono('exito', 'h-5 w-5') ?>
        Ningún alumno del padrón (<?= number_format($reporte['padron']) ?>) tiene dos inscripciones encimadas.
    </span>
    <p class="mt-2 text-xs text-slate-500">
        Si acabas de cambiar un evento de día, revisa que el cambio ya esté guardado en
        <a href="<?= BASE_URL ?>/admin/public/eventos.php" class="underline">Eventos</a>: el reporte compara
        contra el catálogo actual, así que un cambio pendiente todavía no aparece como problema.
    </p>
</div>
<?php else: ?>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="flex items-center gap-3 rounded-lg border-l-4 border-red-500 bg-red-50 p-3 shadow-sm">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
            <?= icono('alerta', 'h-5 w-5') ?>
        </span>
        <span>
            <span class="block text-xs font-medium text-red-700">Alumnos con conflicto</span>
            <span class="block text-xl font-bold text-red-900"><?= number_format(count($reporte['alumnos'])) ?></span>
            <span class="block text-[11px] text-red-600">de <?= number_format($reporte['padron']) ?> del padrón</span>
        </span>
    </div>
    <div class="flex items-center gap-3 rounded-lg border-l-4 border-amber-500 bg-amber-50 p-3 shadow-sm">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
            <?= icono('reloj', 'h-5 w-5') ?>
        </span>
        <span>
            <span class="block text-xs font-medium text-amber-700">Choques detectados</span>
            <span class="block text-xl font-bold text-amber-900"><?= number_format($reporte['total_conflictos']) ?></span>
            <span class="block text-[11px] text-amber-600">un alumno puede tener más de uno</span>
        </span>
    </div>
    <div class="flex items-center gap-3 rounded-lg border-l-4 border-slate-400 bg-white p-3 shadow-sm">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
            <?= icono('lista', 'h-5 w-5') ?>
        </span>
        <span>
            <span class="block text-xs font-medium text-slate-600">Parejas de actividades</span>
            <span class="block text-xl font-bold text-slate-900"><?= number_format(count($porActividad)) ?></span>
            <span class="block text-[11px] text-slate-500">que se estorban entre sí</span>
        </span>
    </div>
</div>

<?php // --- Por pareja de actividades: a cuánta gente afecta cada choque --- ?>
<div class="mb-6 rounded-xl bg-white p-5 shadow-sm">
    <h2 class="mb-1 flex items-center gap-2 text-base font-semibold">
        <?= icono('grafica', 'h-4 w-4 text-slate-400') ?>
        Qué se estorba con qué
    </h2>
    <p class="mb-3 text-xs text-slate-500">
        De más a menos alumnos afectados. Sirve para decidir: mover una de las dos actividades arregla de
        golpe a todos los de ese renglón. Para quitar a un alumno de uno de los dos eventos, su botón está
        en la tabla de abajo.
    </p>
    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <th class="px-3 py-2">Día</th>
                    <th class="px-3 py-2">Actividad</th>
                    <th class="px-3 py-2">Se estorba con</th>
                    <th class="px-3 py-2">Motivo</th>
                    <th class="px-3 py-2 text-center">Alumnos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($porActividad as $par): ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-3 py-2 whitespace-nowrap text-xs text-slate-500">
                        <?= htmlspecialchars($par['dia_label'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="block"><?= htmlspecialchars($par['horario'], ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td class="px-3 py-2"><?php conflictoCompromiso($par['a']); ?></td>
                    <td class="px-3 py-2"><?php conflictoCompromiso($par['b']); ?></td>
                    <td class="px-3 py-2 text-xs text-slate-500"><?= htmlspecialchars($par['etiqueta'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-3 py-2 text-center font-medium <?= $par['alumnos'] >= 10 ? 'text-red-600' : 'text-amber-600' ?>"><?= $par['alumnos'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php // --- Alumno por alumno: a quién hay que avisarle --------------------- ?>
<div class="rounded-xl bg-white p-5 shadow-sm">
    <h2 class="mb-1 flex items-center gap-2 text-base font-semibold">
        <?= icono('usuarios', 'h-4 w-4 text-slate-400') ?>
        Alumno por alumno
    </h2>
    <p class="mb-3 text-xs text-slate-500">
        A quién hay que avisarle y qué tiene encimado. El nombre abre su ficha de consulta, con todo lo que
        trae inscrito.
    </p>
    <div class="max-h-[32rem] overflow-auto rounded-lg border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 bg-slate-50">
                <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <th class="px-3 py-2">Alumno</th>
                    <th class="px-3 py-2">Día</th>
                    <th class="px-3 py-2">Tiene esto…</th>
                    <th class="px-3 py-2">…y también esto</th>
                    <th class="px-3 py-2">Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reporte['alumnos'] as $alumno): ?>
                <?php foreach ($alumno['conflictos'] as $indice => $conflicto): ?>
                <tr class="border-b border-slate-100 <?= $indice === 0 ? '' : 'bg-slate-50' ?>">
                    <?php if ($indice === 0): ?>
                    <td class="px-3 py-2" rowspan="<?= count($alumno['conflictos']) ?>">
                        <a href="<?= BASE_URL ?>/admin/public/consulta.php?cuenta=<?= urlencode((string) $alumno['numero_cuenta']) ?>"
                           class="font-medium hover:underline"><?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></a>
                        <span class="block text-xs text-slate-400">
                            <span class="font-mono"><?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></span>
                            · <?= htmlspecialchars($alumno['grado_grupo'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="block text-xs text-slate-400"><?= htmlspecialchars($alumno['correo_institucional'], ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <?php endif; ?>
                    <td class="px-3 py-2 whitespace-nowrap text-xs text-slate-500">
                        <?= htmlspecialchars($conflicto['dia_label'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="block"><?= htmlspecialchars($conflicto['horario'], ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td class="px-3 py-2"><?php conflictoCompromiso($conflicto['a'], $alumno); ?></td>
                    <td class="px-3 py-2"><?php conflictoCompromiso($conflicto['b'], $alumno); ?></td>
                    <td class="px-3 py-2 text-xs text-slate-500">
                        <?= htmlspecialchars($conflicto['etiqueta'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($conflicto['a']['clase'] !== 'evento' && $conflicto['b']['clase'] !== 'evento'): ?>
                        <span class="mt-1 block text-slate-400">Los equipos no se desarman desde aquí.</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php layoutAdminCerrar(); ?>
