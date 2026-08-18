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
