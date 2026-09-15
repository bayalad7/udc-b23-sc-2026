<?php
declare(strict_types=1);

// Etiquetas (temas) de un evento. Viven en `eventos.etiquetas` como una lista
// separada por comas dentro de la misma fila, no en una tabla aparte, porque
// solo se leen para mostrarse: hoy no hay filtro ni agrupación por etiqueta
// (si algún día lo hay, ESE es el momento de normalizarlas).
//
// Compartido entre app/inscripciones (las lee para pintar los chips del modal
// "Ver detalles") y app/admin (las captura), mismo criterio que
// convocatoria.php.

/** Tope de la columna `eventos.etiquetas` — ver database/schema.sql. */
const ETIQUETAS_MAX_LARGO = 255;

/**
 * Lista de etiquetas a partir del valor crudo de la columna. Tolera espacios
 * de más, comas de sobra y NULL: lo que escriba el staff en app/admin no
 * puede tumbar la página del alumnado.
 *
 * @return list<string>
 */
function etiquetasLista(?string $crudo): array
{
    if ($crudo === null || trim($crudo) === '') {
        return [];
    }

    $etiquetas = [];
    foreach (explode(',', $crudo) as $etiqueta) {
        // Colapsa los espacios internos para que "Historia   del arte" y
        // "Historia del arte" no se vean distintas en el chip.
        $limpia = trim((string) preg_replace('/\s+/u', ' ', $etiqueta));
        if ($limpia !== '') {
            $etiquetas[] = $limpia;
        }
    }

    return $etiquetas;
}

/**
 * Forma canónica para guardar: sin duplicados (ignorando mayúsculas y
 * acentos, para no terminar con "Tecnología" y "tecnologia" como dos
 * etiquetas), separadas por ", " y recortada al tope de la columna.
 * Devuelve null cuando no queda ninguna, que es como se guarda "sin tema".
 */
function etiquetasNormalizar(?string $crudo): ?string
{
    $vistas = [];
    $unicas = [];
    foreach (etiquetasLista($crudo) as $etiqueta) {
        $clave = etiquetaClave($etiqueta);
        if (!isset($vistas[$clave])) {
            $vistas[$clave] = true;
            $unicas[] = $etiqueta;
        }
    }

    if ($unicas === []) {
        return null;
    }

    $texto = implode(', ', $unicas);

    // Recorta por etiqueta completa, no a media palabra: se van quitando las
    // últimas hasta que quepa. Con el tope de 255 esto no debería dispararse
    // nunca, pero el valor llega de un formulario y no se le puede creer.
    while (mb_strlen($texto) > ETIQUETAS_MAX_LARGO && count($unicas) > 1) {
        array_pop($unicas);
        $texto = implode(', ', $unicas);
    }

    return mb_substr($texto, 0, ETIQUETAS_MAX_LARGO);
}

/** Clave de comparación: sin mayúsculas ni acentos. */
function etiquetaClave(string $etiqueta): string
{
    $minuscula = mb_strtolower($etiqueta, 'UTF-8');
    $sinAcentos = strtr($minuscula, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n',
    ]);

    return $sinAcentos;
}
