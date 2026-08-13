<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require __DIR__ . '/codigo-participante.php';
iniciarSesionInscripciones();

// Backend de inscripción a un acto del Escenario de Talentos (Día Cultural).
// A diferencia del Concurso del Conocimiento y los torneos deportivos:
//   - El acto puede ser individual (0 acompañantes) o en equipo, de tamaño
//     libre (hasta 9 acompañantes, 10 personas en total).
//   - Un mismo alumno puede inscribirse a MÁS de un acto — no se valida ni
//     cruce de horario ni "ya perteneces a otro equipo de esta competición".

function volverConMensaje(string $tipo, string $codigo): never
{
    header('Location: /inscripciones/public/cultural.php?' . $tipo . '=' . urlencode($codigo));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /inscripciones/public/cultural.php');
    exit;
}

$idCapitan = alumnoIdentificadoId();
if ($idCapitan === null) {
    header('Location: /inscripciones/public/index.php?volver=cultural');
    exit;
}

$nombreActo = trim((string) ($_POST['nombre_acto'] ?? ''));
if ($nombreActo === '' || mb_strlen($nombreActo) > 150) {
    volverConMensaje('error', 'nombre_acto_invalido');
}

$numerosCuentaAcompanantes = array_map(
    static fn ($v): string => strtoupper(trim((string) $v)),
    (array) ($_POST['integrantes'] ?? [])
);
$numerosCuentaAcompanantes = array_values(array_filter($numerosCuentaAcompanantes, static fn (string $v): bool => $v !== ''));

if (count($numerosCuentaAcompanantes) > 9) {
    volverConMensaje('error', 'demasiados_integrantes');
}

foreach ($numerosCuentaAcompanantes as $numeroCuenta) {
    if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
        volverConMensaje('error', 'numero_cuenta_invalido');
    }
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consultaCapitan = $pdo->prepare('SELECT id, nombre_completo, numero_cuenta FROM alumnos WHERE id = :id');
$consultaCapitan->execute(['id' => $idCapitan]);
$capitan = $consultaCapitan->fetch();
if ($capitan === false) {
    volverConMensaje('error', 'error_servidor');
}

if (in_array($capitan['numero_cuenta'], $numerosCuentaAcompanantes, true)) {
    volverConMensaje('error', 'integrante_duplicado');
}
if (count(array_unique($numerosCuentaAcompanantes)) !== count($numerosCuentaAcompanantes)) {
    volverConMensaje('error', 'integrante_duplicado');
}

$integrantes = [(int) $capitan['id'] => $capitan['nombre_completo']];

if ($numerosCuentaAcompanantes !== []) {
    $marcadores = implode(',', array_fill(0, count($numerosCuentaAcompanantes), '?'));
    $consultaAlumnos = $pdo->prepare(
        "SELECT id, nombre_completo, numero_cuenta FROM alumnos WHERE numero_cuenta IN ($marcadores)"
    );
    $consultaAlumnos->execute($numerosCuentaAcompanantes);
    $alumnosEncontrados = $consultaAlumnos->fetchAll();

    if (count($alumnosEncontrados) !== count($numerosCuentaAcompanantes)) {
        volverConMensaje('error', 'integrante_no_encontrado');
    }

    foreach ($alumnosEncontrados as $alumnoFila) {
        $integrantes[(int) $alumnoFila['id']] = $alumnoFila['nombre_completo'];
    }
    if (count($integrantes) !== count($numerosCuentaAcompanantes) + 1) {
        volverConMensaje('error', 'integrante_duplicado');
    }
}

$competicion = $pdo->query(
    "SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso' LIMIT 1"
)->fetch();
if ($competicion === false) {
    volverConMensaje('error', 'error_servidor');
}
$idCompeticion = (int) $competicion['id'];

$pdo->beginTransaction();

try {
    $insertarEquipo = $pdo->prepare(
        'INSERT INTO equipos (id_competicion, nombre, id_alumno_capitan) VALUES (:competicion, :nombre, :capitan)'
    );
    $insertarEquipo->execute([
        'competicion' => $idCompeticion,
        'nombre' => $nombreActo,
        'capitan' => $idCapitan,
    ]);
    $idEquipo = (int) $pdo->lastInsertId();

    $insertarIntegrante = $pdo->prepare(
        "INSERT INTO integrantes (id_equipo, id_alumno, tipo, nombre, codigo_participante)
         VALUES (:equipo, :alumno, 'alumno', :nombre, :codigo)"
    );
    foreach ($integrantes as $idAlumnoIntegrante => $nombreIntegrante) {
        $insertarIntegrante->execute([
            'equipo' => $idEquipo,
            'alumno' => $idAlumnoIntegrante,
            'nombre' => $nombreIntegrante,
            'codigo' => generarCodigoParticipante($pdo, $idCompeticion),
        ]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al crear acto del Escenario de Talentos (capitán ' . $idCapitan . '): ' . $e->getMessage());
    volverConMensaje('error', 'error_servidor');
}

volverConMensaje('msg', 'acto_creado');
