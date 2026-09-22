<?php
declare(strict_types=1);

require_once __DIR__ . '/dias.php';

// Cupo de las ponencias y talleres (Día Académico y Día Cultural) y quién está
// inscrito en cada uno, compartido por el dashboard (public/index.php) y por
// sus descargas (includes/exportar-inscripciones.php) — mismo criterio que
// includes/equipos-competicion.php y includes/tallas-camisa.php: el archivo
// que se descarga tiene que decir exactamente lo mismo que la pantalla.
//
// El Día Deportivo no aparece nunca porque se organiza todo por equipo y no
// tiene filas en `eventos` (ver el ENUM de eventos.dia en schema.sql); su
// equivalente es equipos-competicion.php.

/** Horario del evento en el formato del proyecto ("08:00 – 09:00"). */
function cupoEventoHorario(?string $horaInicio, ?string $horaFin): string
{
    if ($horaInicio === null || $horaFin === null) {
        return '—';
    }
    return substr($horaInicio, 0, 5) . ' – ' . substr($horaFin, 0, 5);
}

/**
 * Estado de la inscripción de una persona a su evento: la fila existe desde
 * que se inscribió, así que "sin llegar" no es lo mismo que "no inscrito".
 */
function cupoEventoEstadoInscripcion(?string $horaEntrada, ?string $horaSalida): string
{
    if ($horaEntrada === null) {
        return 'Sin llegar';
    }
    return $horaSalida === null ? 'Presente' : 'Salió';
}

/**
 * Eventos con su cupo ya calculado (ocupados y porcentaje), en el orden
 * cronológico de cada día. Con $idEvento se limita a uno solo.
 *
 * `ocupados` se deriva de cupo_maximo - cupo_disponible y no de contar
 * inscripciones: cupo_disponible es la columna que la app decrementa al
 * inscribir, y es la que manda para saber si todavía cabe alguien.
 *
 * @return list<array<string, mixed>>
 */
function eventosConCupo(PDO $pdo, ?int $idEvento = null): array
{
    $consulta = $pdo->prepare(
        'SELECT id, dia, tipo, nombre, facilitador, responsable, espacio,
                hora_inicio, hora_fin, cupo_maximo, cupo_disponible
         FROM eventos'
        . ($idEvento !== null ? ' WHERE id = :id' : '') .
        ' ORDER BY dia, hora_inicio, id'
    );
    $consulta->execute($idEvento !== null ? ['id' => $idEvento] : []);

    $eventos = [];
    foreach ($consulta->fetchAll() as $evento) {
        $cupoMaximo = (int) $evento['cupo_maximo'];
        $ocupados = $cupoMaximo - (int) $evento['cupo_disponible'];

        $evento['id'] = (int) $evento['id'];
        $evento['cupo_maximo'] = $cupoMaximo;
        $evento['cupo_disponible'] = (int) $evento['cupo_disponible'];
        $evento['ocupados'] = $ocupados;
        $evento['porcentaje'] = $cupoMaximo > 0 ? (int) round($ocupados / $cupoMaximo * 100) : 0;
        $evento['horario'] = cupoEventoHorario($evento['hora_inicio'], $evento['hora_fin']);
        $evento['dia_label'] = diaEventoLabel((string) $evento['dia']);
        $eventos[] = $evento;
    }

    return $eventos;
}

/**
 * Inscritos de cada evento, indexados por id de evento. Una sola consulta para
 * todos los eventos y el agrupado en PHP, no una consulta por evento dentro
 * del ciclo — mismo criterio que equiposDeCompeticiones().
 *
 * @return array<int, list<array<string, mixed>>>
 */
function inscritosDeEventos(PDO $pdo, ?int $idEvento = null): array
{
    $consulta = $pdo->prepare(
        'SELECT i.id_evento, a.numero_cuenta, a.nombre_completo, a.grado, a.grupo,
                i.origen, i.registrado_por, i.fecha_registro,
                i.hora_entrada, i.punto_control_entrada, i.escaneado_por_entrada,
                i.hora_salida, i.punto_control_salida, i.escaneado_por_salida
         FROM inscripciones i
         JOIN alumnos a ON a.id = i.id_alumno'
        . ($idEvento !== null ? ' WHERE i.id_evento = :id' : '') .
        ' ORDER BY i.id_evento, a.nombre_completo'
    );
    $consulta->execute($idEvento !== null ? ['id' => $idEvento] : []);

    $inscritosPorEvento = [];
    foreach ($consulta->fetchAll() as $inscrito) {
        $inscrito['grado_grupo'] = $inscrito['grado'] . '°' . $inscrito['grupo'];
        $inscrito['origen_label'] = $inscrito['origen'] === 'previo' ? 'Previo' : 'Orden de llegada';
        $inscrito['estado'] = cupoEventoEstadoInscripcion($inscrito['hora_entrada'], $inscrito['hora_salida']);
        $inscritosPorEvento[(int) $inscrito['id_evento']][] = $inscrito;
    }

    return $inscritosPorEvento;
}
