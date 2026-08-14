<?php
declare(strict_types=1);

// Íconos SVG inline tomados de Lucide (lucide.dev, licencia ISC) — mismo
// criterio que app/registro/includes/iconos.php y app/asistencias/includes/iconos.php:
// vendorizados como markup estático, sin CDN ni paquete JS en tiempo de ejecución.

const ICONOS_SVG = [
    // lucide "credit-card"
    'credencial' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    // lucide "graduation-cap" (Día Académico)
    'academico' => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
    // lucide "drama" (Día Cultural)
    'cultural' => '<path d="M10 11h.01"/><path d="M14 6h.01"/><path d="M18 6h.01"/><path d="M6.5 13.1h.01"/><path d="M22 5c0 9-4 12-6 12s-6-3-6-12c0-2 2-3 6-3s6 1 6 3"/><path d="M17.4 9.9c-.8.8-2 .8-2.8 0"/><path d="M10.1 7.1C9 7.2 7.7 7.7 6 8.6c-3.5 2-4.7 3.9-3.7 5.6 4.5 7.8 9.5 8.4 11.2 7.4.9-.5 1.9-2.1 1.9-4.7"/><path d="M9.1 16.5c.3-1.1 1.4-1.7 2.4-1.4"/>',
    // lucide "trophy" (Día Deportivo / competiciones)
    'trofeo' => '<path d="M10 14.66V17a1 1 0 0 1-1 1 2 2 0 0 0-2 2v2"/><path d="M14 14.66V17a1 1 0 0 0 1 1 2 2 0 0 1 2 2v2"/><path d="M17.916 10H19.5A2.5 2.5 0 0 0 22 7.5V5a1 1 0 0 0-1-1h-3"/><path d="M4 22h16"/><path d="M6 9a6 6 0 0 0 12 0V3a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1z"/><path d="M6.084 10H4.5A2.5 2.5 0 0 1 2 7.5V5a1 1 0 0 1 1-1h3"/>',
    // lucide "clock"
    'reloj' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    // lucide "map-pin"
    'ubicacion' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
    // lucide "users"
    'cupo' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/>',
    // lucide "circle-check"
    'verificado' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    // lucide "lock"
    'candado' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    // lucide "triangle-alert"
    'alerta' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    // lucide "user-round"
    'usuario' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
    // lucide "log-out"
    'salir' => '<path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>',
    // lucide "chevron-left"
    'volver' => '<path d="m15 18-6-6 6-6"/>',
    // lucide "user-round-plus"
    'inscribir' => '<path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/>',
    // lucide "mic"
    'facilitador' => '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/>',
    // lucide "chevron-down"
    'expandir' => '<path d="m6 9 6 6 6-6"/>',
    // lucide "mail"
    'correo' => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/>',
    // lucide "plus"
    'agregar' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    // lucide "x"
    'quitar' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    // lucide "shirt" (color de camisa, torneos deportivos)
    'camisa' => '<path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23"/>',
    // lucide "search" (botón "Buscar" del constructor de equipos)
    'buscar' => '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
    // lucide "type" (campos de texto libre: nombre de equipo/acto)
    'nombre' => '<polyline points="4 7 4 4 20 4 20 7"/><line x1="9" x2="15" y1="20" y2="20"/><line x1="12" x2="12" y1="4" y2="20"/>',
];

/** Devuelve el markup de un ícono SVG inline (Lucide). $clase son clases Tailwind (tamaño/color). */
function icono(string $nombre, string $clase = 'h-4 w-4'): string
{
    $trazos = ICONOS_SVG[$nombre] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . htmlspecialchars($clase, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">'
        . $trazos . '</svg>';
}
