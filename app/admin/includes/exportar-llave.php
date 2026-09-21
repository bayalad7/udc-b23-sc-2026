<?php
declare(strict_types=1);

// Descarga la llave de una competición como PDF imprimible: el cuadro
// completo (una columna por ronda, para seguir con el dedo a dónde va cada
// equipo si gana) más una hoja de calendario con todos los partidos.
//
// Es la hoja que se publica el 2 de octubre, un día antes del torneo, para
// que cada equipo sepa contra quién juega y qué le espera si avanza — ver
// 03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md#formato-de-llaves.
//
// Mismo patrón Dompdf que exportar-corte-camisas.php / exportar-tallas-camisa.php.
//
// OJO con el dibujo del cuadro: Dompdf renderiza CSS 2.1, sin flexbox ni
// grid, así que el bracket NO se puede armar como en la pantalla (columnas
// flex). Se arma con una <table> y `rowspan`: la tabla tiene tantas filas
// como partidos de primera ronda, y un partido de la ronda R ocupa 2^(R-1)
// filas. Así cada partido queda centrado verticalmente frente a los dos de
// los que se alimenta, que es exactamente la forma de un cuadro.

require __DIR__ . '/sesion.php';
require_once __DIR__ . '/llaves.php';
iniciarSesionAdmin();
exigirAdmin();

require __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$idCompeticion = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($idCompeticion <= 0) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

$consulta = $pdo->prepare('SELECT id, dia, tipo, nombre, hora_inicio, hora_fin FROM competiciones WHERE id = :id');
$consulta->execute(['id' => $idCompeticion]);
$competicion = $consulta->fetch();
if ($competicion === false) {
    http_response_code(404);
    exit('No se encontró esa competición.');
}

$porRonda = llavesPartidos($pdo, $idCompeticion);
if ($porRonda === []) {
    http_response_code(404);
    exit('Esta competición todavía no tiene una llave armada.');
}

$totalRondas = max(array_keys($porRonda));
$campeon = llavesCampeon($porRonda);

$diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural', 'deportivo' => 'Día Deportivo'];

