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
// EL ESCENARIO DE TALENTOS QUEDA FUERA DEL CONTEO a propósito: no reparte
// cupo, un alumno puede tener varias participaciones y no compite contra
// ninguna otra actividad de su horario, así que contar "no está en el show"
// como "no está inscrito en nada" sobreestimaría el problema. Se reconoce por
// ser la competición del Día Cultural (dia = 'cultural' AND tipo = 'concurso');
// si algún día se crea otra competición cultural que SÍ deba contar, este es el
// lugar donde hay que afinar la regla. Queda fuera en los dos lados del
// reporte: ni suma al conteo ni aparece como columna del pivote.

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

/** Horario del bloque en el formato del proyecto ("09:00 – 10:00"). */
function bloqueHorario(string $horaInicio, string $horaFin): string
{
    return substr($horaInicio, 0, 5) . ' – ' . substr($horaFin, 0, 5);
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
        "SELECT dia, hora_inicio, hora_fin,
                COUNT(*) AS actividades,
                SUM(es_competicion) AS competiciones,
                GROUP_CONCAT(nombre ORDER BY nombre SEPARATOR ' · ') AS nombres,
                GROUP_CONCAT(DISTINCT tipo_competicion) AS tipos_competicion
         FROM (
             SELECT dia, hora_inicio, hora_fin, nombre, 0 AS es_competicion, NULL AS tipo_competicion
             FROM eventos
             UNION ALL
             SELECT dia, hora_inicio, hora_fin, nombre, 1, tipo
             FROM competiciones
             WHERE NOT " . sinInscripcionCompeticionExcluida() . "
         ) AS agenda
         GROUP BY dia, hora_inicio, hora_fin
         ORDER BY FIELD(dia, 'academico', 'cultural', 'deportivo'), hora_inicio"
    )->fetchAll();

    $bloques = [];
    $bloquesPorDia = [];
    foreach ($filas as $fila) {
        $dia = (string) $fila['dia'];
        $competiciones = (int) $fila['competiciones'];
        $eventos = (int) $fila['actividades'] - $competiciones;

        // Etiqueta corta para el encabezado del pivote, donde el día ya se lee
        // arriba: los bloques de ponencias/talleres se numeran por día
        // ("Bloque 1", "Bloque 2") y los que son puras competiciones se nombran
        // por lo que son — con una sola, su nombre; con varias a la misma hora,
        // "Algún torneo", porque la marca dice si el alumno está en AL MENOS
        // una de ellas (los 3 torneos del Día Deportivo corren en paralelo y no
        // son excluyentes entre sí).
        if ($eventos > 0) {
            $bloquesPorDia[$dia] = ($bloquesPorDia[$dia] ?? 0) + 1;
            $etiqueta = 'Bloque ' . $bloquesPorDia[$dia];
        } elseif ($competiciones === 1) {
            $etiqueta = (string) $fila['nombres'];
        } else {
            $tipos = array_filter(explode(',', (string) $fila['tipos_competicion']));
            $etiqueta = 'Algún ' . (count($tipos) === 1 ? reset($tipos) : 'evento');
        }

        $bloques[] = [
            'clave' => bloqueClave($dia, (string) $fila['hora_inicio'], (string) $fila['hora_fin']),
            'dia' => $dia,
            'dia_label' => diaEventoLabel($dia),
            'hora_inicio' => (string) $fila['hora_inicio'],
            'hora_fin' => (string) $fila['hora_fin'],
            'horario' => bloqueHorario((string) $fila['hora_inicio'], (string) $fila['hora_fin']),
            'actividades' => (int) $fila['actividades'],
            'nombres' => (string) $fila['nombres'],
            'etiqueta' => $etiqueta,
        ];
    }

    return $bloques;
}

/**
 * Reporte completo: qué alumnos no están inscritos en nada de cada bloque,
 * cuáles no están en nada de TODO el día, y el pivote (un renglón por alumno,
 * una columna por bloque/torneo) agrupado por grado y grupo.
 *
 * Son 4 consultas y el cruce se hace en PHP —no una consulta por bloque con su
 * NOT EXISTS— porque los bloques son pocos pero el padrón es de cientos de
 * alumnos: traer una vez la lista de alumnos y una vez la de participaciones
 * sale más barato y además deja calcular el "todo el día" como intersección y
 * el pivote completo, sin volver a la base.
 *
 * @return array{bloques: list<array<string, mixed>>, dias: list<array<string, mixed>>, pivote: array<string, mixed>, total_alumnos: int}
 */
