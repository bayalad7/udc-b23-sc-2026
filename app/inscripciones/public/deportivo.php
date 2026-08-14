<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
require __DIR__ . '/../includes/colores-camisa.php';

iniciarSesionInscripciones();

$idAlumno = alumnoIdentificadoId();
if ($idAlumno === null) {
    header('Location: /inscripciones/public/index.php?volver=deportivo');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consultaAlumno = $pdo->prepare('SELECT nombre_completo, numero_cuenta FROM alumnos WHERE id = :id');
$consultaAlumno->execute(['id' => $idAlumno]);
$alumno = $consultaAlumno->fetch();
if ($alumno === false) {
    header('Location: /inscripciones/includes/salir.php');
    exit;
}

$torneos = $pdo->query(
    "SELECT id, nombre, hora_inicio, hora_fin FROM competiciones WHERE dia = 'deportivo' ORDER BY id"
)->fetchAll();

// --- Para cada torneo: equipos ya registrados (transparencia), colores ya
// tomados, y si el alumno identificado ya es integrante de alguno. ---------

$consultaEquiposTorneo = $pdo->prepare(
    "SELECT eq.id, eq.nombre, eq.color_camisa, eq.fecha_registro, a.nombre_completo AS capitan,
            (SELECT COUNT(*) FROM integrantes it WHERE it.id_equipo = eq.id) AS total_integrantes
     FROM equipos eq
     JOIN alumnos a ON a.id = eq.id_alumno_capitan
     WHERE eq.id_competicion = :id
     ORDER BY eq.fecha_registro"
);
$consultaYaInscrito = $pdo->prepare(
    "SELECT 1 FROM integrantes it
     JOIN equipos eq ON eq.id = it.id_equipo
     WHERE eq.id_competicion = :id AND it.id_alumno = :alumno AND it.tipo = 'alumno'
     LIMIT 1"
);

$consultaIntegrantesEquipo = $pdo->prepare(
    "SELECT it.id_equipo, it.tipo, it.nombre, a.numero_cuenta, a.grado, a.grupo
     FROM integrantes it
     JOIN alumnos a ON a.id = it.id_alumno
     WHERE it.id_equipo = :id
     ORDER BY it.tipo = 'alumno' DESC, it.nombre"
);

$equiposPorTorneo = [];
$coloresDisponiblesPorTorneo = [];
$yaInscritoPorTorneo = [];
$integrantesPorEquipo = [];
foreach ($torneos as $torneo) {
    $idTorneo = (int) $torneo['id'];

    $consultaEquiposTorneo->execute(['id' => $idTorneo]);
    $equiposPorTorneo[$idTorneo] = $consultaEquiposTorneo->fetchAll();

    $coloresTomados = array_column($equiposPorTorneo[$idTorneo], 'color_camisa');
    $coloresDisponiblesPorTorneo[$idTorneo] = array_values(array_diff(COLORES_CAMISA, $coloresTomados));

    $consultaYaInscrito->execute(['id' => $idTorneo, 'alumno' => $idAlumno]);
    $yaInscritoPorTorneo[$idTorneo] = $consultaYaInscrito->fetch() !== false;

    // Integrantes de cada equipo de este torneo — para el modal "Ver
    // equipos" (punto 6 del rediseño del formulario).
    foreach ($equiposPorTorneo[$idTorneo] as $equipo) {
        $consultaIntegrantesEquipo->execute(['id' => $equipo['id']]);
        $integrantesPorEquipo[(int) $equipo['id']] = $consultaIntegrantesEquipo->fetchAll();
    }
}

$errores = [
    'torneo_invalido' => 'Ese torneo ya no está disponible.',
    'nombre_equipo_invalido' => 'Ingresa un nombre de equipo válido.',
    'color_invalido' => 'Elige un color de camisa del catálogo disponible.',
    'color_tomado' => 'Ese color de camisa ya lo tomó otro equipo de este torneo.',
    'integrantes_incompletos' => 'Debes capturar los 9 integrantes restantes (10 en total, contigo), cada uno con su número de cuenta.',
    'numero_cuenta_invalido' => 'Alguno de los números de cuenta capturados no tiene el formato correcto (8 caracteres).',
    'nombre_integrante_invalido' => 'Falta el nombre completo de algún padre/madre de familia.',
    'integrante_duplicado' => 'Capturaste dos veces el mismo número de cuenta entre los integrantes.',
    'integrante_no_encontrado' => 'Alguno de los números de cuenta capturados no corresponde a ningún alumno pre-registrado.',
    'integrante_ya_en_equipo' => 'Ya formas parte de otro equipo de ese mismo torneo.',
    'error_servidor' => 'Ocurrió un error al guardar tu inscripción. Intenta de nuevo.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;
$mensajeExito = ($_GET['msg'] ?? '') === 'equipo_creado' ? '¡Equipo registrado!' : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Día Deportivo — Inscripciones B23</title>
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
        <h1 class="flex items-center gap-2 text-xl font-bold"><?= icono('trofeo', 'h-5 w-5 shrink-0') ?> Día Deportivo</h1>
        <p class="mt-1 text-sm text-slate-600">
            Torneos deportivos — Polideportivo de San Pedrito.
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

    <p class="mb-6 rounded-lg bg-blue-50 px-4 py-3 text-xs text-blue-800">
        Puedes inscribirte a más de un torneo (en equipos distintos), pero solo a <strong>un</strong> equipo por
        torneo. Si te toca partido de dos torneos a la misma hora, tú decides en cuál participar.
    </p>

    <?php foreach ($torneos as $torneo):
        $idTorneo = (int) $torneo['id'];
        $equipos = $equiposPorTorneo[$idTorneo];
        $yaInscrito = $yaInscritoPorTorneo[$idTorneo];
        $coloresDisponibles = $coloresDisponiblesPorTorneo[$idTorneo];
        $sinColorDisponible = $coloresDisponibles === [];
    ?>
    <section class="mb-6 rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-1 flex items-center gap-2 text-base font-semibold">
            <?= icono('trofeo', 'h-4 w-4 shrink-0 text-slate-400') ?>
            <?= htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p class="mb-4 text-xs text-slate-500">
            <?= substr($torneo['hora_inicio'], 0, 5) ?> – <?= substr($torneo['hora_fin'], 0, 5) ?> ·
            Equipos de 10 (alumnos y padres/madres de familia).
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($equipos !== []): ?>
            <button type="button" data-abrir-modal="equipos-torneo-<?= $idTorneo ?>"
                    class="flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium cursor-pointer text-slate-600">
                <?= icono('cupo', 'h-3.5 w-3.5 shrink-0') ?>
                Ver equipos (<?= count($equipos) ?>)
            </button>
            <?php endif; ?>

            <?php if ($yaInscrito): ?>
            <span class="flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">
                <?= icono('verificado', 'h-4 w-4 shrink-0') ?> Ya eres parte de un equipo
            </span>
            <?php elseif ($sinColorDisponible): ?>
            <span class="flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-400">
                <?= icono('candado', 'h-3.5 w-3.5 shrink-0') ?> Sin colores de camisa disponibles
            </span>
            <?php else: ?>
            <button type="button" data-abrir-modal="formar-equipo-torneo-<?= $idTorneo ?>"
                    class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700 cursor-pointer">
                <?= icono('inscribir', 'h-4 w-4 shrink-0') ?>
                Formar equipo
            </button>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($equipos !== []): ?>
    <dialog id="equipos-torneo-<?= $idTorneo ?>" class="m-auto w-[90%] max-w-2xl rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <h3 class="text-base font-semibold"><?= htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mb-3 text-xs text-slate-500">
                <?= count($equipos) ?> equipo<?= count($equipos) === 1 ? '' : 's' ?> registrado<?= count($equipos) === 1 ? '' : 's' ?>
            </p>
            <div class="flex max-h-96 flex-col gap-3 overflow-auto">
                <?php foreach ($equipos as $indice => $equipo): ?>
                <div class="rounded-lg border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-t-lg bg-slate-50 px-3 py-2">
                        <span class="text-xs font-semibold">
                            #<?= $indice + 1 ?> · <?= htmlspecialchars($equipo['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            <span class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">
                                <?= htmlspecialchars((string) $equipo['color_camisa'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </span>
                        <span class="text-[11px] text-slate-500">
                            Capitán: <?= htmlspecialchars($equipo['capitan'], ENT_QUOTES, 'UTF-8') ?>
                            · <?= (int) $equipo['total_integrantes'] ?>/10
                            · <?= date('d/m/Y H:i', strtotime((string) $equipo['fecha_registro'])) ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-1 p-3 text-xs sm:grid-cols-3">
                        <?php foreach ($integrantesPorEquipo[(int) $equipo['id']] ?? [] as $integrante): ?>
                        <span class="truncate text-slate-600">
                            <?= htmlspecialchars($integrante['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($integrante['tipo'] === 'alumno'): ?>
                            <span class="text-slate-400"><?= htmlspecialchars($integrante['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                            <span class="rounded-full bg-slate-900 px-1 py-0.5 text-[9px] font-semibold text-white"><?= $integrante['tipo'] === 'padre' ? 'Padre' : 'Madre' ?></span>
                            <?php endif; ?>
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

    <?php if (!$yaInscrito && !$sinColorDisponible): ?>
    <dialog id="formar-equipo-torneo-<?= $idTorneo ?>" class="m-auto w-[92%] max-w-xl rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="max-h-[85vh] overflow-y-auto p-5">
            <h3 class="mb-1 text-base font-semibold">Formar equipo — <?= htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mb-4 text-xs text-slate-500">
                Un equipo son <strong>exactamente 10 integrantes</strong> (mezclando alumnos y padres/madres de
                familia), capitán incluido — tú quedas como capitán. Cada integrante, sea alumno o padre/madre,
                se captura con el <strong>número de cuenta del alumno de su familia</strong> (para alumnos, es el
                suyo propio).
            </p>
            <form action="/inscripciones/includes/crear-equipo-deportivo.php" method="post" data-equipo-form novalidate>
                <input type="hidden" name="id_competicion" value="<?= $idTorneo ?>">

                <div class="mb-3">
                    <label class="mb-1 block text-xs font-medium">Nombre del equipo</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('nombre', 'h-4 w-4') ?></span>
                        <input type="text" name="nombre_equipo" required maxlength="150"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="mb-1 block text-xs font-medium">Color de camisa</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('camisa', 'h-4 w-4') ?></span>
                        <select name="color_camisa" required
                                class="w-full appearance-none rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                            <option value="">Elige un color…</option>
                            <?php foreach ($coloresDisponibles as $color): ?>
                            <option value="<?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3 flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">
                    <?= icono('verificado', 'h-3.5 w-3.5 shrink-0') ?>
                    Capitán: <strong><?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></strong> (tú, integrante #1)
                </div>

                <div data-equipo-builder
                     data-contexto="deportivo"
                     data-id-competicion="<?= $idTorneo ?>"
                     data-max-integrantes="9"
                     data-requiere-exactos="true"
                     data-capitan-cuenta="<?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Buscar integrante por número de cuenta</label>
                        <p class="mb-2 text-[11px] text-slate-500">
                            Busca siempre por el número de cuenta del <strong>alumno de la familia</strong> — si va a
                            participar su papá o mamá, lo eliges después de encontrarlo.
                        </p>
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
                            <label class="block text-xs font-medium text-slate-700">Integrantes agregados</label>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600" data-contador-integrantes>0/9</span>
                        </div>
                        <div data-grid-integrantes class="grid grid-cols-3 gap-2"></div>
                        <p class="mt-2 text-xs text-red-600" data-error-integrantes hidden></p>
                    </div>
                    <div data-campos-ocultos></div>
                </div>

                <p class="mb-4 text-xs text-slate-500">
                    Una vez guardado, este equipo <strong>ya no se podrá deshacer ni modificar</strong> desde aquí.
                </p>

                <div class="flex gap-2">
                    <button type="button" data-cerrar-modal="formar-equipo-torneo-<?= $idTorneo ?>"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 cursor-pointer">
                        <?= icono('quitar', 'h-3.5 w-3.5 shrink-0') ?>
                        Cancelar
                    </button>
                    <button type="submit" data-equipo-submit
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer">
                        <?= icono('inscribir', 'h-4 w-4 shrink-0') ?>
                        Registrar equipo
                    </button>
                </div>
            </form>
        </div>
    </dialog>
    <?php endif; ?>

    <?php endforeach; ?>

</div>
<script src="/assets/js/inscripciones.js"></script>
</body>
</html>
