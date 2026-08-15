<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

iniciarSesionAsistencias();

if (!turnoAutorizado()) {
    header('Location: /asistencias/public/evento.php');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$eventos = $pdo->query(
    "SELECT id, dia, tipo, hora_inicio, hora_fin, nombre, espacio
     FROM eventos ORDER BY dia, hora_inicio, nombre"
)->fetchAll();

$diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural'];
$eventosPorDia = ['academico' => [], 'cultural' => []];
foreach ($eventos as $ev) {
    $eventosPorDia[$ev['dia']][] = $ev;
}

$errores = [
    'evento_no_valido' => 'Elige un evento, ingresa tu nombre y el punto de control.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;

$idEventoActual = (int) ($_SESSION['id_evento'] ?? 0);
$operadorActual = (string) ($_SESSION['operador_evento'] ?? '');
$puntoControlActual = (string) ($_SESSION['punto_control_evento'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asistencia a evento — Semana Acádemica, Cultural y Deportiva B23</title>
<link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p>&nbsp;</p>
        <p class="text-sm text-slate-600"><strong>Control de asistencia por evento: </strong>Solo para el maestro/staff responsable de una ponencia o taller en particular, fijo en su salón, para escanear la entrada y salida de los alumnos inscritos a ESE evento.</p>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <?php if ($mensajeError): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= icono('alerta', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <form action="/asistencias/includes/iniciar-turno-evento.php" method="post" novalidate class="flex flex-col gap-5 rounded-xl bg-white p-5 shadow-sm">

        <div>
            <label for="id_evento" class="mb-1 block text-sm font-medium">¿Qué ponencia/taller vas a controlar?</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('lista') ?></span>
                <select id="id_evento" name="id_evento" required autofocus
                        class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                    <option value="">Elige un evento...</option>
                    <?php foreach ($diasLabel as $diaClave => $diaLabel): ?>
                    <?php if ($eventosPorDia[$diaClave] === []) continue; ?>
                    <optgroup label="<?= htmlspecialchars($diaLabel, ENT_QUOTES, 'UTF-8') ?>">
                        <?php foreach ($eventosPorDia[$diaClave] as $ev): ?>
                        <option value="<?= (int) $ev['id'] ?>" <?= $idEventoActual === (int) $ev['id'] ? 'selected' : '' ?>>
                            <?= substr((string) $ev['hora_inicio'], 0, 5) ?>–<?= substr((string) $ev['hora_fin'], 0, 5) ?>
                            — <?= htmlspecialchars($ev['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            (<?= htmlspecialchars($ev['espacio'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

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
                       placeholder="Ej. Aula 3"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
            </div>
        </div>

        <button type="submit"
                class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
            <?= icono('entrada', 'h-4 w-4 shrink-0') ?>
            Comenzar a escanear
        </button>
    </form>

    <a href="/asistencias/public/evento.php" class="mt-4 flex items-center justify-center gap-1.5 text-center text-xs font-medium text-slate-500 underline">
        <?= icono('cambiar', 'h-3.5 w-3.5 shrink-0') ?>
        Volver a asistencia general
    </a>

</div>
</body>
</html>
