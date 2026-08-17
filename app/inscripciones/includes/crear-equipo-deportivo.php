<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require __DIR__ . '/codigo-participante.php';
require __DIR__ . '/colores-camisa.php';
iniciarSesionInscripciones();

// Backend de formación de equipo de un torneo deportivo (Día Deportivo).
// Equipos de exactamente competiciones.tam_equipo integrantes mezclando
// alumnos y padres/madres de familia — a diferencia del Concurso del
// Conocimiento y el Escenario de Talentos. Quien envía el formulario queda
// como capitán (siempre alumno).
//
// Cada integrante (alumno o padre/madre) se captura con el número de cuenta
// del alumno "ancla" de su familia (ver nota de integrantes.id_alumno en
// schema.sql) — obligatorio siempre porque la columna es NOT NULL/FK a
// alumnos, aunque quien participe sea el padre/madre. Si tipo=alumno, el
// nombre se toma del padrón; si tipo=padre/madre, el nombre lo captura quien
// llena el formulario.
//
// Reglas de negocio (ver torneos-deportivos.md):
//   1. Un alumno no puede repetirse como integrante tipo=alumno en dos
//      equipos del MISMO torneo (sí puede en torneos distintos — no se valida
//      aquí, cada torneo es una competición independiente).
//   2. Exactamente competiciones.tam_equipo integrantes (capitán incluido).
//   3. NO se valida cruce de horario entre torneos (ver "Reglas de
//      inscripción a más de un torneo").
//   4. Tope de equipos por torneo — competiciones.max_equipos, exigido por el
//      trigger trg_equipos_limite_maximo (ver schema.sql), no aquí.

const TIPOS_INTEGRANTE_VALIDOS = ['alumno', 'padre', 'madre'];

function volverConMensaje(string $tipo, string $codigo): never
{
    header('Location: /inscripciones/public/deportivo.php?' . $tipo . '=' . urlencode($codigo));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /inscripciones/public/deportivo.php');
    exit;
}

$idCapitan = alumnoIdentificadoId();
if ($idCapitan === null) {
    header('Location: /inscripciones/public/index.php?volver=deportivo');
    exit;
}

$idCompeticion = filter_var($_POST['id_competicion'] ?? '', FILTER_VALIDATE_INT);
if ($idCompeticion === false) {
    volverConMensaje('error', 'torneo_invalido');
}

$nombreEquipo = trim((string) ($_POST['nombre_equipo'] ?? ''));
if ($nombreEquipo === '' || mb_strlen($nombreEquipo) > 150) {
    volverConMensaje('error', 'nombre_equipo_invalido');
}