/** Escapa para el HTML que come Dompdf. */
function pdfTexto(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Etiqueta corta de una casilla, la que se imprime como "#2.1". */
function pdfCasilla(int $ronda, int $posicion): string
{
    return '#' . $ronda . '.' . $posicion;
}

/**
 * Nombre del equipo de un lado, o por qué esa casilla está vacía. En la ronda
 * 1 una casilla vacía es un pase directo; de la 2 en adelante es un rival que
 * todavía no se conoce.
 */
function pdfNombreLado(array $partido, string $lado): string
{
    $nombre = $partido['nombre_' . $lado];
    if ($nombre !== null) {
        return pdfTexto($nombre);
    }

    return llavesEsBye($partido) ? '<i>Pase directo (bye)</i>' : '<i>Por definir</i>';
}

/** Hora y cancha del partido en una línea, o cadena vacía si no se ha programado. */
function pdfProgramacion(array $partido): string
{
    $partes = [];
    if ($partido['hora_programada'] !== null) {
        $partes[] = substr((string) $partido['hora_programada'], 0, 5);
    }
    if ($partido['cancha'] !== null && $partido['cancha'] !== '') {
        $partes[] = (string) $partido['cancha'];
    }

    return $partes === [] ? '' : pdfTexto(implode(' · ', $partes));
}

// --- Hoja 1: el cuadro -----------------------------------------------------
$partidosPrimeraRonda = count($porRonda[1]);
$anchoColumna = round(100 / $totalRondas, 4);

$cuadro = '<table class="cuadro"><colgroup>';
for ($ronda = 1; $ronda <= $totalRondas; $ronda++) {
    $cuadro .= '<col style="width: ' . $anchoColumna . '%">';
}
$cuadro .= '</colgroup><thead><tr>';
for ($ronda = 1; $ronda <= $totalRondas; $ronda++) {
    $cuadro .= '<th>' . pdfTexto(llavesNombreRonda($ronda, $totalRondas)) . '</th>';
}
$cuadro .= '</tr></thead><tbody>';

for ($fila = 0; $fila < $partidosPrimeraRonda; $fila++) {
    $cuadro .= '<tr>';
    for ($ronda = 1; $ronda <= $totalRondas; $ronda++) {
        // Un partido de la ronda R abarca 2^(R-1) filas y solo se emite en la
        // primera de ellas — de ahí que las demás filas "salten" esa columna.
        $filasQueAbarca = 2 ** ($ronda - 1);
        if ($fila % $filasQueAbarca !== 0) {
            continue;
        }

        $partido = $porRonda[$ronda][intdiv($fila, $filasQueAbarca)] ?? null;
        if ($partido === null) {
            $cuadro .= '<td rowspan="' . $filasQueAbarca . '"></td>';
            continue;
        }

        $posicion = (int) $partido['posicion'];
        $ganador = $partido['id_equipo_ganador'] !== null ? (int) $partido['id_equipo_ganador'] : null;
        $programacion = pdfProgramacion($partido);

        // El "pasa a" va en el encabezado de la celda y no en una línea propia:
        // con 8 partidos de primera ronda, esa línea de más sacaba el cuadro
        // de la única hoja en la que tiene sentido imprimirlo.
        $siguiente = llavesSiguienteCasilla($ronda, $posicion, $totalRondas);
        $cuadro .= '<td rowspan="' . $filasQueAbarca . '"><div class="partido">';
        $cuadro .= '<div class="cab"><span class="pasa">'
            . ($siguiente === null ? '&rarr; Campeón' : '&rarr; ' . pdfCasilla($siguiente['ronda'], $siguiente['posicion']))
            . '</span>' . pdfCasilla($ronda, $posicion)
            . ($programacion !== '' ? ' · ' . $programacion : '') . '</div>';

        foreach (['a', 'b'] as $lado) {
            $idLado = $partido['id_equipo_' . $lado] !== null ? (int) $partido['id_equipo_' . $lado] : null;
            $clase = 'equipo';
            if ($ganador !== null && $idLado === $ganador) {
                $clase .= ' gana';
            } elseif ($ganador !== null && $idLado !== null) {
                $clase .= ' pierde';
            }
            $marcador = $partido['marcador_' . $lado];
            $cuadro .= '<div class="' . $clase . '">' . pdfNombreLado($partido, $lado)
                . ($marcador !== null ? '<span class="marcador">' . (int) $marcador . '</span>' : '')
                . '</div>';
        }

        $cuadro .= '</div></td>';
    }
    $cuadro .= '</tr>';
}
$cuadro .= '</tbody></table>';

// --- Hoja 2: calendario de partidos ---------------------------------------
$calendario = '<table class="calendario"><thead><tr>'
    . '<th>Partido</th><th>Ronda</th><th>Hora</th><th>Cancha</th>'
    . '<th>Equipo A</th><th>Equipo B</th><th>Ganador</th><th>El que gane pasa a</th>'
    . '</tr></thead><tbody>';

foreach ($porRonda as $ronda => $partidos) {
    foreach ($partidos as $partido) {
        $posicion = (int) $partido['posicion'];
        $ganador = $partido['id_equipo_ganador'] !== null ? (int) $partido['id_equipo_ganador'] : null;
        $nombreGanador = '<i>—</i>';
        if ($ganador !== null) {
            $nombreGanador = pdfTexto($ganador === (int) $partido['id_equipo_a'] ? $partido['nombre_a'] : $partido['nombre_b']);
        }
        $siguiente = llavesSiguienteCasilla((int) $ronda, $posicion, $totalRondas);

        $calendario .= '<tr>'
            . '<td class="centro mono">' . pdfCasilla((int) $ronda, $posicion) . '</td>'
            . '<td>' . pdfTexto(llavesNombreRonda((int) $ronda, $totalRondas)) . '</td>'
            . '<td class="centro">' . ($partido['hora_programada'] !== null ? substr((string) $partido['hora_programada'], 0, 5) : '—') . '</td>'
            . '<td>' . ($partido['cancha'] !== null && $partido['cancha'] !== '' ? pdfTexto($partido['cancha']) : '—') . '</td>'
            . '<td>' . pdfNombreLado($partido, 'a') . '</td>'
            . '<td>' . pdfNombreLado($partido, 'b') . '</td>'
            . '<td>' . $nombreGanador . '</td>'
            . '<td class="centro mono">' . ($siguiente === null ? 'Campeón' : pdfCasilla($siguiente['ronda'], $siguiente['posicion'])) . '</td>'
            . '</tr>';
    }
}
$calendario .= '</tbody></table>';

// --- Armado del documento --------------------------------------------------
$html = '<style>
    body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 13px; margin: 0 0 1px; }
    h2 { font-size: 11px; font-weight: normal; color: #475569; margin: 0 0 3px; }
    .meta { color: #64748b; font-size: 9px; margin: 0 0 6px; }
    .campeon { border: 1px solid #059669; background: #ecfdf5; color: #065f46;
               padding: 4px 8px; font-weight: bold; margin: 0 0 6px; }
    .comoleer { color: #64748b; font-size: 8.5px; margin: 0 0 6px; }

    table.cuadro { width: 100%; border-collapse: collapse; }
    table.cuadro th { font-size: 9px; text-transform: uppercase; color: #64748b;
                      padding: 0 4px 6px; text-align: center; }
    table.cuadro td { vertical-align: middle; padding: 2px 3px; }
    .partido { border: 1px solid #cbd5e1; }
    .partido .cab { background: #f1f5f9; color: #64748b; font-size: 7.5px; padding: 1px 4px;
                    border-bottom: 1px solid #e2e8f0; }
    .partido .cab .pasa { float: right; color: #0f172a; }
    .partido .equipo { padding: 3px 4px; font-size: 9.5px; border-bottom: 1px solid #f1f5f9; }
    .partido .equipo.gana { background: #ecfdf5; color: #065f46; font-weight: bold; }
    .partido .equipo.pierde { color: #94a3b8; }
    .partido .equipo .marcador { float: right; font-weight: bold; }

    table.calendario { width: 100%; border-collapse: collapse; font-size: 10px; }
    table.calendario th { background: #f1f5f9; color: #475569; font-size: 9px; text-transform: uppercase;
                          text-align: left; padding: 5px 6px; border-bottom: 1px solid #cbd5e1; }
    table.calendario td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
    .centro { text-align: center; }
    .mono { font-family: monospace; }
    .salto { page-break-before: always; }
    .pie { margin-top: 14px; font-size: 8px; color: #94a3b8; }
</style>';

$encabezado = '<h1>Universidad de Colima — Bachillerato 23</h1>'
    . '<h2>Semana Académica, Cultural y Deportiva — Aniversario #45<br>'
    . pdfTexto($competicion['nombre']) . ' — Llave de eliminación directa</h2>'
    . '<p class="meta">' . pdfTexto($diasLabel[$competicion['dia']] ?? $competicion['dia'])
    . ' · ' . substr((string) $competicion['hora_inicio'], 0, 5) . '–' . substr((string) $competicion['hora_fin'], 0, 5)
    . ' · ' . $totalRondas . ' ronda' . ($totalRondas === 1 ? '' : 's')
    . ' · ' . array_sum(array_map('count', $porRonda)) . ' partidos</p>';

$html .= $encabezado;

if ($campeon !== null) {
    $html .= '<p class="campeon">Campeón: ' . pdfTexto($campeon['nombre']) . '</p>';
}

$html .= '<p class="comoleer">Cada columna es una ronda. En la esquina de cada partido, '
    . '"&rarr; ' . pdfCasilla(2, 1) . '" indica a qué partido pasa el que gane. '
    . '"Pase directo (bye)" = ese equipo avanza sin jugar esa ronda, porque los equipos inscritos '
    . 'no son una potencia de 2.</p>';

$html .= $cuadro;

$html .= '<div class="salto">' . $encabezado
    . '<h2>Calendario de partidos</h2>' . $calendario
    . '<p class="pie">Generado el ' . date('d/m/Y H:i') . ' desde el panel de administración. '
    . 'Si la llave se regenera o se corrige un resultado, esta hoja deja de estar vigente.</p></div>';

// Nombre de archivo sin acentos ni espacios: viaja en una cabecera HTTP y lo
// abre gente en Windows, Android y iOS.
//
// La conversión de acentos va con un strtr explícito y no con
// iconv(..., 'ASCII//TRANSLIT'), cuyo resultado depende de la libc: en la
// imagen de Docker (Debian) "Fútbol" queda "Futbol", pero en Windows sale
// "F'utbol" y el archivo termina llamándose "f_utbol".
$sinAcentos = strtr((string) $competicion['nombre'], [
    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
]);
$nombreBase = 'llave_' . strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $sinAcentos), '_'));
if ($nombreBase === 'llave_') {
    $nombreBase = 'llave_competicion_' . $idCompeticion;
}

$opciones = new Options();
$opciones->set('isRemoteEnabled', false);

$dompdf = new Dompdf($opciones);
$dompdf->loadHtml($html, 'UTF-8');
// Horizontal: un cuadro de 4 rondas no cabe a lo ancho en vertical.
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream($nombreBase . '.pdf', ['Attachment' => true]);
exit;
