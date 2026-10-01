<?php
declare(strict_types=1);

require_once __DIR__ . '/dias.php';

// Problemas de inscripción de un alumno: dónde quedó comprometido a dos cosas
// a la vez. Compartido por la pantalla (public/conflictos.php) y por su
// descarga (includes/exportar-conflictos.php), mismo criterio que el resto de
// los reportes del panel.
//
// POR QUÉ EXISTE: la app valida el cruce de horario al momento de inscribir
// (ver app/inscripciones/includes/inscribir.php y las "Reglas de inscripción
// por franja horaria" en 01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md),
// pero esa validación mira el catálogo como está ESE día. Si después se mueve
// un evento de día o de horario —como el intercambio de un taller del Día
// Académico con uno del Día Cultural— las inscripciones que ya estaban
// guardadas no se revisan solas, y un alumno puede acabar con dos talleres a
// la misma hora sin que nadie se enterara. Este reporte revisa el estado
// actual de la base y dice a quién le pasó.
//
// OJO: lee el catálogo COMO ESTÁ AHORA. Si el cambio de día todavía no se
// aplicó en `eventos`, el reporte sale limpio porque todavía no hay nada roto;
// hay que correrlo DESPUÉS de mover los eventos.
//
// QUÉ CUENTA COMO PROBLEMA
//
//   1. `traslape` — dos compromisos del mismo día cuyos horarios se encabalgan
//      y al menos uno es un evento (ponencia/taller). Dos competiciones a la
//      misma hora NO entran: los 3 torneos del Día Deportivo corren en
//      paralelo a propósito y un alumno puede estar en más de uno (ver
//      03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md), y el Escenario
//      de Talentos admite varias participaciones. Un taller contra el Concurso
//      del Conocimiento sí entra: el Día Académico exige exclusividad.
//
//   2. `equipo_duplicado` — dos equipos de la MISMA competición, que es un
//      alumno inscrito dos veces al mismo torneo (el problema que se dio en
//      producción antes del bloqueo por competición, ver
//      app/inscripciones/includes/crear-equipo-deportivo.php). Se exceptúa el
//      Escenario de Talentos, donde participar en varios actos es la regla.

/** Horario del compromiso en el formato del proyecto ("10:30 – 12:30"). */
function conflictoHorario(string $horaInicio, string $horaFin): string
{
    return substr($horaInicio, 0, 5) . ' – ' . substr($horaFin, 0, 5);
}

/**
 * Todos los compromisos de cada alumno —inscripciones a eventos y lugares en
 * equipos— en una sola lista por alumno, que es lo que hace falta para
 * comparar unos contra otros.
 *
 * Solo las filas de `integrantes` con tipo = 'alumno': las de padre/madre
 * llevan el id_alumno del hijo (ver schema.sql) y no comprometen su horario.
 *
 * @return array<int, list<array<string, mixed>>>
 */
function compromisosPorAlumno(PDO $pdo): array
{
    $consultas = [
        "SELECT i.id_alumno, 'evento' AS clase, e.id, e.dia, e.hora_inicio, e.hora_fin,
                e.nombre, e.tipo, e.espacio AS detalle, NULL AS id_equipo
         FROM inscripciones i
         JOIN eventos e ON e.id = i.id_evento",
        "SELECT it.id_alumno, 'competicion' AS clase, c.id, c.dia, c.hora_inicio, c.hora_fin,
                c.nombre, c.tipo, eq.nombre AS detalle, eq.id AS id_equipo
         FROM integrantes it
         JOIN equipos eq ON eq.id = it.id_equipo
         JOIN competiciones c ON c.id = eq.id_competicion
         WHERE it.tipo = 'alumno'",
    ];

    $compromisos = [];
    foreach ($consultas as $sql) {
        foreach ($pdo->query($sql)->fetchAll() as $fila) {
            $compromisos[(int) $fila['id_alumno']][] = [
                'clase' => (string) $fila['clase'],
                'id' => (int) $fila['id'],
                'id_equipo' => $fila['id_equipo'] !== null ? (int) $fila['id_equipo'] : null,
                'dia' => (string) $fila['dia'],
                'dia_label' => diaEventoLabel((string) $fila['dia']),
                'hora_inicio' => (string) $fila['hora_inicio'],
                'hora_fin' => (string) $fila['hora_fin'],
                'horario' => conflictoHorario((string) $fila['hora_inicio'], (string) $fila['hora_fin']),
                'nombre' => (string) $fila['nombre'],
                'tipo' => (string) $fila['tipo'],
                'detalle' => (string) $fila['detalle'],
            ];
        }
    }

    return $compromisos;
}

/** ¿El Escenario de Talentos? Ahí sí se vale repetir (ver nota del encabezado). */
function conflictoCompeticionLibre(array $compromiso): bool
{
    return $compromiso['clase'] === 'competicion'
        && $compromiso['dia'] === 'cultural'
        && $compromiso['tipo'] === 'concurso';
}

