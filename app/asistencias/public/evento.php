<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

iniciarSesionAsistencias();

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';
$claveYaRegistrada = $pdo->query('SELECT 1 FROM sistema LIMIT 1')->fetch() !== false;

$errores = [
    'clave_incorrecta' => 'Contraseña incorrecta.',
    'claves_no_coinciden' => 'Las contraseñas no coinciden.',
    'clave_muy_corta' => 'La contraseña debe tener al menos 8 caracteres.',
    'ya_registrada' => 'La contraseña ya había sido registrada — usa el formulario de acceso.',
    'campos_incompletos' => 'Elige un día e ingresa tu nombre y el punto de control.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Escaneo de asistencia — Semana Cultural B23</title>
<link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p>&nbsp;</p>
        <p class="text-sm text-slate-600"><strong>Control de asistencias generales: </strong>Solo para maestros/staff en el punto de control, para escanear el QR de los estudiantes en su hora de llegada y salida para cada día en general.</p>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <?php if ($mensajeError): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= icono('alerta', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <?php if (!$claveYaRegistrada): ?>

        <div class="mb-4 flex items-start gap-2 rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <?= icono('verificado', 'mt-0.5 h-4 w-4 shrink-0') ?>
            <span>Todavía no hay contraseña registrada. Como quien haga esto primero será el encargado del evento, defínela aquí — queda guardada de forma segura (hasheada), nadie puede leerla después.</span>
        </div>

        <form action="/asistencias/includes/registrar-clave.php" method="post" novalidate class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">
            <div>
                <label for="clave" class="mb-1 block text-sm font-medium">Nueva contraseña de acceso</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('candado') ?></span>
                    <input type="password" id="clave" name="clave" required minlength="8" autofocus
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">Al menos 8 caracteres. Compártela solo con el staff que va a escanear.</p>
            </div>
            <div>
                <label for="clave_confirmar" class="mb-1 block text-sm font-medium">Confirmar contraseña</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('candado') ?></span>
                    <input type="password" id="clave_confirmar" name="clave_confirmar" required minlength="8"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
            </div>
            <button type="submit"
                    class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
                <?= icono('verificado', 'h-4 w-4 shrink-0') ?>
                Registrar contraseña
            </button>
        </form>

    <?php elseif (!turnoAutorizado()): ?>

        <form action="/asistencias/includes/verificar-clave.php" method="post" novalidate class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">
            <div>
                <label for="clave" class="mb-1 block text-sm font-medium">Contraseña de acceso</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('candado') ?></span>
                    <input type="password" id="clave" name="clave" required autofocus
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">Pídela al encargado del evento si no la tienes.</p>
            </div>
            <button type="submit"
                    class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
                <?= icono('verificado', 'h-4 w-4 shrink-0') ?>
                Entrar
            </button>
        </form>

    <?php else: ?>

        <?php
        $eventos = [
            'academico' => ['label' => 'Día Académico', 'icono' => 'academico'],
            'cultural' => ['label' => 'Día Cultural', 'icono' => 'cultural'],
            'deportivo' => ['label' => 'Día Deportivo', 'icono' => 'deportivo'],
        ];
        $eventoActual = $_SESSION['evento'] ?? '';
        $operadorActual = (string) ($_SESSION['operador'] ?? '');
        $puntoControlActual = (string) ($_SESSION['punto_control'] ?? '');
        ?>

        <form action="/asistencias/includes/iniciar-turno.php" method="post" novalidate class="flex flex-col gap-5 rounded-xl bg-white p-5 shadow-sm">

            <fieldset>
                <legend class="mb-2 text-sm font-medium">¿Qué día vas a escanear?</legend>
                <div class="grid grid-cols-1 gap-2">
                    <?php foreach ($eventos as $valor => $datos): ?>
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-300 p-3 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-900 has-[:checked]:text-white">
                        <input type="radio" name="evento" value="<?= $valor ?>" required
                               <?= $eventoActual === $valor ? 'checked' : '' ?>
                               class="h-4 w-4 shrink-0 text-slate-900 focus:ring-slate-500">
                        <?= icono($datos['icono'], 'h-5 w-5 shrink-0') ?>
                        <span class="font-medium"><?= htmlspecialchars($datos['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div>
                <label for="operador" class="mb-1 block text-sm font-medium">Tu nombre</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('persona') ?></span>
                    <input type="text" id="operador" name="operador" required maxlength="100"
                           value="<?= htmlspecialchars($operadorActual, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Ej. Profa. María López"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">Queda registrado como quién operó cada escaneo.</p>
            </div>

            <div>
                <label for="punto_control" class="mb-1 block text-sm font-medium">Punto de control</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('ubicacion') ?></span>
                    <input type="text" id="punto_control" name="punto_control" required maxlength="100"
                           value="<?= htmlspecialchars($puntoControlActual, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Ej. Entrada principal"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
            </div>

            <button type="submit"
                    class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
                <?= icono('entrada', 'h-4 w-4 shrink-0') ?>
                Comenzar a escanear
            </button>
        </form>

        <a href="/asistencias/includes/cerrar-turno.php?modo=todo" class="mt-4 flex items-center justify-center gap-1.5 text-center text-xs font-medium text-slate-500 underline">
            <?= icono('salida', 'h-3.5 w-3.5 shrink-0') ?>
            Cerrar sesión en este dispositivo
        </a>

    <?php endif; ?>

</div>
</body>
</html>
