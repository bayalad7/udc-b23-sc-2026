<?php
declare(strict_types=1);

// BASE_URL: prefijo de ruta bajo el cual vive esta app dentro del dominio.
// Se calcula solo comparando la carpeta real de app/ (padre de este
// config/) contra el Document Root del servidor -- así el código no
// depende de en qué subcarpeta quedó publicada (ej. '' si app/ ES el
// Document Root, como en Docker; '/b23/app' si el VPS sirve desde
// public_html y el repo se subió dentro de una subcarpeta). Todas las
// rutas "absolutas" del código (href, src, action, header('Location: ...'),
// cookies de sesión) se arman con esta constante en vez de escribir "/"
// directo, para no depender de mover archivos ni de tocar el Document Root
// en cada despliegue.
if (!defined('BASE_URL')) {
    $raizApp = str_replace('\\', '/', dirname(__DIR__));
    $documentRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    $baseUrl = ($documentRoot !== '' && str_starts_with($raizApp, $documentRoot))
        ? substr($raizApp, strlen($documentRoot))
        : '';

    define('BASE_URL', rtrim($baseUrl, '/'));
}

// Versionado de archivos estáticos (cache busting). El navegador cachea un
// .js o un .css por su URL, así que reemplazarlo en el servidor no basta:
// quien ya lo tenía sigue ejecutando el viejo hasta que decida revalidar —
// y en un celular eso puede tardar días. Un Ctrl+F5 lo arregla en UN equipo,
// pero aquí el alumnado entra desde decenas de teléfonos distintos y no hay
// forma de pedirles eso.
//
// Colgar la fecha de modificación del archivo como ?v=... cambia la URL en
// cada despliegue, y una URL nueva el navegador SÍ la baja. No requiere
// configurar nada en Apache.
//
// Funciona incluso en un dispositivo que ya tiene el archivo viejo cacheado,
// porque lo que se cachea es el .js/.css, no la página .php que lo enlaza:
// todas las páginas de la app llaman a session_start(), y PHP les manda
// cabeceras "no-cache" por su cuenta (session.cache_limiter). O sea que el
// HTML siempre llega fresco, con el ?v= nuevo dentro, y de ahí sale la
// descarga del archivo actualizado.
//
// $rutaRelativa va sin la barra inicial y relativa a app/ — por ejemplo
// 'assets/js/inscripciones.js'.
// El if es por el mismo motivo que el !defined('BASE_URL') de arriba: hoy los
// 17 sitios que incluyen este archivo usan require_once, pero un require a
// secas en el futuro tumbaria la app entera con un "Cannot redeclare".
if (!function_exists('assetVersionado')) {
    function assetVersionado(string $rutaRelativa): string
    {
        $url = BASE_URL . '/' . $rutaRelativa;

        // Si el archivo no está donde se dice (despliegue a medias, ruta mal
        // escrita), se devuelve la URL sin versión en vez de romper la página:
        // que el estilo llegue cacheado es mucho menos grave que un 500.
        $archivo = dirname(__DIR__) . '/' . $rutaRelativa;
        if (!is_file($archivo)) {
            return $url;
        }

        return $url . '?v=' . filemtime($archivo);
    }
}
