<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

iniciarSesionAdmin();

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';
$claveYaRegistrada = $pdo->query('SELECT 1 FROM sistema WHERE clave_admin IS NOT NULL LIMIT 1')->fetch() !== false;

$errores = [
    'clave_incorrecta' => 'Contraseña incorrecta.',
    'claves_no_coinciden' => 'Las contraseñas no coinciden.',
    'clave_muy_corta' => 'La contraseña debe tener al menos 8 caracteres.',
    'ya_registrada' => 'La contraseña ya había sido registrada — usa el formulario de acceso.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;

if ($claveYaRegistrada && adminAutorizado()) {
    require __DIR__ . '/../includes/layout.php';

    // --- 1. Alumnos registrados por grado/grupo -----------------------------
    $totalAlumnos = (int) $pdo->query('SELECT COUNT(*) AS n FROM alumnos')->fetch()['n'];
    $porGradoGrupo = $pdo->query(
        'SELECT grado, grupo, COUNT(*) AS total FROM alumnos GROUP BY grado, grupo ORDER BY grado, grupo'
    )->fetchAll();

    // --- 2. Asistencia general en vivo por día ------------------------------
    $diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural', 'deportivo' => 'Día Deportivo'];
    $asistenciaPorDia = array_fill_keys(array_keys($diasLabel), ['total' => 0, 'sin_salida' => 0, 'con_salida' => 0]);
    $filasAsistencia = $pdo->query(
        "SELECT dia, COUNT(*) AS total,
                SUM(hora_salida IS NULL) AS sin_salida,
                SUM(hora_salida IS NOT NULL) AS con_salida
         FROM asistencias_generales GROUP BY dia"
    )->fetchAll();
    foreach ($filasAsistencia as $fila) {
        $asistenciaPorDia[$fila['dia']] = [
            'total' => (int) $fila['total'],
            'sin_salida' => (int) $fila['sin_salida'],
            'con_salida' => (int) $fila['con_salida'],
        ];
    }

    // --- 3. Cupo ocupado por evento ------------------------------------------
    $eventosCupo = $pdo->query(
        'SELECT nombre, dia, tipo, espacio, cupo_maximo, cupo_disponible
         FROM eventos ORDER BY dia, hora_inicio'
    )->fetchAll();

    // --- 4. Equipos inscritos por competición ---------------------------------
    $equiposPorCompeticion = $pdo->query(
        "SELECT c.id, c.nombre, c.dia, c.tipo, c.max_equipos, COUNT(e.id) AS total_equipos
         FROM competiciones c
         LEFT JOIN equipos e ON e.id_competicion = c.id
         GROUP BY c.id, c.nombre, c.dia, c.tipo, c.max_equipos
         ORDER BY c.dia, c.hora_inicio"
    )->fetchAll();

    // --- 5. Tallas de camisa solicitadas (pedido al proveedor) ---------------
    // El pedido cubre alumnos Y personal: las dos poblaciones se suman por
    // talla (ver includes/tallas-camisa.php, compartido con la exportación).
    require_once __DIR__ . '/../includes/tallas-camisa.php';

    $resumenCamisa = tallasCamisaResumen($pdo);
    $tallasCamisa = $resumenCamisa['tallas'];
    $columnasCamisa = $resumenCamisa['columnas'];
    $tallasCamisaPivote = $resumenCamisa['pivote'];
    $tallasCamisaTotales = $resumenCamisa['totales'];

    $alumnosCamisaPorGrupo = tallasCamisaDetalleAlumnos($pdo);
    $personalCamisaPorTipo = tallasCamisaDetallePersonal($pdo);
    $gruposCamisaDescarga = camisaGruposValidos($pdo);

    // Series de la gráfica: una barra apilada por talla, para que la altura
    // sea lo que hay que encargarle al proveedor.
    $serieCamisaAlumnos = tallasCamisaSerie($pdo, 'alumnos', $tallasCamisa);
    $serieCamisaPersonal = tallasCamisaSerie($pdo, 'trabajadores', $tallasCamisa);

    // --- 6. Ausentismo: asistencia general vs. asistencia al evento asignado -
    // (solo Día Académico y Día Cultural, que son los que tienen eventos
    // individuales — el Día Deportivo se organiza todo por equipo).
    $ausentismoPorDia = [];
    $consultaAusentismo = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM asistencias_generales WHERE dia = :dia1) AS entraron_plantel,
            (SELECT COUNT(*) FROM inscripciones i JOIN eventos e ON e.id = i.id_evento
             WHERE e.dia = :dia2 AND i.hora_entrada IS NOT NULL) AS entraron_evento'
    );
    foreach (['academico' => 'Día Académico', 'cultural' => 'Día Cultural'] as $diaClave => $diaLabelCorto) {
        $consultaAusentismo->execute(['dia1' => $diaClave, 'dia2' => $diaClave]);
        $filaAusentismo = $consultaAusentismo->fetch();
        $ausentismoPorDia[] = [
            'dia' => $diaLabelCorto,
            'entraron_plantel' => (int) $filaAusentismo['entraron_plantel'],
            'entraron_evento' => (int) $filaAusentismo['entraron_evento'],
        ];
    }

    // --- 7. Bandera de inscripciones abiertas/cerradas -----------------------
    require_once __DIR__ . '/../../inscripciones/includes/estado.php';
    $inscripcionesAbiertas = inscripcionesLiberadas($pdo);

    // --- Datos derivados para las gráficas y para resaltar KPIs de alerta ----
    $matrizGradoGrupo = [];
    foreach (['1', '3', '5'] as $g) {
        foreach (['A', 'B', 'C'] as $gr) {
            $matrizGradoGrupo[$g][$gr] = 0;
        }
    }
    foreach ($porGradoGrupo as $fila) {
        $matrizGradoGrupo[$fila['grado']][$fila['grupo']] = (int) $fila['total'];
    }

    $cupoEventosOrdenado = array_map(static function (array $evento): array {
        $ocupados = (int) $evento['cupo_maximo'] - (int) $evento['cupo_disponible'];
        $porcentaje = (int) $evento['cupo_maximo'] > 0 ? (int) round($ocupados / (int) $evento['cupo_maximo'] * 100) : 0;
        return [
            'nombre' => $evento['nombre'],
            'espacio' => $evento['espacio'],
            'ocupados' => $ocupados,
            'cupo_maximo' => (int) $evento['cupo_maximo'],
            'porcentaje' => $porcentaje,
        ];
    }, $eventosCupo);
    usort($cupoEventosOrdenado, static fn(array $a, array $b): int => $b['porcentaje'] <=> $a['porcentaje']);
    $eventosCercaDeAgotarse = count(array_filter($cupoEventosOrdenado, static fn(array $e): bool => $e['porcentaje'] >= 80));

    $equiposCompeticionDatos = array_map(static function (array $c): array {
        return [
            'nombre' => $c['nombre'],
            'total' => (int) $c['total_equipos'],
            'max_equipos' => $c['max_equipos'] !== null ? (int) $c['max_equipos'] : null,
            'es_conocimiento' => $c['dia'] === 'academico' && $c['tipo'] === 'concurso',
        ];
    }, $equiposPorCompeticion);
    $conocimientoActual = null;
    foreach ($equiposCompeticionDatos as $c) {
        if ($c['es_conocimiento']) {
            $conocimientoActual = $c;
            break;
        }
    }

    // Copias ordenadas de mayor a menor solo para las tablas de detalle de
    // las gráficas (el orden de la gráfica en sí — cronológico por día —
    // no cambia).
    $equiposCompeticionOrdenado = $equiposCompeticionDatos;
    usort($equiposCompeticionOrdenado, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);

    $filasGradoGrupo = [];
    foreach (['1', '3', '5'] as $g) {
        foreach (['A', 'B', 'C'] as $gr) {
            $filasGradoGrupo[] = ['grado' => $g, 'grupo' => $gr, 'total' => $matrizGradoGrupo[$g][$gr]];
        }
    }

    // Resultado del reseteo del padrón (ver includes/resetear-alumnos.php) —
    // aparte del $mensajeError de la pantalla de acceso, que solo aplica
    // cuando todavía no hay sesión autorizada.
    $resetMensajeExito = null;
    if (($_GET['msg'] ?? '') === 'reseteado') {
        $resetMensajeExito = sprintf(
            'Padrón reseteado: se eliminaron %s alumnos (con sus inscripciones, equipos y asistencias) y %s archivos de fotos y credenciales.',
            number_format((int) ($_GET['alumnos'] ?? 0)),
            number_format((int) ($_GET['archivos'] ?? 0))
        );
    }
    if (($_GET['msg'] ?? '') === 'inscripciones_abiertas') {
        $resetMensajeExito = 'Inscripciones abiertas: el alumnado ya puede identificarse e inscribirse.';
    }
    if (($_GET['msg'] ?? '') === 'inscripciones_cerradas') {
        $resetMensajeExito = 'Inscripciones cerradas: el alumnado ya no puede identificarse ni reservar lugar.';
    }

    $resetErrores = [
        'reset_clave' => 'Contraseña de administrador incorrecta — no se borró nada.',
        'reset_confirmacion' => 'La confirmación no coincide: escribe RESETEAR tal cual para continuar.',
        'reset_error' => 'No se pudo completar el reseteo; no se borró nada. Revisa el log del servidor.',
    ];
    $resetMensajeError = $resetErrores[$_GET['error'] ?? ''] ?? null;

    layoutAdminAbrir('Dashboard', 'dashboard');
    if ($resetMensajeExito !== null) {
        bannerAdmin('exito', $resetMensajeExito);
    }
    if ($resetMensajeError !== null) {
        bannerAdmin('error', $resetMensajeError);
    }
    ?>

    <!-- KPIs — tarjetas compactas tipo "chip"; la jerarquía visual queda en
         el color: el aforo en vivo y las alertas de cupo/límite llevan
         acento de color, "Alumnos registrados" es puramente informativo y
         se queda neutro. -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <?= icono('usuarios', 'h-5 w-5') ?>
            </span>
            <div class="min-w-0">
                <span class="block truncate text-xs text-slate-500">Alumnos registrados</span>
                <span class="block text-xl font-bold text-slate-900"><?= number_format($totalAlumnos) ?></span>
            </div>
        </div>

        <?php foreach (['academico' => 'Académico', 'deportivo' => 'Deportivo'] as $diaClave => $diaCorto): ?>
        <div class="flex items-center gap-3 rounded-lg border-l-4 border-blue-500 bg-blue-50 p-3 shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                <?= icono('qr', 'h-5 w-5') ?>
            </span>
            <div class="min-w-0">
                <span class="block truncate text-xs font-medium text-blue-700">Sin salida — <?= $diaCorto ?></span>
                <span class="block text-xl font-bold text-blue-900"><?= number_format($asistenciaPorDia[$diaClave]['sin_salida']) ?></span>
                <span class="block truncate text-[11px] text-blue-600">de <?= number_format($asistenciaPorDia[$diaClave]['total']) ?> con entrada</span>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="flex items-center gap-3 rounded-lg border-l-4 p-3 shadow-sm <?= $eventosCercaDeAgotarse > 0 ? 'border-amber-500 bg-amber-50' : 'border-emerald-500 bg-emerald-50' ?>">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full <?= $eventosCercaDeAgotarse > 0 ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600' ?>">
                <?= icono('alerta', 'h-5 w-5') ?>
            </span>
            <div class="min-w-0">
                <span class="block truncate text-xs font-medium <?= $eventosCercaDeAgotarse > 0 ? 'text-amber-700' : 'text-emerald-700' ?>">Eventos por agotarse</span>
                <span class="block text-xl font-bold <?= $eventosCercaDeAgotarse > 0 ? 'text-amber-900' : 'text-emerald-900' ?>"><?= $eventosCercaDeAgotarse ?></span>
                <span class="block truncate text-[11px] <?= $eventosCercaDeAgotarse > 0 ? 'text-amber-600' : 'text-emerald-600' ?>">≥80% de cupo ocupado</span>
            </div>
        </div>

        <?php if ($conocimientoActual !== null):
            $cdMaxEquipos = $conocimientoActual['max_equipos'];
            $cdCercaUmbral = $cdMaxEquipos !== null ? max(0, $cdMaxEquipos - 2) : null;
            $cdEstado = $cdMaxEquipos === null ? 'ok' : ($conocimientoActual['total'] >= $cdMaxEquipos ? 'lleno' : ($conocimientoActual['total'] >= $cdCercaUmbral ? 'cerca' : 'ok'));
            $cdClases = [
                'lleno' => ['border-red-500 bg-red-50', 'bg-red-100 text-red-600', 'text-red-700', 'text-red-900', 'text-red-600'],
                'cerca' => ['border-amber-500 bg-amber-50', 'bg-amber-100 text-amber-600', 'text-amber-700', 'text-amber-900', 'text-amber-600'],
                'ok' => ['border-slate-200 bg-white', 'bg-slate-100 text-slate-500', 'text-slate-500', 'text-slate-900', 'text-slate-400'],
            ][$cdEstado];
        ?>
        <div class="flex items-center gap-3 rounded-lg border-l-4 p-3 shadow-sm <?= $cdClases[0] ?>">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full <?= $cdClases[1] ?>">
                <?= icono('trofeo', 'h-5 w-5') ?>
            </span>
            <div class="min-w-0">
                <span class="block truncate text-xs font-medium <?= $cdClases[2] ?>" title="Concurso del Conocimiento">Concurso del Conocimiento</span>
                <span class="block text-xl font-bold <?= $cdClases[3] ?>"><?= $conocimientoActual['total'] ?><?= $cdMaxEquipos !== null ? '/' . $cdMaxEquipos : '' ?></span>
                <span class="block truncate text-[11px] <?= $cdClases[4] ?>">equipos inscritos</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <?= icono('grafica', 'h-4 w-4 text-slate-400') ?>
                    Alumnos por grado y grupo
                </h2>
                <?php if ($porGradoGrupo !== []): ?>
                <button type="button" data-abrir-modal="detalle-grado-grupo" title="Ver detalle en tabla"
                        class="flex shrink-0 cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
                    <?= icono('tabla', 'h-3.5 w-3.5') ?>
                    Ver tabla
                </button>
                <?php endif; ?>
            </div>
            <?php if ($porGradoGrupo === []): ?>
            <p class="text-sm text-slate-500">Todavía no hay alumnos registrados.</p>
            <?php else: ?>
            <div class="h-64"><canvas id="grafica-grado-grupo"></canvas></div>
            <?php endif; ?>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold">
                <?= icono('qr', 'h-4 w-4 text-slate-400') ?>
                Asistencia general en vivo
            </h2>
            <div class="space-y-3">
                <?php foreach ($diasLabel as $diaClave => $diaLabel): $datos = $asistenciaPorDia[$diaClave]; ?>
                <div class="flex items-center justify-between rounded-lg border border-slate-200 p-3">
                    <span class="text-sm font-medium"><?= $diaLabel ?></span>
                    <span class="text-sm text-slate-500">
                        <strong class="text-slate-900"><?= number_format($datos['sin_salida']) ?></strong> dentro ·
                        <?= number_format($datos['con_salida']) ?> salieron ·
                        <?= number_format($datos['total']) ?> entraron en total
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm lg:col-span-2">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <?= icono('grafica', 'h-4 w-4 text-slate-400') ?>
                    Cupo ocupado por evento
                </h2>
                <?php if ($cupoEventosOrdenado !== []): ?>
                <button type="button" data-abrir-modal="detalle-cupo-eventos" title="Ver detalle en tabla"
                        class="flex shrink-0 cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
                    <?= icono('tabla', 'h-3.5 w-3.5') ?>
                    Ver tabla
                </button>
                <?php endif; ?>
            </div>
            <?php if ($cupoEventosOrdenado === []): ?>
            <p class="text-sm text-slate-500">Todavía no hay eventos registrados.</p>
            <?php else: ?>
            <div style="height: <?= max(220, count($cupoEventosOrdenado) * 34) ?>px"><canvas id="grafica-cupo-eventos"></canvas></div>
            <?php endif; ?>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <?= icono('grafica', 'h-4 w-4 text-slate-400') ?>
                    Equipos por competición
                </h2>
                <?php if ($equiposCompeticionDatos !== []): ?>
                <button type="button" data-abrir-modal="detalle-equipos-competicion" title="Ver detalle en tabla"
                        class="flex shrink-0 cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
                    <?= icono('tabla', 'h-3.5 w-3.5') ?>
                    Ver tabla
                </button>
                <?php endif; ?>
            </div>
            <?php if ($equiposCompeticionDatos === []): ?>
            <p class="text-sm text-slate-500">Todavía no hay competiciones registradas.</p>
            <?php else: ?>
            <div class="h-64"><canvas id="grafica-equipos-competicion"></canvas></div>
            <?php if ($conocimientoActual !== null && $conocimientoActual['max_equipos'] !== null): ?>
            <p class="mt-2 text-xs text-slate-400">La línea punteada marca el límite de <?= $conocimientoActual['max_equipos'] ?> equipos del Concurso del Conocimiento.</p>
            <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <?= icono('camisa', 'h-4 w-4 text-slate-400') ?>
                    Tallas de camisa solicitadas
                </h2>
                <?php if ($tallasCamisa !== []): ?>
                <button type="button" data-abrir-modal="detalle-tallas-camisa" title="Ver detalle en tabla"
                        class="flex shrink-0 cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
                    <?= icono('tabla', 'h-3.5 w-3.5') ?>
                    Ver tabla
                </button>
                <?php endif; ?>
            </div>
            <?php if ($tallasCamisa === []): ?>
            <p class="text-sm text-slate-500">Todavía no hay alumnos ni personal registrados.</p>
            <?php else: ?>
            <div class="h-64"><canvas id="grafica-tallas-camisa"></canvas></div>
            <p class="mt-2 text-xs text-slate-400">Referencia para el pedido al proveedor — incluye alumnos y personal.</p>
            <?php endif; ?>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm lg:col-span-2">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <?= icono('grafica', 'h-4 w-4 text-slate-400') ?>
                    Asistencia al plantel vs. asistencia al evento asignado
                </h2>
                <button type="button" data-abrir-modal="detalle-ausentismo" title="Ver detalle en tabla"
                        class="flex shrink-0 cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
                    <?= icono('tabla', 'h-3.5 w-3.5') ?>
                    Ver tabla
                </button>
            </div>
            <div class="h-64"><canvas id="grafica-ausentismo"></canvas></div>
            <p class="mt-2 text-xs text-slate-400">La diferencia son alumnos que entraron al plantel pero no se presentaron a su ponencia/taller asignado.</p>
        </section>

    </div>

    <!-- Interruptor de inscripciones: la bandera sistema.liberar_inscripciones
         que decide si el alumnado puede identificarse e inscribirse en
         app/inscripciones. Va aquí y no en una página propia porque es un solo
         valor y el staff necesita verlo de un vistazo al entrar, junto al resto
         del estado del evento. -->
    <section class="mt-6 rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-2 flex items-center gap-2 text-base font-semibold">
            <?= icono('lista', 'h-4 w-4 text-slate-400') ?>
            Inscripciones
        </h2>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 text-sm text-slate-600">
                <p class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $inscripcionesAbiertas ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                        <span class="h-1.5 w-1.5 rounded-full <?= $inscripcionesAbiertas ? 'bg-emerald-500' : 'bg-slate-500' ?>"></span>
                        <?= $inscripcionesAbiertas ? 'Abiertas' : 'Cerradas' ?>
                    </span>
                </p>
                <p class="mt-2">
                    <?php if ($inscripcionesAbiertas): ?>
                        El alumnado puede identificarse en <span class="font-mono text-xs">inscripciones/public/index.php</span> y reservar lugar en ponencias, talleres y competiciones.
                    <?php else: ?>
                        El alumnado puede consultar el catálogo, pero no puede identificarse ni reservar lugar. Las inscripciones que ya existen no se tocan.
                    <?php endif; ?>
                </p>
            </div>
            <form action="<?= BASE_URL ?>/admin/includes/guardar-inscripciones.php" method="post" class="shrink-0">
                <input type="hidden" name="liberar" value="<?= $inscripcionesAbiertas ? '0' : '1' ?>">
                <button type="submit"
                        class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-semibold text-white <?= $inscripcionesAbiertas ? 'bg-slate-700 hover:bg-slate-800' : 'bg-emerald-600 hover:bg-emerald-700' ?>">
                    <?= icono($inscripcionesAbiertas ? 'candado' : 'verificado', 'h-4 w-4 shrink-0') ?>
                    <?= $inscripcionesAbiertas ? 'Cerrar inscripciones' : 'Abrir inscripciones' ?>
                </button>
            </form>
        </div>
    </section>

    <!-- Zona de peligro: reseteo total del padrón. Vive en el dashboard y no
         en la sección de Alumnos porque no es parte del CRUD del día a día —
         es la acción de "dejar el sistema en cero" antes de abrir el registro
         de una nueva edición, y conviene tenerla lejos de los botones que el
         staff usa a diario. -->
    <section class="mt-6 rounded-xl border-2 border-red-200 bg-white p-5 shadow-sm">
        <h2 class="mb-2 flex items-center gap-2 text-base font-semibold text-red-700">
            <?= icono('alerta', 'h-4 w-4 shrink-0') ?>
            Zona de peligro
        </h2>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 text-sm text-slate-600">
                <p class="font-medium text-slate-900">Resetear el padrón de alumnos</p>
                <p class="mt-1">
                    Elimina los <strong><?= number_format($totalAlumnos) ?></strong> alumnos registrados y todo lo que cuelga de ellos
                    (inscripciones, asistencias generales, equipos e integrantes), más los archivos físicos de
                    <strong>fotos y credenciales</strong>. El catálogo de eventos y competiciones se conserva; solo se les
                    devuelve el cupo disponible.
                </p>
                <p class="mt-1 text-xs text-red-600">Es irreversible y no hay respaldo dentro de la app.</p>
            </div>
            <button type="button" data-abrir-modal="confirmar-reset-alumnos"
                    class="flex shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                <?= icono('eliminar', 'h-4 w-4 shrink-0') ?>
                Resetear padrón de alumnos
            </button>
        </div>
    </section>

    <!-- Doble candado del reseteo: la contraseña de administrador otra vez
         (aunque la sesión ya esté autorizada, por si el panel quedó abierto)
         y la palabra RESETEAR escrita a mano, para que no baste con hacer
         clic de más. Ambas se validan del lado del servidor, en
         includes/resetear-alumnos.php: el formulario va con novalidate como
         todos los del proyecto, así que el required de aquí no bloquea nada
         por sí solo. -->
    <dialog id="confirmar-reset-alumnos" class="m-auto w-[90%] max-w-md rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-semibold text-red-700">
                    <?= icono('alerta', 'h-4 w-4 shrink-0') ?>
                    Resetear el padrón de alumnos
                </h3>
                <button type="button" data-cerrar-modal="confirmar-reset-alumnos" title="Cerrar" class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <?= icono('cerrar', 'h-4 w-4') ?>
                </button>
            </div>

            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">Se borrará de forma permanente:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    <li><?= number_format($totalAlumnos) ?> alumnos del padrón</li>
                    <li>Sus inscripciones a ponencias y talleres</li>
                    <li>Su asistencia general de los 3 días</li>
                    <li>Todos los equipos e integrantes (incluidos padres y madres)</li>
                    <li>Los archivos de fotos y de credenciales generadas</li>
                </ul>
            </div>

            <form action="<?= BASE_URL ?>/admin/includes/resetear-alumnos.php" method="post" novalidate class="flex flex-col gap-4">
                <div>
                    <label for="reset_clave" class="mb-1 block text-sm font-medium">Contraseña de administrador</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('candado') ?></span>
                        <input type="password" id="reset_clave" name="clave" required autocomplete="off"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-red-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label for="reset_confirmacion" class="mb-1 block text-sm font-medium">Escribe <span class="font-mono font-bold">RESETEAR</span> para confirmar</label>
                    <input type="text" id="reset_confirmacion" name="confirmacion" required autocomplete="off" spellcheck="false"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-base uppercase focus:border-red-500 focus:outline-none">
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-cerrar-modal="confirmar-reset-alumnos"
                            class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                        <?= icono('eliminar', 'h-4 w-4 shrink-0') ?>
                        Sí, borrar todo
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Modales de detalle: la tabla completa (ordenada/clasificada) detrás
         de cada gráfica, para cuando el staff necesita los números exactos
         en vez de leerlos de las barras. -->

    <dialog id="detalle-grado-grupo" class="m-auto w-[90%] max-w-md rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">Alumnos por grado y grupo</h3>
                <button type="button" data-cerrar-modal="detalle-grado-grupo" title="Cerrar" class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <?= icono('cerrar', 'h-4 w-4') ?>
                </button>
            </div>
            <div class="max-h-96 overflow-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2">Grado</th>
                            <th class="px-3 py-2 text-center">Grupo</th>
                            <th class="px-3 py-2 text-center">Alumnos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filasGradoGrupo as $fila): ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2 font-medium"><?= htmlspecialchars($fila['grado'], ENT_QUOTES, 'UTF-8') ?>°</td>
                            <td class="px-3 py-2 text-center text-slate-500"><?= htmlspecialchars($fila['grupo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center font-medium"><?= number_format($fila['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </dialog>

    <dialog id="detalle-cupo-eventos" class="m-auto w-[90%] max-w-2xl rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">Cupo ocupado por evento</h3>
                <button type="button" data-cerrar-modal="detalle-cupo-eventos" title="Cerrar" class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <?= icono('cerrar', 'h-4 w-4') ?>
                </button>
            </div>
            <p class="mb-3 text-xs text-slate-500">Ordenado de más lleno a menos lleno.</p>
            <div class="max-h-96 overflow-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2">Evento</th>
                            <th class="px-3 py-2">Espacio</th>
                            <th class="px-3 py-2 text-center">Cupo</th>
                            <th class="px-3 py-2 text-center">% ocupado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cupoEventosOrdenado as $evento): ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2 font-medium"><?= htmlspecialchars($evento['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars($evento['espacio'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center text-slate-500"><?= $evento['ocupados'] ?>/<?= $evento['cupo_maximo'] ?></td>
                            <td class="px-3 py-2 text-center font-medium <?= $evento['porcentaje'] >= 100 ? 'text-red-600' : ($evento['porcentaje'] >= 80 ? 'text-amber-600' : 'text-emerald-600') ?>"><?= $evento['porcentaje'] ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </dialog>

    <dialog id="detalle-equipos-competicion" class="m-auto w-[90%] max-w-lg rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">Equipos por competición</h3>
                <button type="button" data-cerrar-modal="detalle-equipos-competicion" title="Cerrar" class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <?= icono('cerrar', 'h-4 w-4') ?>
                </button>
            </div>
            <p class="mb-3 text-xs text-slate-500">Ordenado de más a menos equipos inscritos.</p>
            <div class="max-h-96 overflow-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2">Competición</th>
                            <th class="px-3 py-2 text-center">Equipos</th>
                            <th class="px-3 py-2 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equiposCompeticionOrdenado as $c):
                            $estadoTexto = '—';
                            $estadoClase = 'text-slate-400';
                            if ($c['max_equipos'] !== null) {
                                $cercaUmbral = max(0, $c['max_equipos'] - 2);
                                if ($c['total'] >= $c['max_equipos']) { $estadoTexto = 'Límite alcanzado'; $estadoClase = 'text-red-600 font-medium'; }
                                elseif ($c['total'] >= $cercaUmbral) { $estadoTexto = 'Cerca del límite'; $estadoClase = 'text-amber-600 font-medium'; }
                                else { $estadoTexto = 'Bajo el límite (' . $c['max_equipos'] . ')'; $estadoClase = 'text-emerald-600'; }
                            }
                        ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2 font-medium"><?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center text-slate-500"><?= $c['total'] ?><?= $c['max_equipos'] !== null ? '/' . $c['max_equipos'] : '' ?></td>
                            <td class="px-3 py-2 text-center <?= $estadoClase ?>"><?= $estadoTexto ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </dialog>

    <dialog id="detalle-tallas-camisa" class="m-auto w-[90%] max-w-3xl rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">Tallas de camisa solicitadas</h3>
                <button type="button" data-cerrar-modal="detalle-tallas-camisa" title="Cerrar" class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <?= icono('cerrar', 'h-4 w-4') ?>
                </button>
            </div>

            <div class="mb-2 flex items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-slate-700">Resumen por talla</h4>
                <div class="flex shrink-0 items-center gap-2">
                    <a href="<?= BASE_URL ?>/admin/includes/exportar-tallas-camisa.php?vista=resumen&amp;formato=xlsx"
                       class="flex cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
                        <?= icono('descargar', 'h-3.5 w-3.5') ?> Excel
                    </a>
                    <a href="<?= BASE_URL ?>/admin/includes/exportar-tallas-camisa.php?vista=resumen&amp;formato=pdf"
                       class="flex cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
                        <?= icono('descargar', 'h-3.5 w-3.5') ?> PDF
                    </a>
                </div>
            </div>
            <div class="max-h-72 overflow-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2 text-center">Talla</th>
                            <?php foreach ($columnasCamisa as $columna): ?>
                            <th class="px-3 py-2 text-center <?= $columna === CAMISA_COLUMNA_PERSONAL ? 'border-l border-slate-300' : '' ?>"><?= htmlspecialchars($columna, ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                            <th class="px-3 py-2 text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tallasCamisa as $talla): ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2 text-center font-medium"><?= htmlspecialchars($talla, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php foreach ($columnasCamisa as $columna): ?>
                            <td class="px-3 py-2 text-center text-slate-500 <?= $columna === CAMISA_COLUMNA_PERSONAL ? 'border-l border-slate-300' : '' ?>"><?= number_format($tallasCamisaPivote[$talla][$columna] ?? 0) ?></td>
                            <?php endforeach; ?>
                            <td class="px-3 py-2 text-center font-medium"><?= number_format($tallasCamisaTotales[$talla]) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mb-2 mt-8 flex items-center justify-between gap-2 pt-2">
                <h4 class="text-sm font-semibold text-slate-700">Detalle por alumno</h4>
            </div>

            <!-- Selector de grupos de la descarga. Es un form GET sin nada de
                 JavaScript: los dos botones envían el mismo formulario y solo
                 cambian el `formato`. Sin ninguna casilla marcada se descarga
                 el padrón completo como lista corrida (lo de siempre); con
                 grupos marcados, cada uno sale en su propia pestaña de Excel o
                 en su propia página del PDF, para poder entregarle su hoja a
                 cada maestro. -->
            <form action="<?= BASE_URL ?>/admin/includes/exportar-tallas-camisa.php" method="get"
                  class="mb-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <input type="hidden" name="vista" value="detalle">

                <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Grupos a descargar</p>

                <!-- Rejilla en vez de un wrap suelto: los grupos quedan
                     alineados en columnas y cada uno es un objetivo de clic
                     grande, que en tablet (el uso real del panel) importa. -->
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                    <?php foreach ($gruposCamisaDescarga as $codigoGrupo => $etiquetaGrupo): ?>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 hover:border-slate-300 hover:bg-slate-50 has-[:checked]:border-slate-900 has-[:checked]:font-semibold has-[:checked]:text-slate-900">
                        <input type="checkbox" name="grupos[]" value="<?= htmlspecialchars($codigoGrupo, ENT_QUOTES, 'UTF-8') ?>"
                               class="h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 accent-slate-900">
                        <?= htmlspecialchars($etiquetaGrupo, ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-relaxed text-slate-500">
                        Sin marcar nada se descarga la <span class="font-medium text-slate-700">lista completa</span> de corrido. Marcando grupos, cada uno va en su propia pestaña o página.
                    </p>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="submit" name="formato" value="xlsx"
                                class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                            <?= icono('descargar', 'h-4 w-4 shrink-0') ?> Excel
                        </button>
                        <button type="submit" name="formato" value="pdf"
                                class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                            <?= icono('descargar', 'h-4 w-4 shrink-0') ?> PDF
                        </button>
                    </div>
                </div>
            </form>
            <div class="max-h-72 overflow-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2 text-center">#</th>
                            <th class="px-3 py-2">No. cuenta</th>
                            <th class="px-3 py-2">Nombre completo</th>
                            <th class="px-3 py-2 text-center">Corte</th>
                            <th class="px-3 py-2 text-center">Talla</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumnosCamisaPorGrupo as $grupoEtiqueta => $alumnosDelGrupo): ?>
                        <tr class="border-b border-slate-200 bg-slate-100">
                            <td colspan="5" class="px-3 py-1.5 text-xs font-semibold text-slate-600"><?= htmlspecialchars($grupoEtiqueta, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php foreach ($alumnosDelGrupo as $indice => $alumnoFila): ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2 text-center text-slate-500"><?= $indice + 1 ?></td>
                            <td class="px-3 py-2 font-mono text-xs"><?= htmlspecialchars($alumnoFila['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($alumnoFila['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center"><?= htmlspecialchars($alumnoFila['camisa_corte'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center"><?= htmlspecialchars($alumnoFila['camisa_talla'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mb-2 mt-8 flex items-center justify-between gap-2 pt-5">
                <h4 class="text-sm font-semibold text-slate-700">Detalle por trabajador</h4>
                <div class="flex shrink-0 items-center gap-2">
                    <a href="<?= BASE_URL ?>/admin/includes/exportar-tallas-camisa.php?vista=detalle_personal&amp;formato=xlsx"
                       class="flex cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
                        <?= icono('descargar', 'h-3.5 w-3.5') ?> Excel
                    </a>
                    <a href="<?= BASE_URL ?>/admin/includes/exportar-tallas-camisa.php?vista=detalle_personal&amp;formato=pdf"
                       class="flex cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
                        <?= icono('descargar', 'h-3.5 w-3.5') ?> PDF
                    </a>
                </div>
            </div>
            <?php if ($personalCamisaPorTipo === []): ?>
            <p class="rounded-lg border border-slate-200 px-3 py-4 text-center text-sm text-slate-500">Todavía no hay personal registrado.</p>
            <?php else: ?>
            <div class="max-h-72 overflow-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2 text-center">#</th>
                            <th class="px-3 py-2">No. trabajador</th>
                            <th class="px-3 py-2">Nombre completo</th>
                            <th class="px-3 py-2 text-center">Corte</th>
                            <th class="px-3 py-2 text-center">Talla</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personalCamisaPorTipo as $tipoEtiqueta => $personalDelTipo): ?>
                        <tr class="border-b border-slate-200 bg-slate-100">
                            <td colspan="5" class="px-3 py-1.5 text-xs font-semibold text-slate-600"><?= htmlspecialchars($tipoEtiqueta, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php foreach ($personalDelTipo as $indice => $trabajadorFila): ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2 text-center text-slate-500"><?= $indice + 1 ?></td>
                            <td class="px-3 py-2 font-mono text-xs"><?= htmlspecialchars($trabajadorFila['numero_trabajador'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($trabajadorFila['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center"><?= htmlspecialchars($trabajadorFila['camisa_corte'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center"><?= htmlspecialchars($trabajadorFila['camisa_talla'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </dialog>

    <dialog id="detalle-ausentismo" class="m-auto w-[90%] max-w-lg rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">Asistencia al plantel vs. al evento asignado</h3>
                <button type="button" data-cerrar-modal="detalle-ausentismo" title="Cerrar" class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <?= icono('cerrar', 'h-4 w-4') ?>
                </button>
            </div>
            <div class="max-h-96 overflow-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2">Día</th>
                            <th class="px-3 py-2 text-center">Entraron al plantel</th>
                            <th class="px-3 py-2 text-center">Entraron a su evento</th>
                            <th class="px-3 py-2 text-center">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ausentismoPorDia as $fila): $diferencia = $fila['entraron_plantel'] - $fila['entraron_evento']; ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-3 py-2 font-medium"><?= htmlspecialchars($fila['dia'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center text-slate-500"><?= number_format($fila['entraron_plantel']) ?></td>
                            <td class="px-3 py-2 text-center text-slate-500"><?= number_format($fila['entraron_evento']) ?></td>
                            <td class="px-3 py-2 text-center font-medium <?= $diferencia > 0 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= number_format($diferencia) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </dialog>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var COLOR_TEXTO = '#475569';
        var COLOR_REJILLA = '#e2e8f0';
        Chart.defaults.color = COLOR_TEXTO;
        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

        <?php if ($porGradoGrupo !== []): ?>
        new Chart(document.getElementById('grafica-grado-grupo'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($g) => $g . '°', ['1', '3', '5'])) ?>,
                datasets: [
                    <?php foreach (['A', 'B', 'C'] as $indiceGrupo => $gr): ?>
                    {
                        label: 'Grupo <?= $gr ?>',
                        data: <?= json_encode(array_map(fn($g) => $matrizGradoGrupo[$g][$gr], ['1', '3', '5'])) ?>,
                        backgroundColor: ['#0ea5e9', '#6366f1', '#f59e0b'][<?= $indiceGrupo ?>],
                        borderRadius: 4
                    }<?= $indiceGrupo < 2 ? ',' : '' ?>
                    <?php endforeach; ?>
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: COLOR_REJILLA } } },
                plugins: { legend: { position: 'bottom' } }
            }
        });
        <?php endif; ?>

        <?php if ($cupoEventosOrdenado !== []): ?>
        new Chart(document.getElementById('grafica-cupo-eventos'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($e) => $e['nombre'], $cupoEventosOrdenado)) ?>,
                datasets: [{
                    label: '% de cupo ocupado',
                    data: <?= json_encode(array_map(fn($e) => $e['porcentaje'], $cupoEventosOrdenado)) ?>,
                    ocupados: <?= json_encode(array_map(fn($e) => $e['ocupados'], $cupoEventosOrdenado)) ?>,
                    cupoMaximo: <?= json_encode(array_map(fn($e) => $e['cupo_maximo'], $cupoEventosOrdenado)) ?>,
                    backgroundColor: <?= json_encode(array_map(
                        fn($e) => $e['porcentaje'] >= 100 ? '#ef4444' : ($e['porcentaje'] >= 80 ? '#f59e0b' : '#10b981'),
                        $cupoEventosOrdenado
                    )) ?>,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                scales: { x: { beginAtZero: true, max: 100, grid: { color: COLOR_REJILLA } }, y: { grid: { display: false } } },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (contexto) {
                        var d = contexto.dataset;
                        return d.ocupados[contexto.dataIndex] + '/' + d.cupoMaximo[contexto.dataIndex] + ' (' + contexto.parsed.x + '%)';
                    } } }
                }
            }
        });
        <?php endif; ?>

        <?php if ($equiposCompeticionDatos !== []): ?>
        new Chart(document.getElementById('grafica-equipos-competicion'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($c) => $c['nombre'], $equiposCompeticionDatos)) ?>,
                datasets: [{
                    label: 'Equipos inscritos',
                    data: <?= json_encode(array_map(fn($c) => $c['total'], $equiposCompeticionDatos)) ?>,
                    backgroundColor: <?= json_encode(array_map(function ($c) {
                        if ($c['max_equipos'] === null) return '#6366f1';
                        $cercaUmbral = max(0, $c['max_equipos'] - 2);
                        return $c['total'] >= $c['max_equipos'] ? '#ef4444' : ($c['total'] >= $cercaUmbral ? '#f59e0b' : '#10b981');
                    }, $equiposCompeticionDatos)) ?>,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: COLOR_REJILLA } } },
                plugins: { legend: { display: false } }
            },
            plugins: [{
                id: 'lineaLimiteConocimiento',
                afterDraw: function (chart) {
                    var limite = <?= $conocimientoActual !== null && $conocimientoActual['max_equipos'] !== null ? (int) $conocimientoActual['max_equipos'] : 'null' ?>;
                    if (limite === null) { return; }
                    var y = chart.scales.y.getPixelForValue(limite);
                    if (y < chart.chartArea.top || y > chart.chartArea.bottom) { return; }
                    var ctx = chart.ctx;
                    ctx.save();
                    ctx.strokeStyle = '#ef4444';
                    ctx.setLineDash([5, 4]);
                    ctx.lineWidth = 1.5;
                    ctx.beginPath();
                    ctx.moveTo(chart.chartArea.left, y);
                    ctx.lineTo(chart.chartArea.right, y);
                    ctx.stroke();
                    ctx.restore();
                }
            }]
        });
        <?php endif; ?>

        <?php if ($tallasCamisa !== []): ?>
        // Barras apiladas: la altura total de cada talla es lo que hay que
        // encargarle al proveedor (alumnos + personal), y cada segmento dice
        // cuánto de eso va a cada población.
        new Chart(document.getElementById('grafica-tallas-camisa'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($tallasCamisa) ?>,
                datasets: [
                    {
                        label: 'Alumnos',
                        data: <?= json_encode($serieCamisaAlumnos) ?>,
                        backgroundColor: '#0ea5e9',
                        borderRadius: 4
                    },
                    {
                        label: 'Personal',
                        data: <?= json_encode($serieCamisaPersonal) ?>,
                        backgroundColor: '#6366f1',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: COLOR_REJILLA } }
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { footer: function (elementos) {
                        var total = elementos.reduce(function (suma, e) { return suma + e.parsed.y; }, 0);
                        return 'Total: ' + total;
                    } } }
                }
            }
        });
        <?php endif; ?>

        new Chart(document.getElementById('grafica-ausentismo'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($a) => $a['dia'], $ausentismoPorDia)) ?>,
                datasets: [
                    {
                        label: 'Entraron al plantel',
                        data: <?= json_encode(array_map(fn($a) => $a['entraron_plantel'], $ausentismoPorDia)) ?>,
                        backgroundColor: '#6366f1',
                        borderRadius: 4
                    },
                    {
                        label: 'Entraron a su evento asignado',
                        data: <?= json_encode(array_map(fn($a) => $a['entraron_evento'], $ausentismoPorDia)) ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: COLOR_REJILLA } } },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    });
    </script>

    <?php
    layoutAdminCerrar();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de administración — Semana Acádemica, Cultural y Deportiva B23</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="<?= BASE_URL ?>/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p>&nbsp;</p>
        <p class="text-sm text-slate-600"><strong>Panel de administración: </strong>Solo para el staff organizador — reportes, estadísticas y gestión de alumnos, eventos y competiciones.</p>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <?php if ($mensajeError): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= icono('alerta', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <?php if (!$claveYaRegistrada): ?>

        <div class="mb-4 flex items-start gap-2 rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <?= icono('verificado', 'mt-0.5 h-4 w-4 shrink-0') ?>
            <span>Todavía no hay contraseña de administrador registrada. Como quien haga esto primero será el encargado del panel, defínela aquí — queda guardada de forma segura (hasheada), nadie puede leerla después.</span>
        </div>

        <form action="<?= BASE_URL ?>/admin/includes/registrar-clave.php" method="post" novalidate class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">
            <div>
                <label for="clave" class="mb-1 block text-sm font-medium">Nueva contraseña de administrador</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('candado') ?></span>
                    <input type="password" id="clave" name="clave" required minlength="8" autofocus
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">Al menos 8 caracteres. Compártela solo con el staff organizador.</p>
            </div>
            <div>
                <label for="clave_confirmar" class="mb-1 block text-sm font-medium">Confirmar contraseña</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('candado') ?></span>
                    <input type="password" id="clave_confirmar" name="clave_confirmar" required minlength="8"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
            </div>
            <button type="submit"
                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
                <?= icono('verificado', 'h-4 w-4 shrink-0') ?>
                Registrar contraseña
            </button>
        </form>

    <?php else: ?>

        <form action="<?= BASE_URL ?>/admin/includes/verificar-clave.php" method="post" novalidate class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">
            <div>
                <label for="clave" class="mb-1 block text-sm font-medium">Contraseña de administrador</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('candado') ?></span>
                    <input type="password" id="clave" name="clave" required autofocus
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">Pídela al encargado del panel si no la tienes.</p>
            </div>
            <button type="submit"
                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
                <?= icono('verificado', 'h-4 w-4 shrink-0') ?>
                Entrar
            </button>
        </form>

    <?php endif; ?>

</div>
</body>
</html>
