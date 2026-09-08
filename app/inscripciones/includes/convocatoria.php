<?php
declare(strict_types=1);

// URL pública de una convocatoria (competiciones.convocatoria) a partir de su
// ruta relativa a app/assets/img/ (ej. "convocatorias/día-académico.png").
// Se usa tanto en app/inscripciones (mostrarla al alumno) como en
// app/admin/public/competicion.php (previsualizar la ya subida) — vive aquí
// porque el lado que la muestra es inscripciones, mismo criterio que
// app/trabajadores/includes/catalogo.php reusado desde app/admin.
//
// Cada segmento se codifica por separado (no la ruta completa) para no
// romper la "/" entre carpeta y archivo, y para que acentos/espacios en el
// nombre del archivo no rompan el link (ver día-académico.png).
function convocatoriaUrl(?string $rutaRelativa): ?string
{
    if ($rutaRelativa === null || $rutaRelativa === '') {
        return null;
    }

    $segmentos = array_map('rawurlencode', explode('/', $rutaRelativa));

    return BASE_URL . '/assets/img/' . implode('/', $segmentos);
}
