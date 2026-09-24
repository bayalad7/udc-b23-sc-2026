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
require_once __DIR__ . '/../includes/sin-inscripcion.php';

// Consulta de un alumno: todo lo que el sistema sabe de él en una sola
// pantalla —credencial, datos generales, camisa, ponencias y talleres,
// equipos de competición, asistencia de los 3 días y qué le falta por
// inscribir—, para cuando alguien llega a la mesa del staff a preguntar
// "¿yo en qué quedé?".
//
// Es de SOLO LECTURA: las acciones que cambian datos siguen viviendo donde
// siempre (alumno.php para editar, regenerar-credencial.php para el QR), y
// desde aquí solo se enlaza a ellas. Así esta pantalla se puede usar con el
// alumno enfrente sin miedo a tocar nada por accidente.

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$busqueda = trim((string) ($_GET['cuenta'] ?? ''));

$alumno = null;
$coincidencias = [];

if ($busqueda !== '') {
    // Primero el número de cuenta exacto —es como busca el staff, con la
    // credencial en la mano— y solo si no aparece se cae a la búsqueda
    // parcial por cuenta o por nombre.
    $consultaExacta = $pdo->prepare('SELECT * FROM alumnos WHERE numero_cuenta = :cuenta');
    $consultaExacta->execute(['cuenta' => $busqueda]);
    $alumno = $consultaExacta->fetch() ?: null;

    if ($alumno === null) {
        // Placeholders separados porque PDO::ATTR_EMULATE_PREPARES está
        // desactivado (ver config/db.php y alumnos.php).
        $consultaParcial = $pdo->prepare(
            'SELECT id, numero_cuenta, nombre_completo, grado, grupo, correo_institucional, foto_path
             FROM alumnos
             WHERE numero_cuenta LIKE :buscar1 OR nombre_completo LIKE :buscar2
             ORDER BY nombre_completo LIMIT 25'
        );
        $consultaParcial->execute(['buscar1' => '%' . $busqueda . '%', 'buscar2' => '%' . $busqueda . '%']);
        $coincidencias = $consultaParcial->fetchAll();

        if (count($coincidencias) === 1) {
            $consultaExacta->execute(['cuenta' => $coincidencias[0]['numero_cuenta']]);
            $alumno = $consultaExacta->fetch() ?: null;
            $coincidencias = [];
        }
    }
}

$asistencias = [];
$eventos = [];
$equipos = [];
$bloques = [];
$participaBloque = [];

