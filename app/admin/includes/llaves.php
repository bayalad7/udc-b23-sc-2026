<?php
declare(strict_types=1);

// Lógica compartida de las llaves (brackets) de eliminación directa: la
// aritmética del cuadro, la generación y el avance de los ganadores. Vive
// aparte de las páginas porque la usan tanto app/admin/public/llave.php
// (dibujar la llave) como los includes que escriben (generar-llaves.php,
// guardar-partido.php, eliminar-llaves.php).
//
// Este archivo NO se llama por HTTP — solo se incluye desde PHP — así que no
// lleva entrada en includes/.htaccess (que es un allowlist, ver la nota en
// CLAUDE.md).
//
// El modelo de datos está documentado en la tabla `partidos` de
// database/schema.sql; lo único que hay que tener en la cabeza para leer
// este archivo:
//
//   - (ronda, posicion) es la casilla del partido. Ronda 1 es la primera y
//     la ronda más alta es la final.
//   - El ganador de (R, P) pasa a (R+1, CEIL(P/2)), al lado A si P es impar
//     y al lado B si P es par.
//   - Como el número de equipos inscritos casi nunca es potencia de 2 (el
//     tope por torneo es un máximo, no una meta — ver
//     03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md), la llave se
//     rellena con "byes": partidos de primera ronda con un solo equipo, que
//     pasa directo sin jugar.

/**
 * Cuántas rondas necesita una eliminación directa con esta cantidad de
 * equipos: el exponente de la potencia de 2 inmediata superior. 5 equipos →
 * 3 rondas (cuadro de 8), 16 equipos → 4 rondas.
 */
function llavesRondasNecesarias(int $equipos): int
{
    $rondas = 0;
    $tamano = 1;
    while ($tamano < $equipos) {
        $tamano *= 2;
        $rondas++;
    }

    return $rondas;
}

/** Tamaño del cuadro (potencia de 2) en el que cabe esta cantidad de equipos. */
function llavesTamanoCuadro(int $equipos): int
{
    return 2 ** llavesRondasNecesarias($equipos);
}

/**
 * Orden de siembra estándar de un cuadro de eliminación directa: devuelve,
 * para cada casilla del cuadro (de arriba hacia abajo), qué número de
 * sembrado va ahí. Para un cuadro de 8: [1, 8, 4, 5, 2, 7, 3, 6] — o sea los
 * partidos 1vs8, 4vs5, 2vs7 y 3vs6.
 *
 * Se construye duplicando el cuadro anterior y reflejando cada sembrado
 * (s → tamaño+1-s), que es la forma clásica de conseguir dos cosas a la vez:
 * los dos primeros sembrados solo se cruzan en la final, y los byes (los
 * sembrados que sobran cuando hay menos equipos que casillas) caen repartidos
 * en mitades distintas del cuadro en vez de amontonarse arriba.
 */
function llavesOrdenSiembra(int $tamanoCuadro): array
{
    $orden = [1];
    while (count($orden) < $tamanoCuadro) {
        $tamano = count($orden) * 2;
        $nuevo = [];
        foreach ($orden as $sembrado) {
            $nuevo[] = $sembrado;
            $nuevo[] = $tamano + 1 - $sembrado;
        }
        $orden = $nuevo;
    }

    return $orden;
}

/**
 * Nombre de la ronda según lo lejos que esté de la final, no según su número:
 * en un cuadro de 8 la ronda 1 son los "Cuartos de final" y en uno de 16 son
 * los "Octavos". Por eso hace falta el total de rondas de ESA competición.
 */
function llavesNombreRonda(int $ronda, int $totalRondas): string
{
    $faltan = $totalRondas - $ronda;

    return match ($faltan) {
        0 => 'Final',
        1 => 'Semifinales',
        2 => 'Cuartos de final',
        3 => 'Octavos de final',
        4 => 'Dieciseisavos de final',
        default => 'Ronda ' . $ronda,
    };
}

