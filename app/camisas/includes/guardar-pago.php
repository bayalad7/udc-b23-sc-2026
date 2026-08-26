<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require_once __DIR__ . '/costo.php';
iniciarSesionCamisas();

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$jefe = exigirJefe($pdo);

// Se guarda un alumno a la vez (un formulario por fila del listado) y no la
// tabla completa de un jalón: el jefe captura desde el celular mientras cobra
// en efectivo en el salón, y con un solo formulario gigante una conexión que se
// cae a media captura se lleva todo lo tecleado.

/** Vuelve al listado conservando los filtros con los que el jefe estaba viendo la lista. */
function volverAlListado(array $extra): never
{
    $filtros = array_filter([
        'buscar' => trim((string) ($_POST['buscar'] ?? '')),
        'estado' => trim((string) ($_POST['estado'] ?? '')),
    ], static fn(string $valor): bool => $valor !== '');

    header('Location: ' . BASE_URL . '/camisas/public/index.php?' . http_build_query($filtros + $extra));
    exit;
}

function volverConError(string $codigo, ?int $idAlumno = null): never
{
    volverAlListado(array_filter(['error' => $codigo, 'alumno' => $idAlumno]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/camisas/public/index.php');
    exit;
}

$idAlumno = (int) ($_POST['id_alumno'] ?? 0);
$accion = (string) ($_POST['accion'] ?? 'guardar');

if ($idAlumno <= 0) {
    volverConError('no_encontrado');
}

// Control de acceso a nivel de fila: el id del alumno llega por POST, así que
// NO se puede confiar en él. Se comprueba contra el grado+grupo del jefe que
// está en la sesión — sin esto, cambiar un número en el formulario dejaría
// editar el pago de cualquier alumno del plantel.
$consulta = $pdo->prepare(
    'SELECT id, nombre_completo, camisa_pedir, camisa_pago
     FROM alumnos WHERE id = :id AND grado = :grado AND grupo = :grupo'
);
$consulta->execute(['id' => $idAlumno, 'grado' => $jefe['grado'], 'grupo' => $jefe['grupo']]);
$alumno = $consulta->fetch();

if ($alumno === false) {
    volverConError('fuera_de_grupo');
}

$costo = camisaCosto($pdo);

if ($accion === 'liquidar') {
    // Atajo de "ya pagó completo": el caso más común al cobrar de contado, y
    // teclear el monto exacto en un celular es justo donde se cuelan los
    // errores de dedo. Implica que sí pide camisa.
    $pedir = 1;
    $pago = $costo;
} else {
    // Sin la casilla marcada el navegador no manda el campo: ausente = no pide.
    $pedir = ($_POST['camisa_pedir'] ?? '') === '1' ? 1 : 0;
    $pago = camisaMontoDesdeTexto((string) ($_POST['camisa_pago'] ?? ''));

    if ($pago === null) {
        volverConError('monto_invalido', $idAlumno);
    }
    if ($pago > $costo) {
        volverConError('pago_excede', $idAlumno);
    }
    // Coherencia que también vigila chk_alumnos_camisa_pago en la base: no
    // puede quedar un pago registrado a nombre de quien no encarga camisa. Se
    // avisa en vez de poner el pago en cero solo: ese dinero ya se cobró y
    // borrarlo sin decir nada dejaría un descuadre imposible de rastrear.
    if ($pedir === 0 && $pago > 0) {
        volverConError('pago_sin_pedido', $idAlumno);
    }
}

// El try/catch cubre la carrera con el panel: si el staff baja el costo entre
// que se leyó camisaCosto() y este UPDATE, el tope lo hace valer la base
// (trg_alumnos_camisa_pago_update) y el jefe vería un 500 en vez de un aviso.
try {
    $actualizar = $pdo->prepare(
        'UPDATE alumnos SET camisa_pedir = :pedir, camisa_pago = :pago WHERE id = :id'
    );
    $actualizar->execute(['pedir' => $pedir, 'pago' => number_format($pago, 2, '.', ''), 'id' => $idAlumno]);
} catch (PDOException $e) {
    // 45000 es el SIGNAL del trigger del tope; 23000, el CHECK de coherencia.
    if (in_array($e->getCode(), ['45000', '23000'], true)) {
        volverConError('pago_excede', $idAlumno);
    }
    throw $e;
}

volverAlListado(['msg' => 'guardado', 'alumno' => $idAlumno]);
