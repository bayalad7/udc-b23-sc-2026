<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';

// Sesión propia de app/trabajadores (nombre de cookie y path distintos a los
// otros módulos para no chocar entre sí — mismo patrón que
// app/admin/includes/sesion.php). No hay contraseña ni usuarios: el registro
// de personal es público igual que el de alumnos. La sesión solo carga el
// mensaje de confirmación de guardar-registro.php a exito.php, para no tener
// que exponer el número de trabajador en la URL (la tabla `trabajadores` no
// tiene un token aleatorio como alumnos.token_descarga, y numerarlos en la
// URL dejaría listar el nombre y la talla de cualquier compañero).

function iniciarSesionTrabajadores(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('b23_trabajadores');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/trabajadores/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
    ]);
    session_start();
}

/** Guarda los datos del registro recién hecho para que exito.php los muestre una sola vez. */
function guardarConfirmacionTrabajador(array $datos): void
{
    $_SESSION['trabajador_confirmacion'] = $datos;
}

/** Devuelve (y borra) la confirmación pendiente — null si se entró a exito.php sin registrarse. */
function tomarConfirmacionTrabajador(): ?array
{
    $confirmacion = $_SESSION['trabajador_confirmacion'] ?? null;
    unset($_SESSION['trabajador_confirmacion']);

    return is_array($confirmacion) ? $confirmacion : null;
}
