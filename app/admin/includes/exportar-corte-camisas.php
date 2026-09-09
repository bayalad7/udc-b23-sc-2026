<?php
declare(strict_types=1);

require __DIR__ . '/sesion.php';
require_once __DIR__ . '/../../camisas/includes/costo.php';
iniciarSesionAdmin();
exigirAdmin();

require __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

$consulta = $pdo->prepare(
    'SELECT id, grado, grupo, monto, entregado_por, recibido_por, fecha_movimiento, fecha_registro
     FROM camisa_cortes WHERE id = :id'
);
$consulta->execute(['id' => $id]);
$corte = $consulta->fetch();

if ($corte === false) {
    http_response_code(404);
    exit('No se encontró ese corte.');
}

// Sin setlocale/intl (poco confiables entre contenedores): un arreglo fijo
// de meses en español es lo más simple y no depende de la configuración del
// servidor — mismo criterio "vendorizado" que los íconos SVG inline.
const CORTE_MESES = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
    7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
];

function corteFechaBonita(string $fechaSql): string
{
    [$anio, $mes, $dia] = explode('-', substr($fechaSql, 0, 10));

    return ((int) $dia) . ' de ' . CORTE_MESES[(int) $mes] . ' de ' . $anio;
}

$nombreBase = 'recibo_corte_' . $corte['grado'] . $corte['grupo'] . '_folio' . $corte['id'];

$html = '<style>
    body { font-family: sans-serif; font-size: 12px; color: #1e293b; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    h2 { font-size: 12px; font-weight: normal; color: #475569; margin: 0 0 18px; }
    table.datos { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    table.datos td { padding: 6px 0; border-bottom: 1px solid #e2e8f0; }
    table.datos td.etiqueta { width: 35%; color: #64748b; }
    table.datos td.valor { font-weight: bold; }
    .monto { font-size: 20px; }
    table.firmas { width: 100%; margin-top: 60px; }
    table.firmas td { width: 50%; text-align: center; padding-top: 6px; border-top: 1px solid #1e293b; font-size: 11px; }
    .pie { margin-top: 40px; font-size: 9px; color: #94a3b8; }
</style>';

$html .= '<h1>Universidad de Colima — Bachillerato 23</h1>'
    . '<h2>Semana Académica, Cultural y Deportiva — Aniversario #45<br>Recibo de entrega — Cobranza de camisa oficial · Folio #' . (int) $corte['id'] . '</h2>';

$html .= '<table class="datos">'
    . '<tr><td class="etiqueta">Grado y grupo</td><td class="valor">' . htmlspecialchars($corte['grado'], ENT_QUOTES, 'UTF-8') . '°' . htmlspecialchars($corte['grupo'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td class="etiqueta">Fecha de entrega</td><td class="valor">' . corteFechaBonita($corte['fecha_movimiento']) . '</td></tr>'
    . '<tr><td class="etiqueta">Monto entregado</td><td class="valor monto">' . camisaMoneda((float) $corte['monto']) . '</td></tr>'
    . '<tr><td class="etiqueta">Entregó</td><td class="valor">' . htmlspecialchars($corte['entregado_por'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td class="etiqueta">Recibió</td><td class="valor">' . htmlspecialchars($corte['recibido_por'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '</table>';

$html .= '<table class="firmas"><tr>'
    . '<td>Firma de quien entrega<br>' . htmlspecialchars($corte['entregado_por'], ENT_QUOTES, 'UTF-8') . '</td>'
    . '<td>Firma de quien recibe<br>' . htmlspecialchars($corte['recibido_por'], ENT_QUOTES, 'UTF-8') . '</td>'
    . '</tr></table>';

$html .= '<p class="pie">Folio #' . (int) $corte['id'] . ' · Generado el ' . htmlspecialchars((string) $corte['fecha_registro'], ENT_QUOTES, 'UTF-8') . '</p>';

$opciones = new Options();
$opciones->set('isRemoteEnabled', false);

$dompdf = new Dompdf($opciones);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream($nombreBase . '.pdf', ['Attachment' => true]);
exit;
