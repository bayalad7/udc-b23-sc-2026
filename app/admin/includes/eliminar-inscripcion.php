<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

// Quita a UN alumno de UN evento (ponencia/taller) y le devuelve el lugar al
// evento. Se usa desde el reporte de Conflictos (public/conflictos.php) para
// deshacer una de las dos inscripciones encimadas cuando un evento cambió de
// día u horario y dejó al alumno comprometido dos veces a la misma hora.
//
// Solo toca `inscripciones`. Los equipos NO se desarman desde aquí: quitar a
// un integrante deja al equipo incompleto (los torneos exigen
// competiciones.tam_equipo exacto) y eso es una decisión del capitán, no del
// staff — ver app/admin/public/competicion.php, donde los equipos se ven en
// modo solo lectura.

$volver = BASE_URL . '/admin/public/conflictos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $volver);
    exit;
}

$idAlumno = (int) ($_POST['id_alumno'] ?? 0);
$idEvento = (int) ($_POST['id_evento'] ?? 0);
if ($idAlumno <= 0 || $idEvento <= 0) {
    header('Location: ' . $volver . '?error=datos_invalidos');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare(
    'SELECT a.nombre_completo, a.numero_cuenta, e.nombre AS evento, i.hora_entrada
     FROM inscripciones i
     JOIN alumnos a ON a.id = i.id_alumno
     JOIN eventos e ON e.id = i.id_evento
     WHERE i.id_alumno = :alumno AND i.id_evento = :evento'
);
$consulta->execute(['alumno' => $idAlumno, 'evento' => $idEvento]);
$inscripcion = $consulta->fetch();

if ($inscripcion === false) {
    header('Location: ' . $volver . '?error=no_encontrada');
    exit;
}

// Si ya le escanearon la entrada a ese evento, borrar la inscripción se
// llevaría también el registro de que estuvo ahí. Eso ya no es resolver un
// choque de horario, es perder asistencia: se bloquea a propósito.
if ($inscripcion['hora_entrada'] !== null) {
    header('Location: ' . $volver . '?error=con_asistencia&detalle=' . urlencode(
        $inscripcion['nombre_completo'] . ' — ' . $inscripcion['evento']
    ));
    exit;
}

$pdo->beginTransaction();

try {
    $eliminar = $pdo->prepare('DELETE FROM inscripciones WHERE id_alumno = :alumno AND id_evento = :evento');
    $eliminar->execute(['alumno' => $idAlumno, 'evento' => $idEvento]);

    if ($eliminar->rowCount() === 1) {
        // El lugar vuelve al evento. El LEAST evita pasarse de cupo_maximo si
        // el contador ya venía descuadrado por alguna corrección a mano: el
        // cupo disponible nunca puede ser mayor que el máximo.
        $devolver = $pdo->prepare(
            'UPDATE eventos SET cupo_disponible = LEAST(cupo_disponible + 1, cupo_maximo) WHERE id = :evento'
        );
        $devolver->execute(['evento' => $idEvento]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error quitando la inscripción del alumno ' . $idAlumno . ' al evento ' . $idEvento . ': ' . $e->getMessage());
    header('Location: ' . $volver . '?error=error_servidor');
    exit;
}

header('Location: ' . $volver . '?msg=inscripcion_eliminada&detalle=' . urlencode(
    $inscripcion['nombre_completo'] . ' — ' . $inscripcion['evento']
));
exit;
