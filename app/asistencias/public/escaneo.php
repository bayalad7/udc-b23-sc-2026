<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

iniciarSesionAsistencias();

if (!turnoListo()) {
    header('Location: ' . BASE_URL . '/asistencias/public/evento.php');
    exit;
}

$eventos = [
    'academico' => 'Día Académico',
    'cultural' => 'Día Cultural',
    'deportivo' => 'Día Deportivo',
];

$evento = (string) $_SESSION['evento'];
$operador = (string) $_SESSION['operador'];
$puntoControl = (string) $_SESSION['punto_control'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Escaneando — <?= htmlspecialchars($eventos[$evento], ENT_QUOTES, 'UTF-8') ?> — Semana Acádemica, Cultural y Deportiva B23</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-950 text-white">
<div id="escaneo-app" data-evento="<?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?>" data-endpoint="<?= BASE_URL ?>/asistencias/includes/registrar-escaneo.php" class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-5">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="<?= BASE_URL ?>/assets/img/logo/UdeC_2L%20izq%20Blanco.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Aniversario #45</h1>
        <h1 class="text-xl font-bold">Control de asistencias generales</h1>
    </div>

    <header class="mb-4 flex items-start justify-between gap-3">
        <div>
            <p class="flex items-center gap-1.5 text-sm font-semibold uppercase tracking-wide text-slate-300">
                <?= icono($evento, 'h-4 w-4 shrink-0') ?>
                <?= htmlspecialchars($eventos[$evento], ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400">
                <?= icono('persona', 'h-3.5 w-3.5 shrink-0') ?>
                <?= htmlspecialchars($operador, ENT_QUOTES, 'UTF-8') ?>
                <span class="text-slate-600">·</span>
                <?= icono('ubicacion', 'h-3.5 w-3.5 shrink-0') ?>
                <?= htmlspecialchars($puntoControl, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <a href="<?= BASE_URL ?>/asistencias/includes/cerrar-turno.php?modo=turno"
           class="flex shrink-0 items-center gap-1 rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs font-medium text-slate-300">
            <?= icono('cambiar', 'h-3.5 w-3.5 shrink-0') ?>
            Cambiar turno
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

    <!-- El resultado sale en un modal y ya no debajo del recuadro de la
         cámara: ahí quedaba fuera de la pantalla del celular y el operador
         tenía que hacer scroll para ver si el alumno entró o salió, con el
         siguiente alumno ya enfrente. El diálogo lo abre y lo cierra
         assets/js/escaneo.js (se cierra solo al reanudar el escaneo). El
         color de fondo lo pone el JS en #resultado, por eso el <dialog> va
         transparente y sin padding. -->
    <dialog id="resultado-modal" class="m-auto w-[90%] max-w-sm rounded-2xl border-0 bg-transparent p-0 text-white backdrop:bg-slate-950/80">
        <div id="resultado" class="flex max-h-[85vh] flex-col items-center gap-3 overflow-auto rounded-2xl p-5 text-center"></div>
    </dialog>

    <template id="plantilla-resultado">
        <div class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide" data-rol="etiqueta"></div>
        <img class="hidden h-24 w-24 rounded-full border-2 border-white/50 object-cover" data-rol="foto" alt="">
        <p class="text-lg font-bold" data-rol="nombre"></p>
        <p class="text-sm text-white/80" data-rol="detalle"></p>
        <p class="text-sm font-medium" data-rol="mensaje"></p>
        <a class="hidden items-center justify-center gap-1.5 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-slate-900" data-rol="boton-inscripcion" href="#">
            Elegir ponencia/taller
        </a>
        <button type="button" class="mt-1 flex items-center gap-1.5 rounded-lg border border-white/30 px-4 py-2 text-sm font-medium text-white" data-rol="boton-siguiente">
            Escanear siguiente
        </button>
    </template>
</div>

<script src="<?= BASE_URL ?>/assets/js/lib/jsQR.js"></script>
<script src="<?= BASE_URL ?>/assets/js/escaneo.js"></script>
</body>
</html>
