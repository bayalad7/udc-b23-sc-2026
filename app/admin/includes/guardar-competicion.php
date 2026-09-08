<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/public/competiciones.php');
    exit;
}

function volverConError(?int $id, string $codigo): never
{
    $destino = $id !== null ? BASE_URL . '/admin/public/competicion.php?id=' . $id : BASE_URL . '/admin/public/competicion.php?nuevo=1';
    header('Location: ' . $destino . '&error=' . urlencode($codigo));
    exit;
}

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

$dia = trim((string) ($_POST['dia'] ?? ''));
$tipo = trim((string) ($_POST['tipo'] ?? ''));
$horaInicio = trim((string) ($_POST['hora_inicio'] ?? ''));
$horaFin = trim((string) ($_POST['hora_fin'] ?? ''));
$nombre = trim((string) ($_POST['nombre'] ?? ''));
$fechaLimite = trim((string) ($_POST['fecha_limite'] ?? ''));
$maxEquiposTexto = trim((string) ($_POST['max_equipos'] ?? ''));
$tamEquipoTexto = trim((string) ($_POST['tam_equipo'] ?? ''));

if (!in_array($dia, ['academico', 'cultural', 'deportivo'], true) || !in_array($tipo, ['concurso', 'torneo'], true)) {
    volverConError($id, 'campos_incompletos');
}
if ($nombre === '' || mb_strlen($nombre) > 150) {
    volverConError($id, 'campos_incompletos');
}
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaInicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaFin)) {
    volverConError($id, 'campos_incompletos');
}
if ($horaFin <= $horaInicio) {
    volverConError($id, 'horario_invalido');
}

// Vacío = sin regla (NULL); si viene, debe ser un entero positivo.
$maxEquipos = null;
if ($maxEquiposTexto !== '') {
    if (!preg_match('/^\d+$/', $maxEquiposTexto) || (int) $maxEquiposTexto < 1) {
        volverConError($id, 'campos_incompletos');
    }
    $maxEquipos = (int) $maxEquiposTexto;
}
$tamEquipo = null;
if ($tamEquipoTexto !== '') {
    if (!preg_match('/^\d+$/', $tamEquipoTexto) || (int) $tamEquipoTexto < 1) {
        volverConError($id, 'campos_incompletos');
    }
    $tamEquipo = (int) $tamEquipoTexto;
}

$fechaLimiteSql = DateTime::createFromFormat('Y-m-d\TH:i', $fechaLimite);
if ($fechaLimiteSql === false) {
    volverConError($id, 'campos_incompletos');
}
$fechaLimiteSql = $fechaLimiteSql->format('Y-m-d H:i:s');

// --- Convocatoria (imagen), opcional — mismo patrón de subida que
// guardar-alumno.php (foto), pero el archivo no es obligatorio: la
// competición puede no tener convocatoria todavía.
$directorioConvocatorias = __DIR__ . '/../../assets/img/convocatorias';
$tiposPermitidosConvocatoria = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
$TAMANO_MAXIMO_CONVOCATORIA = 5 * 1024 * 1024;

$convocatoriaNueva = isset($_FILES['convocatoria']) && $_FILES['convocatoria']['error'] !== UPLOAD_ERR_NO_FILE;
$rutaConvocatoriaRelativa = null;
$rutaConvocatoriaAbsoluta = null;

