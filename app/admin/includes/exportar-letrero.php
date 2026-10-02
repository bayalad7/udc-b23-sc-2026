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

// Cuántos renglones entran por hoja. El primer bloque comparte hoja con el
// cartel, así que le toca menos; los siguientes tienen la hoja entera. Los
// números están MEDIDOS generando el PDF y viendo en qué hoja empieza la lista
// (hoja carta vertical, con la fuente, los márgenes y la banda de logos de
// abajo): con 44 la lista arranca en la hoja 1 y con 48 se va a la 2. Si el
// cartel trae el recuadro de "Qué debes traer" crece y caben menos (36), de ahí
// el segundo tope. Todo lo que le agregue alto al cartel —los logos lo hicieron
// y costó 4 renglones— obliga a volver a medir. Quedarse corto
// solo deja un hueco al final de la hoja; pasarse manda el bloque completo a la
// siguiente y vuelve a dejar media hoja en blanco, que es justo lo que se
// corrigió. Si se cambia la tipografía o los márgenes, hay que volver a medir.
// Los dos logos institucionales del cartel: la Universidad a la izquierda y el
// 45 aniversario a la derecha, que es como se firman los impresos del evento.
//
// OJO, son las copias de assets/img/logo/impresos/ y no los originales: Dompdf
// incrusta el PNG tal cual le llega, y con el A45 original (1254x1254, 900 KB)
// cada letrero pesaba 1 MB y tardaba 18 segundos en generarse. Las copias están
// al triple del tamaño al que se imprimen (300 DPI), así que se ven igual de
// nítidas en papel y el letrero vuelve a salir en menos de un segundo. Si se
// cambia el alto con el que se dibujan abajo, hay que regenerarlas.
//
// Van por ruta de archivo y no como data: URI en base64, que crece un tercio
// más. Para que Dompdf pueda abrirlas hay que apuntarle el chroot a app/assets
// (ver más abajo): fuera de ahí las bloquea, y con isRemoteEnabled en false no
// hay forma de traerlas por URL.
const LETRERO_LOGO_UDEC = 'logo/impresos/UdeC_2L izq Negro.png';
const LETRERO_LOGO_ANIVERSARIO = 'logo/impresos/A45.png';

const LETRERO_FILAS_PRIMERA_HOJA = 44;
const LETRERO_FILAS_PRIMERA_HOJA_CON_RECUADRO = 36;
const LETRERO_FILAS_POR_HOJA = 64;

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
 * Parte la lista en bloques, y cada bloque en dos columnas del mismo alto: la
 * primera mitad a la izquierda y la segunda a la derecha, para que se lea de
 * arriba a abajo en cada columna como una lista de papel y no en zigzag.
 *
 * POR QUÉ EN BLOQUES: Dompdf no parte una tabla de dos celdas entre hojas, así
 * que una lista larga en una sola tabla no cabía debajo del cartel y se iba
 * entera a la hoja 2, dejando media hoja 1 en blanco. Troceándola, el primer
 * bloque es el que sí cabe junto al cartel y los siguientes llenan las hojas
 * que haga falta. De ahí los dos topes: el primer bloque es más corto porque
 * comparte hoja con el cartel.
 *
 * Cada bloque repite los encabezados de columna, y si un bloque arranca a la
 * mitad de un grupo (los integrantes de un equipo) se repite el renglón del
 * grupo con un "(continúa)", para que la hoja se entienda sola.
 *
 * @param list<array{html: string, grupo?: string, es_grupo?: bool}> $celdas
 */
