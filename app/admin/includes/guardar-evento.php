<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/public/eventos.php');
    exit;
}

function volverConError(?int $id, string $codigo): never
{
    $destino = $id !== null ? '/admin/public/evento.php?id=' . $id : '/admin/public/evento.php?nuevo=1';
    header('Location: ' . $destino . '&error=' . urlencode($codigo));
    exit;
}

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

$dia = trim((string) ($_POST['dia'] ?? ''));
$tipo = trim((string) ($_POST['tipo'] ?? ''));
$horaInicio = trim((string) ($_POST['hora_inicio'] ?? ''));
$horaFin = trim((string) ($_POST['hora_fin'] ?? ''));
$facilitador = trim((string) ($_POST['facilitador'] ?? ''));
$nombre = trim((string) ($_POST['nombre'] ?? ''));
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
$espacio = trim((string) ($_POST['espacio'] ?? ''));
$cupoMaximo = (int) ($_POST['cupo_maximo'] ?? 0);
$responsable = trim((string) ($_POST['responsable'] ?? ''));

if (!in_array($dia, ['academico', 'cultural'], true) || !in_array($tipo, ['ponencia', 'taller'], true)) {
    volverConError($id, 'campos_incompletos');
}
if ($nombre === '' || $facilitador === '' || $espacio === '' || $responsable === '' || $descripcion === '') {
    volverConError($id, 'campos_incompletos');
}
if (mb_strlen($nombre) > 150 || mb_strlen($descripcion) > 150 || mb_strlen($facilitador) > 150
    || mb_strlen($espacio) > 100 || mb_strlen($responsable) > 150) {
    volverConError($id, 'campos_incompletos');
}
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaInicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaFin)) {
    volverConError($id, 'campos_incompletos');
}
// Validación de hora_fin > hora_inicio en servidor — no solo el CHECK de BD.
if ($horaFin <= $horaInicio) {
    volverConError($id, 'horario_invalido');
}
if ($cupoMaximo < 1) {
    volverConError($id, 'cupo_invalido');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

if ($id === null) {
    // Nota: EMULATE_PREPARES está deshabilitado (ver config/db.php), así que
    // MySQL no permite reutilizar el mismo placeholder con nombre dos veces —
    // cupo_disponible necesita su propio marcador aunque valga lo mismo que
    // cupo_maximo al crear el evento.
    $insertar = $pdo->prepare(
        'INSERT INTO eventos (dia, tipo, hora_inicio, hora_fin, facilitador, nombre, descripcion, espacio, cupo_maximo, cupo_disponible, responsable)
         VALUES (:dia, :tipo, :hora_inicio, :hora_fin, :facilitador, :nombre, :descripcion, :espacio, :cupo_maximo, :cupo_disponible, :responsable)'
    );
    $insertar->execute([
        'dia' => $dia, 'tipo' => $tipo, 'hora_inicio' => $horaInicio, 'hora_fin' => $horaFin,
        'facilitador' => $facilitador, 'nombre' => $nombre, 'descripcion' => $descripcion, 'espacio' => $espacio,
        'cupo_maximo' => $cupoMaximo, 'cupo_disponible' => $cupoMaximo, 'responsable' => $responsable,
    ]);
    $idNuevo = (int) $pdo->lastInsertId();
    header('Location: /admin/public/evento.php?id=' . $idNuevo . '&msg=creado');
    exit;
}

// cupo_disponible siempre se recalcula desde los inscritos reales (nunca se
// confía en un valor enviado por el cliente) — así nunca queda negativo ni
// desincronizado si el cupo_maximo cambia.
$consultaInscritos = $pdo->prepare('SELECT COUNT(*) AS n FROM inscripciones WHERE id_evento = :id');
$consultaInscritos->execute(['id' => $id]);
$inscritos = (int) $consultaInscritos->fetch()['n'];

if ($cupoMaximo < $inscritos) {
    volverConError($id, 'cupo_menor_a_inscritos');
}

$actualizar = $pdo->prepare(
    'UPDATE eventos SET dia = :dia, tipo = :tipo, hora_inicio = :hora_inicio, hora_fin = :hora_fin,
        facilitador = :facilitador, nombre = :nombre, descripcion = :descripcion, espacio = :espacio,
        cupo_maximo = :cupo_maximo, cupo_disponible = :cupo_disponible, responsable = :responsable
     WHERE id = :id'
);
$actualizar->execute([
    'dia' => $dia, 'tipo' => $tipo, 'hora_inicio' => $horaInicio, 'hora_fin' => $horaFin,
    'facilitador' => $facilitador, 'nombre' => $nombre, 'descripcion' => $descripcion, 'espacio' => $espacio,
    'cupo_maximo' => $cupoMaximo, 'cupo_disponible' => $cupoMaximo - $inscritos, 'responsable' => $responsable,
    'id' => $id,
]);

header('Location: /admin/public/evento.php?id=' . $id . '&msg=actualizado');
exit;
