<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

iniciarSesionInscripciones();

$idAlumno = alumnoIdentificadoId();
if ($idAlumno === null) {
    header('Location: /inscripciones/public/index.php?volver=cultural');
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
}

$errores = [
    'nombre_acto_invalido' => 'Ingresa un nombre para tu acto o presentación.',
    'demasiados_integrantes' => 'Un acto admite como máximo 9 acompañantes (10 personas en total).',
    'numero_cuenta_invalido' => 'Alguno de los números de cuenta capturados no tiene el formato correcto (8 caracteres).',
    'integrante_duplicado' => 'Capturaste dos veces el mismo número de cuenta entre los acompañantes (o es el tuyo).',
    'integrante_no_encontrado' => 'Alguno de los números de cuenta capturados no corresponde a ningún alumno pre-registrado.',
    'error_servidor' => 'Ocurrió un error al guardar tu inscripción. Intenta de nuevo.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;
$mensajeExito = ($_GET['msg'] ?? '') === 'acto_creado' ? '¡Inscripción al show guardada!' : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Día Cultural — Inscripciones B23</title>
<link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-2xl flex-col px-4 py-8">

    <a href="/inscripciones/public/index.php" class="mb-4 flex items-center gap-1 text-sm font-medium text-slate-600">
        <?= icono('volver', 'h-4 w-4 shrink-0') ?>
        <b>Regresar</b>
    </a>

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="flex items-center gap-2 text-xl font-bold"><?= icono('cultural', 'h-5 w-5 shrink-0') ?> Día Cultural</h1>
        <p class="mt-1 text-sm text-slate-600">
            Escenario de Talentos "Expresa tu esencia".
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

    <?php if ($competicion === false): ?>
    <p class="text-sm text-slate-500">La inscripción al show todavía no está disponible.</p>
    <?php else: ?>

    <section class="mb-6 rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-1 flex items-center gap-2 text-base font-semibold">
            <?= icono('trofeo', 'h-4 w-4 shrink-0 text-slate-400') ?>
            <?= htmlspecialchars($competicion['nombre'], ENT_QUOTES, 'UTF-8') ?>
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
                    class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700">
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
                <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Cerrar</button>
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
            <form action="/inscripciones/includes/crear-acto-cultural.php" method="post" data-equipo-form novalidate>
                <div class="mb-3">
                    <label for="nombre_acto" class="mb-1 block text-xs font-medium">Nombre del acto o presentación</label>
                    <input type="text" id="nombre_acto" name="nombre_acto" required maxlength="150"
                           placeholder="Ej. Canto — &quot;Color esperanza&quot;"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
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
                                    class="shrink-0 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white active:bg-slate-700">
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
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">
                        Cancelar
                    </button>
                    <button type="submit" data-equipo-submit
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40">
                        <?= icono('inscribir', 'h-4 w-4 shrink-0') ?>
                        Inscribir
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <?php endif; ?>

</div>
<script src="/assets/js/inscripciones.js"></script>
</body>
</html>