function letreroListaPaginada(array $celdas, string $encabezado, int $topePrimero, int $topeResto): string
{
    if ($celdas === []) {
        return '';
    }

    $bloques = [];
    $pendientes = $celdas;
    $tope = $topePrimero;
    while ($pendientes !== []) {
        $bloques[] = array_slice($pendientes, 0, $tope);
        $pendientes = array_slice($pendientes, $tope);
        $tope = $topeResto;
    }

    $html = '';
    foreach ($bloques as $indice => $bloque) {
        if ($indice > 0) {
            $html .= '<div class="salto"></div>';

            // ¿El bloque empieza a media lista de un grupo? Se repite su
            // renglón para no dejar integrantes huérfanos de su equipo.
            $primera = $bloque[0];
            if (($primera['es_grupo'] ?? false) === false && ($primera['grupo'] ?? '') !== '') {
                array_unshift($bloque, [
                    'html' => '<tr class="equipo"><td colspan="3">'
                        . htmlspecialchars($primera['grupo'] . ' (continúa)', ENT_QUOTES, 'UTF-8') . '</td></tr>',
                    'es_grupo' => true,
                ]);
            }
        }

        $mitad = (int) ceil(count($bloque) / 2);
        $columnas = [array_slice($bloque, 0, $mitad), array_slice($bloque, $mitad)];

        $html .= '<table class="columnas"><tr>';
        foreach ($columnas as $columna) {
            $html .= '<td class="columna">';
            if ($columna !== []) {
                $html .= '<table class="lista"><thead><tr>' . $encabezado . '</tr></thead><tbody>'
                    . implode('', array_column($columna, 'html')) . '</tbody></table>';
            }
            $html .= '</td>';
        }
        $html .= '</tr></table>';
    }

    return $html;
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
    table.logos { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.logos td { padding: 0; vertical-align: middle; }
    td.logo-udec { text-align: left; }
    td.logo-udec img { height: 34px; }
    td.logo-aniversario { text-align: right; }
    td.logo-aniversario img { height: 52px; }
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
    div.salto { page-break-before: always; }
    p.vacio { margin: 0; border: 1px solid #cbd5e1; padding: 10px; font-size: 14px; color: #475569; }
</style>';

$escapar = static fn(?string $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');

/**
 * Banda de logos del cartel. Si falta alguno de los dos archivos se omite sin
 * decir nada: el letrero es para pegarlo hoy en una puerta, y vale mucho más
 * imprimirlo sin logo que no poder imprimirlo.
 */
function letreroLogos(): string
{
    $directorio = __DIR__ . '/../../assets/img/';
    $udec = $directorio . LETRERO_LOGO_UDEC;
    $aniversario = $directorio . LETRERO_LOGO_ANIVERSARIO;

    if (!is_file($udec) || !is_file($aniversario)) {
        return '';
    }

    return '<table class="logos"><tr>'
        . '<td class="logo-udec"><img src="' . htmlspecialchars($udec, ENT_QUOTES, 'UTF-8') . '" alt="Universidad de Colima"></td>'
        . '<td class="logo-aniversario"><img src="' . htmlspecialchars($aniversario, ENT_QUOTES, 'UTF-8') . '" alt="45 Aniversario"></td>'
        . '</tr></table>';
}

// --- Letrero de un evento (ponencia o taller) ------------------------------

if ($idEvento !== null) {
    $eventos = eventosConCupo($pdo, $idEvento);
    if ($eventos === []) {
        http_response_code(404);
        exit('Evento no encontrado.');
    }
    $evento = $eventos[0];
    $inscritos = inscritosDeEventos($pdo, $idEvento)[$idEvento] ?? [];

    $html = $estilos . '<div class="cartel">' . letreroLogos()
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
            $celdas[] = ['html' => '<tr>'
                . '<td class="num">' . ($indice + 1) . '</td>'
                . '<td>' . $escapar($inscrito['nombre_completo']) . '</td>'
                . '<td class="grupo">' . $escapar($inscrito['grado_grupo']) . '</td>'
                . '<td class="centro">' . $escapar($inscrito['numero_cuenta']) . '</td>'
                . '</tr>'];
        }
        $html .= letreroListaPaginada(
            $celdas,
            '<th></th><th>Alumno</th><th class="centro">Grupo</th><th class="centro">No. cuenta</th>',
            $requerimientos !== [] ? LETRERO_FILAS_PRIMERA_HOJA_CON_RECUADRO : LETRERO_FILAS_PRIMERA_HOJA,
            LETRERO_FILAS_POR_HOJA
        );
    }

    $tituloLetrero = (string) $evento['nombre'];
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

    $html = $estilos . '<div class="cartel">' . letreroLogos()
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
            $celdas[] = [
                'html' => '<tr class="equipo"><td colspan="3">' . $escapar($encabezadoEquipo) . '</td></tr>',
                'grupo' => (string) $equipo['nombre'],
                'es_grupo' => true,
            ];

            foreach ($equipo['integrantes'] as $indice => $integrante) {
                // Por número de cuenta y no por nombre: hay alumnos
                // homónimos en el padrón.
                $esCapitan = $integrante['numero_cuenta'] !== null
                    && (string) $integrante['numero_cuenta'] === (string) $equipo['capitan_cuenta'];
                $celdas[] = [
                    'html' => '<tr>'
                        . '<td class="num">' . ($indice + 1) . '</td>'
                        . '<td>' . $escapar($integrante['nombre']) . ($esCapitan ? ' <strong>(capitán)</strong>' : '') . '</td>'
                        . '<td class="grupo">' . $escapar(
                            equiposGradoGrupo($integrante['grado'], $integrante['grupo']) ?? ucfirst((string) $integrante['tipo'])
                        ) . '</td>'
                        . '</tr>',
                    'grupo' => (string) $equipo['nombre'],
                ];
            }
        }
        $html .= letreroListaPaginada(
            $celdas,
            '<th></th><th>Integrante</th><th class="centro">Grupo</th>',
            LETRERO_FILAS_PRIMERA_HOJA,
            LETRERO_FILAS_POR_HOJA
        );
    }

    $tituloLetrero = (string) $competicion['nombre'];
    $nombreArchivo = letreroNombreArchivo('letrero', (string) $competicion['nombre'], $idCompeticion);
}

$opciones = new Options();
$opciones->set('isRemoteEnabled', false);
// Sin este chroot Dompdf no abre los logos: por seguridad solo lee archivos
// debajo del directorio permitido, y por omisión ese no incluye app/assets.
$opciones->set('chroot', realpath(__DIR__ . '/../../assets'));

$dompdf = new Dompdf($opciones);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();

// Encabezado de continuación: cuando la lista no cabe en una hoja, cada una
// lleva en el margen de arriba de qué evento es y qué hoja de cuántas. Va
// sellado sobre el PDF ya armado (page_text) y no como HTML, porque los
// marcadores {PAGE_NUM}/{PAGE_COUNT} los resuelve Dompdf cuando ya sabe
// cuántas hojas salieron — contar bloques a mano mentiría si alguno se
// desborda. Con una sola hoja no se sella nada: no hay nada que numerar.
$lienzo = $dompdf->getCanvas();
if ($lienzo->get_page_count() > 1) {
    $lienzo->page_text(
        34,
        22,
        $tituloLetrero . ' — hoja {PAGE_NUM} de {PAGE_COUNT}',
        $dompdf->getFontMetrics()->getFont('sans-serif', 'bold'),
        9,
        [0.3, 0.35, 0.42]
    );
}

$dompdf->stream($nombreArchivo . '.pdf', ['Attachment' => true]);
exit;
