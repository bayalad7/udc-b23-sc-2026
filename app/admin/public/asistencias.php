<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: /admin/public/index.php');
    exit;
}
require __DIR__ . '/../includes/layout.php';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

/** Agrega marcadores únicos (:prefijoN) para un IN (...) — evita repetir nombres de parámetro con ATTR_EMULATE_PREPARES desactivado. */
function marcadoresIn(array $valores, string $prefijo, array &$parametros): string
{
    $marcadores = [];
    foreach (array_values($valores) as $indice => $valor) {
        $clave = $prefijo . $indice;
        $marcadores[] = ':' . $clave;
        $parametros[$clave] = $valor;
    }
    return implode(', ', $marcadores);
}

/** Condición SQL del filtro de estado sobre un par de columnas hora_entrada/hora_salida. */
function condicionEstado(string $estado, string $colEntrada, string $colSalida): string
{
    return match ($estado) {
        'sin_entrada' => "$colEntrada IS NULL",
        'entrada_sin_salida' => "$colEntrada IS NOT NULL AND $colSalida IS NULL",
        'completos' => "$colEntrada IS NOT NULL AND $colSalida IS NOT NULL",
        default => '',
    };
}

/** Insignia visual del estado de asistencia (no llegó / sigue dentro / completo). */
function badgeEstadoAsistencia(?string $entrada, ?string $salida): string
{
    if ($entrada === null) {
        return '<span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">' . icono('alerta', 'h-3 w-3') . ' No llegó</span>';
    }
    if ($salida === null) {
        return '<span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">' . icono('reloj', 'h-3 w-3') . ' Sigue dentro</span>';
    }
    return '<span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">' . icono('exito', 'h-3 w-3') . ' Completo</span>';
}

/** Tiempo transcurrido entre hora_entrada y hora_salida, o null si falta alguna de las dos. */
function duracionAsistencia(?string $entrada, ?string $salida): ?string
{
    if ($entrada === null || $salida === null) {
        return null;
    }
    $segundos = max(0, (new DateTimeImmutable($salida))->getTimestamp() - (new DateTimeImmutable($entrada))->getTimestamp());
    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    return $horas > 0 ? sprintf('%dh %dmin', $horas, $minutos) : sprintf('%dmin', $minutos);
}

$diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural', 'deportivo' => 'Día Deportivo'];
$dia = trim((string) ($_GET['dia'] ?? 'academico'));
if (!isset($diasLabel[$dia])) {
    $dia = 'academico';
}

// --- Filtros (comunes a las 3 tablas) ---------------------------------------
$estadosValidos = ['sin_entrada', 'entrada_sin_salida', 'completos'];
$estado = trim((string) ($_GET['estado'] ?? ''));
if (!in_array($estado, $estadosValidos, true)) {
    $estado = '';
}
$cuenta = trim((string) ($_GET['cuenta'] ?? ''));
$grado = trim((string) ($_GET['grado'] ?? ''));
if (!in_array($grado, ['1', '3', '5'], true)) {
    $grado = '';
}
$grupo = trim((string) ($_GET['grupo'] ?? ''));
if (!in_array($grupo, ['A', 'B', 'C'], true)) {
    $grupo = '';
}
$eventosSeleccionados = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['evento'] ?? [])))));
$competicionesSeleccionadas = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['competicion'] ?? [])))));
$equiposSeleccionados = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['equipo'] ?? [])))));

$hayFiltrosActivos = $estado !== '' || $cuenta !== '' || $grado !== '' || $grupo !== ''
    || $eventosSeleccionados !== [] || $competicionesSeleccionadas !== [] || $equiposSeleccionados !== [];

// --- Catálogos para los selects de filtro (solo del día activo) ------------
$eventosDia = [];
if ($dia !== 'deportivo') {
    $consultaEventosDia = $pdo->prepare('SELECT id, nombre FROM eventos WHERE dia = :dia ORDER BY hora_inicio, nombre');
    $consultaEventosDia->execute(['dia' => $dia]);
    $eventosDia = $consultaEventosDia->fetchAll();
}
$consultaCompeticionesDia = $pdo->prepare('SELECT id, nombre FROM competiciones WHERE dia = :dia ORDER BY hora_inicio, nombre');
$consultaCompeticionesDia->execute(['dia' => $dia]);
$competicionesDia = $consultaCompeticionesDia->fetchAll();