$colorCamisa = trim((string) ($_POST['color_camisa'] ?? ''));
if (!in_array($colorCamisa, COLORES_CAMISA, true)) {
    volverConMensaje('error', 'color_invalido');
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$consultaTorneo = $pdo->prepare("SELECT id, tam_equipo FROM competiciones WHERE id = :id AND dia = 'deportivo'");
$consultaTorneo->execute(['id' => $idCompeticion]);
$torneo = $consultaTorneo->fetch();
if ($torneo === false) {
    volverConMensaje('error', 'torneo_invalido');
}
$tamEquipo = (int) $torneo['tam_equipo'];
$acompanantesEsperados = $tamEquipo - 1;

$tipos = array_map(static fn ($v): string => strtolower(trim((string) $v)), (array) ($_POST['integrantes_tipo'] ?? []));
$cuentas = array_map(static fn ($v): string => strtoupper(trim((string) $v)), (array) ($_POST['integrantes_cuenta'] ?? []));
$nombres = array_map(static fn ($v): string => trim((string) $v), (array) ($_POST['integrantes_nombre'] ?? []));

if (count($tipos) !== $acompanantesEsperados || count($cuentas) !== $acompanantesEsperados || count($nombres) !== $acompanantesEsperados) {
    volverConMensaje('error', 'integrantes_incompletos');
}

foreach ($tipos as $tipo) {
    if (!in_array($tipo, TIPOS_INTEGRANTE_VALIDOS, true)) {
        volverConMensaje('error', 'integrantes_incompletos');
    }
}
foreach ($cuentas as $numeroCuenta) {
    if (!preg_match('/^[A-Z0-9]{8}$/', $numeroCuenta)) {
        volverConMensaje('error', 'numero_cuenta_invalido');
    }
}
foreach ($tipos as $indice => $tipo) {
    if ($tipo !== 'alumno' && $nombres[$indice] === '') {
        volverConMensaje('error', 'nombre_integrante_invalido');
    }
}

$consultaCapitan = $pdo->prepare('SELECT id, nombre_completo, numero_cuenta FROM alumnos WHERE id = :id');
$consultaCapitan->execute(['id' => $idCapitan]);
$capitan = $consultaCapitan->fetch();
if ($capitan === false) {
    volverConMensaje('error', 'error_servidor');
}

// Nota: la misma cuenta "ancla" SÍ puede repetirse entre filas — es el caso
// normal de un alumno que participa junto con su padre/madre (ver nota de
// integrantes.id_alumno arriba). Lo que no puede repetirse es la combinación
// (ancla, tipo) — eso se valida más abajo (regla 1 y $clavesFilas), después
// de resolver cada cuenta contra el padrón.

$marcadores = implode(',', array_fill(0, count($cuentas), '?'));
$consultaAlumnosAncla = $pdo->prepare(
    "SELECT id, nombre_completo, numero_cuenta FROM alumnos WHERE numero_cuenta IN ($marcadores)"
);
$consultaAlumnosAncla->execute($cuentas);
$alumnosPorCuenta = [];
foreach ($consultaAlumnosAncla->fetchAll() as $fila) {
    $alumnosPorCuenta[$fila['numero_cuenta']] = $fila;
}
// Ojo: no comparar count($alumnosPorCuenta) contra count($cuentas) — la
// misma cuenta ancla puede repetirse legítimamente (alumno + su padre/madre,
// ver nota arriba), así que $alumnosPorCuenta puede tener menos entradas
// únicas que $cuentas aunque todas existan. Se valida una por una.
foreach ($cuentas as $cuenta) {
    if (!isset($alumnosPorCuenta[$cuenta])) {
        volverConMensaje('error', 'integrante_no_encontrado');
    }
}

// Arma las filas de acompañantes a insertar (id_alumno ancla, tipo, nombre).
// Los alumnos tipo='alumno' que se repitan como ancla (ej. el mismo alumno
// aparece dos veces, uno como "alumno" y otra fila padre/madre de la MISMA
// familia) sí están permitidos — solo se valida duplicado de integrantes
// tipo=alumno más abajo (regla 1), porque son los únicos con cuenta propia
// que "participan".
$filasIntegrantes = [];
$idsAlumnoTipoAlumno = [(int) $capitan['id']]; // El capitán siempre es tipo=alumno.
foreach ($tipos as $indice => $tipo) {
    $cuenta = $cuentas[$indice];
    $alumnoAncla = $alumnosPorCuenta[$cuenta];
    $nombreFila = $tipo === 'alumno' ? $alumnoAncla['nombre_completo'] : $nombres[$indice];
    $filasIntegrantes[] = [
        'id_alumno' => (int) $alumnoAncla['id'],
        'tipo' => $tipo,
        'nombre' => $nombreFila,
    ];
    if ($tipo === 'alumno') {
        $idsAlumnoTipoAlumno[] = (int) $alumnoAncla['id'];
    }
}

// Regla 1: ningún alumno (capitán incluido) puede aparecer dos veces como
// tipo=alumno en este mismo equipo (ni ya estar en otro equipo del mismo
// torneo — se valida dentro de la transacción, ver abajo).
if (count(array_unique($idsAlumnoTipoAlumno)) !== count($idsAlumnoTipoAlumno)) {
    volverConMensaje('error', 'integrante_duplicado');
}

// Un padre/madre no puede repetirse dos veces sobre el mismo alumno-ancla
// con el mismo tipo dentro del propio equipo (PK de integrantes).
$clavesFilas = [];
foreach ($filasIntegrantes as $fila) {
    $clavesFilas[] = $fila['id_alumno'] . '|' . $fila['tipo'];
}
if (count(array_unique($clavesFilas)) !== count($clavesFilas)) {
    volverConMensaje('error', 'integrante_duplicado');
}

$pdo->beginTransaction();

try {
    // --- 1. Ningún alumno (capitán incluido) ya integrante tipo=alumno de
    //        OTRO equipo de este mismo torneo -----------------------------

    $marcadoresIds = implode(',', array_fill(0, count($idsAlumnoTipoAlumno), '?'));
    $consultaYaEnEquipo = $pdo->prepare(
        "SELECT 1 FROM integrantes it
         JOIN equipos eq ON eq.id = it.id_equipo
         WHERE eq.id_competicion = ? AND it.tipo = 'alumno' AND it.id_alumno IN ($marcadoresIds)
         LIMIT 1"
    );
    $consultaYaEnEquipo->execute(array_merge([$idCompeticion], $idsAlumnoTipoAlumno));
    if ($consultaYaEnEquipo->fetch() !== false) {
        $pdo->rollBack();
        volverConMensaje('error', 'integrante_ya_en_equipo');
    }

    // --- 2. Guardar equipo + integrantes -----------------------------------

    $insertarEquipo = $pdo->prepare(
        'INSERT INTO equipos (id_competicion, nombre, id_alumno_capitan, color_camisa) VALUES (:competicion, :nombre, :capitan, :color)'
    );
    $insertarEquipo->execute([
        'competicion' => $idCompeticion,
        'nombre' => $nombreEquipo,
        'capitan' => $idCapitan,
        'color' => $colorCamisa,
    ]);
    $idEquipo = (int) $pdo->lastInsertId();

    $insertarIntegrante = $pdo->prepare(
        'INSERT INTO integrantes (id_equipo, id_alumno, tipo, nombre, codigo_participante)
         VALUES (:equipo, :alumno, :tipo, :nombre, :codigo)'
    );

    $insertarIntegrante->execute([
        'equipo' => $idEquipo,
        'alumno' => (int) $capitan['id'],
        'tipo' => 'alumno',
        'nombre' => $capitan['nombre_completo'],
        'codigo' => generarCodigoParticipante($pdo, $idCompeticion),
    ]);
    foreach ($filasIntegrantes as $fila) {
        $insertarIntegrante->execute([
            'equipo' => $idEquipo,
            'alumno' => $fila['id_alumno'],
            'tipo' => $fila['tipo'],
            'nombre' => $fila['nombre'],
            'codigo' => generarCodigoParticipante($pdo, $idCompeticion),
        ]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23000') {
        // UNIQUE (id_competicion, color_camisa) — alguien tomó el color justo
        // antes, o duplicado de integrante (id_equipo, id_alumno, tipo).
        volverConMensaje('error', str_contains($e->getMessage(), 'uq_equipos_color') ? 'color_tomado' : 'integrante_ya_en_equipo');
    }
    if ($e->getCode() === '45000') {
        // SIGNAL del trigger trg_equipos_limite_maximo (ver schema.sql) — ya
        // se alcanzó competiciones.max_equipos para este torneo.
        volverConMensaje('error', 'equipo_limite_alcanzado');
    }
    error_log('Error al crear equipo del torneo ' . $idCompeticion . ' (capitán ' . $idCapitan . '): ' . $e->getMessage());
    volverConMensaje('error', 'error_servidor');
}

volverConMensaje('msg', 'equipo_creado');
