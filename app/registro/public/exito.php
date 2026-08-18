<?php
declare(strict_types=1);

require __DIR__ . '/../includes/iconos.php';

$token = $_GET['token'] ?? '';
$alumno = null;

if (preg_match('/^[a-f0-9]{32}$/', $token)) {
    $pdo = require __DIR__ . '/../../config/db.php';
    $consulta = $pdo->prepare(
        'SELECT nombre_completo, numero_cuenta, credencial_generada, credencial_path
         FROM alumnos WHERE token_descarga = :token'
    );
    $consulta->execute(['token' => $token]);
    $alumno = $consulta->fetch() ?: null;
}
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

    <?php if ($alumno === null): ?>

        <div class="mt-16 rounded-xl bg-white p-6 shadow-sm">
            <p class="text-lg font-semibold">No encontramos ese registro.</p>
            <p class="mt-2 text-sm text-slate-600">Verifica el enlace o vuelve a registrarte.</p>
            <a href="<?= BASE_URL ?>/registro/public/index.php" class="mt-4 inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Ir al registro</a>
        </div>

    <?php else: ?>

        <h1 class="mt-4 text-xl font-bold">¡Listo, <?= htmlspecialchars(explode(' ', $alumno['nombre_completo'])[0], ENT_QUOTES, 'UTF-8') ?>!</h1>
        <p class="mt-1 text-sm text-slate-600">Tu registro quedó guardado con el número de cuenta <strong><?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></strong>.</p>

        <?php if ($alumno['credencial_generada'] && $alumno['credencial_path']): ?>

            <div class="mt-6 w-full overflow-hidden rounded-xl bg-white shadow-sm">
                <img src="<?= BASE_URL ?>/registro/public/<?= htmlspecialchars($alumno['credencial_path'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="Credencial digital" class="w-full">
            </div>
            
            <p class="mt-4 text-xs text-slate-500">Guarda esta imagen en tu celular, <b>es tu credencial para tomar tus asistencias todos los días de los eventos.</b></p>

            <a href="<?= BASE_URL ?>/registro/public/<?= htmlspecialchars($alumno['credencial_path'], ENT_QUOTES, 'UTF-8') ?>"
               download="credencial-<?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?>.png"
               class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white">
                <?= icono('descargar', 'h-4 w-4 shrink-0') ?>
                Descargar credencial
            </a>

        <?php else: ?>

            <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Tu registro se guardó, pero hubo un problema generando la credencial. Contacta al equipo organizador con tu número de cuenta.
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>
</body>
</html>
