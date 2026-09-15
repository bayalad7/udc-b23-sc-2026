<?php
declare(strict_types=1);

// Requerimientos de un evento: qué tiene que llevar el alumno, según lo que
// pida el ponente/tallerista. Viven en `eventos.requerimientos` como texto con
// un requerimiento por línea.
//
// POR QUÉ SALTOS DE LÍNEA Y NO COMAS como en etiquetas.php: una etiqueta es
// una palabra o dos ("Inteligencia artificial"), pero un requerimiento es
// prosa y lleva comas propias — "Laptop, de preferencia con Windows" se
// partiría en dos requerimientos sin sentido. El salto de línea además es el
// separador natural del <textarea> donde se capturan.
//
// Compartido entre app/inscripciones (los lee para la lista del modal "Ver
// detalles") y app/admin (los captura), mismo criterio que convocatoria.php.

/**
 * Lista de requerimientos a partir del valor crudo de la columna. Tolera
 * líneas vacías, espacios de más, CRLF y NULL: lo que escriba el staff en
 * app/admin no puede tumbar la página del alumnado.
 *
 * @return list<string>
 */
function requerimientosLista(?string $crudo): array
{
    if ($crudo === null || trim($crudo) === '') {
        return [];
    }

    $requerimientos = [];
    foreach (preg_split('/\R/u', $crudo) ?: [] as $linea) {
        // Se le quita la viñeta si el staff la escribió a mano: la lista ya
        // pinta la suya y si no saldría "• - Laptop".
        $limpia = trim((string) preg_replace('/^\s*[-*•]\s*/u', '', (string) $linea));
        if ($limpia !== '') {
            $requerimientos[] = $limpia;
        }
    }

    return $requerimientos;
}

/**
 * Forma canónica para guardar: una línea por requerimiento, sin líneas vacías
 * ni viñetas sueltas. Devuelve null cuando no queda ninguno, que es como se
 * guarda "este evento no pide nada".
 */
function requerimientosNormalizar(?string $crudo): ?string
{
    $lista = requerimientosLista($crudo);

    return $lista === [] ? null : implode("\n", $lista);
}