/**
 * Arma el cuadro completo en memoria a partir de los equipos YA ordenados
 * (el orden de entrada es el sembrado: quien venga primero es el sembrado 1).
 * Devuelve [ronda => [posicion => ['a' => ?int, 'b' => ?int, 'ganador' => ?int]]].
 *
 * Solo la primera ronda nace con equipos; las demás se llenan con los
 * ganadores conforme se juegue — salvo los byes, que ya se conocen desde
 * ahora y por eso aparecen como ganador en la ronda 1 y como equipo puesto
 * en la ronda 2.
 */
function llavesArmarCuadro(array $idsEquipos): array
{
    $total = count($idsEquipos);
    $totalRondas = llavesRondasNecesarias($total);
    $tamanoCuadro = 2 ** $totalRondas;
    $siembra = llavesOrdenSiembra($tamanoCuadro);

    $cuadro = [];

    for ($posicion = 1; $posicion <= intdiv($tamanoCuadro, 2); $posicion++) {
        // Un sembrado mayor al número de equipos inscritos es una casilla
        // vacía: el rival de ese lado no existe (bye).
        $sembradoA = $siembra[2 * $posicion - 2];
        $sembradoB = $siembra[2 * $posicion - 1];
        $equipoA = $sembradoA <= $total ? $idsEquipos[$sembradoA - 1] : null;
        $equipoB = $sembradoB <= $total ? $idsEquipos[$sembradoB - 1] : null;

        // La siembra estándar nunca deja las dos casillas de un mismo partido
        // vacías (siempre hay menos byes que partidos), así que aquí "uno de
        // los dos es null" significa exactamente "pase directo".
        $ganador = null;
        if (($equipoA === null) xor ($equipoB === null)) {
            $ganador = $equipoA ?? $equipoB;
        }

        $cuadro[1][$posicion] = ['a' => $equipoA, 'b' => $equipoB, 'ganador' => $ganador];
    }

    for ($ronda = 2; $ronda <= $totalRondas; $ronda++) {
        $partidosRonda = intdiv($tamanoCuadro, 2 ** $ronda);
        for ($posicion = 1; $posicion <= $partidosRonda; $posicion++) {
            $cuadro[$ronda][$posicion] = [
                'a' => $cuadro[$ronda - 1][2 * $posicion - 1]['ganador'],
                'b' => $cuadro[$ronda - 1][2 * $posicion]['ganador'],
                'ganador' => null,
            ];
        }
    }

    return $cuadro;
}

/**
 * Borra la llave anterior (si la hay) y graba una nueva con estos equipos.
 * Todo dentro de una transacción: una llave a medio generar sería peor que
 * no tener ninguna.
 */
