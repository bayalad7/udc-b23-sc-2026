<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/catalogo.php';
require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

// Confirmación del registro de personal. Los datos vienen de la sesión (la
// deja guardar-registro.php) y se consumen una sola vez: aquí no hay
// credencial que descargar ni enlace permanente que conservar, así que no
// tiene caso exponer el número de trabajador en la URL.

iniciarSesionTrabajadores();
$registro = tomarConfirmacionTrabajador();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro completado — Semana Acádemica, Cultural y Deportiva B23</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col items-center px-4 py-8 text-center">

    <?php if ($registro === null): ?>

        <div class="mt-16 rounded-xl bg-white p-6 shadow-sm">
            <p class="text-lg font-semibold">No hay un registro que mostrar.</p>
            <p class="mt-2 text-sm text-slate-600">Si ya te registraste, tu talla quedó guardada. Si no, hazlo aquí.</p>
            <a href="<?= BASE_URL ?>/trabajadores/public/index.php" class="mt-4 inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Ir al registro</a>
        </div>

    <?php else: ?>

        <span class="mt-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><?= icono('exito', 'h-6 w-6') ?></span>

        <h1 class="mt-4 text-xl font-bold">¡Listo, <?= htmlspecialchars(explode(' ', $registro['nombre_completo'])[0], ENT_QUOTES, 'UTF-8') ?>!</h1>
        <p class="mt-1 text-sm text-slate-600">Tu camisa del aniversario quedó apartada. Esto fue lo que registramos:</p>

        <div class="mt-6 w-full rounded-xl bg-white p-5 text-left shadow-sm">
            <dl class="flex flex-col gap-3 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="flex items-center gap-1.5 text-slate-500"><?= icono('usuario', 'h-4 w-4 shrink-0') ?> Nombre</dt>
                    <dd class="font-medium"><?= htmlspecialchars($registro['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="flex items-center gap-1.5 text-slate-500"><?= icono('trabajo', 'h-4 w-4 shrink-0') ?> Tipo</dt>
                    <dd class="font-medium"><?= htmlspecialchars($registro['tipo'], ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="flex items-center gap-1.5 text-slate-500"><?= icono('numeral', 'h-4 w-4 shrink-0') ?> No. trabajador</dt>
                    <dd class="font-medium"><?= htmlspecialchars($registro['numero_trabajador'], ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="flex items-center gap-1.5 text-slate-500"><?= icono('camisa', 'h-4 w-4 shrink-0') ?> Camisa</dt>
                    <dd class="font-medium">
                        <?= htmlspecialchars($registro['camisa_corte'], ENT_QUOTES, 'UTF-8') ?> ·
                        <?= htmlspecialchars(TRABAJADOR_CAMISA_TALLAS[$registro['camisa_talla']] ?? $registro['camisa_talla'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= htmlspecialchars($registro['camisa_talla'], ENT_QUOTES, 'UTF-8') ?>)
                    </dd>
                </div>
            </dl>
        </div>

        <p class="mt-4 text-xs text-slate-500">Si algo quedó mal, avisa al equipo organizador para corregirlo antes de mandar a hacer las camisas.</p>

        <a href="<?= BASE_URL ?>/index.php" class="mt-4 flex items-center justify-center gap-1.5 text-center text-sm font-medium text-slate-700 underline">
            <?= icono('atras', 'h-3.5 w-3.5 shrink-0') ?>
            Volver al inicio
        </a>

    <?php endif; ?>

</div>
</body>
</html>