$consultaEquiposDia = $pdo->prepare(
    'SELECT eq.id, eq.nombre, c.nombre AS competicion
     FROM equipos eq JOIN competiciones c ON c.id = eq.id_competicion
     WHERE c.dia = :dia ORDER BY c.nombre, eq.nombre'
);
$consultaEquiposDia->execute(['dia' => $dia]);
$equiposDia = $consultaEquiposDia->fetchAll();

// --- 1. Asistencia general del día (incluye a quien NUNCA escaneó entrada) --
$condicionesGeneral = [];
$parametrosGeneral = ['dia' => $dia];
if ($cuenta !== '') {
    $condicionesGeneral[] = 'a.numero_cuenta LIKE :cuenta';
    $parametrosGeneral['cuenta'] = '%' . $cuenta . '%';
}
if ($grado !== '') {
    $condicionesGeneral[] = 'a.grado = :grado';
    $parametrosGeneral['grado'] = $grado;
}
if ($grupo !== '') {
    $condicionesGeneral[] = 'a.grupo = :grupo';
    $parametrosGeneral['grupo'] = $grupo;
}
if ($eventosSeleccionados !== []) {
    $marcadores = marcadoresIn($eventosSeleccionados, 'ev_gen_', $parametrosGeneral);
    $condicionesGeneral[] = "EXISTS (SELECT 1 FROM inscripciones ig WHERE ig.id_alumno = a.id AND ig.id_evento IN ($marcadores))";
}
if ($competicionesSeleccionadas !== []) {
    $marcadores = marcadoresIn($competicionesSeleccionadas, 'comp_gen_', $parametrosGeneral);
    $condicionesGeneral[] = "EXISTS (SELECT 1 FROM integrantes itg JOIN equipos eqg ON eqg.id = itg.id_equipo WHERE itg.id_alumno = a.id AND itg.tipo = 'alumno' AND eqg.id_competicion IN ($marcadores))";
}
if ($equiposSeleccionados !== []) {
    $marcadores = marcadoresIn($equiposSeleccionados, 'equ_gen_', $parametrosGeneral);
    $condicionesGeneral[] = "EXISTS (SELECT 1 FROM integrantes itg2 WHERE itg2.id_alumno = a.id AND itg2.tipo = 'alumno' AND itg2.id_equipo IN ($marcadores))";
}
$condicionEstadoGeneral = condicionEstado($estado, 'ag.hora_entrada', 'ag.hora_salida');
if ($condicionEstadoGeneral !== '') {
    $condicionesGeneral[] = $condicionEstadoGeneral;
}
$whereGeneral = $condicionesGeneral !== [] ? 'AND ' . implode(' AND ', $condicionesGeneral) : '';

$consultaGeneral = $pdo->prepare(
    "SELECT a.nombre_completo, a.numero_cuenta, a.grado, a.grupo,
            ag.hora_entrada, ag.punto_control_entrada, ag.escaneado_por_entrada,
            ag.hora_salida, ag.punto_control_salida, ag.escaneado_por_salida
     FROM alumnos a
     LEFT JOIN asistencias_generales ag ON ag.id_alumno = a.id AND ag.dia = :dia
     WHERE 1=1 $whereGeneral
     ORDER BY (ag.hora_entrada IS NULL) ASC, ag.hora_entrada DESC, a.nombre_completo ASC"
);
$consultaGeneral->execute($parametrosGeneral);
$asistenciaGeneral = $consultaGeneral->fetchAll();

// --- 2. Asistencia por evento (ponencias/talleres, solo académico/cultural) -
$asistenciaEventos = [];
if ($dia !== 'deportivo') {
    $condicionesEventos = [];
    $parametrosEventos = ['dia' => $dia];
    if ($cuenta !== '') {
        $condicionesEventos[] = 'a.numero_cuenta LIKE :cuenta';
        $parametrosEventos['cuenta'] = '%' . $cuenta . '%';
    }
    if ($grado !== '') {
        $condicionesEventos[] = 'a.grado = :grado';
        $parametrosEventos['grado'] = $grado;
    }
    if ($grupo !== '') {
        $condicionesEventos[] = 'a.grupo = :grupo';
        $parametrosEventos['grupo'] = $grupo;
    }
    if ($eventosSeleccionados !== []) {
        $marcadores = marcadoresIn($eventosSeleccionados, 'ev_ev_', $parametrosEventos);
        $condicionesEventos[] = "i.id_evento IN ($marcadores)";
    }
    $condicionEstadoEventos = condicionEstado($estado, 'i.hora_entrada', 'i.hora_salida');
    if ($condicionEstadoEventos !== '') {
        $condicionesEventos[] = $condicionEstadoEventos;
    }
    $whereEventos = $condicionesEventos !== [] ? 'AND ' . implode(' AND ', $condicionesEventos) : '';

    $consultaEventos = $pdo->prepare(
        "SELECT e.nombre AS evento, a.nombre_completo, a.numero_cuenta, a.grado, a.grupo, i.origen,
                i.hora_entrada, i.punto_control_entrada, i.escaneado_por_entrada,
                i.hora_salida, i.punto_control_salida, i.escaneado_por_salida
         FROM inscripciones i
         JOIN eventos e ON e.id = i.id_evento
         JOIN alumnos a ON a.id = i.id_alumno
         WHERE e.dia = :dia $whereEventos
         ORDER BY e.nombre, a.nombre_completo"
    );
    $consultaEventos->execute($parametrosEventos);
    $asistenciaEventos = $consultaEventos->fetchAll();
}

