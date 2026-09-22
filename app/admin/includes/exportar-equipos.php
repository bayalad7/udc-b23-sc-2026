<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
iniciarSesionAdmin();
exigirAdmin();

require __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/equipos-competicion.php';

$formato = trim((string) ($_GET['formato'] ?? ''));
if (!in_array($formato, ['xlsx', 'pdf'], true)) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

// Sin `competicion` sale el padrón completo (el botón del encabezado del
// modal); con él, solo esa competición (los botones de cada renglón de
// encabezado). Una competición inexistente se responde 404 en vez de entregar
// un archivo vacío que parecería decir "no hay equipos inscritos".
$idCompeticion = isset($_GET['competicion']) ? (int) $_GET['competicion'] : null;
if ($idCompeticion !== null && $idCompeticion <= 0) {
    http_response_code(400);
    exit('Competición inválida.');
}

$competiciones = competicionesConEquipos($pdo, $idCompeticion);
if ($competiciones === []) {
    http_response_code(404);
    exit('No hay competiciones que reportar.');
}

$equiposPorCompeticion = equiposDeCompeticiones($pdo, $idCompeticion);

// --- Nombre del archivo ----------------------------------------------------
// Sin acentos ni espacios: viaja en una cabecera HTTP y lo abre gente en
// Windows, Android y iOS. El strtr explícito, en vez de
// iconv(..., 'ASCII//TRANSLIT'), porque el resultado de iconv depende de la
// libc (ver includes/exportar-llave.php).

$nombreBase = 'equipos_competiciones';
if ($idCompeticion !== null) {
    $sinAcentos = strtr((string) $competiciones[0]['nombre'], [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $sufijo = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $sinAcentos), '_'));
    $nombreBase = 'equipos_' . ($sufijo !== '' ? $sufijo : 'competicion_' . $idCompeticion);
}
$nombreBase .= '_' . date('Y-m-d_His');

// --- Excel -----------------------------------------------------------------
// Dos hojas en vez de una por competición: los nombres de competición no caben
// en el título de una pestaña (31 caracteres) y, sobre todo, una tabla corrida
// es lo que se puede filtrar y ordenar en Excel. La hoja "Equipos" es el
// padrón (un renglón por equipo) y la de "Integrantes" el detalle persona por
// persona.

if ($formato === 'xlsx') {
    $hoja = new Spreadsheet();

    $hojaEquipos = $hoja->getActiveSheet();
    $hojaEquipos->setTitle('Equipos');
    $hojaEquipos->fromArray(
        ['Día', 'Competición', 'Equipo', 'Color', 'Capitán', 'No. cuenta', 'Grado', 'Grupo', 'Integrantes', 'Tamaño de equipo'],
        null,
        'A1'
    );
    $hojaEquipos->getStyle('A1:J1')->getFont()->setBold(true);

    $hojaIntegrantes = $hoja->createSheet();
    $hojaIntegrantes->setTitle('Integrantes');
    $hojaIntegrantes->fromArray(
        ['Día', 'Competición', 'Equipo', 'Integrante', 'Tipo', 'No. cuenta', 'Grado', 'Grupo', 'Código participante', 'Entrada', 'Salida'],
        null,
        'A1'
    );
    $hojaIntegrantes->getStyle('A1:K1')->getFont()->setBold(true);

    $filaEquipos = 2;
    $filaIntegrantes = 2;
    foreach ($competiciones as $competicion) {
        $diaLabel = diaEventoLabel((string) $competicion['dia']);
        foreach ($equiposPorCompeticion[(int) $competicion['id']] ?? [] as $equipo) {
            $hojaEquipos->fromArray([
                $diaLabel,
                $competicion['nombre'],
                $equipo['nombre'],
                $equipo['color_camisa'] ?? '',
                $equipo['capitan'],
                $equipo['capitan_cuenta'],
                $equipo['capitan_grado'] . '°',
                $equipo['capitan_grupo'],
                count($equipo['integrantes']),
                $competicion['tam_equipo'] !== null ? (int) $competicion['tam_equipo'] : '',
            ], null, 'A' . $filaEquipos);
            $filaEquipos++;

            foreach ($equipo['integrantes'] as $integrante) {
                // Grado, grupo y número de cuenta van vacíos en padres y madres
                // de familia a propósito: los del alumno-ancla no son suyos
                // (ver equiposGradoGrupo en equipos-competicion.php).
                $hojaIntegrantes->fromArray([
                    $diaLabel,
                    $competicion['nombre'],
                    $equipo['nombre'],
                    $integrante['nombre'],
                    ucfirst((string) $integrante['tipo']),
                    $integrante['numero_cuenta'] ?? '',
                    $integrante['grado'] !== null ? $integrante['grado'] . '°' : '',
                    $integrante['grupo'] ?? '',
                    $integrante['codigo_participante'],
                    $integrante['hora_entrada'] ?? '',
                    $integrante['hora_salida'] ?? '',
                ], null, 'A' . $filaIntegrantes);
                $filaIntegrantes++;
            }
        }
    }

    foreach (range('A', 'J') as $columna) {
        $hojaEquipos->getColumnDimension($columna)->setAutoSize(true);
    }
    foreach (range('A', 'K') as $columna) {
        $hojaIntegrantes->getColumnDimension($columna)->setAutoSize(true);
    }
    $hoja->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreBase . '.xlsx"');
    header('Cache-Control: max-age=0');

    $escritor = new Xlsx($hoja);
    $escritor->save('php://output');
    exit;
}

