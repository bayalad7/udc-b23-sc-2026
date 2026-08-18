<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

iniciarSesionAsistencias();

if (!turnoEventoListo()) {
    header('Location: ' . BASE_URL . '/asistencias/public/turno-evento.php');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$idEvento = (int) $_SESSION['id_evento'];
$operador = (string) $_SESSION['operador_evento'];
$puntoControl = (string) $_SESSION['punto_control_evento'];

// Solo lectura sobre eventos, únicamente para mostrar en pantalla a qué
// ponencia/taller está atado este punto de control.
$consulta = $pdo->prepare('SELECT dia, tipo, hora_inicio, hora_fin, nombre, espacio FROM eventos WHERE id = :id');
$consulta->execute(['id' => $idEvento]);
$evento = $consulta->fetch();

if ($evento === false) {
    // El evento se borró desde app/admin mientras el turno seguía abierto.
    unset($_SESSION['id_evento'], $_SESSION['operador_evento'], $_SESSION['punto_control_evento']);
    header('Location: ' . BASE_URL . '/asistencias/public/turno-evento.php');
    exit;
}

$diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Escaneando — <?= htmlspecialchars($evento['nombre'], ENT_QUOTES, 'UTF-8') ?> — Semana Acádemica, Cultural y Deportiva B23</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-950 text-white">
<div id="escaneo-app" data-endpoint="<?= BASE_URL ?>/asistencias/includes/registrar-escaneo-evento.php" class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-5">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="<?= BASE_URL ?>/assets/img/logo/UdeC_2L%20izq%20Blanco.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Aniversario #45</h1>
        <h1 class="text-xl font-bold">Asistencia a evento</h1>
    </div>

    <header class="mb-4 flex items-start justify-between gap-3">
        <div>
            <p class="flex items-center gap-1.5 text-sm font-semibold uppercase tracking-wide text-slate-300">
                <?= icono($evento['dia'], 'h-4 w-4 shrink-0') ?>
                <?= htmlspecialchars($diasLabel[$evento['dia']] ?? $evento['dia'], ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p class="mt-1 text-base font-bold text-white"><?= htmlspecialchars($evento['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400">
                <?= icono('reloj', 'h-3.5 w-3.5 shrink-0') ?>
                <?= substr((string) $evento['hora_inicio'], 0, 5) ?>–<?= substr((string) $evento['hora_fin'], 0, 5) ?>
                <span class="text-slate-600">·</span>
                <?= icono('espacio', 'h-3.5 w-3.5 shrink-0') ?>
                <?= htmlspecialchars($evento['espacio'], ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400">
                <?= icono('persona', 'h-3.5 w-3.5 shrink-0') ?>
                <?= htmlspecialchars($operador, ENT_QUOTES, 'UTF-8') ?>
                <span class="text-slate-600">·</span>
                <?= icono('ubicacion', 'h-3.5 w-3.5 shrink-0') ?>
                <?= htmlspecialchars($puntoControl, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <a href="<?= BASE_URL ?>/asistencias/includes/cerrar-turno.php?modo=turno_evento"
           class="flex shrink-0 items-center gap-1 rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs font-medium text-slate-300">
            <?= icono('cambiar', 'h-3.5 w-3.5 shrink-0') ?>
            Cambiar evento
        </a>
    </header>

    <div class="relative overflow-hidden rounded-2xl bg-black">
        <video id="video-camara" class="aspect-square w-full object-cover" playsinline muted></video>
        <canvas id="canvas-camara" class="hidden"></canvas>

        <div id="marco-escaneo" class="pointer-events-none absolute inset-8 rounded-2xl border-4 border-white/70"></div>

        <p id="estado-escaneo" class="absolute inset-x-0 bottom-3 mx-auto w-fit rounded-full bg-black/60 px-3 py-1 text-xs font-medium text-white">
            Apunta la cámara al código QR
        </p>
    </div>

    <div id="sin-camara" class="hidden mt-4 flex-col items-center gap-2 rounded-xl border border-amber-800 bg-amber-950/40 px-4 py-6 text-center text-sm text-amber-200">
        <?= icono('camara_apagada', 'h-6 w-6 shrink-0') ?>
        <p>No se pudo acceder a la cámara. Revisa los permisos del navegador y que la página se abra por HTTPS (o http://localhost en desarrollo).</p>
    </div>

    <div id="resultado" class="hidden mt-4 flex-col items-center gap-3 rounded-2xl p-5 text-center"></div>

    <template id="plantilla-resultado">
        <div class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide" data-rol="etiqueta"></div>
        <img class="hidden h-24 w-24 rounded-full border-2 border-white/50 object-cover" data-rol="foto" alt="">
        <p class="text-lg font-bold" data-rol="nombre"></p>
        <p class="text-sm text-white/80" data-rol="detalle"></p>
        <p class="text-sm font-medium" data-rol="mensaje"></p>
        <button type="button" class="mt-1 flex items-center gap-1.5 rounded-lg border border-white/30 px-4 py-2 text-sm font-medium text-white" data-rol="boton-siguiente">
            Escanear siguiente
        </button>
    </template>
</div>

<script src="<?= BASE_URL ?>/assets/js/lib/jsQR.js"></script>
<script src="<?= BASE_URL ?>/assets/js/escaneo.js"></script>
</body>
</html>