if ($convocatoriaNueva) {
    if ($_FILES['convocatoria']['error'] !== UPLOAD_ERR_OK || $_FILES['convocatoria']['size'] > $TAMANO_MAXIMO_CONVOCATORIA) {
        volverConError($id, 'convocatoria_invalida');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $_FILES['convocatoria']['tmp_name']);
    finfo_close($finfo);
    if (!isset($tiposPermitidosConvocatoria[$mimeReal])) {
        volverConError($id, 'convocatoria_invalida');
    }
    $extension = $tiposPermitidosConvocatoria[$mimeReal];
    $tokenConvocatoria = bin2hex(random_bytes(16));
    if (!is_dir($directorioConvocatorias)) {
        mkdir($directorioConvocatorias, 0755, true);
    }
    $rutaConvocatoriaRelativa = 'convocatorias/' . $tokenConvocatoria . '.' . $extension;
    $rutaConvocatoriaAbsoluta = $directorioConvocatorias . '/' . $tokenConvocatoria . '.' . $extension;
    if (!move_uploaded_file($_FILES['convocatoria']['tmp_name'], $rutaConvocatoriaAbsoluta)) {
        volverConError($id, 'error_servidor');
    }
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

if ($id === null) {
    $insertar = $pdo->prepare(
        'INSERT INTO competiciones (dia, tipo, hora_inicio, hora_fin, nombre, fecha_limite, max_equipos, tam_equipo, convocatoria)
         VALUES (:dia, :tipo, :hora_inicio, :hora_fin, :nombre, :fecha_limite, :max_equipos, :tam_equipo, :convocatoria)'
    );
    $insertar->execute([
        'dia' => $dia, 'tipo' => $tipo, 'hora_inicio' => $horaInicio, 'hora_fin' => $horaFin,
        'nombre' => $nombre, 'fecha_limite' => $fechaLimiteSql,
        'max_equipos' => $maxEquipos, 'tam_equipo' => $tamEquipo,
        'convocatoria' => $rutaConvocatoriaRelativa,
    ]);
    $idNuevo = (int) $pdo->lastInsertId();
    header('Location: ' . BASE_URL . '/admin/public/competicion.php?id=' . $idNuevo . '&msg=creado');
    exit;
}

if ($convocatoriaNueva) {
    // Se necesita la ruta anterior ANTES del UPDATE para poder borrar el
    // archivo viejo después — una vez actualizada la fila ya no hay forma de
    // saber cuál era.
    $anterior = $pdo->prepare('SELECT convocatoria FROM competiciones WHERE id = :id');
    $anterior->execute(['id' => $id]);
    $convocatoriaAnterior = $anterior->fetch()['convocatoria'] ?? null;

    $actualizar = $pdo->prepare(
        'UPDATE competiciones SET dia = :dia, tipo = :tipo, hora_inicio = :hora_inicio, hora_fin = :hora_fin,
            nombre = :nombre, fecha_limite = :fecha_limite, max_equipos = :max_equipos, tam_equipo = :tam_equipo,
            convocatoria = :convocatoria
         WHERE id = :id'
    );
    $actualizar->execute([
        'dia' => $dia, 'tipo' => $tipo, 'hora_inicio' => $horaInicio, 'hora_fin' => $horaFin,
        'nombre' => $nombre, 'fecha_limite' => $fechaLimiteSql, 'id' => $id,
        'max_equipos' => $maxEquipos, 'tam_equipo' => $tamEquipo,
        'convocatoria' => $rutaConvocatoriaRelativa,
    ]);

    if ($convocatoriaAnterior) {
        $rutaAnterior = $directorioConvocatorias . '/' . basename($convocatoriaAnterior);
        if (is_file($rutaAnterior)) {
            unlink($rutaAnterior);
        }
    }
} else {
    $actualizar = $pdo->prepare(
        'UPDATE competiciones SET dia = :dia, tipo = :tipo, hora_inicio = :hora_inicio, hora_fin = :hora_fin,
            nombre = :nombre, fecha_limite = :fecha_limite, max_equipos = :max_equipos, tam_equipo = :tam_equipo
         WHERE id = :id'
    );
    $actualizar->execute([
        'dia' => $dia, 'tipo' => $tipo, 'hora_inicio' => $horaInicio, 'hora_fin' => $horaFin,
        'nombre' => $nombre, 'fecha_limite' => $fechaLimiteSql, 'id' => $id,
        'max_equipos' => $maxEquipos, 'tam_equipo' => $tamEquipo,
    ]);
}

header('Location: ' . BASE_URL . '/admin/public/competicion.php?id=' . $id . '&msg=actualizado');
exit;