function alumnosSinInscripcion(PDO $pdo): array
{
    $bloques = bloquesDelEvento($pdo);

    $alumnos = $pdo->query(
        'SELECT id, numero_cuenta, nombre_completo, grado, grupo, correo_institucional
         FROM alumnos ORDER BY grado, grupo, nombre_completo'
    )->fetchAll();

    // Participaciones de cada alumno: una fila por inscripción a evento y una
    // por lugar en un equipo. Las dos se leen igual porque la pregunta es la
    // misma —¿este alumno tiene algo que hacer en esta franja?— sin importar
    // si lo que tiene es una inscripción individual o un lugar en un equipo.
    // De `integrantes` solo cuentan las filas de tipo 'alumno': las de
    // padre/madre llevan el id_alumno del hijo (ver schema.sql) y marcarían
    // como ocupado a un alumno que en realidad no está en el equipo.
    //
    // El mismo mapa sirve para el conteo y para el pivote, y por eso las dos
    // mitades del reporte no se pueden contradecir: una ✘ en la columna de un
    // bloque es exactamente estar en su lista de "sin inscripción".
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
    // de las 09:00 y a nada de las 10:30.
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
        'pivote' => pivoteSinInscripcion($alumnos, $bloques, $participa),
        'total_alumnos' => count($alumnos),
    ];
}

/**
 * Pivote: TODO el padrón agrupado por grado y grupo, con una marca por bloque
 * diciendo si ese alumno está inscrito ahí o no. Salen todos los alumnos, no
 * solo los que faltan, para que la tabla se le pueda entregar completa al
 * maestro de cada grupo.
 *
 * Las columnas son los mismos bloques del resumen, ni más ni menos: el Día
 * Deportivo es UNA columna —✔ si el alumno entró a al menos uno de los 3
 * torneos, ✘ si no entró a ninguno— y el Escenario de Talentos no aparece.
 *
 * @param list<array<string, mixed>> $alumnos
 * @param list<array<string, mixed>> $columnas
 * @param array<string, array<int, bool>> $participa
 * @return array{columnas: list<array<string, mixed>>, dias: list<array<string, mixed>>, grupos: array<string, list<array<string, mixed>>>, totales: array<string, int>}
 */
function pivoteSinInscripcion(array $alumnos, array $columnas, array $participa): array
{
    // Encabezado de dos pisos: el día abarca sus columnas y debajo va cada
    // bloque/torneo, para no repetir "Día Académico" en cada una.
    $diasPivote = [];
    foreach ($columnas as $columna) {
        if (!isset($diasPivote[$columna['dia']])) {
            $diasPivote[$columna['dia']] = [
                'dia' => $columna['dia'],
                'dia_label' => $columna['dia_label'],
                'columnas' => 0,
            ];
        }
        $diasPivote[$columna['dia']]['columnas']++;
    }

    $grupos = [];
    $totales = [];
    foreach ($columnas as $columna) {
        $totales[$columna['clave']] = 0;
    }

    foreach ($alumnos as $alumno) {
        $idAlumno = (int) $alumno['id'];
        $marcas = [];
        $faltantes = 0;
        foreach ($columnas as $columna) {
            $inscrito = isset($participa[$columna['clave']][$idAlumno]);
            $marcas[$columna['clave']] = $inscrito;
            if ($inscrito) {
                $totales[$columna['clave']]++;
            } else {
                $faltantes++;
            }
        }
        $alumno['marcas'] = $marcas;
        $alumno['faltantes'] = $faltantes;
        $grupos[$alumno['grado'] . '°' . $alumno['grupo']][] = $alumno;
    }
    ksort($grupos);

    return [
        'columnas' => $columnas,
        'dias' => array_values($diasPivote),
        'grupos' => $grupos,
        'totales' => $totales,
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
