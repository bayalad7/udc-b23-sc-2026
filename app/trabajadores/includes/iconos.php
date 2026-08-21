<?php
declare(strict_types=1);

// Íconos SVG inline tomados de Lucide (lucide.dev, licencia ISC) — se
// vendorizan aquí como markup estático (sin CDN ni paquete JS en tiempo de
// ejecución), mismo patrón que en el resto de módulos. Set propio de
// app/trabajadores.

const ICONOS_SVG = [
    // lucide "user"
    'usuario' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    // lucide "briefcase"
    'trabajo' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
    // lucide "hash"
    'numeral' => '<line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/><line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/>',
    // lucide "shirt"
    'camisa' => '<path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 .55.45 1 1.01 1h9.98c.56 0 1.01-.45 1.01-1V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/>',
    // lucide "ruler"
    'talla' => '<path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.4 2.4 0 0 1 0-3.4l2.6-2.6a2.4 2.4 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/>',
    // lucide "send"
    'enviar' => '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>',
    // lucide "circle-check"
    'exito' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    // lucide "arrow-left"
    'atras' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
];

/** Devuelve el markup de un ícono SVG inline (Lucide). $clase son clases Tailwind (tamaño/color). */
function icono(string $nombre, string $clase = 'h-4 w-4'): string
{
    $trazos = ICONOS_SVG[$nombre] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . htmlspecialchars($clase, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">'
        . $trazos . '</svg>';
}
