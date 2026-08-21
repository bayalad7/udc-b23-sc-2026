<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';
require_once __DIR__ . '/../includes/catalogo.php';
require __DIR__ . '/../includes/iconos.php';

// Registro de personal administrativo y docente. A diferencia de
// app/registro (alumnos), aquí NO se genera credencial ni QR: este módulo
// existe solo para levantar el pedido de camisas del aniversario, así que
// pide únicamente lo que la tabla `trabajadores` guarda.

$errores = [
    'campos_incompletos' => 'Faltan campos obligatorios o alguno tiene un formato inválido.',
    'trabajador_duplicado' => 'Ese número de trabajador ya tiene registrada su camisa. Si necesitas cambiar la talla, avisa al equipo organizador.',
    'error_servidor' => 'Ocurrió un error al guardar tu registro. Intenta de nuevo.',
];
$codigoError = $_GET['error'] ?? null;
$mensajeError = $errores[$codigoError] ?? null;

// Al volver por un error, se repueblan los campos para no obligar a
// recapturar todo (el formulario se envía por POST y no hay estado previo).
$previo = [
    'tipo' => (string) ($_GET['tipo'] ?? ''),
    'numero_trabajador' => (string) ($_GET['numero_trabajador'] ?? ''),
    'nombre_completo' => (string) ($_GET['nombre_completo'] ?? ''),
    'camisa_talla' => (string) ($_GET['camisa_talla'] ?? ''),
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Semana Acádemica, Cultural y Deportiva B23 — Registro de personal</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="<?= BASE_URL ?>/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p>&nbsp;</p>
        <p class="text-sm text-slate-600"><strong>Registro de personal:</strong> Camisa oficial del aniversario.</p>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <?php if ($mensajeError): ?>
    <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>/trabajadores/includes/guardar-registro.php" method="post" class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">

        <div>
            <label for="tipo" class="mb-1 block text-sm font-medium">Tipo de personal</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('trabajo') ?></span>
                <select id="tipo" name="tipo" required
                        class="w-full appearance-none rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                    <option value="" disabled <?= in_array($previo['tipo'], TRABAJADOR_TIPOS, true) ? '' : 'selected' ?>>Elige...</option>
                    <?php foreach (TRABAJADOR_TIPOS as $tipo): ?>
                    <option value="<?= $tipo ?>" <?= $previo['tipo'] === $tipo ? 'selected' : '' ?>><?= $tipo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label for="numero_trabajador" class="mb-1 block text-sm font-medium">Número de trabajador</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('numeral') ?></span>
                <input type="text" id="numero_trabajador" name="numero_trabajador" required maxlength="20"
                       autocapitalize="characters" value="<?= htmlspecialchars($previo['numero_trabajador'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base uppercase focus:border-slate-500 focus:outline-none">
            </div>
            <p class="mt-1 text-xs text-slate-500">Tal como aparece en tu credencial o en tu recibo de nómina.</p>
        </div>

        <div>
            <label for="nombre_completo" class="mb-1 block text-sm font-medium">Nombre completo</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('usuario') ?></span>
                <input type="text" id="nombre_completo" name="nombre_completo" required maxlength="150"
                       value="<?= htmlspecialchars($previo['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
            </div>
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
                            <?php foreach (TRABAJADOR_CAMISA_CORTES as $corte): ?>
                            <option value="<?= $corte ?>" selected><?= $corte ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="camisa_talla" class="mb-1 block text-xs text-slate-500">Talla</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('talla') ?></span>
                        <select id="camisa_talla" name="camisa_talla" required
                                class="w-full appearance-none rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                            <option value="" disabled <?= isset(TRABAJADOR_CAMISA_TALLAS[$previo['camisa_talla']]) ? '' : 'selected' ?>>Elige...</option>
                            <?php foreach (TRABAJADOR_CAMISA_TALLAS as $talla => $etiqueta): ?>
                            <option value="<?= $talla ?>" <?= $previo['camisa_talla'] === $talla ? 'selected' : '' ?>><?= $etiqueta ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <p class="mt-1 text-xs text-slate-500">Se usa únicamente para encargar tu camisa oficial del aniversario.</p>
        </fieldset>

        <button type="submit"
                class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
            <?= icono('enviar', 'h-4 w-4 shrink-0') ?>
            Registrar mi camisa!
        </button>
    </form>

    <p class="mt-4 text-center text-xs text-slate-500">
        Este registro es solo para el control de las camisas del aniversario: no genera credencial ni pasa lista.
    </p>

    <a href="<?= BASE_URL ?>/index.php" class="mt-2 flex items-center justify-center gap-1.5 text-center text-sm font-medium text-slate-700 underline">
        <?= icono('atras', 'h-3.5 w-3.5 shrink-0') ?>
        Volver al inicio
    </a>
</div>
</body>
</html>
