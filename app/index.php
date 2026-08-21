<?php
declare(strict_types=1);

require_once __DIR__ . '/config/rutas.php';

// Página principal: accesos directos a las secciones de la app. No tiene
// lógica propia (sin includes/ ni base de datos) — solo enlaza a los
// módulos, cada uno con su propio flujo y control de acceso.

$iconos = [
    // lucide "id-card"
    'credencial' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    // lucide "qr-code"
    'qr' => '<rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/>',
    // lucide "briefcase"
    'personal' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
    // lucide "list-checks"
    'lista' =>'<path d="M13 5h8"/><path d="M13 12h8"/><path d="M13 19h8"/><path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/>',
    // lucide "shield-check" (Panel de administración)
    'admin' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
];

function icono(string $nombre, string $clase = 'h-5 w-5'): string
{
    global $iconos;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . htmlspecialchars($clase, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">'
        . ($iconos[$nombre] ?? '') . '</svg>';
}

$secciones = [
    [
        'icono' => 'credencial',
        'titulo' => 'Registro de alumnos',
        'descripcion' => 'Registro y credencial digital con QR.',
        'href' => BASE_URL . '/registro/public/index.php',
        'disponible' => true,
    ],
    [
        'icono' => 'personal',
        'titulo' => 'Registro de personal',
        'descripcion' => 'Solo para personal administrativo y docente — registro de la camisa oficial del aniversario (talla y corte).',
        'href' => BASE_URL . '/trabajadores/public/index.php',
        'disponible' => true,
    ],
    [
        'icono' => 'qr',
        'titulo' => 'Control de asistencias generales',
        'descripcion' => 'Escaneo QR — solo maestros/staff en el punto de control, para la hora de llegada y salida en cada día (académico/cultural/deportivo).',
        'href' => BASE_URL . '/asistencias/public/evento.php',
        'disponible' => true,
    ],
    [
        'icono' => 'lista',
        'titulo' => 'Inscripciones a eventos',
        'descripcion' => 'Ponencias, talleres y concursos de la semana.',
        'href' => BASE_URL . '/inscripciones/public/index.php',
        'disponible' => true,
    ],
    [
        'icono' => 'admin',
        'titulo' => 'Panel de administración',
        'descripcion' => 'Solo para el staff organizador — reportes, estadísticas y gestión de alumnos, eventos y competiciones.',
        'href' => BASE_URL . '/admin/public/index.php',
        'disponible' => true,
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Semana Acádemica, Cultural y Deportiva B23</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="<?= BASE_URL ?>/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <nav class="flex flex-col gap-3">
        <?php foreach ($secciones as $seccion): ?>
        <?php if ($seccion['disponible']): ?>
        <a href="<?= htmlspecialchars($seccion['href'], ENT_QUOTES, 'UTF-8') ?>"
           class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-white"><?= icono($seccion['icono']) ?></span>
            <span>
                <span class="block font-semibold"><?= htmlspecialchars($seccion['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="block text-sm text-slate-500"><?= htmlspecialchars($seccion['descripcion'], ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        </a>
        <?php else: ?>
        <div class="flex items-center gap-3 rounded-xl bg-white/60 p-4 text-slate-400">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-slate-400"><?= icono($seccion['icono']) ?></span>
            <span>
                <span class="block font-semibold"><?= htmlspecialchars($seccion['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="block text-sm">Próximamente</span>
            </span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </nav>

</div>
</body>
</html>
