<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';

// Sesión propia de app/admin (nombre de cookie y path distintos a los otros
// módulos para no chocar entre sí — mismo patrón que
// app/asistencias/includes/sesion.php). Solo guarda si ya se validó la
// contraseña compartida del panel (clave_admin en sistema); no hay usuarios
// individuales — ver decisión de negocio en PROMPTS-DESARROLLO.md.

function iniciarSesionAdmin(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('b23_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/admin/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
    ]);
    session_start();
}

/** True si ya se validó la clave de admin en algún momento de esta sesión de navegador. */
function adminAutorizado(): bool
{
    return ($_SESSION['admin_autorizado'] ?? false) === true;
}

/** Corta la ejecución con 403 si no hay sesión de admin autorizada — usar al inicio de cada include protegido. */
function exigirAdmin(): void
{
    if (!adminAutorizado()) {
        http_response_code(403);
        exit('Acceso no autorizado.');
    }
}
