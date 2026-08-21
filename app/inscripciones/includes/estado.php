<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';

// ¿Están abiertas las inscripciones al alumnado? La bandera vive en
// sistema.liberar_inscripciones y se prende/apaga desde app/admin — ver la
// sección "Inscripciones" del dashboard.
//
// Se consulta en DOS niveles, y los dos hacen falta:
//   1. Las páginas de app/inscripciones/public, para no enseñar el formulario
//      de identificación ni los botones de inscripción cuando está cerrado.
//   2. Los endpoints que ESCRIBEN (identificar.php, inscribir.php,
//      crear-equipo-*.php, crear-acto-cultural.php), porque esconder un botón
//      no impide reenviar el POST a mano — y porque un alumno que se
//      identificó ANTES de que el staff cerrara sigue con su sesión viva.
//
// Cierra por defecto a propósito: si la fila de `sistema` todavía no existe
// (nadie ha configurado ninguna contraseña) o la columna es NULL, se
// considera cerrado. Es preferible que el staff tenga que abrirlas a mano a
// que se abran solas por un descuido de configuración.

function inscripcionesLiberadas(PDO $pdo): bool
{
    $fila = $pdo->query('SELECT liberar_inscripciones FROM sistema ORDER BY id LIMIT 1')->fetch();

    return $fila !== false && (int) $fila['liberar_inscripciones'] === 1;
}

/**
 * Corta la ejecución de un endpoint de escritura si las inscripciones están
 * cerradas, devolviendo al alumno a la página que corresponda con un aviso.
 */
function exigirInscripcionesLiberadas(PDO $pdo, string $destino): void
{
    if (inscripcionesLiberadas($pdo)) {
        return;
    }

    $separador = str_contains($destino, '?') ? '&' : '?';
    header('Location: ' . $destino . $separador . 'error=inscripciones_cerradas');
    exit;
}
