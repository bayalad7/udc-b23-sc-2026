<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionInscripciones();

// Backend de inscripción a un evento individual (ponencia/taller) del Día
// Académico. Antes de guardar valida DOS cosas, no solo el cupo (ver
// "Reglas de inscripción por franja horaria" en
// 01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md):
//   1. Que el evento elegido no se traslape en horario con ninguna otra
//      inscripción (eventos) o membresía de equipo (competiciones) que el
//      alumno ya tenga ESE MISMO día — así se aplica "un evento por bloque"
//      de forma genérica, sin necesitar un concepto de "bloque" aparte:
//      basta con comparar hora_inicio/hora_fin.
//   2. Que quede cupo, de forma atómica (UPDATE ... WHERE cupo_disponible > 0)
//      para evitar sobrecupo si dos alumnos se inscriben al mismo evento al
//      mismo tiempo.

function volverConMensaje(string $tipo, string $codigo): never
{
    header('Location: /inscripciones/public/academico.php?' . $tipo . '=' . urlencode($codigo));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /inscripciones/public/academico.php');
    exit;
}

$idAlumno = alumnoIdentificadoId();
if ($idAlumno === null) {
    header('Location: /inscripciones/public/index.php?volver=academico');
    exit;
}

$idEvento = filter_var($_POST['id_evento'] ?? '', FILTER_VALIDATE_INT);
if ($idEvento === false) {
    volverConMensaje('error', 'evento_invalido');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consultaEvento = $pdo->prepare(
    "SELECT id, dia, hora_inicio, hora_fin, nombre FROM eventos WHERE id = :id AND dia = 'academico'"
);
$consultaEvento->execute(['id' => $idEvento]);
$evento = $consultaEvento->fetch();

if ($evento === false) {
    volverConMensaje('error', 'evento_invalido');
}

$pdo->beginTransaction();

try {
    // --- 1. Cruce de horario -------------------------------------------

    $consultaCruceEventos = $pdo->prepare(
        "SELECT 1 FROM inscripciones i
         JOIN eventos e ON e.id = i.id_evento
         WHERE i.id_alumno = :alumno AND e.dia = 'academico'
           AND e.hora_inicio < :hora_fin AND e.hora_fin > :hora_inicio
         LIMIT 1"
    );
    $consultaCruceEventos->execute([
        'alumno' => $idAlumno,
        'hora_inicio' => $evento['hora_inicio'],
        'hora_fin' => $evento['hora_fin'],
    ]);

    $consultaCruceEquipos = $pdo->prepare(
        "SELECT 1 FROM integrantes it
         JOIN equipos eq ON eq.id = it.id_equipo
         JOIN competiciones c ON c.id = eq.id_competicion
         WHERE it.id_alumno = :alumno AND c.dia = 'academico'
           AND c.hora_inicio < :hora_fin AND c.hora_fin > :hora_inicio
         LIMIT 1"
    );
    $consultaCruceEquipos->execute([
        'alumno' => $idAlumno,
        'hora_inicio' => $evento['hora_inicio'],
        'hora_fin' => $evento['hora_fin'],
    ]);

    if ($consultaCruceEventos->fetch() !== false || $consultaCruceEquipos->fetch() !== false) {
        $pdo->rollBack();
        volverConMensaje('error', 'cruce_horario');
    }

    // --- 2. Cupo, de forma atómica ---------------------------------------

    $descontarCupo = $pdo->prepare(
        'UPDATE eventos SET cupo_disponible = cupo_disponible - 1 WHERE id = :id AND cupo_disponible > 0'
    );
    $descontarCupo->execute(['id' => $idEvento]);

    if ($descontarCupo->rowCount() !== 1) {
        $pdo->rollBack();
        volverConMensaje('error', 'sin_cupo');
    }

    // --- 3. Guardar la inscripción ---------------------------------------

    $consultaAlumno = $pdo->prepare('SELECT nombre_completo FROM alumnos WHERE id = :id');
    $consultaAlumno->execute(['id' => $idAlumno]);
    $alumno = $consultaAlumno->fetch();

    $insertar = $pdo->prepare(
        "INSERT INTO inscripciones (id_evento, id_alumno, origen, registrado_por)
         VALUES (:evento, :alumno, 'orden_llegada', :registrado_por)"
    );
    $insertar->execute([
        'evento' => $idEvento,
        'alumno' => $idAlumno,
        'registrado_por' => $alumno !== false ? $alumno['nombre_completo'] : 'Autoservicio',
    ]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23000') {
        volverConMensaje('error', 'ya_inscrito');
    }
    error_log('Error al inscribir alumno ' . $idAlumno . ' en evento ' . $idEvento . ': ' . $e->getMessage());
    volverConMensaje('error', 'error_servidor');
}

volverConMensaje('msg', 'inscrito');
