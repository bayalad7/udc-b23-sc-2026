<?php
declare(strict_types=1);

// Genera (o regenera) la llave de eliminación directa de una competición con
// los equipos que tenga inscritos EN ESTE MOMENTO. La aritmética del cuadro
// y la escritura viven en llaves.php; aquí solo se valida la petición y se
// decide en qué orden entran los equipos.
//
// No se acepta una lista de equipos por POST a propósito: los equipos se
// leen de la base. Así ni un POST manipulado ni una pestaña vieja pueden
// meter a la llave un equipo de otra competición o dejar fuera a uno
// inscrito.

require __DIR__ . '/sesion.php';
require_once __DIR__ . '/llaves.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php');
    exit;
}

$idCompeticion = (int) ($_POST['id_competicion'] ?? 0);
if ($idCompeticion <= 0) {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php?error=no_encontrado');
    exit;
}

function volverALlave(int $idCompeticion, string $parametro): never
{
    header('Location: ' . BASE_URL . '/admin/public/llave.php?id=' . $idCompeticion . '&' . $parametro);
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consulta = $pdo->prepare('SELECT id FROM competiciones WHERE id = :id');
$consulta->execute(['id' => $idCompeticion]);
if ($consulta->fetch() === false) {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php?error=no_encontrado');
    exit;
}

// Regenerar es destructivo (borra los resultados capturados), así que tiene
// que venir pedido explícitamente desde la zona roja de llave.php — no basta
// con volver a mandar el formulario de "Generar".
$regenerar = ($_POST['regenerar'] ?? '') === '1';

$consultaExiste = $pdo->prepare('SELECT COUNT(*) AS n FROM partidos WHERE id_competicion = :id');
$consultaExiste->execute(['id' => $idCompeticion]);
if ((int) $consultaExiste->fetch()['n'] > 0 && !$regenerar) {
    volverALlave($idCompeticion, 'error=ya_existe');
}

$consultaEquipos = $pdo->prepare(
    'SELECT id FROM equipos WHERE id_competicion = :id ORDER BY fecha_registro, id'
);
$consultaEquipos->execute(['id' => $idCompeticion]);
$idsEquipos = array_map('intval', array_column($consultaEquipos->fetchAll(), 'id'));

// Con un solo equipo no hay enfrentamiento que armar. El tope de la
// competición no importa aquí: la llave se arma con los que se hayan
// inscrito, sean 3 o los 16 permitidos.
if (count($idsEquipos) < 2) {
    volverALlave($idCompeticion, 'error=sin_equipos');
}

// El orden de entrada ES el sembrado (ver llavesArmarCuadro): quien quede
// primero recibe el primer pase directo si sobran lugares en el cuadro. Sin
// un ranking previo de equipos, el sorteo es lo justo; el orden de
// inscripción queda como alternativa para cuando se quiera algo reproducible.
$orden = ($_POST['orden'] ?? 'sorteo') === 'inscripcion' ? 'inscripcion' : 'sorteo';
if ($orden === 'sorteo') {
    shuffle($idsEquipos);
}

try {
    llavesGenerar($pdo, $idCompeticion, $idsEquipos);
} catch (Throwable $e) {
    error_log('Error generando la llave de la competición ' . $idCompeticion . ': ' . $e->getMessage());
    volverALlave($idCompeticion, 'error=error_generando');
}

volverALlave($idCompeticion, 'msg=llave_generada');
