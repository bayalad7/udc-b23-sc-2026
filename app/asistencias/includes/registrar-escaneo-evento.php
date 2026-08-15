<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAsistencias();

header('Content-Type: application/json; charset=utf-8');

function responderEvento(int $codigoHttp, array $cuerpo): never
{
    http_response_code($codigoHttp);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderEvento(405, ['ok' => false, 'error' => 'metodo_invalido']);
}

// Mismo criterio que registrar-escaneo.php: exigir JSON frena un CSRF trivial
// contra este endpoint (además de que ya está detrás de sesión + contraseña).
if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    responderEvento(400, ['ok' => false, 'error' => 'peticion_invalida']);
}

if (!turnoEventoListo()) {
    responderEvento(401, ['ok' => false, 'error' => 'no_autorizado']);
}

$cuerpo = json_decode((string) file_get_contents('php://input'), true);
$codigo = is_array($cuerpo) ? trim((string) ($cuerpo['codigo'] ?? '')) : '';

if ($codigo === '') {
    responderEvento(400, ['ok' => false, 'error' => 'codigo_vacio']);
}

$numeroCuenta = strtoupper($codigo);
if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
    responderEvento(422, ['ok' => false, 'error' => 'codigo_invalido']);
}

$idEvento = (int) $_SESSION['id_evento'];
$operador = (string) $_SESSION['operador_evento'];
$puntoControl = (string) $_SESSION['punto_control_evento'];
$ahora = date('Y-m-d H:i:s');
$horaVisible = date('H:i');

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consultaAlumno = $pdo->prepare(
    'SELECT id, nombre_completo, grado, grupo, foto_path FROM alumnos WHERE numero_cuenta = :cuenta'
);
$consultaAlumno->execute(['cuenta' => $numeroCuenta]);
$alumno = $consultaAlumno->fetch();

if ($alumno === false) {
    responderEvento(404, ['ok' => false, 'error' => 'no_encontrado']);
}

$persona = [
    'nombre' => $alumno['nombre_completo'],
    'detalle' => $alumno['grado'] . '° ' . $alumno['grupo'],
    'foto_url' => '/registro/public/' . $alumno['foto_path'],
];

// Alcance de este módulo: SOLO actualiza hora_entrada/hora_salida sobre una
// inscripción que YA EXISTE (creada de antemano por app/admin). Nunca
// INSERT ni DELETE en inscripciones, y eventos solo se lee arriba (para el
// encabezado de escaneo-evento.php) — aquí ni siquiera se vuelve a tocar.
$pdo->beginTransaction();

$consultaInscripcion = $pdo->prepare(
    'SELECT hora_entrada FROM inscripciones
     WHERE id_evento = :evento AND id_alumno = :alumno
     FOR UPDATE'
);
$consultaInscripcion->execute(['evento' => $idEvento, 'alumno' => $alumno['id']]);
$inscripcion = $consultaInscripcion->fetch();

if ($inscripcion === false) {
    $pdo->rollBack();
    responderEvento(200, [
        'ok' => true,
        'tipo_resultado' => 'sin_inscripcion',
        'hora' => $horaVisible,
        'persona' => $persona,
        'mensaje' => 'Sin inscripción registrada para este evento — remite al alumno con el administrador.',
    ]);
}

if ($inscripcion['hora_entrada'] === null) {
    $actualizar = $pdo->prepare(
        'UPDATE inscripciones
            SET hora_entrada = :ahora, punto_control_entrada = :punto, escaneado_por_entrada = :operador
         WHERE id_evento = :evento AND id_alumno = :alumno'
    );
    $tipoResultado = 'entrada';
} else {
    $actualizar = $pdo->prepare(
        'UPDATE inscripciones
            SET hora_salida = :ahora, punto_control_salida = :punto, escaneado_por_salida = :operador
         WHERE id_evento = :evento AND id_alumno = :alumno'
    );
    $tipoResultado = 'salida';
}
$actualizar->execute([
    'ahora' => $ahora,
    'punto' => $puntoControl,
    'operador' => $operador,
    'evento' => $idEvento,
    'alumno' => $alumno['id'],
]);

$pdo->commit();

responderEvento(200, [
    'ok' => true,
    'tipo_resultado' => $tipoResultado,
    'hora' => $horaVisible,
    'persona' => $persona,
    'mensaje' => $tipoResultado === 'entrada' ? 'Entrada registrada' : 'Salida registrada (actualizada)',
]);
