<?php
declare(strict_types=1);

require_once __DIR__ . '/dias.php';

// Alumnos SIN inscripción, bloque por bloque de cada día — compartido por el
// dashboard (public/index.php) y por sus descargas
// (includes/exportar-sin-inscripcion.php), mismo criterio que
// includes/cupo-eventos.php y includes/equipos-competicion.php: el archivo que
// se descarga tiene que decir exactamente lo mismo que la pantalla.
//
// QUÉ ES UN BLOQUE: una franja horaria de un día — la pareja
// (hora_inicio, hora_fin) que comparten las actividades de ese rato. Sale de
// `eventos` y `competiciones` juntas, así que el bloque de 10:30–12:30 del Día
// Académico incluye tanto los 4 talleres como el Concurso del Conocimiento, y
// el del Día Deportivo, los 3 torneos. Es la misma franja sobre la que el Día
// Académico exige exclusividad (ver "Reglas de inscripción por franja horaria"
// en 01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md), y por eso es la
// unidad en la que tiene sentido preguntar "¿este alumno se quedó sin nada que
// hacer a esta hora?".
//
// EL ESCENARIO DE TALENTOS QUEDA FUERA a propósito: no reparte cupo, un alumno
// puede tener varias participaciones y no compite contra ninguna otra
// actividad de su horario, así que contar "no está en el show" como "no está
// inscrito en nada" sobreestimaría el problema. Se reconoce por ser la
// competición del Día Cultural (dia = 'cultural' AND tipo = 'concurso'); si
// algún día se crea otra competición cultural que SÍ deba contar, este es el
// lugar donde hay que afinar la regla.

function sinInscripcionCompeticionExcluida(string $alias = ''): string
{
    $prefijo = $alias !== '' ? $alias . '.' : '';

    return "({$prefijo}dia = 'cultural' AND {$prefijo}tipo = 'concurso')";
}

/** Clave estable y apta para URL/id de un bloque, ej. "academico-0930-1000". */
function bloqueClave(string $dia, string $horaInicio, string $horaFin): string
{
    $corta = static fn(string $hora): string => substr(str_replace(':', '', $hora), 0, 4);

    return $dia . '-' . $corta($horaInicio) . '-' . $corta($horaFin);
}

/**
 * Catálogo de bloques (franjas horarias con actividad) de los 3 días, en orden
 * cronológico, con cuántas actividades tiene cada uno y cuáles son.
 *
 * @return list<array<string, mixed>>
 */
function bloquesDelEvento(PDO $pdo): array
{
    $filas = $pdo->query(
        "SELECT dia, hora_inicio, hora_fin, COUNT(*) AS actividades,
                GROUP_CONCAT(nombre ORDER BY nombre SEPARATOR ' · ') AS nombres
         FROM (
             SELECT dia, hora_inicio, hora_fin, nombre FROM eventos
             UNION ALL
             SELECT dia, hora_inicio, hora_fin, nombre FROM competiciones
             WHERE NOT " . sinInscripcionCompeticionExcluida() . "
         ) AS agenda
         GROUP BY dia, hora_inicio, hora_fin
         ORDER BY FIELD(dia, 'academico', 'cultural', 'deportivo'), hora_inicio"
    )->fetchAll();

    $bloques = [];
    foreach ($filas as $fila) {
        $bloques[] = [
            'clave' => bloqueClave((string) $fila['dia'], (string) $fila['hora_inicio'], (string) $fila['hora_fin']),
            'dia' => (string) $fila['dia'],
            'dia_label' => diaEventoLabel((string) $fila['dia']),
            'hora_inicio' => (string) $fila['hora_inicio'],
            'hora_fin' => (string) $fila['hora_fin'],
            'horario' => substr((string) $fila['hora_inicio'], 0, 5) . ' – ' . substr((string) $fila['hora_fin'], 0, 5),
            'actividades' => (int) $fila['actividades'],
            'nombres' => (string) $fila['nombres'],
        ];
    }

    return $bloques;
}

/**
 * Reporte completo: qué alumnos no están inscritos en nada de cada bloque y,
 * como cierre de cada día, cuáles no están en nada de TODO el día.
 *
 * Son 3 consultas y el cruce se hace en PHP —no una consulta por bloque con su
 * NOT EXISTS— porque los bloques son pocos pero el padrón es de cientos de
 * alumnos: traer una vez la lista de alumnos y una vez la de participaciones
 * sale más barato y además deja calcular el "todo el día" como intersección,
 * sin volver a la base.
 *
 * @return array{bloques: list<array<string, mixed>>, dias: list<array<string, mixed>>, total_alumnos: int}
 */
