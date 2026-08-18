<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAsistencias();

$modo = (string) ($_GET['modo'] ?? 'turno');

if ($modo === 'todo') {
    // Cierra sesión por completo: hace falta el token (?clave=) de nuevo.
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
    }
    session_destroy();
} elseif ($modo === 'turno_evento') {
    // Solo termina el turno de evento específico (ponencia/taller): sigue
    // autorizado por el token, y el turno general (si lo hubiera) no se toca.
    unset($_SESSION['id_evento'], $_SESSION['operador_evento'], $_SESSION['punto_control_evento']);
} else {
    // Solo termina el turno actual (día/operador/punto de control): sigue
    // autorizado por el token, evento.php no lo vuelve a pedir.
    unset($_SESSION['evento'], $_SESSION['operador'], $_SESSION['punto_control']);
}

header('Location: ' . BASE_URL . '/asistencias/public/evento.php');
exit;
