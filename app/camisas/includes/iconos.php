<?php
declare(strict_types=1);

// Íconos SVG inline tomados de Lucide (lucide.dev, licencia ISC) — mismo
// criterio que app/registro/includes/iconos.php, app/inscripciones/includes/iconos.php
// y app/admin/includes/iconos.php: vendorizados como markup estático, sin CDN
// ni paquete JS en tiempo de ejecución. Set propio de app/camisas.

const ICONOS_SVG = [
    // lucide "shirt"
    'camisa' => '<path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23"/>',
    // lucide "id-card"
    'credencial' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    // lucide "mail"
    'correo' => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/>',
    // lucide "user-round"
    'usuario' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
    // lucide "log-out"
    'salir' => '<path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>',
    // lucide "triangle-alert"
    'alerta' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    // lucide "circle-check"
    'exito' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    // lucide "banknote" (dinero cobrado)
    'dinero' => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
    // lucide "search"
    'buscar' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    // lucide "filter"
    'filtro' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
    // lucide "save"
    'guardar' => '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>',
];

/** Devuelve el markup de un ícono SVG inline (Lucide). $clase son clases Tailwind (tamaño/color). */
function icono(string $nombre, string $clase = 'h-4 w-4'): string
{
    $trazos = ICONOS_SVG[$nombre] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . htmlspecialchars($clase, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">'
        . $trazos . '</svg>';
}
