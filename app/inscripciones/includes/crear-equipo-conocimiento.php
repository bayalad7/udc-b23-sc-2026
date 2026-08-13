<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require __DIR__ . '/codigo-participante.php';
iniciarSesionInscripciones();

// Backend de formación de equipo del Concurso del Conocimiento (Día
// Académico) — equipos de exactamente 10 alumnos (sin padres/madres, a
// diferencia de los torneos deportivos). Quien envía el formulario queda
// como capitán; valida, para el capitán y para cada uno de los otros 9:
//   1. Que el número de cuenta exista en el padrón (alumnos).
//   2. Que nadie se repita entre los 10 (ni consigo mismo).
//   3. Que ninguno esté ya en OTRO equipo de esta misma competición.
//   4. Que a ninguno se le cruce el horario del concurso (10:30–12:30) con
//      otra inscripción (eventos) o membresía de equipo (competiciones) que
//      ya tenga ese día — mismo criterio genérico de
//      "Reglas de inscripción por franja horaria" que usa includes/inscribir.php.

function volverConMensaje(string $tipo, string $codigo): never
{
    header('Location: /inscripciones/public/academico.php?' . $tipo . '=' . urlencode($codigo));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /inscripciones/public/academico.php');
    exit;
}

$idCapitan = alumnoIdentificadoId();
if ($idCapitan === null) {
    header('Location: /inscripciones/public/index.php?volver=academico');
    exit;
}

$nombreEquipo = trim((string) ($_POST['nombre_equipo'] ?? ''));
if ($nombreEquipo === '' || mb_strlen($nombreEquipo) > 150) {
    volverConMensaje('error', 'nombre_equipo_invalido');
}

$numerosCuentaIntegrantes = array_map(
    static fn ($v): string => strtoupper(trim((string) $v)),
    (array) ($_POST['integrantes'] ?? [])
);
$numerosCuentaIntegrantes = array_values(array_filter($numerosCuentaIntegrantes, static fn (string $v): bool => $v !== ''));

if (count($numerosCuentaIntegrantes) !== 9) {
    volverConMensaje('error', 'integrantes_incompletos');
}

foreach ($numerosCuentaIntegrantes as $numeroCuenta) {
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

if (in_array($capitan['numero_cuenta'], $numerosCuentaIntegrantes, true)) {
    volverConMensaje('error', 'integrante_duplicado');
}
if (count(array_unique($numerosCuentaIntegrantes)) !== 9) {
    volverConMensaje('error', 'integrante_duplicado');
}

$competicion = $pdo->query(
    "SELECT id, hora_inicio, hora_fin FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso' LIMIT 1"
)->fetch();
if ($competicion === false) {
    volverConMensaje('error', 'error_servidor');
}
$idCompeticion = (int) $competicion['id'];

// Resolver los 9 números de cuenta contra el padrón — todos deben existir.
$marcadores = implode(',', array_fill(0, count($numerosCuentaIntegrantes), '?'));
$consultaAlumnos = $pdo->prepare(
    "SELECT id, nombre_completo, numero_cuenta FROM alumnos WHERE numero_cuenta IN ($marcadores)"
);
$consultaAlumnos->execute($numerosCuentaIntegrantes);
$alumnosEncontrados = $consultaAlumnos->fetchAll();

if (count($alumnosEncontrados) !== 9) {
    volverConMensaje('error', 'integrante_no_encontrado');
}

// $integrantes: id_alumno => nombre_completo, incluyendo al capitán.
$integrantes = [$capitan['id'] => $capitan['nombre_completo']];
foreach ($alumnosEncontrados as $alumnoFila) {
    $integrantes[(int) $alumnoFila['id']] = $alumnoFila['nombre_completo'];
}
if (count($integrantes) !== 10) {
    // Dos números de cuenta distintos resolvieron al mismo id (no debería
    // pasar con numero_cuenta UNIQUE, pero se cubre por seguridad).
    volverConMensaje('error', 'integrante_duplicado');
}

$idsIntegrantes = array_keys($integrantes);

$pdo->beginTransaction();

try {
    // --- 1. Nadie ya en otro equipo de esta misma competición -----------

    $marcadoresIds = implode(',', array_fill(0, count($idsIntegrantes), '?'));
    $consultaYaEnEquipo = $pdo->prepare(
        "SELECT 1 FROM integrantes it
         JOIN equipos eq ON eq.id = it.id_equipo
         WHERE eq.id_competicion = ? AND it.id_alumno IN ($marcadoresIds)
         LIMIT 1"
    );
    $consultaYaEnEquipo->execute(array_merge([$idCompeticion], $idsIntegrantes));
    if ($consultaYaEnEquipo->fetch() !== false) {
        $pdo->rollBack();
        volverConMensaje('error', 'integrante_ya_en_equipo');
    }

    // --- 2. Nadie con cruce de horario contra el concurso ----------------

    $consultaCruceEventos = $pdo->prepare(
        "SELECT 1 FROM inscripciones i
         JOIN eventos e ON e.id = i.id_evento
         WHERE i.id_alumno = ? AND e.dia = 'academico'
           AND e.hora_inicio < ? AND e.hora_fin > ?
         LIMIT 1"
    );
    $consultaCruceEquipos = $pdo->prepare(
        "SELECT 1 FROM integrantes it
         JOIN equipos eq ON eq.id = it.id_equipo
         JOIN competiciones c ON c.id = eq.id_competicion
         WHERE it.id_alumno = ? AND c.dia = 'academico'
           AND c.hora_inicio < ? AND c.hora_fin > ?
         LIMIT 1"
    );
    foreach ($idsIntegrantes as $idAlumnoIntegrante) {
        $consultaCruceEventos->execute([$idAlumnoIntegrante, $competicion['hora_fin'], $competicion['hora_inicio']]);
        if ($consultaCruceEventos->fetch() !== false) {
            $pdo->rollBack();
            volverConMensaje('error', 'integrante_cruce_horario');
        }
        $consultaCruceEquipos->execute([$idAlumnoIntegrante, $competicion['hora_fin'], $competicion['hora_inicio']]);
        if ($consultaCruceEquipos->fetch() !== false) {
            $pdo->rollBack();
            volverConMensaje('error', 'integrante_cruce_horario');
        }
    }

    // --- 3. Guardar equipo + integrantes ---------------------------------

    $insertarEquipo = $pdo->prepare(
        'INSERT INTO equipos (id_competicion, nombre, id_alumno_capitan) VALUES (:competicion, :nombre, :capitan)'
    );
    $insertarEquipo->execute([
        'competicion' => $idCompeticion,
        'nombre' => $nombreEquipo,
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
    if ($e->getCode() === '23000') {
        volverConMensaje('error', 'integrante_ya_en_equipo');
    }
    if ($e->getCode() === '45000') {
        // SIGNAL del trigger trg_equipos_limite_conocimiento (ver schema.sql)
        // — ya hay 12 equipos registrados.
        volverConMensaje('error', 'equipo_limite_alcanzado');
    }
    error_log('Error al crear equipo del Concurso del Conocimiento (capitán ' . $idCapitan . '): ' . $e->getMessage());
    volverConMensaje('error', 'error_servidor');
}

volverConMensaje('msg', 'equipo_creado');
