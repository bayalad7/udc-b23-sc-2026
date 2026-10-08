<?php
declare(strict_types=1);

require_once __DIR__ . '/dias.php';
require_once __DIR__ . '/sin-inscripcion.php';

// Asistencia por alumno: TODAS las tomas de asistencia del sistema en una sola
// tabla —la general de cada día (asistencias_generales) y la de cada actividad
// (inscripciones a ponencias/talleres e integrantes de equipo)— con un renglón
// por alumno, agrupado por grado y grupo. Compartido por el dashboard
// (public/index.php) y por su descarga (includes/exportar-asistencia-alumnos.php),
// mismo criterio que includes/sin-inscripcion.php: el archivo descargado tiene
// que decir exactamente lo mismo que la pantalla.
//
// LAS COLUMNAS: por cada día, primero "Plantel" (la entrada/salida general) y
// luego un bloque por franja horaria con actividad —los mismos bloques del
// reporte de alumnos sin inscripción, ver bloquesDelEvento()—, así que las dos
// tablas se leen igual. La diferencia es que aquí SÍ entra el Escenario de
// Talentos: allá queda fuera porque "no estar en el show" no es "no estar
// inscrito en nada", pero quien se apuntó y se presentó tiene una entrada que
// reportar.
//
// QUÉ DICE CADA CELDA: en "Plantel", la hora de entrada y la de salida del día.
// En un bloque, la actividad a la que está inscrito el alumno con su entrada y
// salida A ESA actividad; si está inscrito pero nunca le escanearon la entrada,
// "Sin entrada", y si no está inscrito a nada de ese bloque, un guion. El Día
// Deportivo puede traer más de una actividad en la misma celda: los 3 torneos
// corren a la misma hora y no son excluyentes entre sí.
//
// De `integrantes` solo cuentan las filas de tipo 'alumno': las de padre/madre
// llevan el id_alumno del hijo (ver schema.sql) y pondrían como presente al
// alumno con la entrada de su mamá.

/**
 * Hora guardada (zona del servidor) → "08:02" a la hora del plantel; NULL se
 * queda en NULL. Ver config/zona-horaria.php.
 */
function asistenciaHora(?string $fechaHora): ?string
{
    return horaLocal($fechaHora, 'H:i');
}

/**
 * Texto corto de una toma: "08:02 – 12:40", "08:02 – sin salida" o
 * "Sin entrada". Lo comparten la pantalla y el PDF.
 *
 * @param array{entrada: ?string, salida: ?string} $registro
 */
function asistenciaTexto(array $registro): string
{
    if ($registro['entrada'] === null) {
        return 'Sin entrada';
    }

    return $registro['entrada'] . ' – ' . ($registro['salida'] ?? 'sin salida');
}

/**
 * Reporte completo. Son 4 consultas (padrón, asistencia general, inscripciones
 * y equipos) y el cruce se hace en PHP, igual que alumnosSinInscripcion(): el
 * padrón es de cientos de alumnos y los bloques son pocos.
 *
 * @return array{columnas: list<array<string, mixed>>, dias: list<array<string, mixed>>, grupos: array<string, list<array<string, mixed>>>, totales: array<string, array{esperados: int, asistieron: int}>, total_alumnos: int}
 */
