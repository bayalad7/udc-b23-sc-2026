<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';
require __DIR__ . '/../includes/iconos.php';

$errores = [
    'no_encontrado' => 'No encontramos un registro con ese número de cuenta y correo. Verifica los datos o completa el registro si aún no lo has hecho.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recuperar credencial — Semana Acádemica, Cultural y Deportiva B23</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="<?= BASE_URL ?>/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p>&nbsp;</p>
        <p class="text-sm text-slate-600"><strong>Recuperar mi credencial digital:</strong> Si ya completaste tu registro, aquí puedes volver a descargar tu credencial digital.</p>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <?php if ($mensajeError): ?>
    <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>/registro/includes/recuperar-credencial.php" method="post" novalidate class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">

        <div>
            <label for="numero_cuenta" class="mb-1 block text-sm font-medium">Número de cuenta</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('credencial') ?></span>
                <input type="text" id="numero_cuenta" name="numero_cuenta" required maxlength="8" minlength="8"
                       pattern="[A-Za-z0-9]{8}" placeholder="XXXXXXXX" autocapitalize="characters"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base uppercase focus:border-slate-500 focus:outline-none">
            </div>
        </div>

        <div>
            <label for="correo_institucional" class="mb-1 block text-sm font-medium">Correo institucional</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('correo') ?></span>
                <input type="email" id="correo_institucional" name="correo_institucional" required maxlength="150"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
            </div>
            <p class="mt-1 text-xs text-slate-500">El mismo que usaste al pre-registrarte.</p>
        </div>

        <button type="submit" formaction="<?= BASE_URL ?>/registro/includes/recuperar-credencial.php"
                class="mt-2 flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700 cursor-pointer">
            <?= icono('buscar', 'h-4 w-4 shrink-0') ?>
            Buscar mi credencial
        </button>

        <button type="submit" formaction="<?= BASE_URL ?>/registro/includes/iniciar-regeneracion.php"
                class="flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-3 text-base font-semibold text-slate-800 active:bg-slate-100 cursor-pointer">
            <?= icono('editar', 'h-4 w-4 shrink-0') ?>
            Regenerar credencial (Corregir mis datos)
        </button>
        <p class="text-center text-xs text-slate-500">¿Te equivocaste en algún dato o subiste la foto incorrecta? Usa este botón con tu número de cuenta y correo para corregirlo.</p>
    </form>

    <a href="<?= BASE_URL ?>/registro/public/index.php" class="mt-2 flex items-center justify-center gap-1.5 text-center text-sm font-medium text-slate-700 underline">
        <?= icono('correo', 'h-3.5 w-3.5 shrink-0') ?>
        Volver al registro
    </a>
</div>
</body>
</html>
