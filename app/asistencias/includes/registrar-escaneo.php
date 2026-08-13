<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAsistencias();

header('Content-Type: application/json; charset=utf-8');

function responder(int $codigoHttp, array $cuerpo): never
{
    http_response_code($codigoHttp);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'error' => 'metodo_invalido']);
}

// Requiere Content-Type: application/json — un <form> normal no puede
// mandarlo sin preflight CORS, lo que ya frena un CSRF trivial contra este
// endpoint (además de que ya está detrás de sesión + HTTP Basic Auth).
if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    responder(400, ['ok' => false, 'error' => 'peticion_invalida']);
}

if (!turnoListo()) {
    responder(401, ['ok' => false, 'error' => 'no_autorizado']);
}

$cuerpo = json_decode((string) file_get_contents('php://input'), true);
$codigo = is_array($cuerpo) ? trim((string) ($cuerpo['codigo'] ?? '')) : '';

if ($codigo === '') {
    responder(400, ['ok' => false, 'error' => 'codigo_vacio']);
}

$evento = (string) $_SESSION['evento'];
$operador = (string) $_SESSION['operador'];
$puntoControl = (string) $_SESSION['punto_control'];
$ahora = date('Y-m-d H:i:s');
$horaVisible = date('H:i');

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// --- Día Académico / Día Cultural: QR = numero_cuenta de alumno -----------
// Asistencia GENERAL del día (¿ya entró/salió del plantel?), en
// asistencias_generales. No confundir con la asistencia a un evento
// específico (ponencia/taller/concurso), que vive en inscripciones y se
// resuelve aparte (ver app/inscripciones, aún no construido).

if ($evento === 'academico' || $evento === 'cultural') {
    $numeroCuenta = strtoupper($codigo);

    if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
        responder(422, ['ok' => false, 'error' => 'codigo_invalido']);
    }

    $consulta = $pdo->prepare(
        'SELECT id, nombre_completo, grado, grupo, foto_path FROM alumnos WHERE numero_cuenta = :cuenta'
    );
    $consulta->execute(['cuenta' => $numeroCuenta]);
    $alumno = $consulta->fetch();

    if ($alumno === false) {
        responder(404, ['ok' => false, 'error' => 'no_encontrado']);
    }

    try {
        $insertar = $pdo->prepare(
            'INSERT INTO asistencias_generales
                (id_alumno, dia, hora_entrada, punto_control_entrada, escaneado_por_entrada)
             VALUES
                (:alumno, :dia, :ahora, :punto, :operador)'
        );
        $insertar->execute([
            'alumno' => $alumno['id'],
            'dia' => $evento,
            'ahora' => $ahora,
            'punto' => $puntoControl,
            'operador' => $operador,
        ]);
        $tipoResultado = 'entrada';
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            error_log('Error registrando asistencia (alumno ' . $numeroCuenta . '): ' . $e->getMessage());
            responder(500, ['ok' => false, 'error' => 'error_servidor']);
        }
        $actualizar = $pdo->prepare(
            'UPDATE asistencias_generales
                SET hora_salida = :ahora, punto_control_salida = :punto, escaneado_por_salida = :operador
             WHERE id_alumno = :alumno AND dia = :dia'
        );
        $actualizar->execute([
            'ahora' => $ahora,
            'punto' => $puntoControl,
            'operador' => $operador,
            'alumno' => $alumno['id'],
            'dia' => $evento,
        ]);
        $tipoResultado = 'salida';
    }

    $respuesta = [
        'ok' => true,
        'tipo_resultado' => $tipoResultado,
        'hora' => $horaVisible,
        'persona' => [
            'nombre' => $alumno['nombre_completo'],
            'detalle' => $alumno['grado'] . '° ' . $alumno['grupo'],
            'foto_url' => '/registro/public/' . $alumno['foto_path'],
        ],
        'mensaje' => $tipoResultado === 'entrada' ? 'Entrada registrada' : 'Salida registrada (actualizada)',
    ];

    // Asignación de ponencia/taller/concurso: solo Día Académico, solo en la
    // entrada general. Ya no hay un número fijo de sesiones que cubrir (el
    // catálogo de eventos no valida cruces de horario) — solo se distingue
    // "ya tiene algo asignado" de "todavía nada".
    if ($evento === 'academico' && $tipoResultado === 'entrada') {
        $consultaInscripciones = $pdo->prepare(
            'SELECT e.nombre, e.espacio
             FROM inscripciones i JOIN eventos e ON e.id = i.id_evento
             WHERE i.id_alumno = :alumno'
        );
        $consultaInscripciones->execute(['alumno' => $alumno['id']]);
        $asignaciones = $consultaInscripciones->fetchAll();

        if ($asignaciones !== []) {
            $respuesta['asignaciones'] = $asignaciones;
        } else {
            $respuesta['redirect_url'] = '/inscripciones/public/index.php?'
                . http_build_query(['numero_cuenta' => $numeroCuenta]);
        }
    }

    responder(200, $respuesta);
}

