<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';

iniciarSesionInscripciones();

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// La identificación ahora pide numero_cuenta + correo_institucional (ver
// includes/identificar.php), así que ya no se puede identificar de un solo
// vistazo a partir de la URL como antes — si llega desde el redirect de
// app/asistencias (?numero_cuenta=...), solo se le precarga ese campo.
$numeroCuentaPrellenado = '';
if (alumnoIdentificadoId() === null && isset($_GET['numero_cuenta'])) {
    $numeroCuentaPrellenado = strtoupper(trim((string) $_GET['numero_cuenta']));
}

$errores = [
    'numero_cuenta_invalido' => 'Número de cuenta inválido — deben ser 8 caracteres.',
    'correo_invalido' => 'Ingresa un correo válido.',
    'no_encontrado' => 'No encontramos a nadie con ese número de cuenta y correo juntos. Verifica que ya hayas completado tu pre-registro.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;

$idAlumno = alumnoIdentificadoId();
$alumno = null;
if ($idAlumno !== null) {
    $consulta = $pdo->prepare(
        'SELECT nombre_completo, numero_cuenta, grado, grupo, correo_institucional, foto_path FROM alumnos WHERE id = :id'
    );
    $consulta->execute(['id' => $idAlumno]);
    $alumno = $consulta->fetch();
}

$dias = [
    [
        'valor' => 'academico',
        'icono' => 'academico',
        'label' => 'Día Académico',
        'descripcion' => 'Ponencias, talleres y Concurso del Conocimiento.',
        'href' => '/inscripciones/public/academico.php',
        'disponible' => true,
    ],
    [
        'valor' => 'cultural',
        'icono' => 'cultural',
        'label' => 'Día Cultural',
        'descripcion' => 'Talleres y Escenario de Talentos.',
        'href' => '#',
        'disponible' => false,
    ],
    [
        'valor' => 'deportivo',
        'icono' => 'trofeo',
        'label' => 'Día Deportivo',
        'descripcion' => 'Torneos deportivos.',
        'href' => '#',
        'disponible' => false,
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inscripciones — Semana Cultural B23</title>
<link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p>&nbsp;</p>
        <p class="text-sm text-slate-600"><strong>Inscripciones a eventos: </strong>Registro para el control de las ponencias, talleres y concursos de los eventos acádemicos, culturales y deportivos.</p>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <?php if ($mensajeError): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= icono('alerta', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <?php if ($idAlumno === null): ?>

        <div class="mb-4 flex items-start gap-2 rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <?= icono('usuario', 'mt-0.5 h-4 w-4 shrink-0') ?>
            <span>Puedes consultar todo el catálogo libremente. Para inscribirte, identifícate primero con tu número de cuenta y correo institucional.</span>
        </div>

        <?php $volverDestino = ($_GET['volver'] ?? '') === 'academico' ? '/inscripciones/public/academico.php' : '/inscripciones/public/index.php'; ?>
        <form action="/inscripciones/includes/identificar.php" method="post" novalidate class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">
            <input type="hidden" name="volver" value="<?= htmlspecialchars($volverDestino, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label for="numero_cuenta" class="mb-1 block text-sm font-medium">Número de cuenta</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('credencial') ?></span>
                    <input type="text" id="numero_cuenta" name="numero_cuenta" required maxlength="8" minlength="8"
                           pattern="[A-Za-z0-9]{8}" placeholder="XXXXXXXX" autocapitalize="characters" autofocus
                           value="<?= htmlspecialchars($numeroCuentaPrellenado, ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base uppercase focus:border-slate-500 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">El mismo número de cuenta de tu credencial digital.</p>
            </div>
            <div>
                <label for="correo_institucional" class="mb-1 block text-sm font-medium">Correo institucional</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('correo') ?></span>
                    <input type="email" id="correo_institucional" name="correo_institucional" required maxlength="150" placeholder="mi_correo@ucol.mx"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">El mismo correo que usaste al pre-registrarte.</p>
            </div>
            <button type="submit"
                    class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
                <?= icono('usuario', 'h-4 w-4 shrink-0') ?>
                Identificarme
            </button>
        </form>

    <?php else: ?>

        <div class="mb-4 flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm">
            <img src="/registro/public/<?= htmlspecialchars($alumno['foto_path'], ENT_QUOTES, 'UTF-8') ?>" alt=""
                 class="h-16 w-16 shrink-0 rounded-full border border-slate-200 object-cover">
            <div class="min-w-0 text-sm">
                <span class="block text-xs text-slate-500">Hola,</span>
                <span class="block truncate font-semibold"><?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="mt-0.5 block text-slate-500">No. cuenta <?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($alumno['grado'], ENT_QUOTES, 'UTF-8') ?>° <?= htmlspecialchars($alumno['grupo'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="block truncate text-slate-500"><?= htmlspecialchars($alumno['correo_institucional'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <nav class="flex flex-col gap-3">
            <?php foreach ($dias as $dia): ?>
            <?php if ($dia['disponible']): ?>
            <a href="<?= htmlspecialchars($dia['href'], ENT_QUOTES, 'UTF-8') ?>"
               class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-white"><?= icono($dia['icono']) ?></span>
                <span>
                    <span class="block font-semibold"><?= htmlspecialchars($dia['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="block text-sm text-slate-500"><?= htmlspecialchars($dia['descripcion'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </a>
            <?php else: ?>
            <div class="flex items-center gap-3 rounded-xl bg-white/60 p-4 text-slate-400">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-slate-400"><?= icono($dia['icono']) ?></span>
                <span>
                    <span class="block font-semibold"><?= htmlspecialchars($dia['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="block text-sm">Próximamente</span>
                </span>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <a href="/inscripciones/includes/salir.php" class="mt-6 flex items-center justify-center gap-1.5 text-center text-xs font-medium text-slate-500 underline">
            <?= icono('salir', 'h-3.5 w-3.5 shrink-0') ?>
            Cerrar sesión en este dispositivo
        </a>

    <?php endif; ?>

</div>
</body>
</html>
