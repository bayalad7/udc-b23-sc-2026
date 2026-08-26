<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/rutas.php';

// Sesión propia de app/camisas (nombre de cookie y path distintos a los de los
// otros módulos para no chocar entre sí — mismo patrón que
// app/inscripciones/includes/sesion.php y app/admin/includes/sesion.php).
//
// Aquí se identifica al JEFE DE GRUPO: un alumno con alumnos.es_jefe = 1 que
// lleva el control de quién encarga camisa y cuánto ha pagado, PERO solo de su
// propio grado+grupo. Que la cookie viva en /camisas/ es parte del control de
// acceso: esta sesión no viaja a /admin/ ni a /inscripciones/, así que ni por
// accidente sirve para entrar al panel del staff.
//
// Se guarda únicamente el id interno del jefe. Su grado/grupo NO se guardan en
// la sesión a propósito: se vuelven a leer de la base en cada request (ver
// jefeDeSesion) para que un cambio hecho desde app/admin — reasignar el cargo,
// mover al alumno de grupo — surta efecto de inmediato y no quede congelado en
// una cookie que dura hasta que el jefe cierre el navegador.

function iniciarSesionCamisas(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('b23_camisas');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/camisas/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
    ]);
    session_start();
}

/** Id interno del jefe identificado en esta sesión, o null si todavía no se identifica. */
function jefeIdentificadoId(): ?int
{
    return isset($_SESSION['jefe_id']) ? (int) $_SESSION['jefe_id'] : null;
}

/**
 * Datos frescos del jefe de la sesión, o null si no hay sesión o si el alumno
 * ya no es jefe (el staff le pudo haber quitado el cargo desde app/admin
 * mientras tenía la sesión abierta). Devolver null en ese caso es lo que hace
 * que el cargo se pueda revocar de verdad: sin esta comprobación, la cookie
 * seguiría dando acceso al listado del grupo.
 */
function jefeDeSesion(PDO $pdo): ?array
{
    $id = jefeIdentificadoId();
    if ($id === null) {
        return null;
    }

    $consulta = $pdo->prepare(
        'SELECT id, nombre_completo, numero_cuenta, grado, grupo
         FROM alumnos WHERE id = :id AND es_jefe = 1'
    );
    $consulta->execute(['id' => $id]);
    $jefe = $consulta->fetch();

    return $jefe === false ? null : $jefe;
}

/**
 * Corta la ejecución si no hay un jefe válido en la sesión — usar al inicio de
 * cada include que escriba. Devuelve sus datos para que quien escriba pueda
 * comprobar el grado/grupo del alumno que va a tocar.
 */
function exigirJefe(PDO $pdo): array
{
    $jefe = jefeDeSesion($pdo);
    if ($jefe === null) {
        http_response_code(403);
        exit('Acceso no autorizado.');
    }

    return $jefe;
}
