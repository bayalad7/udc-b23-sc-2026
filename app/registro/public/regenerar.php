<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';
require __DIR__ . '/../includes/iconos.php';

$errores = [
    'campos_incompletos' => 'Faltan campos obligatorios o el número de cuenta no tiene el formato correcto (8 caracteres).',
    'cuenta_duplicada' => 'Ese número de cuenta ya pertenece a otro registro. Verifícalo e intenta de nuevo.',
    'foto_invalida' => 'La fotografía debe ser una imagen JPG o PNG de máximo 5 MB.',
    'error_servidor' => 'Ocurrió un error al guardar tus cambios. Intenta de nuevo.',
];
$codigoError = $_GET['error'] ?? null;
$mensajeError = $errores[$codigoError] ?? null;

$token = (string) ($_GET['token'] ?? '');
$alumno = null;

if (preg_match('/^[a-f0-9]{32}$/', $token)) {
    $pdo = require __DIR__ . '/../../config/db.php';
    $consulta = $pdo->prepare(
        'SELECT numero_cuenta, nombre_completo, grado, grupo, correo_institucional, foto_path, camisa_corte, camisa_talla
         FROM alumnos WHERE token_descarga = :token'
    );
    $consulta->execute(['token' => $token]);
    $alumno = $consulta->fetch() ?: null;
}

// La foto conserva el mismo nombre de archivo tras cada regeneración (ver
// exito.php), así que se le agrega la misma marca de versión para que el
// navegador no muestre de caché la foto anterior si el alumno vuelve aquí.
$fotoVersion = null;
if ($alumno !== null) {
    $fotoRutaAbsoluta = __DIR__ . '/' . $alumno['foto_path'];
    $fotoVersion = is_file($fotoRutaAbsoluta) ? filemtime($fotoRutaAbsoluta) : time();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Regenerar credencial — Semana Acádemica, Cultural y Deportiva B23</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="<?= BASE_URL ?>/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p>&nbsp;</p>
        <p class="text-sm text-slate-600"><strong>Corregir mis datos:</strong> Actualiza tu información y vuelve a generar tu credencial digital.</p>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <?php if ($alumno === null): ?>

        <div class="rounded-xl bg-white p-6 text-center shadow-sm">
            <p class="text-lg font-semibold">No encontramos ese registro.</p>
            <p class="mt-2 text-sm text-slate-600">Vuelve a intentarlo desde la recuperación de credencial.</p>
            <a href="<?= BASE_URL ?>/registro/public/recuperar.php" class="mt-4 inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Volver</a>
        </div>

    <?php else: ?>

    <?php if ($mensajeError): ?>
    <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <form id="form-regenerar" action="<?= BASE_URL ?>/registro/includes/regenerar-credencial.php" method="post" enctype="multipart/form-data" novalidate class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">

        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

        <div>
            <label for="nombre_completo" class="mb-1 block text-sm font-medium">Nombre completo</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('usuario') ?></span>
                <input type="text" id="nombre_completo" name="nombre_completo" required maxlength="150"
                       value="<?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
            </div>
        </div>

        <div>
            <label for="numero_cuenta" class="mb-1 block text-sm font-medium">Número de cuenta</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('credencial') ?></span>
                <input type="text" id="numero_cuenta" name="numero_cuenta" required maxlength="8" minlength="8"
                       pattern="[A-Za-z0-9]{8}" placeholder="XXXXXXXX" autocapitalize="characters"
                       value="<?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?>"
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
                        <option value="" disabled>Elige...</option>
                        <?php foreach (['1' => '1°', '3' => '3°', '5' => '5°'] as $valor => $etiqueta): ?>
                        <option value="<?= $valor ?>" <?= $alumno['grado'] === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="grupo" class="mb-1 block text-sm font-medium">Grupo</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('grupo') ?></span>
                    <select id="grupo" name="grupo" required
                            class="w-full appearance-none rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                        <option value="" disabled>Elige...</option>
                        <?php foreach (['A', 'B', 'C'] as $valor): ?>
                        <option value="<?= $valor ?>" <?= $alumno['grupo'] === $valor ? 'selected' : '' ?>><?= $valor ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div>
            <label for="correo_institucional" class="mb-1 block text-sm font-medium">Correo institucional</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('correo') ?></span>
                <input type="email" id="correo_institucional" name="correo_institucional" required maxlength="150" placeholder="mi_correo@ucol.mx"
                       value="<?= htmlspecialchars($alumno['correo_institucional'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
            </div>
            <p class="mt-1 text-xs text-slate-500">Aquí se enviará tu credencial digital.</p>
        </div>

        <div>
            <label for="foto" class="mb-1 block text-sm font-medium">Fotografía tipo carnet (tamaño infantil)</label>
            <div class="mb-2 overflow-hidden rounded-lg border border-slate-200" style="width:96px;height:96px;">
                <img src="<?= BASE_URL ?>/registro/public/<?= htmlspecialchars($alumno['foto_path'], ENT_QUOTES, 'UTF-8') ?>?v=<?= $fotoVersion ?>" alt="Foto actual" class="h-full w-full object-cover">
            </div>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('camara') ?></span>
                <input type="file" id="foto" name="foto" accept="image/jpeg,image/png"
                       class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-800 file:px-3 file:py-1.5 file:text-white">
            </div>
            <p class="mt-1 text-xs text-slate-500">Solo rostro, tamaño infantil. JPG o PNG, máximo 5 MB. Déjalo vacío para conservar tu foto actual.</p>
        </div>

        <fieldset>
            <legend class="mb-1 flex items-center gap-1.5 text-sm font-medium">
                <?= icono('camisa', 'h-4 w-4 shrink-0 text-slate-400') ?>
                Camisa oficial del aniversario
            </legend>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="camisa_corte" class="mb-1 block text-xs text-slate-500">Corte</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('camisa') ?></span>
                        <select id="camisa_corte" name="camisa_corte" required
                                class="w-full appearance-none rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                            <option value="Unisex" selected>Unisex</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="camisa_talla" class="mb-1 block text-xs text-slate-500">Talla</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('talla') ?></span>
                        <select id="camisa_talla" name="camisa_talla" required
                                class="w-full appearance-none rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                            <option value="" disabled>Elige...</option>
                            <?php foreach (['XS' => 'Extra Chica', 'S' => 'Chica', 'M' => 'Mediana', 'L' => 'Grande', 'XL' => 'Extra Grande', '2XL' => 'Doble Extra Grande'] as $valor => $etiqueta): ?>
                            <option value="<?= $valor ?>" <?= $alumno['camisa_talla'] === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <p class="mt-1 text-xs text-slate-500">Se usa para encargar tu camisa oficial del aniversario.</p>
        </fieldset>

        <p id="mensaje-validacion" class="hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"></p>

        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>Nota importante.</strong> Al regenerar tu credencial, la anterior queda reemplazada por esta nueva versión.
        </div>

        <button type="submit"
                class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
            <?= icono('editar', 'h-4 w-4 shrink-0') ?>
            Guardar cambios y regenerar credencial
        </button>
    </form>

    <a href="<?= BASE_URL ?>/registro/public/recuperar.php" class="mt-2 flex items-center justify-center gap-1.5 text-center text-sm font-medium text-slate-700 underline">
        <?= icono('buscar', 'h-3.5 w-3.5 shrink-0') ?>
        Cancelar
    </a>

    <?php endif; ?>
</div>

<script src="<?= BASE_URL ?>/assets/js/regenerar.js"></script>
</body>
</html>