// --- 3. Asistencia por equipo (concursos/torneos) ---------------------------
$condicionesEquipos = [];
$parametrosEquipos = ['dia' => $dia];
if ($cuenta !== '') {
    $condicionesEquipos[] = 'a.numero_cuenta LIKE :cuenta';
    $parametrosEquipos['cuenta'] = '%' . $cuenta . '%';
}
if ($grado !== '') {
    $condicionesEquipos[] = 'a.grado = :grado';
    $parametrosEquipos['grado'] = $grado;
}
if ($grupo !== '') {
    $condicionesEquipos[] = 'a.grupo = :grupo';
    $parametrosEquipos['grupo'] = $grupo;
}
if ($competicionesSeleccionadas !== []) {
    $marcadores = marcadoresIn($competicionesSeleccionadas, 'comp_eq_', $parametrosEquipos);
    $condicionesEquipos[] = "c.id IN ($marcadores)";
}
if ($equiposSeleccionados !== []) {
    $marcadores = marcadoresIn($equiposSeleccionados, 'equ_eq_', $parametrosEquipos);
    $condicionesEquipos[] = "eq.id IN ($marcadores)";
}
$condicionEstadoEquipos = condicionEstado($estado, 'it.hora_entrada', 'it.hora_salida');
if ($condicionEstadoEquipos !== '') {
    $condicionesEquipos[] = $condicionEstadoEquipos;
}
$whereEquipos = $condicionesEquipos !== [] ? 'AND ' . implode(' AND ', $condicionesEquipos) : '';

$consultaEquipos = $pdo->prepare(
    "SELECT c.nombre AS competicion, eq.nombre AS equipo, it.nombre, it.tipo, it.codigo_participante,
            a.numero_cuenta AS ancla_cuenta, a.grado AS ancla_grado, a.grupo AS ancla_grupo,
            it.hora_entrada, it.punto_control_entrada, it.escaneado_por_entrada,
            it.hora_salida, it.punto_control_salida, it.escaneado_por_salida
     FROM integrantes it
     JOIN equipos eq ON eq.id = it.id_equipo
     JOIN competiciones c ON c.id = eq.id_competicion
     JOIN alumnos a ON a.id = it.id_alumno
     WHERE c.dia = :dia $whereEquipos
     ORDER BY c.nombre, eq.nombre, it.nombre"
);
$consultaEquipos->execute($parametrosEquipos);
$asistenciaEquipos = $consultaEquipos->fetchAll();

$estadosLabel = [
    '' => 'Todos',
    'sin_entrada' => 'Sin entrada (no llegó)',
    'entrada_sin_salida' => 'Entrada sin salida (sigue dentro)',
    'completos' => 'Completos (entrada y salida)',
];

layoutAdminAbrir('Asistencias', 'asistencias');
?>

<div class="mb-6 flex flex-wrap gap-2">
    <?php foreach ($diasLabel as $diaClave => $diaLabel): ?>
    <a href="?dia=<?= $diaClave ?>"
       class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium <?= $dia === $diaClave ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 shadow-sm hover:bg-slate-50' ?>">
        <?= icono($diaClave, 'h-4 w-4') ?>
        <?= $diaLabel ?>
    </a>
    <?php endforeach; ?>
</div>