/**
 * Reporte completo, un renglón por par de compromisos que no pueden coexistir,
 * agrupado por alumno.
 *
 * El cruce se hace en PHP y no con un self-join en SQL: son pocos compromisos
 * por alumno (dos o tres) y así la regla de qué se vale y qué no queda escrita
 * en un solo lugar legible, al lado del comentario que la explica.
 *
 * @return array{alumnos: list<array<string, mixed>>, total_conflictos: int, padron: int}
 */
function conflictosDeInscripcion(PDO $pdo): array
{
    $alumnos = $pdo->query(
        'SELECT id, numero_cuenta, nombre_completo, grado, grupo, correo_institucional
         FROM alumnos ORDER BY grado, grupo, nombre_completo'
    )->fetchAll();

    $compromisos = compromisosPorAlumno($pdo);

    $conflictivos = [];
    $totalConflictos = 0;

    foreach ($alumnos as $alumno) {
        $mios = $compromisos[(int) $alumno['id']] ?? [];
        if (count($mios) < 2) {
            continue;
        }

        $conflictos = [];
        for ($i = 0; $i < count($mios); $i++) {
            for ($j = $i + 1; $j < count($mios); $j++) {
                $a = $mios[$i];
                $b = $mios[$j];

                // Dos equipos de la misma competición: está inscrito dos veces
                // al mismo torneo. Se revisa antes del horario porque ahí el
                // horario es idéntico por definición.
                if (
                    $a['clase'] === 'competicion' && $b['clase'] === 'competicion'
                    && $a['id'] === $b['id'] && $a['id_equipo'] !== $b['id_equipo']
                ) {
                    if (conflictoCompeticionLibre($a)) {
                        continue;
                    }
                    $conflictos[] = [
                        'tipo' => 'equipo_duplicado',
                        'etiqueta' => 'Dos equipos de la misma competición',
                        'dia' => $a['dia'],
                        'dia_label' => $a['dia_label'],
                        'horario' => $a['horario'],
                        'a' => $a,
                        'b' => $b,
                    ];
                    continue;
                }

                if ($a['dia'] !== $b['dia']) {
                    continue;
                }
                // Dos competiciones distintas a la misma hora están permitidas
                // (los 3 torneos del Día Deportivo); el traslape solo es un
                // problema si al menos uno de los dos es un evento.
                if ($a['clase'] === 'competicion' && $b['clase'] === 'competicion') {
                    continue;
                }
                if (!($a['hora_inicio'] < $b['hora_fin'] && $a['hora_fin'] > $b['hora_inicio'])) {
                    continue;
                }

                $conflictos[] = [
                    'tipo' => 'traslape',
                    'etiqueta' => $a['clase'] === $b['clase']
                        ? 'Dos eventos a la misma hora'
                        : 'Evento y competición a la misma hora',
                    'dia' => $a['dia'],
                    'dia_label' => $a['dia_label'],
                    'horario' => $a['horario'] === $b['horario'] ? $a['horario'] : $a['horario'] . ' / ' . $b['horario'],
                    'a' => $a,
                    'b' => $b,
                ];
            }
        }

        if ($conflictos === []) {
            continue;
        }

        $alumno['grado_grupo'] = $alumno['grado'] . '°' . $alumno['grupo'];
        $alumno['conflictos'] = $conflictos;
        $conflictivos[] = $alumno;
        $totalConflictos += count($conflictos);
    }

    return [
        'alumnos' => $conflictivos,
        'total_conflictos' => $totalConflictos,
        'padron' => count($alumnos),
    ];
}

/**
 * Los mismos conflictos pero contados por par de actividades, que es la vista
 * que sirve para decidir: dice cuántos alumnos choca cada pareja y, por lo
 * tanto, a cuánta gente hay que mover si se reacomoda una de las dos.
 *
 * @param array{alumnos: list<array<string, mixed>>, total_conflictos: int, padron: int} $reporte
 * @return list<array<string, mixed>>
 */
function conflictosPorActividad(array $reporte): array
{
    $pares = [];
    foreach ($reporte['alumnos'] as $alumno) {
        foreach ($alumno['conflictos'] as $conflicto) {
            // Clave con las dos actividades ordenadas, para que A-B y B-A sean
            // el mismo renglón.
            $extremos = [
                $conflicto['a']['clase'] . '-' . $conflicto['a']['id'] . '-' . ($conflicto['a']['id_equipo'] ?? ''),
                $conflicto['b']['clase'] . '-' . $conflicto['b']['id'] . '-' . ($conflicto['b']['id_equipo'] ?? ''),
            ];
            sort($extremos);
            $clave = implode('|', $extremos);

            if (!isset($pares[$clave])) {
                $pares[$clave] = [
                    'tipo' => $conflicto['tipo'],
                    'etiqueta' => $conflicto['etiqueta'],
                    'dia_label' => $conflicto['dia_label'],
                    'horario' => $conflicto['horario'],
                    'a' => $conflicto['a'],
                    'b' => $conflicto['b'],
                    'alumnos' => 0,
                ];
            }
            $pares[$clave]['alumnos']++;
        }
    }

    $lista = array_values($pares);
    usort($lista, static fn(array $x, array $y): int => $y['alumnos'] <=> $x['alumnos']);

    return $lista;
}
