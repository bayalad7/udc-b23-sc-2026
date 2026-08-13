<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionInscripciones();

// Endpoint AJAX (JSON) para el "constructor de equipos" (búsqueda exacta por
// número de cuenta) que usan los formularios de academico.php, cultural.php
// y deportivo.php — ver assets/js/inscripciones.js. Solo lectura: no
// modifica nada, únicamente resuelve el número de cuenta contra el padrón y,
// según el contexto, corre las mismas validaciones que el backend de guardado
// (crear-equipo-*.php) volverá a aplicar al enviar el formulario — aquí es
// solo para dar retroalimentación inmediata mientras se arma el equipo.

header('Content-Type: application/json; charset=utf-8');

function responder(array $datos, int $codigoHttp = 200): never
{
    http_response_code($codigoHttp);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (alumnoIdentificadoId() === null) {
    responder(['encontrado' => false, 'motivo' => 'no_identificado'], 401);
}

$numeroCuenta = strtoupper(trim((string) ($_GET['numero_cuenta'] ?? '')));
$contexto = (string) ($_GET['contexto'] ?? '');
$idCompeticionDeportivo = filter_var($_GET['id_competicion'] ?? '', FILTER_VALIDATE_INT);

if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta) || !in_array($contexto, ['conocimiento', 'talentos', 'deportivo'], true)) {
    responder(['encontrado' => false, 'motivo' => 'parametros_invalidos'], 400);
}
if ($contexto === 'deportivo' && $idCompeticionDeportivo === false) {
    responder(['encontrado' => false, 'motivo' => 'parametros_invalidos'], 400);
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consultaAlumno = $pdo->prepare(
    'SELECT id, numero_cuenta, nombre_completo, grado, grupo, foto_path FROM alumnos WHERE numero_cuenta = :cuenta'
);
$consultaAlumno->execute(['cuenta' => $numeroCuenta]);
$alumno = $consultaAlumno->fetch();

if ($alumno === false) {
    responder(['encontrado' => false, 'motivo' => 'no_encontrado']);
}

$idAlumno = (int) $alumno['id'];
$puedeSerAlumno = true;
$motivo = null;

if ($contexto === 'conocimiento') {
    $competicion = $pdo->query(
        "SELECT id, hora_inicio, hora_fin FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso' LIMIT 1"
    )->fetch();

    if ($competicion !== false) {
        $consultaYaEnEquipo = $pdo->prepare(
            'SELECT 1 FROM integrantes it JOIN equipos eq ON eq.id = it.id_equipo
             WHERE eq.id_competicion = :competicion AND it.id_alumno = :alumno LIMIT 1'
        );
        $consultaYaEnEquipo->execute(['competicion' => $competicion['id'], 'alumno' => $idAlumno]);

        if ($consultaYaEnEquipo->fetch() !== false) {
            $puedeSerAlumno = false;
            $motivo = 'ya_en_equipo';
        } else {
            $consultaCruceEventos = $pdo->prepare(
                "SELECT 1 FROM inscripciones i JOIN eventos e ON e.id = i.id_evento
                 WHERE i.id_alumno = :alumno AND e.dia = 'academico'
                   AND e.hora_inicio < :hora_fin AND e.hora_fin > :hora_inicio LIMIT 1"
            );
            $consultaCruceEventos->execute([
                'alumno' => $idAlumno,
                'hora_inicio' => $competicion['hora_inicio'],
                'hora_fin' => $competicion['hora_fin'],
            ]);
            $consultaCruceEquipos = $pdo->prepare(
                "SELECT 1 FROM integrantes it JOIN equipos eq ON eq.id = it.id_equipo
                 JOIN competiciones c ON c.id = eq.id_competicion
                 WHERE it.id_alumno = :alumno AND c.dia = 'academico'
                   AND c.hora_inicio < :hora_fin AND c.hora_fin > :hora_inicio LIMIT 1"
            );
            $consultaCruceEquipos->execute([
                'alumno' => $idAlumno,
                'hora_inicio' => $competicion['hora_inicio'],
                'hora_fin' => $competicion['hora_fin'],
            ]);

            if ($consultaCruceEventos->fetch() !== false || $consultaCruceEquipos->fetch() !== false) {
                $puedeSerAlumno = false;
                $motivo = 'cruce_horario';
            }
        }
    }
} elseif ($contexto === 'deportivo') {
    $consultaYaEnEquipo = $pdo->prepare(
        "SELECT 1 FROM integrantes it JOIN equipos eq ON eq.id = it.id_equipo
         WHERE eq.id_competicion = :competicion AND it.tipo = 'alumno' AND it.id_alumno = :alumno LIMIT 1"
    );
    $consultaYaEnEquipo->execute(['competicion' => $idCompeticionDeportivo, 'alumno' => $idAlumno]);

    if ($consultaYaEnEquipo->fetch() !== false) {
        $puedeSerAlumno = false;
        $motivo = 'ya_en_equipo';
    }
}
// contexto === 'talentos': sin validación adicional — cualquier alumno del
// padrón puede sumarse a un acto (incluso a varios, ver reglas del show).

responder([
    'encontrado' => true,
    'alumno' => [
        'numero_cuenta' => $alumno['numero_cuenta'],
        'nombre_completo' => $alumno['nombre_completo'],
        'grado' => $alumno['grado'],
        'grupo' => $alumno['grupo'],
        'foto_url' => '/registro/public/' . $alumno['foto_path'],
    ],
    'puede_ser_alumno' => $puedeSerAlumno,
    'motivo' => $motivo,
]);
