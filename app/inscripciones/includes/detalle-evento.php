<?php
declare(strict_types=1);

require_once __DIR__ . '/iconos.php';

// Ficha completa de una ponencia/taller, en un <dialog> — mismo markup para
// academico.php y cultural.php (las dos páginas que listan filas de `eventos`).
//
// Existe porque la descripción dejó de caber en la tarjeta: `eventos.descripcion`
// pasó de VARCHAR(150) a TEXT (ver database/migraciones/2026-09-10-eventos-descripcion-larga.sql),
// así que en la tarjeta se recorta a dos líneas (line-clamp-2) y el texto
// íntegro — más el horario, que la tarjeta no muestra — vive aquí.

/**
 * Id del <dialog> de detalles de un evento. Lleva el id de la fila de
 * `eventos`, que es único en toda la tabla, así que no hace falta prefijarlo
 * por día: la misma función sirve en las dos páginas sin riesgo de colisión.
 */
function detallesEventoDialogoId(array $evento): string
{
    return 'detalles-evento-' . (int) $evento['id'];
}

/**
 * Botón "Ver detalles" que abre ese diálogo. $claseTexto/$claseBorde vienen de
 * la tarjeta porque su paleta cambia según el estado (la tarjeta se pinta
 * oscura cuando el alumno ya está inscrito).
 */
function renderBotonDetallesEvento(array $evento, string $claseTexto, string $claseBorde): void
{
    ?>
    <button type="button" data-abrir-modal="<?= detallesEventoDialogoId($evento) ?>"
            class="flex items-center gap-1 rounded-lg border <?= $claseBorde ?> px-3 py-2 text-xs font-medium cursor-pointer <?= $claseTexto ?>">
        <?= icono('info', 'h-3.5 w-3.5 shrink-0') ?>
        Ver detalles
    </button>
    <?php
}

/**
 * El diálogo en sí. Va como hermano de la tarjeta (nunca anidado dentro de
 * otro <dialog>). Solo lectura: se cierra y se vuelve a la tarjeta, donde está
 * el botón de inscribirse — mismo criterio que el modal "Ver inscritos".
 */
function renderDialogoDetallesEvento(array $evento): void
{
    $cupoMaximo = (int) $evento['cupo_maximo'];
    $cupoDisponible = (int) $evento['cupo_disponible'];
    $etiquetaTipo = ($evento['tipo'] ?? '') === 'ponencia' ? 'Ponencia' : 'Taller';
    ?>
    <dialog id="<?= detallesEventoDialogoId($evento) ?>" class="m-auto w-[90%] max-w-lg rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
        <div class="max-h-[85vh] overflow-y-auto p-5">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= $etiquetaTipo ?></span>
            <h3 class="text-base font-semibold"><?= htmlspecialchars($evento['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>

            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <span class="flex items-center gap-1.5 text-xs text-slate-600">
                    <?= icono('reloj', 'h-3.5 w-3.5 shrink-0 text-slate-400') ?>
                    <?= substr((string) $evento['hora_inicio'], 0, 5) ?> – <?= substr((string) $evento['hora_fin'], 0, 5) ?>
                </span>
                <span class="flex items-center gap-1.5 text-xs text-slate-600">
                    <?= icono('ubicacion', 'h-3.5 w-3.5 shrink-0 text-slate-400') ?>
                    <?= htmlspecialchars($evento['espacio'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="flex items-center gap-1.5 text-xs text-slate-600">
                    <?= icono('facilitador', 'h-3.5 w-3.5 shrink-0 text-slate-400') ?>
                    <?= htmlspecialchars($evento['facilitador'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="flex items-center gap-1.5 text-xs text-slate-600">
                    <?= icono('cupo', 'h-3.5 w-3.5 shrink-0 text-slate-400') ?>
                    <?= $cupoDisponible ?>/<?= $cupoMaximo ?> lugares disponibles
                </span>
            </div>

            <?php if (trim((string) $evento['descripcion']) !== ''): ?>
            <!-- nl2br en vez de la utilidad de Tailwind que respeta los saltos
                 de línea: la descripción viene de un <textarea> de app/admin y
                 puede traerlos, y resolverlo en PHP no obliga a que el .css
                 compilado incluya una clase más. -->
            <p class="mt-4 border-t border-slate-200 pt-4 text-sm leading-relaxed text-slate-600">
                <?= nl2br(htmlspecialchars($evento['descripcion'], ENT_QUOTES, 'UTF-8')) ?>
            </p>
            <?php endif; ?>

            <form method="dialog" class="mt-4">
                <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white cursor-pointer">
                    <?= icono('quitar', 'h-3.5 w-3.5 shrink-0') ?>
                    Cerrar
                </button>
            </form>
        </div>
    </dialog>
    <?php
}
