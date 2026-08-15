<?php
declare(strict_types=1);

// Sesión propia de app/asistencias (nombre de cookie distinto al de PHP por
// defecto para no chocar con otras partes del sitio) que guarda, mientras
// dura el turno del maestro/staff en el punto de control:
//   - autorizado: ya pasó el token secreto de la URL al menos una vez.
//   - evento: 'academico' | 'cultural' | 'deportivo' — fijo para todo el turno.
//   - operador: nombre de quien está escaneando (va en escaneado_por_*).
//   - punto_control: dónde está parado (va en punto_control_*).
// No usa autenticación por usuario/contraseña individual — la protección de
// acceso es HTTP Basic Auth (.htaccess) + este token, ver PROMPTS-DESARROLLO.md.

function iniciarSesionAsistencias(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('b23_asistencias');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/asistencias/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
    ]);
    session_start();
}

/** True si ya se validó el token de acceso en algún momento de esta sesión de navegador. */
function turnoAutorizado(): bool
{
    return ($_SESSION['autorizado'] ?? false) === true;
}

/** True si además del token, ya se eligió día/operador/punto de control (listo para escanear). */
function turnoListo(): bool
{
    return turnoAutorizado()
        && in_array($_SESSION['evento'] ?? '', ['academico', 'cultural', 'deportivo'], true)
        && trim((string) ($_SESSION['operador'] ?? '')) !== ''
        && trim((string) ($_SESSION['punto_control'] ?? '')) !== '';
}

/**
 * True si además del token ya se fijó un evento específico (ponencia/taller)
 * + operador + punto de control (listo para escanear asistencia a ESE
 * evento). Claves de sesión separadas de turnoListo() a propósito: son dos
 * turnos independientes (asistencia general del día vs. asistencia a un
 * evento puntual) que pueden coexistir en el mismo dispositivo sin pisarse.
 */
function turnoEventoListo(): bool
{
    return turnoAutorizado()
        && (int) ($_SESSION['id_evento'] ?? 0) > 0
        && trim((string) ($_SESSION['operador_evento'] ?? '')) !== ''
        && trim((string) ($_SESSION['punto_control_evento'] ?? '')) !== '';
}
