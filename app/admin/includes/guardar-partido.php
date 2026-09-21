<?php
declare(strict_types=1);

// Captura el resultado de un partido de la llave (quién ganó, marcador) y/o
// lo programa (hora y cancha). Tras guardar, el ganador se lleva a su casilla
// de la ronda siguiente — ver llavesPropagar en llaves.php.

require __DIR__ . '/sesion.php';
require_once __DIR__ . '/llaves.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php');
    exit;
}

$idPartido = (int) ($_POST['id_partido'] ?? 0);
if ($idPartido <= 0) {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php?error=partido_no_encontrado');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare(
    'SELECT id, id_competicion, ronda, posicion, id_equipo_a, id_equipo_b FROM partidos WHERE id = :id'
);
$consulta->execute(['id' => $idPartido]);
$partido = $consulta->fetch();
if ($partido === false) {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php?error=partido_no_encontrado');
    exit;
}

$idCompeticion = (int) $partido['id_competicion'];

function volverALlave(int $idCompeticion, string $parametro): never
{
    header('Location: ' . BASE_URL . '/admin/public/llave.php?id=' . $idCompeticion . '&' . $parametro);
    exit;
}

if (llavesEsBye($partido)) {
    volverALlave($idCompeticion, 'error=es_bye');
}

// --- Hora y cancha: se pueden fijar aunque no se sepa todavía quién juega --
$horaTexto = trim((string) ($_POST['hora_programada'] ?? ''));
$hora = null;
if ($horaTexto !== '') {
    $horaValida = DateTime::createFromFormat('H:i', $horaTexto);
    if ($horaValida === false || $horaValida->format('H:i') !== $horaTexto) {
        volverALlave($idCompeticion, 'error=hora_invalida');
    }
    $hora = $horaTexto . ':00';
}

$cancha = trim((string) ($_POST['cancha'] ?? ''));
if (mb_strlen($cancha) > 100) {
    $cancha = mb_substr($cancha, 0, 100);
}
$cancha = $cancha === '' ? null : $cancha;

// --- Resultado -------------------------------------------------------------
// El bloque del ganador solo se dibuja cuando ya se conocen los dos equipos
// (ver llave.php), así que su ausencia en el POST significa "este envío solo
// programa el partido" y no "borra el resultado".
$capturaResultado = array_key_exists('ganador', $_POST);
$ganador = null;
$marcadorA = null;
$marcadorB = null;

if ($capturaResultado) {
    if ($partido['id_equipo_a'] === null || $partido['id_equipo_b'] === null) {
        volverALlave($idCompeticion, 'error=partido_incompleto');
    }

    $ganadorTexto = trim((string) $_POST['ganador']);
    if ($ganadorTexto !== '') {
        $ganador = (int) $ganadorTexto;
        // El ganador tiene que ser uno de los dos equipos de ESTE partido: de
        // otro modo un POST manipulado colaría a cualquier equipo en la ronda
        // siguiente. La base lo vuelve a checar (chk_partidos_ganador).
        if ($ganador !== (int) $partido['id_equipo_a'] && $ganador !== (int) $partido['id_equipo_b']) {
            volverALlave($idCompeticion, 'error=ganador_invalido');
        }
    }

    foreach (['a', 'b'] as $lado) {
        $texto = trim((string) ($_POST['marcador_' . $lado] ?? ''));
        if ($texto === '') {
            continue;
        }
        if (!ctype_digit($texto) || (int) $texto > 999) {
            volverALlave($idCompeticion, 'error=marcador_invalido');
        }
        if ($lado === 'a') {
            $marcadorA = (int) $texto;
        } else {
            $marcadorB = (int) $texto;
        }
    }
}

try {
    $pdo->beginTransaction();

    if ($capturaResultado) {
        $actualizar = $pdo->prepare(
            'UPDATE partidos SET id_equipo_ganador = :ganador, marcador_a = :marcador_a, marcador_b = :marcador_b,
                    hora_programada = :hora, cancha = :cancha
             WHERE id = :id'
        );
        $actualizar->execute([
            'ganador' => $ganador,
            'marcador_a' => $marcadorA,
            'marcador_b' => $marcadorB,
            'hora' => $hora,
            'cancha' => $cancha,
            'id' => $idPartido,
        ]);

        llavesPropagar($pdo, $idCompeticion, (int) $partido['ronda'], (int) $partido['posicion'], $ganador);
    } else {
        $actualizar = $pdo->prepare('UPDATE partidos SET hora_programada = :hora, cancha = :cancha WHERE id = :id');
        $actualizar->execute(['hora' => $hora, 'cancha' => $cancha, 'id' => $idPartido]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error guardando el partido ' . $idPartido . ': ' . $e->getMessage());
    volverALlave($idCompeticion, 'error=error_guardando');
}

volverALlave($idCompeticion, 'msg=' . ($capturaResultado ? 'resultado_guardado' : 'partido_actualizado'));
