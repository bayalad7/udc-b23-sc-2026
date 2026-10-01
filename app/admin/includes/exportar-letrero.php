<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

require __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/dias.php';
require_once __DIR__ . '/cupo-eventos.php';
require_once __DIR__ . '/equipos-competicion.php';
require_once __DIR__ . '/../../inscripciones/includes/requerimientos.php';

// Letrero imprimible para pegar en la puerta del aula o del espacio: arriba el
// evento en letras grandes, para leerse de pie a dos o tres metros, y debajo la
// lista de quién está inscrito, para que el alumnado se busque sin tener que
// preguntar.
//
// Sirve para un evento (`evento=N`: ponencias y talleres, con sus inscritos) o
// para una competición (`competicion=N`: concursos y torneos, con sus equipos
// e integrantes). Es el mismo papel con dos contenidos, así que va en un solo
// archivo en vez de duplicar el cartel.
//
// Ojo con el ancho: Dompdf renderiza CSS 2.1 —sin flexbox ni grid— así que las
// dos columnas de la lista son una tabla de dos celdas, cada una con su propia
// subtabla (mismo recurso que el cuadro de llaves en exportar-llave.php). Dos
// columnas no son un lujo: una ponencia de 180 inscritos a una columna son
// cuatro hojas que alguien tiene que pegar y nadie va a leer completas.

$idEvento = isset($_GET['evento']) ? (int) $_GET['evento'] : null;
$idCompeticion = isset($_GET['competicion']) ? (int) $_GET['competicion'] : null;

if (($idEvento === null) === ($idCompeticion === null)) {
    http_response_code(400);
    exit('Pide un evento o una competición, no los dos.');
}
if (($idEvento !== null && $idEvento <= 0) || ($idCompeticion !== null && $idCompeticion <= 0)) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

/**
 * Parte la lista en dos columnas del mismo alto: la primera mitad a la
 * izquierda y la segunda a la derecha, para que se lea de arriba a abajo en
 * cada columna como una lista de papel y no en zigzag.
 *
 * @param list<string> $celdas
 */
function letreroDosColumnas(array $celdas, string $encabezado): string
{
    if ($celdas === []) {
        return '';
    }

    $mitad = (int) ceil(count($celdas) / 2);
    $columnas = [array_slice($celdas, 0, $mitad), array_slice($celdas, $mitad)];

    $html = '<table class="columnas"><tr>';
    foreach ($columnas as $columna) {
        $html .= '<td class="columna">';
        if ($columna !== []) {
            $html .= '<table class="lista"><thead><tr>' . $encabezado . '</tr></thead><tbody>'
                . implode('', $columna) . '</tbody></table>';
        }
        $html .= '</td>';
    }

    return $html . '</tr></table>';
}

/** Nombre de archivo sin acentos ni espacios — viaja en una cabecera HTTP. */
function letreroNombreArchivo(string $prefijo, string $nombre, int $id): string
{
    // El strtr explícito y no iconv(..., 'ASCII//TRANSLIT'), cuyo resultado
    // depende de la libc (ver includes/exportar-llave.php).
    $sinAcentos = strtr($nombre, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $sufijo = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $sinAcentos), '_'));

    return $prefijo . '_' . ($sufijo !== '' ? $sufijo : (string) $id) . '_' . date('Y-m-d_His');
}

