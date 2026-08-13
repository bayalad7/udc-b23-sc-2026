<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

iniciarSesionInscripciones();

$idAlumno = alumnoIdentificadoId();
if ($idAlumno === null) {
    header('Location: /inscripciones/public/index.php?volver=academico');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consultaAlumno = $pdo->prepare('SELECT nombre_completo FROM alumnos WHERE id = :id');
$consultaAlumno->execute(['id' => $idAlumno]);
$alumno = $consultaAlumno->fetch();
if ($alumno === false) {
    // La sesión apunta a un alumno que ya no existe (caso raro) — se limpia y se vuelve a identificar.
    header('Location: /inscripciones/includes/salir.php');
    exit;
}

// --- Catálogo completo del día — visible siempre, sin filtrar nada -------
// (ver regla 1 del módulo: todo debe poder consultarse, esté o no disponible).

$eventos = $pdo->query(
    "SELECT id, tipo, hora_inicio, hora_fin, facilitador, nombre, descripcion, espacio, cupo_maximo, cupo_disponible
     FROM eventos WHERE dia = 'academico' ORDER BY hora_inicio, id"
)->fetchAll();

$competiciones = $pdo->query(
    "SELECT id, tipo, hora_inicio, hora_fin, nombre FROM competiciones WHERE dia = 'academico' ORDER BY hora_inicio"
)->fetchAll();

// --- Quién ya está inscrito en cada evento (modal "Ver inscritos") --------

$consultaListaInscritos = $pdo->query(
    "SELECT i.id_evento, a.numero_cuenta, a.nombre_completo, a.grado, a.grupo, i.fecha_registro
     FROM inscripciones i
     JOIN alumnos a ON a.id = i.id_alumno
     JOIN eventos e ON e.id = i.id_evento
     WHERE e.dia = 'academico'
     ORDER BY a.nombre_completo"
);
$inscritosPorEvento = [];
foreach ($consultaListaInscritos->fetchAll() as $fila) {
    $inscritosPorEvento[(int) $fila['id_evento']][] = $fila;
}

// --- Qué ya tiene el alumno este día (para marcar tarjetas) ---------------

$consultaInscripciones = $pdo->prepare(
    "SELECT i.id_evento FROM inscripciones i JOIN eventos e ON e.id = i.id_evento
     WHERE i.id_alumno = :alumno AND e.dia = 'academico'"
);
$consultaInscripciones->execute(['alumno' => $idAlumno]);
$idsEventosInscritos = array_map('intval', array_column($consultaInscripciones->fetchAll(), 'id_evento'));

$consultaEquipos = $pdo->prepare(
    "SELECT eq.id_competicion FROM integrantes it
     JOIN equipos eq ON eq.id = it.id_equipo
     JOIN competiciones c ON c.id = eq.id_competicion
     WHERE it.id_alumno = :alumno AND c.dia = 'academico'"
);
$consultaEquipos->execute(['alumno' => $idAlumno]);
$idsCompeticionesInscrito = array_map('intval', array_column($consultaEquipos->fetchAll(), 'id_competicion'));

function seTraslapan(string $inicioA, string $finA, string $inicioB, string $finB): bool
{
    return $inicioA < $finB && $finA > $inicioB;
}

// Horario de cada cosa a la que el alumno ya tiene lugar este día — para
// detectar, de forma genérica, si otra tarjeta se traslapa (ver
// "Reglas de inscripción por franja horaria" en registro-asistencia.md).
$rangosOcupados = [];
foreach ($eventos as $evento) {
    if (in_array((int) $evento['id'], $idsEventosInscritos, true)) {
        $rangosOcupados[] = [$evento['hora_inicio'], $evento['hora_fin']];
    }
}
foreach ($competiciones as $competicion) {
    if (in_array((int) $competicion['id'], $idsCompeticionesInscrito, true)) {
        $rangosOcupados[] = [$competicion['hora_inicio'], $competicion['hora_fin']];
    }
}

function bloqueadoPorOtraInscripcion(array $rangosOcupados, string $inicio, string $fin): bool
{
    foreach ($rangosOcupados as [$rInicio, $rFin]) {
        if (seTraslapan($inicio, $fin, $rInicio, $rFin)) {
            return true;
        }
    }
    return false;
}

function horaBonita(string $hora): string
{
    return substr($hora, 0, 5);
}

function claseTarjeta(bool $yaInscrito, bool $sinCupo, bool $bloqueado): string
{
    if ($yaInscrito) {
        return 'border-slate-900 bg-slate-900 text-white';
    }
    if ($sinCupo || $bloqueado) {
        return 'border-slate-200 bg-white/60 text-slate-400';
    }
    return 'border-slate-200 bg-white';
}

// Una sola tarjeta de evento (facilitador, descripción, cupo con barra visual
// e inscritos) — se reutiliza tanto para la fila destacada (Auditorio) como
// para la grilla del resto de talleres/ponencias, el contenido es idéntico.
function renderTarjetaEvento(array $evento, bool $yaInscrito, bool $bloqueado, array $inscritos): void
{
    $idEvento = (int) $evento['id'];
    $cupoMaximo = (int) $evento['cupo_maximo'];
    $cupoDisponible = (int) $evento['cupo_disponible'];
    $sinCupo = $cupoDisponible <= 0;
    $porcentaje = $cupoMaximo > 0 ? (int) round(100 * $cupoDisponible / $cupoMaximo) : 0;
    $colorBarra = $sinCupo ? 'bg-red-500' : ($porcentaje <= 25 ? 'bg-amber-500' : 'bg-emerald-500');
    $trackBarra = $yaInscrito ? 'bg-white/20' : 'bg-slate-200';
    $textoSecundario = $yaInscrito ? 'text-slate-300' : 'text-slate-500';
    ?>
    <div class="flex flex-col justify-between gap-3 rounded-xl border-2 p-4 <?= claseTarjeta($yaInscrito, $sinCupo, $bloqueado) ?>">
        <div>
            <span class="block font-semibold"><?= htmlspecialchars($evento['nombre'], ENT_QUOTES, 'UTF-8') ?></span>

            <?php if (trim((string) $evento['descripcion']) !== ''): ?>
            <span class="mt-1 block text-xs <?= $textoSecundario ?>"><?= htmlspecialchars($evento['descripcion'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>

            <span class="mt-2 flex items-center gap-1 text-xs <?= $textoSecundario ?>">
                <?= icono('facilitador', 'h-3.5 w-3.5 shrink-0') ?> <?= htmlspecialchars($evento['facilitador'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="mt-1 flex items-center gap-1 text-xs <?= $textoSecundario ?>">
                <?= icono('ubicacion', 'h-3.5 w-3.5 shrink-0') ?> <?= htmlspecialchars($evento['espacio'], ENT_QUOTES, 'UTF-8') ?>
            </span>

            <div class="mt-2">
                <div class="flex items-center justify-between text-xs <?= $textoSecundario ?>">
                    <span class="flex items-center gap-1"><?= icono('cupo', 'h-3.5 w-3.5 shrink-0') ?> Cupo</span>
                    <span><?= $cupoDisponible ?>/<?= $cupoMaximo ?> lugares</span>
                </div>
                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full <?= $trackBarra ?>">
                    <div class="h-full <?= $colorBarra ?>" style="width: <?= $porcentaje ?>%"></div>
                </div>
            </div>

            <?php if ($inscritos !== []): ?>
            <button type="button" data-abrir-modal="inscritos-<?= $idEvento ?>"
                    class="mt-2 flex items-center gap-1 rounded-lg border <?= $yaInscrito ? 'border-white/20' : 'border-slate-200' ?> px-3 py-2 text-xs font-medium cursor-pointer <?= $textoSecundario ?>">
                <?= icono('cupo', 'h-3.5 w-3.5 shrink-0') ?>
                Ver inscritos (<?= count($inscritos) ?>)
            </button>
            <?php else: ?>
            <span class="mt-2 block text-xs <?= $textoSecundario ?>">Sin inscritos aún</span>
            <?php endif; ?>
        </div>

        <?php if ($yaInscrito): ?>
        <span class="flex items-center justify-center gap-1.5 rounded-lg bg-white/20 px-3 py-2 text-xs font-semibold">
            <?= icono('verificado', 'h-4 w-4 shrink-0') ?> Tu inscripción
        </span>
        <?php elseif ($bloqueado): ?>
        <span class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-center text-xs font-medium text-slate-400">
            <?= icono('candado', 'h-3.5 w-3.5 shrink-0') ?> Ya elegiste otro evento en este horario
        </span>
        <?php elseif ($sinCupo): ?>
        <span class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-400">
            <?= icono('candado', 'h-3.5 w-3.5 shrink-0') ?> Sin cupo
        </span>
        <?php else: ?>
        <button type="button" data-abrir-modal="confirmar-<?= $idEvento ?>"
                class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700">
            <?= icono('inscribir', 'h-4 w-4 shrink-0') ?>
            Inscribirme
        </button>
        <?php endif; ?>
    </div>

    <?php if ($inscritos !== []): ?>
    <dialog id="inscritos-<?= $idEvento ?>" class="m-auto w-[90%] max-w-2xl rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <h3 class="text-base font-semibold"><?= htmlspecialchars($evento['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mb-3 text-xs text-slate-500">
                <?= count($inscritos) ?> alumno<?= count($inscritos) === 1 ? '' : 's' ?> inscrito<?= count($inscritos) === 1 ? '' : 's' ?>
            </p>
            <div class="max-h-80 overflow-auto rounded-lg border border-slate-200">
                <table class="w-full min-w-[520px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-500">
                            <th class="px-3 py-2 font-medium">#</th>
                            <th class="px-3 py-2 font-medium">No. cuenta</th>
                            <th class="px-3 py-2 font-medium">Nombre completo</th>
                            <th class="px-3 py-2 font-medium">Grado</th>
                            <th class="px-3 py-2 font-medium">Grupo</th>
                            <th class="px-3 py-2 font-medium">Fecha de inscripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inscritos as $indice => $inscrito): ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2"><?= $indice + 1 ?></td>
                            <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($inscrito['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($inscrito['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($inscrito['grado'], ENT_QUOTES, 'UTF-8') ?>°</td>
                            <td class="px-3 py-2"><?= htmlspecialchars($inscrito['grupo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime((string) $inscrito['fecha_registro'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="dialog" class="mt-4">
                <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Cerrar</button>
            </form>
        </div>
    </dialog>
    <?php endif; ?>

    <?php if (!$yaInscrito && !$bloqueado && !$sinCupo): ?>
    <dialog id="confirmar-<?= $idEvento ?>" class="m-auto w-[90%] max-w-sm rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <div class="mb-3 flex items-start gap-2">
                <?= icono('alerta', 'mt-0.5 h-5 w-5 shrink-0 text-amber-600') ?>
                <h3 class="text-base font-semibold">Confirmar inscripción</h3>
            </div>
            <p class="mb-4 text-sm text-slate-600">
                Vas a inscribirte a <strong><?= htmlspecialchars($evento['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>.
                Una vez guardada, esta inscripción para ese bloque de horario
                <strong>ya no se podrá deshacer ni eliminar</strong>. ¿Confirmas?
            </p>
            <div class="flex gap-2">
                <form method="dialog" class="flex-1">
                    <button type="submit" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">
                        Cancelar
                    </button>
                </form>
                <form action="/inscripciones/includes/inscribir.php" method="post" class="flex-1">
                    <input type="hidden" name="id_evento" value="<?= $idEvento ?>">
                    <button type="submit"
                            class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700">
                        <?= icono('inscribir', 'h-4 w-4 shrink-0') ?>
                        Confirmar
                    </button>
                </form>
            </div>
        </div>
    </dialog>
    <?php endif; ?>
    <?php
}

// --- Agrupar eventos y competiciones en bloques por horario compartido ---
// (no hay concepto de "bloque" en el esquema — se deriva agrupando por
// hora_inicio/hora_fin, así que un bloque nuevo en el catálogo aparece solo).

$bloques = [];
foreach ($eventos as $evento) {
    $clave = $evento['hora_inicio'] . '|' . $evento['hora_fin'];
    $bloques[$clave]['hora_inicio'] = $evento['hora_inicio'];
    $bloques[$clave]['hora_fin'] = $evento['hora_fin'];
    $bloques[$clave]['eventos'][] = $evento;
}
foreach ($competiciones as $competicion) {
    $clave = $competicion['hora_inicio'] . '|' . $competicion['hora_fin'];
    $bloques[$clave]['hora_inicio'] = $competicion['hora_inicio'];
    $bloques[$clave]['hora_fin'] = $competicion['hora_fin'];
    $bloques[$clave]['competiciones'][] = $competicion;
}
ksort($bloques);

$errores = [
    'cruce_horario' => 'Ya tienes un lugar en otro evento (o concurso) que se cruza con ese horario.',
    'sin_cupo' => 'Ese evento ya no tiene cupo disponible.',
    'ya_inscrito' => 'Ya estabas inscrito ahí.',
    'evento_invalido' => 'Ese evento ya no está disponible.',
    'error_servidor' => 'Ocurrió un error al guardar tu inscripción. Intenta de nuevo.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;
$mensajeExito = ($_GET['msg'] ?? '') === 'inscrito' ? '¡Inscripción guardada!' : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Día Académico — Inscripciones B23</title>
<link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-3xl flex-col px-4 py-8">

    <a href="/inscripciones/public/index.php" class="mb-4 flex items-center gap-1 text-sm font-medium text-slate-600">
        <?= icono('volver', 'h-4 w-4 shrink-0') ?>
        <b>Regresar</b>
    </a>

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="flex items-center gap-2 text-xl font-bold"><?= icono('academico', 'h-5 w-5 shrink-0') ?> Día Académico</h1>
        <p class="mt-1 text-sm text-slate-600">
            Elige como máximo <strong>un</strong> evento por bloque de horario.
            Hola, <?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
            (<a href="/inscripciones/includes/salir.php" class="underline">no soy yo</a>).
        </p>
    </div>

    <?php if ($mensajeExito): ?>
    <div class="mb-6 flex items-start gap-2 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
        <?= icono('verificado', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>
    <?php if ($mensajeError): ?>
    <div class="mb-6 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= icono('alerta', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <?php foreach (array_values($bloques) as $numeroBloque => $bloque): ?>
    <section class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
            <?= icono('reloj', 'h-4 w-4 shrink-0 text-slate-400') ?>
            Bloque <?= $numeroBloque + 1 ?>
            <span class="font-normal text-slate-500"><?= horaBonita($bloque['hora_inicio']) ?> – <?= horaBonita($bloque['hora_fin']) ?></span>
        </h2>

        <?php
        // "Destacado" = ocurre en el Auditorio Principal (la ponencia
        // magistral, el propio Concurso del Conocimiento) — se muestra en
        // una fila completa, igual que la tarjeta de competición, en vez de
        // compartir la grilla compacta con el resto de talleres/ponencias.
        $eventosDestacados = array_filter($bloque['eventos'] ?? [], static fn (array $e): bool => $e['espacio'] === 'Auditorio Principal');
        $eventosResto = array_filter($bloque['eventos'] ?? [], static fn (array $e): bool => $e['espacio'] !== 'Auditorio Principal');
        ?>

        <?php if (!empty($bloque['competiciones']) || $eventosDestacados !== []): ?>
        <div class="mb-3 grid grid-cols-1 gap-3">
            <?php foreach ($bloque['competiciones'] ?? [] as $competicion):
                $yaInscrito = in_array((int) $competicion['id'], $idsCompeticionesInscrito, true);
            ?>
            <div class="flex items-center justify-between gap-3 rounded-xl border-2 <?= $yaInscrito ? 'border-slate-900 bg-slate-900 text-white' : 'border-dashed border-slate-300 bg-white/60 text-slate-500' ?> p-4">
                <span class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg <?= $yaInscrito ? 'bg-white/20' : 'bg-slate-200' ?>"><?= icono('trofeo', 'h-5 w-5 shrink-0') ?></span>
                    <span>
                        <span class="block font-semibold">Competición · <?= htmlspecialchars($competicion['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="block text-xs <?= $yaInscrito ? 'text-slate-300' : 'text-slate-400' ?>">
                            <?= $yaInscrito ? 'Ya eres parte de un equipo' : 'Inscripción de equipos — próximamente' ?>
                        </span>
                    </span>
                </span>
                <?php if ($yaInscrito): ?>
                <?= icono('verificado', 'h-5 w-5 shrink-0') ?>
                <?php else: ?>
                <?= icono('candado', 'h-5 w-5 shrink-0 text-slate-300') ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php foreach ($eventosDestacados as $evento):
                $idEvento = (int) $evento['id'];
                $yaInscrito = in_array($idEvento, $idsEventosInscritos, true);
                $bloqueado = !$yaInscrito && bloqueadoPorOtraInscripcion($rangosOcupados, $evento['hora_inicio'], $evento['hora_fin']);
                renderTarjetaEvento($evento, $yaInscrito, $bloqueado, $inscritosPorEvento[$idEvento] ?? []);
            endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($eventosResto !== []): ?>
        <?php if (!empty($bloque['competiciones']) || $eventosDestacados !== []): ?>
        <h3 class="mb-2 text-sm font-medium text-slate-500">Talleres</h3>
        <?php endif; ?>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <?php foreach ($eventosResto as $evento):
                $idEvento = (int) $evento['id'];
                $yaInscrito = in_array($idEvento, $idsEventosInscritos, true);
                $bloqueado = !$yaInscrito && bloqueadoPorOtraInscripcion($rangosOcupados, $evento['hora_inicio'], $evento['hora_fin']);
                renderTarjetaEvento($evento, $yaInscrito, $bloqueado, $inscritosPorEvento[$idEvento] ?? []);
            endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

</div>
<script src="/assets/js/inscripciones.js"></script>
</body>
</html>
