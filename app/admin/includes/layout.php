<?php
declare(strict_types=1);

// Layout compartido de todas las páginas de app/admin/public/*.php ya
// autenticadas: sidebar de navegación (drawer en móvil, fija en escritorio)
// + encabezado. A diferencia del resto de módulos (de una sola columna,
// pensados para celular en el punto de control), el panel de administración
// es de uso interno en escritorio/tablet, así que necesita una navegación
// con varias secciones — de ahí el layout propio en vez de reusar el de
// registro/asistencias/inscripciones.

const ADMIN_NAV = [
    ['clave' => 'dashboard', 'icono' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin/public/index.php'],
    ['clave' => 'alumnos', 'icono' => 'usuarios', 'label' => 'Alumnos', 'href' => '/admin/public/alumnos.php'],
    ['clave' => 'eventos', 'icono' => 'lista', 'label' => 'Eventos', 'href' => '/admin/public/eventos.php'],
    ['clave' => 'competiciones', 'icono' => 'trofeo', 'label' => 'Competiciones', 'href' => '/admin/public/competiciones.php'],
    ['clave' => 'asistencias', 'icono' => 'qr', 'label' => 'Asistencias', 'href' => '/admin/public/asistencias.php'],
];

function layoutAdminAbrir(string $titulo, string $activa): void
{
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?> — Panel de administración B23</title>
<link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="flex min-h-screen">

    <input type="checkbox" id="admin-menu-toggle" class="peer hidden">
    <label for="admin-menu-toggle" class="fixed inset-0 z-30 hidden bg-slate-900/50 peer-checked:block lg:hidden"></label>

    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 text-white transition-transform duration-200 peer-checked:translate-x-0 lg:static lg:z-auto lg:translate-x-0">
        <div class="flex items-center gap-3 px-5 py-6">
            <img src="/assets/img/logo/UdeC_2L%20izq%20Blanco.png" alt="Universidad de Colima" class="h-9 w-auto">
            <div class="leading-tight">
                <span class="block text-sm font-bold">Bachillerato 23</span>
                <span class="block text-xs text-slate-400">Panel de administración</span>
            </div>
        </div>
        <nav class="flex-1 space-y-1 px-3">
            <?php foreach (ADMIN_NAV as $item): ?>
            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
               class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium <?= $activa === $item['clave'] ? 'bg-white text-slate-900' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                <?= icono($item['icono'], 'h-4 w-4 shrink-0') ?>
                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <a href="/admin/includes/cerrar-sesion.php" class="mx-3 mb-5 flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white">
            <?= icono('salida', 'h-4 w-4 shrink-0') ?>
            Cerrar sesión
        </a>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
            <label for="admin-menu-toggle" class="cursor-pointer rounded-lg p-2 hover:bg-slate-100">
                <?= icono('menu', 'h-5 w-5') ?>
            </label>
            <span class="font-semibold"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></span>
        </header>
        <main class="min-w-0 flex-1 p-4 lg:p-8">
            <h1 class="mb-6 hidden text-2xl font-bold lg:block"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php
}

function layoutAdminCerrar(): void
{
    ?>
        </main>
    </div>
</div>

<dialog id="foto-lightbox" class="m-auto w-[90%] max-w-lg rounded-xl border-0 bg-transparent p-0 shadow-none backdrop:bg-slate-900/70">
    <div class="relative">
        <button type="button" data-cerrar-modal="foto-lightbox" title="Cerrar"
                class="absolute -right-3 -top-3 z-10 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-white text-slate-700 shadow-lg hover:bg-slate-100">
            <?= icono('cerrar', 'h-4 w-4') ?>
        </button>
        <img id="foto-lightbox-img" src="" alt="" class="max-h-[80vh] w-full rounded-xl bg-white object-contain">
    </div>
</dialog>

<script src="/assets/js/chart.min.js"></script>
<script src="/assets/js/admin.js"></script>
</body>
</html>
    <?php
}

/**
 * Miniatura circular de la foto de un alumno para listados — clic abre el
 * lightbox compartido (ver <dialog id="foto-lightbox"> arriba y
 * assets/js/admin.js). Si no hay foto_path o el archivo no existe en
 * app/registro/public/uploads/, muestra un placeholder con ícono en su lugar.
 */
function fotoMiniatura(?string $fotoPath, string $alt, string $tamano = 'h-9 w-9', string $tamanoIcono = 'h-4 w-4'): void
{
    $existe = $fotoPath !== null && $fotoPath !== ''
        && is_file(__DIR__ . '/../../registro/public/' . $fotoPath);

    if (!$existe) {
        ?>
        <span class="flex <?= $tamano ?> shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-slate-300">
            <?= icono('foto_rota', $tamanoIcono) ?>
        </span>
        <?php
        return;
    }

    $url = '/registro/public/' . htmlspecialchars($fotoPath, ENT_QUOTES, 'UTF-8');
    ?>
    <button type="button" data-foto-lightbox="<?= $url ?>" data-foto-alt="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>"
            title="Ver foto completa"
            class="<?= $tamano ?> shrink-0 cursor-pointer overflow-hidden rounded-full border border-slate-200 hover:opacity-80">
        <img src="<?= $url ?>" alt="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>" class="h-full w-full object-cover">
    </button>
    <?php
}

/** Banner de éxito o error reutilizable en las páginas del panel. */
function bannerAdmin(string $tipo, string $mensaje): void
{
    $esError = $tipo === 'error';
    $clases = $esError
        ? 'border-red-300 bg-red-50 text-red-800'
        : 'border-emerald-300 bg-emerald-50 text-emerald-800';
    ?>
    <div class="mb-6 flex items-start gap-2 rounded-lg border <?= $clases ?> px-4 py-3 text-sm">
        <?= icono($esError ? 'alerta' : 'exito', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php
}