// --- Día Deportivo: QR = codigo_participante de integrantes ---------------
// integrantes ya trae su propio control de entrada/salida (alumnos, padres
// y madres por igual) — la fila existe desde que se inscribió el equipo
// (hora_entrada nace NULL), así que aquí siempre es UPDATE, nunca INSERT
// (a diferencia de asistencias_generales, donde la fila no existe hasta el
// primer escaneo). hora_entrada NULL -> este escaneo es la entrada;
// hora_entrada ya tiene valor -> este escaneo es la salida.

if ($evento === 'deportivo') {
    $codigoParticipante = strtoupper($codigo);

    if (!preg_match('/^[A-Z0-9\-]{4,20}$/', $codigoParticipante)) {
        responder(422, ['ok' => false, 'error' => 'codigo_invalido']);
    }

    $consulta = $pdo->prepare(
        'SELECT i.id_equipo, i.id_alumno, i.tipo, i.nombre, i.hora_entrada,
                e.nombre AS nombre_equipo, e.tipo AS deporte
         FROM integrantes i JOIN equipos e ON e.id = i.id_equipo
         WHERE i.codigo_participante = :codigo'
    );
    $consulta->execute(['codigo' => $codigoParticipante]);
    $integrante = $consulta->fetch();

    if ($integrante === false) {
        responder(404, ['ok' => false, 'error' => 'no_encontrado']);
    }

    $deportes = [
        'futbol_rapido' => 'Fútbol Rápido',
        'voleibol' => 'Voleibol',
        'quemados' => 'Quemados',
    ];
    $detalleEquipo = $integrante['nombre_equipo'] . ' · ' . ($deportes[$integrante['deporte']] ?? $integrante['deporte']);

    if ($integrante['hora_entrada'] === null) {
        $actualizar = $pdo->prepare(
            'UPDATE integrantes
                SET hora_entrada = :ahora, punto_control_entrada = :punto, escaneado_por_entrada = :operador
             WHERE id_equipo = :equipo AND id_alumno = :alumno AND tipo = :tipo'
        );
        $tipoResultado = 'entrada';
    } else {
        $actualizar = $pdo->prepare(
            'UPDATE integrantes
                SET hora_salida = :ahora, punto_control_salida = :punto, escaneado_por_salida = :operador
             WHERE id_equipo = :equipo AND id_alumno = :alumno AND tipo = :tipo'
        );
        $tipoResultado = 'salida';
    }
    $actualizar->execute([
        'ahora' => $ahora,
        'punto' => $puntoControl,
        'operador' => $operador,
        'equipo' => $integrante['id_equipo'],
        'alumno' => $integrante['id_alumno'],
        'tipo' => $integrante['tipo'],
    ]);

    responder(200, [
        'ok' => true,
        'tipo_resultado' => $tipoResultado,
        'hora' => $horaVisible,
        'persona' => ['nombre' => $integrante['nombre'], 'detalle' => $detalleEquipo, 'foto_url' => null],
        'mensaje' => $tipoResultado === 'entrada' ? 'Entrada registrada' : 'Salida registrada (actualizada)',
    ]);
}

responder(400, ['ok' => false, 'error' => 'evento_invalido']);