// --- PDF -------------------------------------------------------------------
// Una competición por hoja: es lo que se le entrega al responsable de ese
// torneo o concurso, así que cada hoja repite su encabezado y se vale sola.

$estilos = '<style>
    body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 14px; margin-bottom: 2px; }
    p.resumen { margin: 0 0 10px; color: #64748b; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; }
    th { background: #f1f5f9; text-align: center; }
    td.centro, th.centro { text-align: center; }
    tr.equipo td { background: #e2e8f0; }
    tr.vacio td { color: #64748b; }
    p.pie { margin-top: 10px; color: #64748b; font-size: 10px; }
    div.hoja-competicion { page-break-before: always; }
    div.hoja-competicion:first-of-type { page-break-before: avoid; }
</style>';

$html = $estilos;
foreach ($competiciones as $competicion) {
    $equipos = $equiposPorCompeticion[(int) $competicion['id']] ?? [];
    $resumen = diaEventoLabel((string) $competicion['dia']) . ' · ' . count($equipos)
        . ($competicion['max_equipos'] !== null ? ' de ' . (int) $competicion['max_equipos'] : '')
        . ' equipo' . (count($equipos) === 1 ? '' : 's')
        . ($competicion['tam_equipo'] !== null ? ' · ' . (int) $competicion['tam_equipo'] . ' integrantes por equipo' : '');

    $html .= '<div class="hoja-competicion">'
        . '<h1>' . htmlspecialchars((string) $competicion['nombre'], ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<p class="resumen">' . htmlspecialchars($resumen, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<table><thead><tr>'
        . '<th class="centro">#</th><th>Integrante</th><th class="centro">Tipo</th>'
        . '<th class="centro">Grado y grupo</th><th class="centro">Código participante</th>'
        . '</tr></thead><tbody>';

    if ($equipos === []) {
        $html .= '<tr class="vacio"><td colspan="5">Todavía no hay equipos inscritos.</td></tr>';
    }

    foreach ($equipos as $equipo) {
        $capitan = 'Capitán: ' . $equipo['capitan'];
        $gradoGrupoCapitan = equiposGradoGrupo($equipo['capitan_grado'], $equipo['capitan_grupo']);
        if ($gradoGrupoCapitan !== null) {
            $capitan .= ' (' . $gradoGrupoCapitan . ')';
        }
        $encabezadoEquipo = $equipo['nombre'] . ' — ' . $capitan
            . ($equipo['color_camisa'] !== null ? ' — Color: ' . $equipo['color_camisa'] : '');

        $html .= '<tr class="equipo"><td colspan="5"><strong>'
            . htmlspecialchars((string) $encabezadoEquipo, ENT_QUOTES, 'UTF-8') . '</strong></td></tr>';

        if ($equipo['integrantes'] === []) {
            $html .= '<tr class="vacio"><td colspan="5">Sin integrantes capturados.</td></tr>';
            continue;
        }

        foreach ($equipo['integrantes'] as $indice => $integrante) {
            $html .= '<tr>'
                . '<td class="centro">' . ($indice + 1) . '</td>'
                . '<td>' . htmlspecialchars((string) $integrante['nombre'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="centro">' . htmlspecialchars(ucfirst((string) $integrante['tipo']), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="centro">' . htmlspecialchars(equiposGradoGrupo($integrante['grado'], $integrante['grupo']) ?? '—', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="centro">' . htmlspecialchars((string) $integrante['codigo_participante'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
    }

    $html .= '</tbody></table>'
        . '<p class="pie">Generado el ' . date('d/m/Y H:i') . ' desde el panel de administración. '
        . 'Es una foto del momento: los equipos que se inscriban después no aparecen en esta hoja.</p>'
        . '</div>';
}

$opciones = new Options();
$opciones->set('isRemoteEnabled', false);

$dompdf = new Dompdf($opciones);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream($nombreBase . '.pdf', ['Attachment' => true]);
exit;