$estilos = '<style>
    @page { margin: 1.2cm; }
    body { font-family: sans-serif; color: #0f172a; }

    /* --- Cartel: lo que se lee de lejos --------------------------------- */
    div.cartel { border: 3px solid #0f172a; border-radius: 10px; padding: 14px 18px; text-align: center; }
    p.dia { margin: 0 0 6px; font-size: 15px; letter-spacing: 2px; text-transform: uppercase; color: #475569; }
    h1.nombre { margin: 0; font-size: 40px; line-height: 1.1; }
    p.tipo { margin: 8px 0 0; font-size: 17px; text-transform: uppercase; letter-spacing: 1px; color: #475569; }
    table.datos { width: 100%; margin-top: 14px; border-collapse: collapse; }
    table.datos td { width: 50%; padding: 6px 4px; text-align: center; }
    span.etiqueta { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
    span.dato { display: block; font-size: 30px; font-weight: bold; line-height: 1.15; }
    p.facilitador { margin: 10px 0 0; font-size: 18px; }

    /* --- Qué llevar: cabe en la puerta y evita el "no sabía" ------------- */
    div.llevar { margin-top: 12px; border: 2px dashed #94a3b8; border-radius: 8px; padding: 8px 12px; text-align: left; }
    div.llevar strong { font-size: 15px; text-transform: uppercase; letter-spacing: 1px; }
    div.llevar ul { margin: 4px 0 0; padding-left: 18px; font-size: 16px; }

    /* --- Lista de inscritos -------------------------------------------- */
    h2.titulo-lista { margin: 18px 0 6px; font-size: 20px; }
    p.aviso { margin: 0 0 8px; font-size: 12px; color: #64748b; }
    table.columnas { width: 100%; border-collapse: collapse; }
    td.columna { width: 50%; vertical-align: top; padding: 0 6px; }
    table.lista { width: 100%; border-collapse: collapse; }
    table.lista th { background: #e2e8f0; font-size: 11px; text-transform: uppercase; padding: 4px 5px; text-align: left; }
    table.lista td { font-size: 13px; padding: 4px 5px; border-bottom: 1px solid #cbd5e1; }
    table.lista td.num { width: 26px; color: #64748b; text-align: right; }
    table.lista td.grupo { width: 40px; text-align: center; color: #475569; }
    table.lista th.centro, table.lista td.centro { text-align: center; }
    tr.equipo td { background: #e2e8f0; font-weight: bold; font-size: 13px; }
    p.pie { margin-top: 14px; font-size: 11px; color: #64748b; text-align: center; }
    p.vacio { margin: 0; border: 1px solid #cbd5e1; padding: 10px; font-size: 14px; color: #475569; }
</style>';

$escapar = static fn(?string $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');

// --- Letrero de un evento (ponencia o taller) ------------------------------

if ($idEvento !== null) {
    $eventos = eventosConCupo($pdo, $idEvento);
    if ($eventos === []) {
        http_response_code(404);
        exit('Evento no encontrado.');
    }
    $evento = $eventos[0];
    $inscritos = inscritosDeEventos($pdo, $idEvento)[$idEvento] ?? [];

    $html = $estilos . '<div class="cartel">'
        . '<p class="dia">' . $escapar($evento['dia_label'] . ' · ' . diaEventoFecha((string) $evento['dia'])) . '</p>'
        . '<h1 class="nombre">' . $escapar($evento['nombre']) . '</h1>'
        . '<p class="tipo">' . $escapar(ucfirst((string) $evento['tipo'])) . '</p>'
        . '<table class="datos"><tr>'
        . '<td><span class="etiqueta">Horario</span><span class="dato">' . $escapar($evento['horario']) . '</span></td>'
        . '<td><span class="etiqueta">Lugar</span><span class="dato">' . $escapar($evento['espacio']) . '</span></td>'
        . '</tr></table>'
        . '<p class="facilitador">Imparte: <strong>' . $escapar($evento['facilitador']) . '</strong></p>';

    $requerimientos = requerimientosLista($evento['requerimientos'] ?? null);
    if ($requerimientos !== []) {
        $html .= '<div class="llevar"><strong>Qué debes traer</strong><ul>';
        foreach ($requerimientos as $requerimiento) {
            $html .= '<li>' . $escapar($requerimiento) . '</li>';
        }
        $html .= '</ul></div>';
    }

    $html .= '</div>';

    $html .= '<h2 class="titulo-lista">Alumnos inscritos — ' . count($inscritos) . ' de ' . $evento['cupo_maximo'] . '</h2>'
        . '<p class="aviso">Impreso el ' . date('d/m/Y H:i')
        . '. Si no apareces en la lista, pregunta en la mesa de registro antes de entrar.</p>';

    if ($inscritos === []) {
        $html .= '<p class="vacio">Todavía no hay nadie inscrito en este evento.</p>';
    } else {
        $celdas = [];
        foreach ($inscritos as $indice => $inscrito) {
            $celdas[] = '<tr>'
                . '<td class="num">' . ($indice + 1) . '</td>'
                . '<td>' . $escapar($inscrito['nombre_completo']) . '</td>'
                . '<td class="grupo">' . $escapar($inscrito['grado_grupo']) . '</td>'
                . '<td class="centro">' . $escapar($inscrito['numero_cuenta']) . '</td>'
                . '</tr>';
        }
        $html .= letreroDosColumnas(
            $celdas,
            '<th></th><th>Alumno</th><th class="centro">Grupo</th><th class="centro">No. cuenta</th>'
        );
    }

    $nombreArchivo = letreroNombreArchivo('letrero', (string) $evento['nombre'], $idEvento);
} else {

// --- Letrero de una competición (concurso o torneo) ------------------------

    $competiciones = competicionesConEquipos($pdo, $idCompeticion);
    if ($competiciones === []) {
        http_response_code(404);
        exit('Competición no encontrada.');
    }
    $competicion = $competiciones[0];
    $equipos = equiposDeCompeticiones($pdo, $idCompeticion)[$idCompeticion] ?? [];
    $totalEquipos = (int) $competicion['total_equipos'];

    $html = $estilos . '<div class="cartel">'
        . '<p class="dia">' . $escapar(diaEventoLabel((string) $competicion['dia']) . ' · ' . diaEventoFecha((string) $competicion['dia'])) . '</p>'
        . '<h1 class="nombre">' . $escapar($competicion['nombre']) . '</h1>'
        . '<p class="tipo">' . $escapar(ucfirst((string) $competicion['tipo'])) . '</p>'
        . '<table class="datos"><tr>'
        . '<td><span class="etiqueta">Horario</span><span class="dato">'
        . $escapar(cupoEventoHorario($competicion['hora_inicio'], $competicion['hora_fin'])) . '</span></td>'
        . '<td><span class="etiqueta">Equipos</span><span class="dato">' . $totalEquipos
        . ($competicion['max_equipos'] !== null ? ' de ' . (int) $competicion['max_equipos'] : '') . '</span></td>'
        . '</tr></table>'
        . '</div>';

    $html .= '<h2 class="titulo-lista">Equipos inscritos — ' . $totalEquipos . '</h2>'
        . '<p class="aviso">Impreso el ' . date('d/m/Y H:i')
        . '. Si tu equipo no aparece, pregunta en la mesa de registro antes de empezar.</p>';

    if ($equipos === []) {
        $html .= '<p class="vacio">Todavía no hay equipos inscritos en esta competición.</p>';
    } else {
        // Un bloque por equipo —su renglón de encabezado y debajo sus
        // integrantes— repartidos en las dos columnas de la hoja.
        $celdas = [];
        foreach ($equipos as $equipo) {
            $encabezadoEquipo = $equipo['nombre'];
            if ($equipo['color_camisa'] !== null) {
                $encabezadoEquipo .= ' · ' . $equipo['color_camisa'];
            }
            $celdas[] = '<tr class="equipo"><td colspan="3">' . $escapar($encabezadoEquipo) . '</td></tr>';

            foreach ($equipo['integrantes'] as $indice => $integrante) {
                // Por número de cuenta y no por nombre: hay alumnos
                // homónimos en el padrón.
                $esCapitan = $integrante['numero_cuenta'] !== null
                    && (string) $integrante['numero_cuenta'] === (string) $equipo['capitan_cuenta'];
                $celdas[] = '<tr>'
                    . '<td class="num">' . ($indice + 1) . '</td>'
                    . '<td>' . $escapar($integrante['nombre']) . ($esCapitan ? ' <strong>(capitán)</strong>' : '') . '</td>'
                    . '<td class="grupo">' . $escapar(
                        equiposGradoGrupo($integrante['grado'], $integrante['grupo']) ?? ucfirst((string) $integrante['tipo'])
                    ) . '</td>'
                    . '</tr>';
            }
        }
        $html .= letreroDosColumnas($celdas, '<th></th><th>Integrante</th><th class="centro">Grupo</th>');
    }

    $nombreArchivo = letreroNombreArchivo('letrero', (string) $competicion['nombre'], $idCompeticion);
}

$opciones = new Options();
$opciones->set('isRemoteEnabled', false);

$dompdf = new Dompdf($opciones);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream($nombreArchivo . '.pdf', ['Attachment' => true]);
exit;
