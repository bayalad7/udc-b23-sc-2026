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

$diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural', 'deportivo' => 'Día Deportivo'];
$dia = trim((string) ($_GET['dia'] ?? 'academico'));
if (!isset($diasLabel[$dia])) {
    $dia = 'academico';
}

// --- 1. Asistencia general del día ------------------------------------------
$consultaGeneral = $pdo->prepare(
    'SELECT a.nombre_completo, a.numero_cuenta, ag.hora_entrada, ag.punto_control_entrada, ag.escaneado_por_entrada,
            ag.hora_salida, ag.punto_control_salida, ag.escaneado_por_salida
     FROM asistencias_generales ag
     JOIN alumnos a ON a.id = ag.id_alumno
     WHERE ag.dia = :dia
     ORDER BY ag.hora_entrada DESC'
);
$consultaGeneral->execute(['dia' => $dia]);
$asistenciaGeneral = $consultaGeneral->fetchAll();

// --- 2. Asistencia por evento (ponencias/talleres, solo académico/cultural) -
$asistenciaEventos = [];
if ($dia !== 'deportivo') {
    $consultaEventos = $pdo->prepare(
        'SELECT e.nombre AS evento, a.nombre_completo, a.numero_cuenta, i.origen,
                i.hora_entrada, i.punto_control_entrada, i.escaneado_por_entrada,
                i.hora_salida, i.punto_control_salida, i.escaneado_por_salida
         FROM inscripciones i
         JOIN eventos e ON e.id = i.id_evento
         JOIN alumnos a ON a.id = i.id_alumno
         WHERE e.dia = :dia
         ORDER BY e.nombre, a.nombre_completo'
    );
    $consultaEventos->execute(['dia' => $dia]);
    $asistenciaEventos = $consultaEventos->fetchAll();
}

// --- 3. Asistencia por equipo (concursos/torneos) ---------------------------
$consultaEquipos = $pdo->prepare(
    'SELECT c.nombre AS competicion, eq.nombre AS equipo, it.nombre, it.tipo, it.codigo_participante,
            it.hora_entrada, it.punto_control_entrada, it.escaneado_por_entrada,
            it.hora_salida, it.punto_control_salida, it.escaneado_por_salida
     FROM integrantes it
     JOIN equipos eq ON eq.id = it.id_equipo
     JOIN competiciones c ON c.id = eq.id_competicion
     WHERE c.dia = :dia
     ORDER BY c.nombre, eq.nombre, it.nombre'
);
$consultaEquipos->execute(['dia' => $dia]);
$asistenciaEquipos = $consultaEquipos->fetchAll();

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

<section class="mb-8">
    <h2 class="mb-3 text-base font-semibold">Asistencia general (¿ya entró/salió del plantel?)</h2>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full min-w-[900px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-3">Alumno</th>
                    <th class="px-4 py-3 text-center">No. cuenta</th>
                    <th class="px-4 py-3 text-center">Entrada</th>
                    <th class="px-4 py-3 text-center">Punto (entrada)</th>
                    <th class="px-4 py-3 text-center">Escaneó (entrada)</th>
                    <th class="px-4 py-3 text-center">Salida</th>
                    <th class="px-4 py-3 text-center">Punto (salida)</th>
                    <th class="px-4 py-3 text-center">Escaneó (salida)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($asistenciaGeneral === []): ?>
                <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">
                    <span class="flex flex-col items-center gap-2">
                        &nbsp;<br/><br/>
                        <?= icono('qr', 'h-6 w-6 text-slate-300') ?>
                        Sin registros todavía para este día.
                        <br/>
                        &nbsp;
                    </span>
                </td></tr>
                <?php endif; ?>
                <?php foreach ($asistenciaGeneral as $fila): ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($fila['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($fila['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars((string) $fila['hora_entrada'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars((string) $fila['punto_control_entrada'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars((string) $fila['escaneado_por_entrada'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center <?= $fila['hora_salida'] ? 'text-slate-500' : 'font-medium text-emerald-700' ?>"><?= $fila['hora_salida'] ? htmlspecialchars((string) $fila['hora_salida'], ENT_QUOTES, 'UTF-8') : 'Sigue dentro' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['punto_control_salida'] ? htmlspecialchars((string) $fila['punto_control_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_salida'] ? htmlspecialchars((string) $fila['escaneado_por_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
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
        <table class="w-full min-w-[900px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-3">Evento</th>
                    <th class="px-4 py-3">Alumno</th>
                    <th class="px-4 py-3 text-center">No. cuenta</th>
                    <th class="px-4 py-3 text-center">Origen</th>
                    <th class="px-4 py-3 text-center">Entrada</th>
                    <th class="px-4 py-3 text-center">Escaneó (entrada)</th>
                    <th class="px-4 py-3 text-center">Salida</th>
                    <th class="px-4 py-3 text-center">Escaneó (salida)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($asistenciaEventos === []): ?>
                <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">
                    <span class="flex flex-col items-center gap-2">
                        <?= icono('lista', 'h-6 w-6 text-slate-300') ?>
                        Sin inscripciones todavía para este día.
                    </span>
                </td></tr>
                <?php endif; ?>
                <?php foreach ($asistenciaEventos as $fila): ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($fila['evento'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($fila['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($fila['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['origen'] === 'previo' ? 'Previo' : 'Orden de llegada' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_entrada'] ? htmlspecialchars((string) $fila['hora_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_entrada'] ? htmlspecialchars((string) $fila['escaneado_por_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_salida'] ? htmlspecialchars((string) $fila['hora_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_salida'] ? htmlspecialchars((string) $fila['escaneado_por_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
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
        <table class="w-full min-w-[1000px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-3">Competición</th>
                    <th class="px-4 py-3">Equipo</th>
                    <th class="px-4 py-3">Integrante</th>
                    <th class="px-4 py-3 text-center">Código participante</th>
                    <th class="px-4 py-3 text-center">Tipo</th>
                    <th class="px-4 py-3 text-center">Entrada</th>
                    <th class="px-4 py-3 text-center">Escaneó (entrada)</th>
                    <th class="px-4 py-3 text-center">Salida</th>
                    <th class="px-4 py-3 text-center">Escaneó (salida)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($asistenciaEquipos === []): ?>
                <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">
                    <span class="flex flex-col items-center gap-2">
                        <?= icono('trofeo', 'h-6 w-6 text-slate-300') ?>
                        Sin equipos todavía para este día.
                    </span>
                </td></tr>
                <?php endif; ?>
                <?php foreach ($asistenciaEquipos as $fila): ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($fila['competicion'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($fila['equipo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center font-mono text-xs text-slate-500"><?= htmlspecialchars($fila['codigo_participante'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center capitalize text-slate-500"><?= htmlspecialchars($fila['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_entrada'] ? htmlspecialchars((string) $fila['hora_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_entrada'] ? htmlspecialchars((string) $fila['escaneado_por_entrada'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['hora_salida'] ? htmlspecialchars((string) $fila['hora_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="px-4 py-3 text-center text-slate-500"><?= $fila['escaneado_por_salida'] ? htmlspecialchars((string) $fila['escaneado_por_salida'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php layoutAdminCerrar(); ?>