function asistenciaAlumnos(PDO $pdo): array
{
    // Columnas: "Plantel" de cada día seguido de sus bloques. Un día sin
    // actividades capturadas conserva su columna de plantel, porque la
    // asistencia general se toma igual.
    $bloques = bloquesDelEvento($pdo, true);
    $columnas = [];
    foreach (array_keys(DIAS_EVENTO_LABEL) as $dia) {
        $columnas[] = [
            'clave' => 'plantel-' . $dia,
            'tipo' => 'plantel',
            'dia' => $dia,
            'dia_label' => diaEventoLabel($dia),
            'etiqueta' => 'Plantel',
            'horario' => 'Entrada y salida',
        ];
        foreach ($bloques as $bloque) {
            if ($bloque['dia'] === $dia) {
                $columnas[] = [
                    'clave' => $bloque['clave'],
                    'tipo' => 'bloque',
                    'dia' => $dia,
                    'dia_label' => $bloque['dia_label'],
                    'etiqueta' => $bloque['etiqueta'],
                    'horario' => $bloque['horario'],
                ];
            }
        }
    }

    $alumnos = $pdo->query(
        'SELECT id, numero_cuenta, nombre_completo, grado, grupo
         FROM alumnos ORDER BY grado, grupo, nombre_completo'
    )->fetchAll();

    // $tomas[clave de columna][id_alumno] = lista de registros {nombre, entrada, salida}.
    $tomas = [];
    $generales = $pdo->query(
        'SELECT id_alumno, dia, hora_entrada, hora_salida FROM asistencias_generales'
    )->fetchAll();
    foreach ($generales as $fila) {
        $tomas['plantel-' . $fila['dia']][(int) $fila['id_alumno']][] = [
            'nombre' => '',
            'entrada' => asistenciaHora($fila['hora_entrada']),
            'salida' => asistenciaHora($fila['hora_salida']),
        ];
    }

    $consultas = [
        'SELECT i.id_alumno, e.dia, e.hora_inicio, e.hora_fin, e.nombre,
                i.hora_entrada, i.hora_salida
         FROM inscripciones i JOIN eventos e ON e.id = i.id_evento
         ORDER BY e.nombre',
        "SELECT it.id_alumno, c.dia, c.hora_inicio, c.hora_fin,
                CONCAT(c.nombre, ' (', eq.nombre, ')') AS nombre,
                it.hora_entrada, it.hora_salida
         FROM integrantes it
         JOIN equipos eq ON eq.id = it.id_equipo
         JOIN competiciones c ON c.id = eq.id_competicion
         WHERE it.tipo = 'alumno'
         ORDER BY c.nombre",
    ];
    foreach ($consultas as $sql) {
        foreach ($pdo->query($sql)->fetchAll() as $fila) {
            $clave = bloqueClave((string) $fila['dia'], (string) $fila['hora_inicio'], (string) $fila['hora_fin']);
            $tomas[$clave][(int) $fila['id_alumno']][] = [
                'nombre' => (string) $fila['nombre'],
                'entrada' => asistenciaHora($fila['hora_entrada']),
                'salida' => asistenciaHora($fila['hora_salida']),
            ];
        }
    }

    // Totales por columna: "esperados" son los que debían presentarse (todo el
    // padrón en Plantel, los inscritos en un bloque) y "asistieron" los que
    // tienen al menos una entrada escaneada en esa columna.
    $totales = [];
    foreach ($columnas as $columna) {
        $totales[$columna['clave']] = ['esperados' => 0, 'asistieron' => 0];
    }

    $grupos = [];
    foreach ($alumnos as $alumno) {
        $idAlumno = (int) $alumno['id'];
        $celdas = [];
        $esperadas = 0;
        $asistidas = 0;
        foreach ($columnas as $columna) {
            $registros = $tomas[$columna['clave']][$idAlumno] ?? [];
            $esPlantel = $columna['tipo'] === 'plantel';

            // Sin fila en asistencias_generales también es una toma esperada:
            // la asistencia general se le pide a todo el padrón.
            if ($esPlantel && $registros === []) {
                $registros = [['nombre' => '', 'entrada' => null, 'salida' => null]];
            }

            if ($registros === []) {
                $estado = 'sin';
            } else {
                $presente = array_filter($registros, static fn(array $r): bool => $r['entrada'] !== null);
                $estado = $presente !== [] ? 'asistio' : 'falto';
                $esperadas += count($registros);
                $asistidas += count($presente);
                $totales[$columna['clave']]['esperados']++;
                if ($presente !== []) {
                    $totales[$columna['clave']]['asistieron']++;
                }
            }

            $celdas[$columna['clave']] = ['estado' => $estado, 'registros' => $registros];
        }

        $alumno['celdas'] = $celdas;
        $alumno['esperadas'] = $esperadas;
        $alumno['asistidas'] = $asistidas;
        $grupos[$alumno['grado'] . '°' . $alumno['grupo']][] = $alumno;
    }
    ksort($grupos);

    // Encabezado de dos pisos, mismo recurso que el pivote de sin inscripción.
    $dias = [];
    foreach ($columnas as $columna) {
        if (!isset($dias[$columna['dia']])) {
            $dias[$columna['dia']] = ['dia' => $columna['dia'], 'dia_label' => $columna['dia_label'], 'columnas' => 0];
        }
        $dias[$columna['dia']]['columnas']++;
    }

    return [
        'columnas' => $columnas,
        'dias' => array_values($dias),
        'grupos' => $grupos,
        'totales' => $totales,
        'total_alumnos' => count($alumnos),
    ];
}