function llavesGenerar(PDO $pdo, int $idCompeticion, array $idsEquipos): void
{
    $cuadro = llavesArmarCuadro(array_values($idsEquipos));

    $pdo->beginTransaction();
    try {
        $borrar = $pdo->prepare('DELETE FROM partidos WHERE id_competicion = :id');
        $borrar->execute(['id' => $idCompeticion]);

        $insertar = $pdo->prepare(
            'INSERT INTO partidos (id_competicion, ronda, posicion, id_equipo_a, id_equipo_b, id_equipo_ganador)
             VALUES (:id_competicion, :ronda, :posicion, :a, :b, :ganador)'
        );
        foreach ($cuadro as $ronda => $partidos) {
            foreach ($partidos as $posicion => $partido) {
                $insertar->execute([
                    'id_competicion' => $idCompeticion,
                    'ronda' => $ronda,
                    'posicion' => $posicion,
                    'a' => $partido['a'],
                    'b' => $partido['b'],
                    'ganador' => $partido['ganador'],
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Lleva al ganador de (ronda, posicion) a su casilla de la ronda siguiente.
 *
 * Si esa casilla ya tenía otro equipo, el resultado que estuviera capturado
 * ahí deja de tener sentido (se habría jugado contra un rival que ya no es
 * el que pasa), así que se limpia y la limpieza sigue en cascada hacia la
 * final. La recursión se corta sola en cuanto encuentra una casilla que ya
 * tiene el valor que le toca — o al llegar a la final, que no tiene ronda
 * siguiente.
 */
function llavesPropagar(PDO $pdo, int $idCompeticion, int $ronda, int $posicion, ?int $idGanador): void
{
    $siguienteRonda = $ronda + 1;
    $siguientePosicion = intdiv($posicion + 1, 2);
    $columna = $posicion % 2 === 1 ? 'id_equipo_a' : 'id_equipo_b';

    $consulta = $pdo->prepare(
        'SELECT id, id_equipo_a, id_equipo_b FROM partidos
         WHERE id_competicion = :id_competicion AND ronda = :ronda AND posicion = :posicion'
    );
    $consulta->execute([
        'id_competicion' => $idCompeticion,
        'ronda' => $siguienteRonda,
        'posicion' => $siguientePosicion,
    ]);
    $siguiente = $consulta->fetch();
    if ($siguiente === false) {
        return; // Era la final: no hay a dónde avanzar.
    }

    $actual = $siguiente[$columna] !== null ? (int) $siguiente[$columna] : null;
    if ($actual === $idGanador) {
        return;
    }

    $actualizar = $pdo->prepare(
        'UPDATE partidos SET ' . $columna . ' = :equipo,
                id_equipo_ganador = NULL, marcador_a = NULL, marcador_b = NULL
         WHERE id = :id'
    );
    $actualizar->execute(['equipo' => $idGanador, 'id' => (int) $siguiente['id']]);

    llavesPropagar($pdo, $idCompeticion, $siguienteRonda, $siguientePosicion, null);
}

/**
 * A qué casilla de la ronda siguiente pasa el ganador de (ronda, posicion), o
 * null si ese partido ES la final. Es la misma aritmética que aplica
 * llavesPropagar, expuesta aparte para poder MOSTRARLA — el PDF de la llave
 * imprime "Gana y pasa a #2.1" debajo de cada partido, que es justo lo que un
 * equipo quiere saber al leer el cuadro.
 */
function llavesSiguienteCasilla(int $ronda, int $posicion, int $totalRondas): ?array
{
    if ($ronda >= $totalRondas) {
        return null;
    }

    return ['ronda' => $ronda + 1, 'posicion' => intdiv($posicion + 1, 2)];
}

/**
 * La llave completa de una competición, agrupada por ronda y con el nombre
 * (y color de camisa) de cada equipo ya resuelto, para que la página solo
 * tenga que pintarla.
 */
function llavesPartidos(PDO $pdo, int $idCompeticion): array
{
    $consulta = $pdo->prepare(
        'SELECT p.id, p.ronda, p.posicion, p.id_equipo_a, p.id_equipo_b, p.id_equipo_ganador,
                p.marcador_a, p.marcador_b, p.hora_programada, p.cancha,
                ea.nombre AS nombre_a, ea.color_camisa AS color_a,
                eb.nombre AS nombre_b, eb.color_camisa AS color_b
         FROM partidos p
         LEFT JOIN equipos ea ON ea.id = p.id_equipo_a
         LEFT JOIN equipos eb ON eb.id = p.id_equipo_b
         WHERE p.id_competicion = :id
         ORDER BY p.ronda, p.posicion'
    );
    $consulta->execute(['id' => $idCompeticion]);

    $porRonda = [];
    foreach ($consulta->fetchAll() as $partido) {
        $porRonda[(int) $partido['ronda']][] = $partido;
    }

    return $porRonda;
}

/**
 * Un partido es un "bye" (pase directo) cuando tiene un solo equipo: no se
 * juega y no admite captura de resultado. Solo puede pasar en la ronda 1 —
 * en las siguientes, una casilla vacía significa "todavía no se sabe quién
 * llega", no que nadie vaya a llegar.
 */
function llavesEsBye(array $partido): bool
{
    return (int) $partido['ronda'] === 1
        && (($partido['id_equipo_a'] === null) xor ($partido['id_equipo_b'] === null));
}

/** El equipo que ganó la final, o null si la llave todavía no termina. */
function llavesCampeon(array $porRonda): ?array
{
    if ($porRonda === []) {
        return null;
    }

    $final = $porRonda[max(array_keys($porRonda))][0] ?? null;
    if ($final === null || $final['id_equipo_ganador'] === null) {
        return null;
    }

    $ganadorEsA = (int) $final['id_equipo_ganador'] === (int) $final['id_equipo_a'];

    return [
        'id' => (int) $final['id_equipo_ganador'],
        'nombre' => $ganadorEsA ? $final['nombre_a'] : $final['nombre_b'],
    ];
}