function alumnosSinInscripcion(PDO $pdo): array
{
    $bloques = bloquesDelEvento($pdo);

    $alumnos = $pdo->query(
        'SELECT id, numero_cuenta, nombre_completo, grado, grupo, correo_institucional
         FROM alumnos ORDER BY grado, grupo, nombre_completo'
    )->fetchAll();

    // Presencia por bloque: [clave de bloque][id de alumno] => true. Las dos
    // consultas se leen igual porque la pregunta es la misma —¿este alumno
    // tiene algo que hacer en esta franja?— sin importar si lo que tiene es
    // una inscripción individual o un lugar en un equipo. De `integrantes`
    // solo cuentan las filas de tipo 'alumno': las de padre/madre llevan el
    // id_alumno del hijo (ver schema.sql) y marcarían como ocupado a un
    // alumno que en realidad no está en el equipo.
    $participa = [];
    $consultas = [
        'SELECT i.id_alumno, e.dia, e.hora_inicio, e.hora_fin
         FROM inscripciones i JOIN eventos e ON e.id = i.id_evento',
        "SELECT it.id_alumno, c.dia, c.hora_inicio, c.hora_fin
         FROM integrantes it
         JOIN equipos eq ON eq.id = it.id_equipo
         JOIN competiciones c ON c.id = eq.id_competicion
         WHERE it.tipo = 'alumno' AND NOT " . sinInscripcionCompeticionExcluida('c'),
    ];
    foreach ($consultas as $sql) {
        foreach ($pdo->query($sql)->fetchAll() as $fila) {
            $clave = bloqueClave((string) $fila['dia'], (string) $fila['hora_inicio'], (string) $fila['hora_fin']);
            $participa[$clave][(int) $fila['id_alumno']] = true;
        }
    }

    foreach ($bloques as &$bloque) {
        $ocupados = $participa[$bloque['clave']] ?? [];
        $bloque['alumnos'] = array_values(array_filter(
            $alumnos,
            static fn(array $alumno): bool => !isset($ocupados[(int) $alumno['id']])
        ));
        $bloque['total'] = count($bloque['alumnos']);
        $bloque['por_grupo'] = sinInscripcionPorGrupo($bloque['alumnos']);
    }
    unset($bloque);

    // Cierre de cada día: los que no aparecen en NINGÚN bloque de ese día —la
    // intersección, no la suma— porque un alumno puede haber ido a la ponencia
    // de las 09:00 y no al taller de las 10:30.
    $dias = [];
    foreach ($bloques as $bloque) {
        $dias[$bloque['dia']]['dia'] = $bloque['dia'];
        $dias[$bloque['dia']]['dia_label'] = $bloque['dia_label'];
        $dias[$bloque['dia']]['bloques'][] = $bloque['clave'];
    }
    foreach ($dias as &$dia) {
        $dia['alumnos'] = array_values(array_filter($alumnos, static function (array $alumno) use ($dia, $participa): bool {
            foreach ($dia['bloques'] as $claveBloque) {
                if (isset($participa[$claveBloque][(int) $alumno['id']])) {
                    return false;
                }
            }
            return true;
        }));
        $dia['total'] = count($dia['alumnos']);
        $dia['por_grupo'] = sinInscripcionPorGrupo($dia['alumnos']);
    }
    unset($dia);

    return [
        'bloques' => $bloques,
        'dias' => array_values($dias),
        'total_alumnos' => count($alumnos),
    ];
}

/**
 * Cuántos de esos alumnos son de cada grado y grupo ("3°B" => 12), para el
 * desglose de la tabla y del reporte.
 *
 * @param list<array<string, mixed>> $alumnos
 * @return array<string, int>
 */
function sinInscripcionPorGrupo(array $alumnos): array
{
    $porGrupo = [];
    foreach ($alumnos as $alumno) {
        $clave = $alumno['grado'] . '°' . $alumno['grupo'];
        $porGrupo[$clave] = ($porGrupo[$clave] ?? 0) + 1;
    }
    ksort($porGrupo);

    return $porGrupo;
}

/**
 * Los mismos alumnos agrupados por grado y grupo, que es como se reparte la
 * lista a los maestros de grupo.
 *
 * @param list<array<string, mixed>> $alumnos
 * @return array<string, list<array<string, mixed>>>
 */
function sinInscripcionAgrupado(array $alumnos): array
{
    $agrupado = [];
    foreach ($alumnos as $alumno) {
        $agrupado[$alumno['grado'] . '°' . $alumno['grupo']][] = $alumno;
    }
    ksort($agrupado);

    return $agrupado;
}
