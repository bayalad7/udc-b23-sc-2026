<?php
declare(strict_types=1);

// Padrón de equipos de cada competición (quiénes son, de qué color, quién los
// capitanea y de quién se componen), compartido por el dashboard
// (public/index.php) y por sus descargas (includes/exportar-equipos.php) —
// mismo criterio que includes/tallas-camisa.php: el archivo que se descarga
// tiene que decir exactamente lo mismo que la pantalla, así que los dos leen
// de aquí y no cada uno por su cuenta.

require_once __DIR__ . '/dias.php';

/**
 * Grado y grupo en el formato corto que se usa en todo el panel ("3°B").
 * Devuelve null cuando la fila no corresponde a un alumno: los padres y madres
 * de familia comparten el id_alumno del hijo (ver el comentario de la tabla
 * `integrantes` en schema.sql), así que copiarles su grado y grupo los pondría
 * como si estuvieran inscritos en ese grupo.
 */
function equiposGradoGrupo(?string $grado, ?string $grupo): ?string
{
    if ($grado === null || $grupo === null) {
        return null;
    }
    return $grado . '°' . $grupo;
}

/**
 * Catálogo de competiciones con cuántos equipos lleva cada una, en el orden
 * cronológico del evento. Con $idCompeticion se limita a una sola.
 *
 * @return list<array<string, mixed>>
 */
function competicionesConEquipos(PDO $pdo, ?int $idCompeticion = null): array
{
    $filtro = $idCompeticion !== null ? ' WHERE c.id = :id' : '';
    $consulta = $pdo->prepare(
        "SELECT c.id, c.nombre, c.dia, c.tipo, c.max_equipos, c.tam_equipo, COUNT(e.id) AS total_equipos
         FROM competiciones c
         LEFT JOIN equipos e ON e.id_competicion = c.id" . $filtro . "
         GROUP BY c.id, c.nombre, c.dia, c.tipo, c.max_equipos, c.tam_equipo
         ORDER BY c.dia, c.hora_inicio"
    );
    $consulta->execute($idCompeticion !== null ? ['id' => $idCompeticion] : []);

    return $consulta->fetchAll();
}

/**
 * Equipos de cada competición con sus integrantes, indexados por id de
 * competición. Son DOS consultas —todos los equipos y de golpe todos sus
 * integrantes— que se agrupan en PHP, no una consulta de integrantes por
 * equipo dentro del ciclo: con 5 competiciones de hasta 16 equipos serían
 * decenas de idas a la base solo para pintar un modal.
 *
 * @return array<int, list<array<string, mixed>>>
 */
function equiposDeCompeticiones(PDO $pdo, ?int $idCompeticion = null): array
{
    $parametros = $idCompeticion !== null ? ['id' => $idCompeticion] : [];

    $consultaEquipos = $pdo->prepare(
        'SELECT eq.id, eq.id_competicion, eq.nombre, eq.color_camisa,
                a.nombre_completo AS capitan, a.numero_cuenta AS capitan_cuenta,
                a.grado AS capitan_grado, a.grupo AS capitan_grupo
         FROM equipos eq
         JOIN alumnos a ON a.id = eq.id_alumno_capitan'
        . ($idCompeticion !== null ? ' WHERE eq.id_competicion = :id' : '') .
        ' ORDER BY eq.id_competicion, eq.fecha_registro, eq.id'
    );
    $consultaEquipos->execute($parametros);
    $filasEquipos = $consultaEquipos->fetchAll();

    if ($filasEquipos === []) {
        return [];
    }

    // El grado y el grupo salen del alumno "ancla" de la fila, pero solo se
    // conservan cuando la fila ES el alumno (ver equiposGradoGrupo).
    $consultaIntegrantes = $pdo->prepare(
        'SELECT it.id_equipo, it.tipo, it.nombre, it.codigo_participante,
                it.hora_entrada, it.hora_salida,
                CASE WHEN it.tipo = \'alumno\' THEN a.grado END AS grado,
                CASE WHEN it.tipo = \'alumno\' THEN a.grupo END AS grupo,
                CASE WHEN it.tipo = \'alumno\' THEN a.numero_cuenta END AS numero_cuenta
         FROM integrantes it
         JOIN alumnos a ON a.id = it.id_alumno
         JOIN equipos eq ON eq.id = it.id_equipo'
        . ($idCompeticion !== null ? ' WHERE eq.id_competicion = :id' : '') .
        ' ORDER BY it.id_equipo, FIELD(it.tipo, "alumno", "padre", "madre"), it.nombre'
    );
    $consultaIntegrantes->execute($parametros);

    $integrantesPorEquipo = [];
    foreach ($consultaIntegrantes->fetchAll() as $integrante) {
        $integrantesPorEquipo[(int) $integrante['id_equipo']][] = $integrante;
    }

    $equiposPorCompeticion = [];
    foreach ($filasEquipos as $equipoFila) {
        $equipoFila['integrantes'] = $integrantesPorEquipo[(int) $equipoFila['id']] ?? [];
        $equiposPorCompeticion[(int) $equipoFila['id_competicion']][] = $equipoFila;
    }

    return $equiposPorCompeticion;
}