<form method="get" class="mb-8 flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm">
    <input type="hidden" name="dia" value="<?= htmlspecialchars($dia, ENT_QUOTES, 'UTF-8') ?>">

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Estado de asistencia</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                <select name="estado" class="w-64 rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <?php foreach ($estadosLabel as $valor => $etiqueta): ?>
                    <option value="<?= $valor ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">No. cuenta</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('buscar', 'h-4 w-4') ?></span>
                <input type="text" name="cuenta" value="<?= htmlspecialchars($cuenta, ENT_QUOTES, 'UTF-8') ?>" placeholder="Número de cuenta"
                       class="w-44 rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Grado</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                <select name="grado" class="rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Todos</option>
                    <?php foreach (['1', '3', '5'] as $g): ?>
                    <option value="<?= $g ?>" <?= $grado === $g ? 'selected' : '' ?>><?= $g ?>°</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Grupo</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('filtro', 'h-3.5 w-3.5') ?></span>
                <select name="grupo" class="rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Todos</option>
                    <?php foreach (['A', 'B', 'C'] as $gr): ?>
                    <option value="<?= $gr ?>" <?= $grupo === $gr ? 'selected' : '' ?>><?= $gr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
            <?= icono('filtro', 'h-4 w-4') ?>
            Filtrar
        </button>
        <?php if ($hayFiltrosActivos): ?>
        <a href="?dia=<?= $dia ?>" class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            <?= icono('cerrar', 'h-4 w-4') ?>
            Limpiar filtros
        </a>
        <?php endif; ?>
    </div>

    <div class="flex flex-wrap gap-4 border-t border-slate-100 pt-4">
        <?php if ($eventosDia !== []): ?>
        <div>
            <label class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-500"><?= icono('lista', 'h-3.5 w-3.5') ?> Evento(s)</label>
            <select name="evento[]" multiple size="4" class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                <?php foreach ($eventosDia as $ev): ?>
                <option value="<?= (int) $ev['id'] ?>" <?= in_array((int) $ev['id'], $eventosSeleccionados, true) ? 'selected' : '' ?>><?= htmlspecialchars($ev['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($competicionesDia !== []): ?>
        <div>
            <label class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-500"><?= icono('trofeo', 'h-3.5 w-3.5') ?> Competición(es)</label>
            <select name="competicion[]" multiple size="4" class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                <?php foreach ($competicionesDia as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $competicionesSeleccionadas, true) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($equiposDia !== []): ?>
        <div>
            <label class="mb-1 flex items-center gap-1 text-xs font-medium text-slate-500"><?= icono('usuarios', 'h-3.5 w-3.5') ?> Equipo(s)</label>
            <select name="equipo[]" multiple size="4" class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                <?php foreach ($equiposDia as $eq): ?>
                <option value="<?= (int) $eq['id'] ?>" <?= in_array((int) $eq['id'], $equiposSeleccionados, true) ? 'selected' : '' ?>><?= htmlspecialchars($eq['competicion'] . ' — ' . $eq['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($eventosDia !== [] || $competicionesDia !== [] || $equiposDia !== []): ?>
        <p class="w-full self-end text-xs text-slate-400">Ctrl/Cmd + clic para elegir varios. El evento/competición/equipo también filtra la asistencia general de quienes están inscritos ahí.</p>
        <?php endif; ?>
    </div>
</form>

<section class="mb-8">
    <h2 class="mb-3 text-base font-semibold">Asistencia general (¿ya entró/salió del plantel?)</h2>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full min-w-[1200px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-3">Alumno</th>
                    <th class="px-4 py-3 text-center">No. cuenta</th>
                    <th class="px-4 py-3 text-center">Grado/Grupo</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-center">Entrada</th>
                    <th class="px-4 py-3 text-center">Punto (entrada)</th>
                    <th class="px-4 py-3 text-center">Escaneó (entrada)</th>
                    <th class="px-4 py-3 text-center">Salida</th>
                    <th class="px-4 py-3 text-center">Punto (salida)</th>
                    <th class="px-4 py-3 text-center">Escaneó (salida)</th>
                    <th class="px-4 py-3 text-center">Duración</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($asistenciaGeneral === []): ?>
                <tr><td colspan="11" class="px-4 py-10 text-center text-slate-500">
                    <span class="flex flex-col items-center gap-2">
                        <?= icono('qr', 'h-6 w-6 text-slate-300') ?>
                        Sin alumnos que coincidan con estos filtros.
                    </span>
                </td></tr>
                <?php endif; ?>
                <?php foreach ($asistenciaGeneral as $fila): ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($fila['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($fila['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars($fila['grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars($fila['grupo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center"><?= badgeEstadoAsistencia($fila['hora_entrada'], $fila['hora_salida']) ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_entrada'] ? htmlspecialchars((string) $fila['hora_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['punto_control_entrada'] ? htmlspecialchars((string) $fila['punto_control_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_entrada'] ? htmlspecialchars((string) $fila['escaneado_por_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_salida'] ? htmlspecialchars((string) $fila['hora_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['punto_control_salida'] ? htmlspecialchars((string) $fila['punto_control_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_salida'] ? htmlspecialchars((string) $fila['escaneado_por_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center font-medium text-slate-700"><?= duracionAsistencia($fila['hora_entrada'], $fila['hora_salida']) ?? '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($dia !== 'deportivo'): ?>
<section class="mb-8">
    <h2 class="mb-3 text-base font-semibold">Asistencia por evento (ponencias/talleres)</h2>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full min-w-[1200px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-3">Evento</th>
                    <th class="px-4 py-3">Alumno</th>
                    <th class="px-4 py-3 text-center">No. cuenta</th>
                    <th class="px-4 py-3 text-center">Grado/Grupo</th>
                    <th class="px-4 py-3 text-center">Origen</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-center">Entrada</th>
                    <th class="px-4 py-3 text-center">Escaneó (entrada)</th>
                    <th class="px-4 py-3 text-center">Salida</th>
                    <th class="px-4 py-3 text-center">Escaneó (salida)</th>
                    <th class="px-4 py-3 text-center">Duración</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($asistenciaEventos === []): ?>
                <tr><td colspan="11" class="px-4 py-10 text-center text-slate-500">
                    <span class="flex flex-col items-center gap-2">
                        <?= icono('lista', 'h-6 w-6 text-slate-300') ?>
                        Sin inscripciones que coincidan con estos filtros.
                    </span>
                </td></tr>
                <?php endif; ?>
                <?php foreach ($asistenciaEventos as $fila): ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($fila['evento'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($fila['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($fila['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars($fila['grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars($fila['grupo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['origen'] === 'previo' ? 'Previo' : 'Orden de llegada' ?></td>
                    <td class="px-4 py-3 text-center"><?= badgeEstadoAsistencia($fila['hora_entrada'], $fila['hora_salida']) ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_entrada'] ? htmlspecialchars((string) $fila['hora_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_entrada'] ? htmlspecialchars((string) $fila['escaneado_por_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_salida'] ? htmlspecialchars((string) $fila['hora_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_salida'] ? htmlspecialchars((string) $fila['escaneado_por_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center font-medium text-slate-700"><?= duracionAsistencia($fila['hora_entrada'], $fila['hora_salida']) ?? '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section>
    <h2 class="mb-3 text-base font-semibold">Asistencia por equipo (concursos/torneos)</h2>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full min-w-[1350px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-3">Competición</th>
                    <th class="px-4 py-3">Equipo</th>
                    <th class="px-4 py-3">Integrante</th>
                    <th class="px-4 py-3 text-center">Alumno ancla</th>
                    <th class="px-4 py-3 text-center">Código participante</th>
                    <th class="px-4 py-3 text-center">Tipo</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-center">Entrada</th>
                    <th class="px-4 py-3 text-center">Escaneó (entrada)</th>
                    <th class="px-4 py-3 text-center">Salida</th>
                    <th class="px-4 py-3 text-center">Escaneó (salida)</th>
                    <th class="px-4 py-3 text-center">Duración</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($asistenciaEquipos === []): ?>
                <tr><td colspan="12" class="px-4 py-10 text-center text-slate-500">
                    <span class="flex flex-col items-center gap-2">
                        <?= icono('trofeo', 'h-6 w-6 text-slate-300') ?>
                        Sin integrantes que coincidan con estos filtros.
                    </span>
                </td></tr>
                <?php endif; ?>
                <?php foreach ($asistenciaEquipos as $fila): ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($fila['competicion'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($fila['equipo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($fila['ancla_cuenta'], ENT_QUOTES, 'UTF-8') ?> <span class="font-sans">(<?= htmlspecialchars($fila['ancla_grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars($fila['ancla_grupo'], ENT_QUOTES, 'UTF-8') ?>)</span></td>
                    <td class="px-4 py-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($fila['codigo_participante'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center capitalize text-slate-500"><?= htmlspecialchars($fila['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center"><?= badgeEstadoAsistencia($fila['hora_entrada'], $fila['hora_salida']) ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_entrada'] ? htmlspecialchars((string) $fila['hora_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_entrada'] ? htmlspecialchars((string) $fila['escaneado_por_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_salida'] ? htmlspecialchars((string) $fila['hora_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_salida'] ? htmlspecialchars((string) $fila['escaneado_por_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center font-medium text-slate-700"><?= duracionAsistencia($fila['hora_entrada'], $fila['hora_salida']) ?? '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php layoutAdminCerrar(); ?>