if ($alumno !== null) {
    $idAlumno = (int) $alumno['id'];

    $consulta = $pdo->prepare(
        'SELECT dia, hora_entrada, punto_control_entrada, escaneado_por_entrada,
                hora_salida, punto_control_salida, escaneado_por_salida
         FROM asistencias_generales WHERE id_alumno = :id'
    );
    $consulta->execute(['id' => $idAlumno]);
    foreach ($consulta->fetchAll() as $fila) {
        $asistencias[(string) $fila['dia']] = $fila;
    }

    $consulta = $pdo->prepare(
        'SELECT e.id, e.dia, e.tipo, e.nombre, e.espacio, e.facilitador, e.hora_inicio, e.hora_fin,
                i.origen, i.registrado_por, i.fecha_registro,
                i.hora_entrada, i.punto_control_entrada, i.hora_salida
         FROM inscripciones i
         JOIN eventos e ON e.id = i.id_evento
         WHERE i.id_alumno = :id
         ORDER BY FIELD(e.dia, \'academico\', \'cultural\', \'deportivo\'), e.hora_inicio'
    );
    $consulta->execute(['id' => $idAlumno]);
    $eventos = $consulta->fetchAll();

    // Todas las filas de `integrantes` con este alumno como ancla: la suya y,
    // si participan, las de su padre y su madre (ver la nota de
    // integrantes.id_alumno en schema.sql). Las de padre/madre se muestran
    // marcadas como tales — son parte de "su" información, pero el que juega
    // en esa fila no es él.
    $consulta = $pdo->prepare(
        'SELECT c.id AS id_competicion, c.nombre AS competicion, c.dia, c.tipo AS tipo_competicion,
                c.hora_inicio, c.hora_fin,
                eq.id AS id_equipo, eq.nombre AS equipo, eq.color_camisa, eq.id_alumno_capitan, eq.fecha_registro,
                it.tipo, it.nombre AS integrante, it.codigo_participante,
                it.hora_entrada, it.punto_control_entrada, it.hora_salida
         FROM integrantes it
         JOIN equipos eq ON eq.id = it.id_equipo
         JOIN competiciones c ON c.id = eq.id_competicion
         WHERE it.id_alumno = :id
         ORDER BY FIELD(c.dia, \'academico\', \'cultural\', \'deportivo\'), c.hora_inicio,
                  eq.nombre, FIELD(it.tipo, \'alumno\', \'padre\', \'madre\')'
    );
    $consulta->execute(['id' => $idAlumno]);
    $equipos = $consulta->fetchAll();

    // Qué le falta: los mismos bloques del reporte "Alumnos sin inscripción"
    // del dashboard (ver includes/sin-inscripcion.php), marcados con lo que
    // ya trae este alumno. Se cruza en PHP con lo que se acaba de consultar,
    // sin volver a la base.
    $bloques = bloquesDelEvento($pdo);
    foreach ($eventos as $evento) {
        $participaBloque[bloqueClave((string) $evento['dia'], (string) $evento['hora_inicio'], (string) $evento['hora_fin'])] = true;
    }
    foreach ($equipos as $equipo) {
        if ($equipo['tipo'] !== 'alumno') {
            continue;
        }
        $participaBloque[bloqueClave((string) $equipo['dia'], (string) $equipo['hora_inicio'], (string) $equipo['hora_fin'])] = true;
    }
}

/** Fecha y hora larga del proyecto, o un guion si la columna viene vacía. */
function consultaMomento(?string $momento): string
{
    if ($momento === null || $momento === '') {
        return '—';
    }

    return date('d/m/Y H:i', strtotime($momento));
}

layoutAdminAbrir('Consulta', 'consulta');
?>

<div class="mb-6">
    <h1 class="text-xl font-bold">Consulta de alumno</h1>
    <p class="mt-1 text-sm text-slate-500">
        Todo lo que el sistema sabe de un alumno en una sola pantalla. Es de solo lectura: los cambios
        siguen haciéndose desde su ficha.
    </p>
</div>

<form method="get" class="mb-6 rounded-xl bg-white p-5 shadow-sm">
    <label for="cuenta" class="mb-1 block text-sm font-medium">Número de cuenta</label>
    <div class="flex flex-wrap gap-2">
        <input type="text" id="cuenta" name="cuenta" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="B2300123" autofocus autocomplete="off"
               class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm focus:border-slate-900 focus:outline-none">
        <button type="submit" class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
            <?= icono('buscar', 'h-4 w-4') ?>
            Buscar
        </button>
    </div>
    <p class="mt-2 text-xs text-slate-400">También funciona con parte del nombre o de la cuenta.</p>
</form>

<?php if ($busqueda === ''): ?>
<div class="rounded-xl bg-white p-8 text-center shadow-sm">
    <span class="flex flex-col items-center gap-2 text-sm text-slate-500">
        <?= icono('buscar', 'h-8 w-8 text-slate-300') ?>
        Escribe un número de cuenta para ver la ficha completa del alumno.
    </span>
</div>
<?php elseif ($alumno === null && $coincidencias === []): ?>
<div class="rounded-xl bg-white p-8 text-center shadow-sm">
    <span class="flex flex-col items-center gap-2 text-sm text-slate-500">
        <?= icono('alerta', 'h-8 w-8 text-amber-300') ?>
        No hay ningún alumno que coincida con «<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>».
    </span>
</div>
<?php elseif ($coincidencias !== []): ?>
<div class="rounded-xl bg-white p-5 shadow-sm">
    <h2 class="mb-3 text-base font-semibold"><?= count($coincidencias) ?> coincidencias — elige una</h2>
    <div class="divide-y divide-slate-100">
        <?php foreach ($coincidencias as $coincidencia): ?>
        <a href="?cuenta=<?= urlencode((string) $coincidencia['numero_cuenta']) ?>"
           class="flex cursor-pointer items-center gap-3 py-2.5 hover:bg-slate-50">
            <?php fotoMiniatura($coincidencia['foto_path'], (string) $coincidencia['nombre_completo']); ?>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium"><?= htmlspecialchars($coincidencia['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="block truncate text-xs text-slate-500"><?= htmlspecialchars($coincidencia['correo_institucional'], ENT_QUOTES, 'UTF-8') ?></span>
            </span>
            <span class="shrink-0 text-center text-xs text-slate-500">
                <span class="block font-mono"><?= htmlspecialchars($coincidencia['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="block"><?= htmlspecialchars($coincidencia['grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars($coincidencia['grupo'], ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <?php // --- Identidad y credencial ------------------------------------ ?>
    <div class="lg:col-span-1 space-y-6">
        <div class="rounded-xl bg-white p-5 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-32 w-32 items-center justify-center">
                <?php fotoMiniatura($alumno['foto_path'], (string) $alumno['nombre_completo'], 'h-32 w-32', 'h-12 w-12'); ?>
            </div>
            <span class="block text-lg font-bold"><?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="mt-1 block font-mono text-sm text-slate-500"><?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="mt-1 block text-sm text-slate-500">
                <?= htmlspecialchars($alumno['grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars($alumno['grupo'], ENT_QUOTES, 'UTF-8') ?>
                · <?= htmlspecialchars($alumno['correo_institucional'], ENT_QUOTES, 'UTF-8') ?>
            </span>

            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <?php if ($alumno['credencial_generada']): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700"><?= icono('exito', 'h-3.5 w-3.5') ?> Credencial generada</span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700"><?= icono('alerta', 'h-3.5 w-3.5') ?> Credencial pendiente</span>
                <?php endif; ?>
                <?php if ((int) $alumno['es_jefe'] === 1): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700"
                      title="Lleva el control de camisas de su grado y grupo"><?= icono('camisa', 'h-3.5 w-3.5') ?> Jefe de grupo</span>
                <?php endif; ?>
            </div>

            <?php if ($alumno['credencial_generada'] && $alumno['credencial_path']): ?>
            <a href="<?= BASE_URL ?>/registro/public/<?= htmlspecialchars((string) $alumno['credencial_path'], ENT_QUOTES, 'UTF-8') ?>"
               download="credencial-<?= htmlspecialchars((string) $alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?>.png"
               class="mt-4 flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                <?= icono('descargar', 'h-4 w-4') ?>
                Descargar credencial
            </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/admin/public/alumno.php?id=<?= (int) $alumno['id'] ?>"
               class="mt-2 flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <?= icono('editar', 'h-4 w-4') ?>
                Editar ficha / regenerar credencial
            </a>

            <p class="mt-3 text-xs text-slate-400">Registrado el <?= consultaMomento((string) $alumno['fecha_registro']) ?></p>
        </div>

        <?php // --- Camisa --------------------------------------------------
              // El costo vive en `sistema` y el pago en el alumno; aquí solo se
              // leen (se capturan desde app/camisas y app/admin/public/camisas.php). ?>
        <?php
        $camisaCosto = (float) ($pdo->query('SELECT camisa_costo FROM sistema LIMIT 1')->fetchColumn() ?: 0);
        $camisaPago = $alumno['camisa_pago'] !== null ? (float) $alumno['camisa_pago'] : null;
        ?>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
                <?= icono('camisa', 'h-4 w-4 text-slate-400') ?>
                Camisa
            </h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">¿Encarga camisa?</dt>
                    <dd class="font-medium"><?= (int) $alumno['camisa_pedir'] === 1 ? 'Sí' : 'No' ?></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Corte y talla</dt>
                    <dd class="font-medium"><?= htmlspecialchars((string) $alumno['camisa_corte'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $alumno['camisa_talla'], ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Pagado</dt>
                    <dd class="font-medium <?= $camisaPago !== null && $camisaPago >= $camisaCosto ? 'text-emerald-600' : 'text-amber-600' ?>">
                        $<?= number_format($camisaPago ?? 0, 2) ?> de $<?= number_format($camisaCosto, 2) ?>
                    </dd>
                </div>
            </dl>
            <a href="<?= BASE_URL ?>/admin/public/camisas.php?buscar=<?= urlencode((string) $alumno['numero_cuenta']) ?>"
               class="mt-3 flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                <?= icono('dinero', 'h-3.5 w-3.5') ?>
                Ver su cobranza
            </a>
        </div>
    </div>

    <div class="lg:col-span-2 space-y-6">

        <?php // --- Qué le falta por inscribir ------------------------------ ?>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-1 flex items-center gap-2 text-base font-semibold">
                <?= icono('lista', 'h-4 w-4 text-slate-400') ?>
                Inscripción por bloque
            </h2>
            <p class="mb-3 text-xs text-slate-500">
                Un bloque es una franja horaria con actividad. El Escenario de Talentos no cuenta: no reparte
                cupo y admite varias participaciones.
            </p>
            <div class="space-y-2">
                <?php foreach ($bloques as $bloque): $inscrito = isset($participaBloque[$bloque['clave']]); ?>
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border <?= $inscrito ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' ?> px-3 py-2">
                    <span class="min-w-0">
                        <span class="flex items-center gap-1.5 text-sm font-medium">
                            <?= icono($bloque['dia'], 'h-3.5 w-3.5 text-slate-400') ?>
                            <?= htmlspecialchars($bloque['dia_label'], ENT_QUOTES, 'UTF-8') ?> ·
                            <?= htmlspecialchars($bloque['etiqueta'], ENT_QUOTES, 'UTF-8') ?>
                            <span class="font-normal text-slate-500"><?= htmlspecialchars($bloque['horario'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <span class="mt-0.5 block text-xs text-slate-500"><?= htmlspecialchars($bloque['nombres'], ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                    <?php if ($inscrito): ?>
                    <span class="shrink-0 text-xs font-medium text-emerald-700">✔ Inscrito</span>
                    <?php else: ?>
                    <span class="flex shrink-0 items-center gap-2">
                        <span class="text-xs font-medium text-amber-700">✘ Sin inscripción</span>
                        <a href="<?= BASE_URL ?>/admin/public/<?= $bloque['dia'] === 'deportivo' ? 'competiciones.php' : 'eventos.php' ?>"
                           class="cursor-pointer rounded-lg border border-amber-300 bg-white px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-100">
                            Ver qué hay
                        </a>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php // --- Ponencias y talleres ------------------------------------ ?>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
                <?= icono('academico', 'h-4 w-4 text-slate-400') ?>
                Ponencias y talleres
                <span class="text-sm font-normal text-slate-400">· <?= count($eventos) ?></span>
            </h2>
            <?php if ($eventos === []): ?>
            <p class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-4 text-sm text-slate-500">
                <?= icono('lista', 'h-4 w-4 text-slate-300') ?>
                No está inscrito en ninguna ponencia ni taller.
            </p>
            <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2">Evento</th>
                            <th class="px-3 py-2">Horario</th>
                            <th class="px-3 py-2">Espacio</th>
                            <th class="px-3 py-2 text-center">Origen</th>
                            <th class="px-3 py-2 text-center">Entrada</th>
                            <th class="px-3 py-2 text-center">Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventos as $evento): ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2">
                                <a href="<?= BASE_URL ?>/admin/public/evento.php?id=<?= (int) $evento['id'] ?>"
                                   class="font-medium hover:underline"><?= htmlspecialchars($evento['nombre'], ENT_QUOTES, 'UTF-8') ?></a>
                                <span class="block text-xs text-slate-400">
                                    <?= htmlspecialchars(diaEventoLabel((string) $evento['dia']), ENT_QUOTES, 'UTF-8') ?> ·
                                    <span class="capitalize"><?= htmlspecialchars($evento['tipo'], ENT_QUOTES, 'UTF-8') ?></span> ·
                                    <?= htmlspecialchars($evento['facilitador'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-500"><?= htmlspecialchars(bloqueHorario((string) $evento['hora_inicio'], (string) $evento['hora_fin']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars($evento['espacio'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center text-xs text-slate-500"><?= $evento['origen'] === 'previo' ? 'Previo' : 'Orden de llegada' ?></td>
                            <td class="px-3 py-2 text-center text-xs text-slate-500"><?= consultaMomento($evento['hora_entrada']) ?></td>
                            <td class="px-3 py-2 text-center text-xs text-slate-500"><?= consultaMomento($evento['hora_salida']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php // --- Competiciones y equipos --------------------------------- ?>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
                <?= icono('trofeo', 'h-4 w-4 text-slate-400') ?>
                Competiciones y equipos
                <span class="text-sm font-normal text-slate-400">· <?= count($equipos) ?></span>
            </h2>
            <?php if ($equipos === []): ?>
            <p class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-4 text-sm text-slate-500">
                <?= icono('trofeo', 'h-4 w-4 text-slate-300') ?>
                No forma parte de ningún equipo.
            </p>
            <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2">Competición</th>
                            <th class="px-3 py-2">Equipo</th>
                            <th class="px-3 py-2 text-center">Participa</th>
                            <th class="px-3 py-2 text-center">Código</th>
                            <th class="px-3 py-2 text-center">Entrada</th>
                            <th class="px-3 py-2 text-center">Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipos as $equipo):
                            $esCapitan = $equipo['tipo'] === 'alumno' && (int) $equipo['id_alumno_capitan'] === (int) $alumno['id'];
                        ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2">
                                <a href="<?= BASE_URL ?>/admin/public/competicion.php?id=<?= (int) $equipo['id_competicion'] ?>"
                                   class="font-medium hover:underline"><?= htmlspecialchars($equipo['competicion'], ENT_QUOTES, 'UTF-8') ?></a>
                                <span class="block text-xs text-slate-400">
                                    <?= htmlspecialchars(diaEventoLabel((string) $equipo['dia']), ENT_QUOTES, 'UTF-8') ?> ·
                                    <?= htmlspecialchars(bloqueHorario((string) $equipo['hora_inicio'], (string) $equipo['hora_fin']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <?= htmlspecialchars($equipo['equipo'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($equipo['color_camisa']): ?>
                                <span class="block text-xs text-slate-400">Color: <?= htmlspecialchars($equipo['color_camisa'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-center text-xs">
                                <?php if ($equipo['tipo'] === 'alumno'): ?>
                                <span class="inline-flex items-center gap-1 rounded-full <?= $esCapitan ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600' ?> px-2 py-0.5 font-medium">
                                    <?= $esCapitan ? 'Capitán' : 'Integrante' ?>
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600 capitalize" title="Participa un familiar, no el alumno">
                                    <?= htmlspecialchars($equipo['tipo'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($equipo['integrante'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($equipo['codigo_participante'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center text-xs text-slate-500"><?= consultaMomento($equipo['hora_entrada']) ?></td>
                            <td class="px-3 py-2 text-center text-xs text-slate-500"><?= consultaMomento($equipo['hora_salida']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php // --- Asistencia general de los 3 días ------------------------ ?>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
                <?= icono('qr', 'h-4 w-4 text-slate-400') ?>
                Asistencia al plantel
            </h2>
            <div class="space-y-2">
                <?php foreach (DIAS_EVENTO_LABEL as $diaClave => $diaLabel): $asistencia = $asistencias[$diaClave] ?? null; ?>
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2">
                    <span class="flex items-center gap-1.5 text-sm font-medium">
                        <?= icono($diaClave, 'h-3.5 w-3.5 text-slate-400') ?>
                        <?= htmlspecialchars($diaLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php if ($asistencia === null): ?>
                    <span class="text-xs text-slate-400">Sin registro de entrada</span>
                    <?php else: ?>
                    <span class="text-xs text-slate-500">
                        Entró <strong class="text-slate-700"><?= consultaMomento($asistencia['hora_entrada']) ?></strong>
                        (<?= htmlspecialchars((string) $asistencia['punto_control_entrada'], ENT_QUOTES, 'UTF-8') ?>,
                        <?= htmlspecialchars((string) $asistencia['escaneado_por_entrada'], ENT_QUOTES, 'UTF-8') ?>)
                        · Salida <strong class="text-slate-700"><?= consultaMomento($asistencia['hora_salida']) ?></strong>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<?php endif; ?>

<?php layoutAdminCerrar(); ?>
