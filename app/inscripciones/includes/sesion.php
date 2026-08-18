<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';

// Sesión propia de app/inscripciones: identifica al alumno que está
// consultando/inscribiéndose por autoservicio (numero_cuenta), sin relación
// con la sesión de staff de app/asistencias (esa es del operador del punto
// de control, esta es del propio alumno). Guarda únicamente el id interno
// del alumno — el resto de sus datos se vuelve a consultar cuando hace falta
// mostrarlos, para no arrastrar información desactualizada en la sesión.

function iniciarSesionInscripciones(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('b23_inscripciones');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/inscripciones/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
    ]);
    session_start();
}

/** Id interno del alumno identificado en esta sesión, o null si todavía no se identifica. */
function alumnoIdentificadoId(): ?int
{
    return isset($_SESSION['alumno_id']) ? (int) $_SESSION['alumno_id'] : null;
}
