<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

iniciarSesionInscripciones();

$idAlumno = alumnoIdentificadoId();
if ($idAlumno === null) {
    header('Location: ' . BASE_URL . '/inscripciones/public/index.php?volver=cultural');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consultaAlumno = $pdo->prepare('SELECT nombre_completo, numero_cuenta FROM alumnos WHERE id = :id');
$consultaAlumno->execute(['id' => $idAlumno]);
$alumno = $consultaAlumno->fetch();
if ($alumno === false) {
    header('Location: ' . BASE_URL . '/inscripciones/includes/salir.php');
    exit;
}

// --- Bloque 1 — Talleres (14:00–16:00): catálogo completo, visible siempre
// (mismo criterio que el catálogo del Día Académico), con cupo y exclusividad
// — un alumno solo puede estar inscrito a UN taller de este bloque. -------

$talleres = $pdo->query(
    "SELECT id, hora_inicio, hora_fin, facilitador, nombre, descripcion, espacio, cupo_maximo, cupo_disponible
     FROM eventos WHERE dia = 'cultural' AND tipo = 'taller' ORDER BY hora_inicio, id"
)->fetchAll();

$consultaListaInscritosTaller = $pdo->query(
    "SELECT i.id_evento, a.numero_cuenta, a.nombre_completo, a.grado, a.grupo, i.fecha_registro
     FROM inscripciones i
     JOIN alumnos a ON a.id = i.id_alumno
     JOIN eventos e ON e.id = i.id_evento
     WHERE e.dia = 'cultural' AND e.tipo = 'taller'
     ORDER BY a.nombre_completo"
);
$inscritosPorTaller = [];
foreach ($consultaListaInscritosTaller->fetchAll() as $fila) {
    $inscritosPorTaller[(int) $fila['id_evento']][] = $fila;
}

$consultaMisTalleres = $pdo->prepare(
    "SELECT i.id_evento FROM inscripciones i JOIN eventos e ON e.id = i.id_evento
     WHERE i.id_alumno = :alumno AND e.dia = 'cultural' AND e.tipo = 'taller'"
);
$consultaMisTalleres->execute(['alumno' => $idAlumno]);
$idsTalleresInscrito = array_map('intval', array_column($consultaMisTalleres->fetchAll(), 'id_evento'));

function seTraslapan(string $inicioA, string $finA, string $inicioB, string $finB): bool
{
    return $inicioA < $finB && $finA > $inicioB;
}

function bloqueadoPorOtroTaller(array $rangosOcupados, string $inicio, string $fin): bool
{
    foreach ($rangosOcupados as [$rInicio, $rFin]) {
        if (seTraslapan($inicio, $fin, $rInicio, $rFin)) {
            return true;
        }
    }
    return false;
}

// Horario de lo que el alumno ya tiene este día (otro taller o el show, si
// ya participa) — para bloquear de forma genérica cualquier tarjeta que se
// traslape, igual que en academico.php.
$rangosOcupadosCultural = [];
foreach ($talleres as $taller) {
    if (in_array((int) $taller['id'], $idsTalleresInscrito, true)) {
        $rangosOcupadosCultural[] = [$taller['hora_inicio'], $taller['hora_fin']];
    }
}

$competicion = $pdo->query(
    "SELECT id, nombre, hora_inicio, hora_fin FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso' LIMIT 1"
)->fetch();

// --- Todos los actos ya inscritos (modal "Ver participaciones") -----------
// (ver regla 1 del módulo: todo debe poder consultarse, esté o no
// disponible — mismo criterio que el catálogo de eventos del Día Académico.)

$actos = [];
if ($competicion !== false) {
    $consultaActos = $pdo->prepare(
        "SELECT eq.id, eq.nombre, eq.fecha_registro, a.nombre_completo AS capitan,
                (SELECT COUNT(*) FROM integrantes it WHERE it.id_equipo = eq.id) AS total_integrantes
         FROM equipos eq
         JOIN alumnos a ON a.id = eq.id_alumno_capitan
         WHERE eq.id_competicion = :id
         ORDER BY eq.fecha_registro"
    );
    $consultaActos->execute(['id' => $competicion['id']]);
    $actos = $consultaActos->fetchAll();
}

// Integrantes de cada acto, agrupados por equipo — para listarlos completos
// en el modal "Ver participaciones" (punto 6 del rediseño del formulario).
$integrantesPorActo = [];
if ($actos !== []) {
    $consultaIntegrantesActos = $pdo->prepare(
        "SELECT it.id_equipo, a.numero_cuenta, a.nombre_completo, a.grado, a.grupo
         FROM integrantes it
         JOIN alumnos a ON a.id = it.id_alumno
         WHERE it.id_equipo IN (" . implode(',', array_fill(0, count($actos), '?')) . ")
         ORDER BY a.nombre_completo"
    );
    $consultaIntegrantesActos->execute(array_column($actos, 'id'));
    foreach ($consultaIntegrantesActos->fetchAll() as $fila) {
        $integrantesPorActo[(int) $fila['id_equipo']][] = $fila;
    }
}

// --- Los propios actos del alumno (puede tener más de uno — a diferencia
// del resto de las competiciones, aquí no hay exclusividad) ----------------

$misActos = [];
if ($competicion !== false) {
    $consultaMisActos = $pdo->prepare(
        "SELECT eq.id, eq.nombre, eq.fecha_registro, eq.id_alumno_capitan,
                (SELECT COUNT(*) FROM integrantes it2 WHERE it2.id_equipo = eq.id) AS total_integrantes
         FROM integrantes it
         JOIN equipos eq ON eq.id = it.id_equipo
         WHERE it.id_alumno = :alumno AND eq.id_competicion = :competicion
         ORDER BY eq.fecha_registro"
    );
    $consultaMisActos->execute(['alumno' => $idAlumno, 'competicion' => $competicion['id']]);
    $misActos = $consultaMisActos->fetchAll();

    // El show no se traslapa en horario con los talleres (16:00 vs. 14:00–16:00),
    // pero se agrega igual al cálculo genérico de rangos ocupados por
    // consistencia con academico.php, por si algún día cambian los horarios.
    if ($misActos !== []) {
        $rangosOcupadosCultural[] = [$competicion['hora_inicio'], $competicion['hora_fin']];
    }
}

$errores = [
    'nombre_acto_invalido' => 'Ingresa un nombre para tu acto o presentación.',
    'demasiados_integrantes' => 'Un acto admite como máximo 9 acompañantes (10 personas en total).',
    'numero_cuenta_invalido' => 'Alguno de los números de cuenta capturados no tiene el formato correcto (8 caracteres).',
    'integrante_duplicado' => 'Capturaste dos veces el mismo número de cuenta entre los acompañantes (o es el tuyo).',
    'integrante_no_encontrado' => 'Alguno de los números de cuenta capturados no corresponde a ningún alumno pre-registrado.',
    'cruce_horario' => 'Ya tienes un taller inscrito en este horario (14:00–16:00).',
    'sin_cupo' => 'Ese taller ya no tiene cupo disponible.',
    'ya_inscrito' => 'Ya estabas inscrito ahí.',
    'evento_invalido' => 'Ese taller ya no está disponible.',
    'error_servidor' => 'Ocurrió un error al guardar tu inscripción. Intenta de nuevo.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;
$mensajeExito = match ($_GET['msg'] ?? '') {
    'acto_creado' => '¡Inscripción al show guardada!',
    'inscrito' => '¡Inscripción al taller guardada!',
    default => null,
};

function horaBonita(string $hora): string
{
    return substr($hora, 0, 5);
}

// Tarjeta de un taller del Bloque 1 — mismo patrón visual que las tarjetas de
// evento del Día Académico (academico.php): facilitador, espacio, cupo con
// barra, "Ver inscritos" y el botón/diálogo de confirmación.
function renderTarjetaTaller(array $taller, bool $yaInscrito, bool $bloqueado, array $inscritos): void
{
    $idTaller = (int) $taller['id'];
    $cupoMaximo = (int) $taller['cupo_maximo'];
    $cupoDisponible = (int) $taller['cupo_disponible'];
    $sinCupo = $cupoDisponible <= 0;
    $porcentaje = $cupoMaximo > 0 ? (int) round(100 * $cupoDisponible / $cupoMaximo) : 0;
    $colorBarra = $sinCupo ? 'bg-red-500' : ($porcentaje <= 25 ? 'bg-amber-500' : 'bg-emerald-500');
    $trackBarra = $yaInscrito ? 'bg-white/20' : 'bg-slate-200';
    $textoSecundario = $yaInscrito ? 'text-slate-300' : 'text-slate-500';
    $claseTarjeta = $yaInscrito
        ? 'border-slate-900 bg-slate-900 text-white'
        : (($sinCupo || $bloqueado) ? 'border-slate-200 bg-white/60 text-slate-400' : 'border-slate-200 bg-white');
    ?>
    <div class="flex flex-col justify-between gap-3 rounded-xl border-2 p-4 <?= $claseTarjeta ?>">
        <div>
            <span class="block font-semibold"><?= htmlspecialchars($taller['nombre'], ENT_QUOTES, 'UTF-8') ?></span>

            <?php if (trim((string) $taller['descripcion']) !== ''): ?>
            <span class="mt-1 block text-xs <?= $textoSecundario ?>"><?= htmlspecialchars($taller['descripcion'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>

            <span class="mt-2 flex items-center gap-1 text-xs <?= $textoSecundario ?>">
                <?= icono('facilitador', 'h-3.5 w-3.5 shrink-0') ?> <?= htmlspecialchars($taller['facilitador'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="mt-1 flex items-center gap-1 text-xs <?= $textoSecundario ?>">
                <?= icono('ubicacion', 'h-3.5 w-3.5 shrink-0') ?> <?= htmlspecialchars($taller['espacio'], ENT_QUOTES, 'UTF-8') ?>
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
            <button type="button" data-abrir-modal="inscritos-taller-<?= $idTaller ?>"
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
            <?= icono('candado', 'h-3.5 w-3.5 shrink-0') ?> Ya elegiste otro taller de este bloque
        </span>
        <?php elseif ($sinCupo): ?>
        <span class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-400">
            <?= icono('candado', 'h-3.5 w-3.5 shrink-0') ?> Sin cupo
        </span>
        <?php else: ?>
        <button type="button" data-abrir-modal="confirmar-taller-<?= $idTaller ?>"
                class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700 cursor-pointer">
            <?= icono('inscribir', 'h-4 w-4 shrink-0') ?>
            Inscribirme
        </button>
        <?php endif; ?>
    </div>

    <?php if ($inscritos !== []): ?>
    <dialog id="inscritos-taller-<?= $idTaller ?>" class="m-auto w-[90%] max-w-2xl rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <h3 class="text-base font-semibold"><?= htmlspecialchars($taller['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
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
                <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white cursor-pointer">
                    <?= icono('quitar', 'h-3.5 w-3.5 shrink-0') ?>
                    Cerrar
                </button>
            </form>
        </div>
    </dialog>
    <?php endif; ?>

    <?php if (!$yaInscrito && !$bloqueado && !$sinCupo): ?>
    <dialog id="confirmar-taller-<?= $idTaller ?>" class="m-auto w-[90%] max-w-sm rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <div class="mb-3 flex items-start gap-2">
                <?= icono('alerta', 'mt-0.5 h-5 w-5 shrink-0 text-amber-600') ?>
                <h3 class="text-base font-semibold">Confirmar inscripción</h3>
            </div>
            <p class="mb-4 text-sm text-slate-600">
                Vas a inscribirte a <strong><?= htmlspecialchars($taller['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>.
                Solo puedes estar en <strong>un</strong> taller de este bloque, y una vez guardada esta inscripción
                <strong>ya no se podrá deshacer ni eliminar</strong>. ¿Confirmas?
            </p>
            <div class="flex gap-2">
                <form method="dialog" class="flex-1">
                    <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 cursor-pointer">
                        <?= icono('quitar', 'h-3.5 w-3.5 shrink-0') ?>
                        Cancelar
                    </button>
                </form>
                <form action="<?= BASE_URL ?>/inscripciones/includes/inscribir.php" method="post" class="flex-1">
                    <input type="hidden" name="id_evento" value="<?= $idTaller ?>">
                    <input type="hidden" name="dia" value="cultural">
                    <button type="submit"
                            class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700 cursor-pointer">
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Día Cultural — Inscripciones B23</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-3xl flex-col px-4 py-8">

    <a href="<?= BASE_URL ?>/inscripciones/public/index.php" class="mb-4 flex items-center gap-1 text-sm font-medium text-slate-600">
        <?= icono('volver', 'h-4 w-4 shrink-0') ?>
        <b>Regresar</b>
    </a>

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="<?= BASE_URL ?>/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="flex items-center gap-2 text-xl font-bold"><?= icono('cultural', 'h-5 w-5 shrink-0') ?> Día Cultural</h1>
        <p class="mt-1 text-sm text-slate-600">
            Talleres (Bloque 1) y Escenario de Talentos "Expresa tu esencia" (Bloque 2).
            Hola, <?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
            (<a href="<?= BASE_URL ?>/inscripciones/includes/salir.php" class="underline">no soy yo</a>).
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

    <?php if ($talleres === []): ?>
    <p class="mb-8 text-sm text-slate-500">El catálogo de talleres todavía no está disponible.</p>
    <?php else: ?>
    <section class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
            <?= icono('reloj', 'h-4 w-4 shrink-0 text-slate-400') ?>
            Bloque 1 · Talleres
            <span class="font-normal text-slate-500"><?= horaBonita($talleres[0]['hora_inicio']) ?> – <?= horaBonita($talleres[0]['hora_fin']) ?></span>
        </h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <?php foreach ($talleres as $taller):
                $idTaller = (int) $taller['id'];
                $yaInscrito = in_array($idTaller, $idsTalleresInscrito, true);
                $bloqueado = !$yaInscrito && bloqueadoPorOtroTaller($rangosOcupadosCultural, $taller['hora_inicio'], $taller['hora_fin']);
                renderTarjetaTaller($taller, $yaInscrito, $bloqueado, $inscritosPorTaller[$idTaller] ?? []);
            endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($competicion === false): ?>
    <p class="text-sm text-slate-500">La inscripción al show todavía no está disponible.</p>
    <?php else: ?>

    <section class="mb-6 rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-1 flex items-center gap-2 text-base font-semibold">
            <?= icono('trofeo', 'h-4 w-4 shrink-0 text-slate-400') ?>
            Bloque 2 · <?= htmlspecialchars($competicion['nombre'], ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p class="mb-4 text-xs text-slate-500">
            <?= substr($competicion['hora_inicio'], 0, 5) ?> – <?= substr($competicion['hora_fin'], 0, 5) ?> · Explanada.
            Puedes participar solo o en equipo, y puedes inscribirte a más de un acto.
        </p>
        <div class="flex flex-wrap gap-2">
            <?php if ($actos !== []): ?>
            <button type="button" data-abrir-modal="participaciones-cultural"
                    class="flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium cursor-pointer text-slate-600">
                <?= icono('cupo', 'h-3.5 w-3.5 shrink-0') ?>
                Ver participaciones (<?= count($actos) ?>)
            </button>
            <?php endif; ?>
            <button type="button" data-abrir-modal="formar-acto-cultural"
                    class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700 cursor-pointer">
                <?= icono('inscribir', 'h-4 w-4 shrink-0') ?>
                Inscribir un acto
            </button>
        </div>
    </section>

    <?php if ($misActos !== []): ?>
    <section class="mb-6">
        <h2 class="mb-3 text-sm font-medium text-slate-500">Tus participaciones</h2>
        <div class="flex flex-col gap-2">
            <?php foreach ($misActos as $acto): ?>
            <div class="flex items-center justify-between gap-3 rounded-xl border-2 border-slate-900 bg-slate-900 p-4 text-white">
                <span>
                    <span class="block font-semibold"><?= htmlspecialchars($acto['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="block text-xs text-slate-300">
                        <?= (int) $acto['total_integrantes'] ?> integrante<?= ((int) $acto['total_integrantes']) === 1 ? '' : 's' ?>
                        <?= ((int) $acto['id_alumno_capitan']) === $idAlumno ? '· Tú eres el capitán' : '' ?>
                    </span>
                </span>
                <?= icono('verificado', 'h-5 w-5 shrink-0') ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($actos !== []): ?>
    <dialog id="participaciones-cultural" class="m-auto w-[90%] max-w-2xl rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <h3 class="text-base font-semibold">Participaciones del show</h3>
            <p class="mb-3 text-xs text-slate-500">
                <?= count($actos) ?> acto<?= count($actos) === 1 ? '' : 's' ?> inscrito<?= count($actos) === 1 ? '' : 's' ?>
            </p>
            <div class="flex max-h-96 flex-col gap-3 overflow-auto">
                <?php foreach ($actos as $indice => $acto): ?>
                <div class="rounded-lg border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-t-lg bg-slate-50 px-3 py-2">
                        <span class="text-xs font-semibold">
                            #<?= $indice + 1 ?> · <?= htmlspecialchars($acto['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="text-[11px] text-slate-500">
                            Capitán: <?= htmlspecialchars($acto['capitan'], ENT_QUOTES, 'UTF-8') ?>
                            · <?= (int) $acto['total_integrantes'] ?> integrante<?= ((int) $acto['total_integrantes']) === 1 ? '' : 's' ?>
                            · <?= date('d/m/Y H:i', strtotime((string) $acto['fecha_registro'])) ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-1 p-3 text-xs sm:grid-cols-3">
                        <?php foreach ($integrantesPorActo[(int) $acto['id']] ?? [] as $integrante): ?>
                        <span class="truncate text-slate-600">
                            <?= htmlspecialchars($integrante['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                            <span class="text-slate-400"><?= htmlspecialchars($integrante['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <form method="dialog" class="mt-4">
                <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white cursor-pointer">
                    <?= icono('quitar', 'h-3.5 w-3.5 shrink-0') ?>
                    Cerrar
                </button>
            </form>
        </div>
    </dialog>
    <?php endif; ?>

    <dialog id="formar-acto-cultural" class="m-auto w-[90%] max-w-lg rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="max-h-[85vh] overflow-y-auto p-5">
            <h3 class="mb-1 text-base font-semibold">Inscribir un acto</h3>
            <p class="mb-4 text-xs text-slate-500">
                Participa solo o agrega acompañantes por número de cuenta (hasta 9 más). Quedas registrado como
                capitán del acto.
            </p>
            <form action="<?= BASE_URL ?>/inscripciones/includes/crear-acto-cultural.php" method="post" data-equipo-form novalidate>
                <div class="mb-3">
                    <label for="nombre_acto" class="mb-1 block text-xs font-medium">Nombre del acto o presentación</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('nombre', 'h-4 w-4') ?></span>
                        <input type="text" id="nombre_acto" name="nombre_acto" required maxlength="150"
                               placeholder="Ej. Canto — &quot;Color esperanza&quot;"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div class="mb-3 flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">
                    <?= icono('verificado', 'h-3.5 w-3.5 shrink-0') ?>
                    Capitán: <strong><?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></strong> (tú)
                </div>

                <div data-equipo-builder
                     data-contexto="talentos"
                     data-max-integrantes="9"
                     data-requiere-exactos="false"
                     data-capitan-cuenta="<?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <label class="mb-1.5 block text-xs font-medium text-slate-700">Buscar acompañante por número de cuenta (opcional)</label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('credencial', 'h-4 w-4') ?></span>
                                <input type="text" data-buscar-cuenta maxlength="8" minlength="8" pattern="[A-Za-z0-9]{8}"
                                       placeholder="XXXXXXXX" autocapitalize="characters"
                                       class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-center text-sm uppercase focus:border-slate-500 focus:outline-none">
                            </div>
                            <button type="button" data-buscar-boton
                                    class="flex shrink-0 items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white active:bg-slate-700 cursor-pointer">
                                <?= icono('buscar', 'h-3.5 w-3.5 shrink-0') ?>
                                Buscar
                            </button>
                        </div>
                        <div data-resultado-busqueda hidden class="mt-3"></div>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-xs font-medium text-slate-700">Acompañantes agregados</label>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600" data-contador-integrantes>0/9</span>
                        </div>
                        <div data-grid-integrantes class="grid grid-cols-3 gap-2"></div>
                        <p class="mt-2 text-xs text-red-600" data-error-integrantes hidden></p>
                    </div>
                    <div data-campos-ocultos></div>
                </div>

                <p class="mb-4 text-xs text-slate-500">
                    Una vez guardado, este acto <strong>ya no se podrá deshacer ni modificar</strong> desde aquí.
                </p>

                <div class="flex gap-2">
                    <button type="button" data-cerrar-modal="formar-acto-cultural"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 cursor-pointer">
                        <?= icono('quitar', 'h-3.5 w-3.5 shrink-0') ?>
                        Cancelar
                    </button>
                    <button type="submit" data-equipo-submit
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer">
                        <?= icono('inscribir', 'h-4 w-4 shrink-0') ?>
                        Inscribir
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <?php endif; ?>

</div>
<script src="<?= BASE_URL ?>/assets/js/inscripciones.js"></script>
</body>
</html>
