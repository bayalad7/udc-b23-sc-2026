<?php
declare(strict_types=1);

require __DIR__ . '/../includes/iconos.php';
require __DIR__ . '/../includes/temas-interes.php';

$errores = [
    'campos_incompletos' => 'Faltan campos obligatorios o el número de cuenta no tiene el formato correcto (8 caracteres).',
    'cuenta_duplicada' => 'Ese número de cuenta ya tiene un registro. Si ya te registraste, usa "Recuperar mi credencial" abajo.',
    'foto_invalida' => 'La fotografía debe ser una imagen JPG o PNG de máximo 5 MB.',
    'error_servidor' => 'Ocurrió un error al guardar tu registro. Intenta de nuevo.',
];
$codigoError = $_GET['error'] ?? null;
$mensajeError = $errores[$codigoError] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Semana Cultural B23 — Registro de estudiantes</title>
<link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p>&nbsp;</p>
        <p class="text-sm text-slate-600"><strong>Registro de estudiantes:</strong> Generación de la credencial digital.</p>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <?php if ($mensajeError): ?>
    <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <form id="form-registro" action="/registro/includes/guardar-registro.php" method="post" enctype="multipart/form-data" novalidate class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">

        <div>
            <label for="nombre_completo" class="mb-1 block text-sm font-medium">Nombre completo</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('usuario') ?></span>
                <input type="text" id="nombre_completo" name="nombre_completo" required maxlength="150"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
            </div>
        </div>

        <div>
            <label for="numero_cuenta" class="mb-1 block text-sm font-medium">Número de cuenta</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('credencial') ?></span>
                <input type="text" id="numero_cuenta" name="numero_cuenta" required maxlength="8" minlength="8"
                       pattern="[A-Za-z0-9]{8}" placeholder="XXXXXXXX" autocapitalize="characters"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base uppercase focus:border-slate-500 focus:outline-none">
            </div>
            <p class="mt-1 text-xs text-slate-500">8 caracteres, tal como aparece en tu credencial oficial.</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="grado" class="mb-1 block text-sm font-medium">Grado</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('grado') ?></span>
                    <select id="grado" name="grado" required
                            class="w-full appearance-none rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                        <option value="" disabled selected>Elige...</option>
                        <option value="1">1°</option>
                        <option value="3">3°</option>
                        <option value="5">5°</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="grupo" class="mb-1 block text-sm font-medium">Grupo</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('grupo') ?></span>
                    <select id="grupo" name="grupo" required
                            class="w-full appearance-none rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                        <option value="" disabled selected>Elige...</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
            </div>
        </div>

        <div>
            <label for="correo_institucional" class="mb-1 block text-sm font-medium">Correo institucional</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('correo') ?></span>
                <input type="email" id="correo_institucional" name="correo_institucional" required maxlength="150" placeholder="mi_correo@ucol.mx"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
            </div>
            <p class="mt-1 text-xs text-slate-500">Aquí se enviará tu credencial digital.</p>
        </div>

        <div>
            <label for="foto" class="mb-1 block text-sm font-medium">Fotografía tipo carnet (tamaño infantil)</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('camara') ?></span>
                <input type="file" id="foto" name="foto" required accept="image/jpeg,image/png"
                       class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-800 file:px-3 file:py-1.5 file:text-white">
            </div>
            <p class="mt-1 text-xs text-slate-500">Solo rostro, tamaño infantil. JPG o PNG, máximo 5 MB. Puedes usar tu foto de SICEUC.</p>
        </div>

        <fieldset>
            <legend class="mb-1 flex items-center gap-1.5 text-sm font-medium">
                <?= icono('ideas', 'h-4 w-4 shrink-0 text-slate-400') ?>
                Temas de tu interés (opcional)
            </legend>
            <div class="grid grid-cols-1 gap-2 rounded-lg border border-slate-300 p-3 sm:grid-cols-2">
                <?php foreach (TEMAS_INTERES_DISPONIBLES as $indice => $tema): ?>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="temas_interes[]" value="<?= htmlspecialchars($tema, ENT_QUOTES, 'UTF-8') ?>"
                           id="tema-<?= $indice ?>" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                    <?= htmlspecialchars($tema, ENT_QUOTES, 'UTF-8') ?>
                </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-1 text-xs text-slate-500">Puedes elegir varios. Ayuda a definir el catálogo final de ponencias y talleres.</p>
        </fieldset>

        <p id="mensaje-validacion" class="hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"></p>

        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            1. Solo puedes realizar el registro <strong>una única vez</strong> por número de cuenta.
            <br/><br/>
            2. Verifica que toda tu información sea correcta antes de continuar: <strong>no podrás editarla después de registrarte.</strong>
        </div>
        
        <button type="submit"
                class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
            <?= icono('enviar', 'h-4 w-4 shrink-0') ?>
            Registrarme!
        </button>
    </form>

    <p class="mt-4 text-center text-xs text-slate-500">
        Registro abierto hasta el 30 de septiembre. Con tu registro se genera tu credencial digital con código QR.
    </p>

    <a href="/registro/public/recuperar.php" class="mt-2 flex items-center justify-center gap-1.5 text-center text-sm font-medium text-slate-700 underline">
        <?= icono('buscar', 'h-3.5 w-3.5 shrink-0') ?>
        ¿Ya te registraste? Recupera tu credencial
    </a>
</div>

<script src="/assets/js/registro.js"></script>
</body>
</html>
