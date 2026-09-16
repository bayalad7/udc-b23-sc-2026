<?php
declare(strict_types=1);

/**
 * Reporte de oficios de comisión — ponentes y talleristas.
 *
 * Genera 00-Informacion-General/oficios-de-comision.xlsx con una fila por
 * evento (ponencia/taller) del Día Académico y del Día Cultural, para saber a
 * quién hay que expedirle oficio de comisión.
 *
 * La fuente es app/database/seeds.sql y NO la base de datos: el catálogo de
 * eventos vive ahí y la base de desarrollo puede estar desfasada (ver la nota
 * de migraciones en CLAUDE.md). Volver a correr este script después de
 * actualizar los facilitadores en seeds.sql regenera el archivo:
 *
 *     php app/database/reportes/oficios-comision.php
 *
 * Dos columnas del reporte NO existen en el esquema y salen vacías a
 * propósito, para llenarse a mano: "# Trabajador" (los ponentes externos no
 * están en la tabla trabajadores, que solo guarda a quien pidió camisa) y
 * "Camisa talla" (misma razón).
 */

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$raizApp   = dirname(__DIR__, 2);            // .../app
$raizProy  = dirname($raizApp);              // .../B23 - Semana Cultural
$seedsPath = $raizApp . '/database/seeds.sql';
$logoUdeC  = $raizApp . '/assets/img/logo/UdeC_2L izq Negro.png';
$logoA45   = $raizApp . '/assets/img/logo/A45.png';
$salida    = $raizProy . '/00-Informacion-General/oficios-de-comision.xlsx';

// --- Datos: se leen de los INSERT INTO eventos de seeds.sql ---------------
// Solo se toman los 6 primeros campos de cada fila (dia, tipo, hora_inicio,
// hora_fin, facilitador, nombre); la descripción trae paréntesis y comas
// propios, así que el patrón se ancla al inicio de línea — cada evento
// sembrado ocupa exactamente una línea.

$sql = file_get_contents($seedsPath);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer $seedsPath\n");
    exit(1);
}

// El cierre del INSERT se busca como ";" a final de línea y no como el primer
// ";" del bloque: varias descripciones traen punto y coma en medio de la
// prosa y cortarían el bloque a la mitad, perdiendo eventos.
preg_match_all('/INSERT INTO eventos\b.*?\bVALUES(.*?);\s*$/ms', $sql, $bloques);

$patronFila = "/^\(\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,/m";

$dias = [
    'academico' => ['etiqueta' => 'Académico', 'fecha' => 'Jueves 1 de Octubre de 2026'],
    'cultural'  => ['etiqueta' => 'Cultural',  'fecha' => 'Viernes 2 de Octubre de 2026'],
    'deportivo' => ['etiqueta' => 'Deportivo', 'fecha' => 'Sábado 3 de Octubre de 2026'],
];

// Las filas salen en el MISMO orden en que están sembradas en seeds.sql (no
// se ordenan por día ni por nombre): así el reporte se puede cotejar renglón
// por renglón contra el archivo de semillas.

$filas = [];
foreach ($bloques[1] as $bloque) {
    preg_match_all($patronFila, $bloque, $rows, PREG_SET_ORDER);
    foreach ($rows as $r) {
        [, $dia, $tipo, , , $facilitador, $nombre] = $r;

        // "Mtra. ¿?" y cualquier placeholder se normalizan a "Por definir":
        // dejar el signo de interrogación en un oficio se vería como un error.
        if ($facilitador === '' || str_contains($facilitador, '¿?') || stripos($facilitador, 'por definir') !== false) {
            $facilitador = 'Por definir';
        }

        $filas[] = [
            'dia'    => $dias[$dia]['etiqueta'] ?? $dia,
            'fecha'  => $dias[$dia]['fecha'] ?? '',
            'tipo'   => ucfirst($tipo),
            'nombre' => $facilitador,
            'evento' => $nombre,
        ];
    }
}

if ($filas === []) {
    fwrite(STDERR, "No se encontró ningún evento en seeds.sql\n");
    exit(1);
}

// --- Hoja ----------------------------------------------------------------

$libro = new Spreadsheet();
$hoja  = $libro->getActiveSheet();
$hoja->setTitle('Oficios de comisión');

$libro->getProperties()
    ->setCreator('Bachillerato 23 — Universidad de Colima')
    ->setTitle('Oficios de comisión — Ponentes y talleristas')
    ->setSubject('Semana Cultural del Aniversario B23');

$anchos = ['A' => 14, 'B' => 28, 'C' => 15, 'D' => 14, 'E' => 34, 'F' => 58, 'G' => 13];
foreach ($anchos as $col => $ancho) {
    $hoja->getColumnDimension($col)->setWidth($ancho);
}

// Encabezado institucional: logos en los extremos, texto al centro.
foreach ([1 => 16, 2 => 16, 3 => 16, 4 => 16, 5 => 8] as $filaAlto => $alto) {
    $hoja->getRowDimension($filaAlto)->setRowHeight($alto);
}

$logo = new Drawing();
$logo->setName('Universidad de Colima');
$logo->setDescription('Universidad de Colima');
$logo->setPath($logoUdeC);
$logo->setHeight(60);
$logo->setCoordinates('A1');
$logo->setOffsetX(6);
$logo->setOffsetY(8);
$logo->setWorksheet($hoja);

$aniv = new Drawing();
$aniv->setName('45 Aniversario');
$aniv->setDescription('45 Aniversario del Bachillerato 23');
$aniv->setPath($logoA45);
$aniv->setHeight(74);
$aniv->setCoordinates('G1');
$aniv->setOffsetX(14);
$aniv->setOffsetY(2);
$aniv->setWorksheet($hoja);

$titulos = [
    1 => ['UNIVERSIDAD DE COLIMA', 13, true],
    2 => ['Bachillerato Técnico No. 23', 11, true],
    3 => ['Semana Cultural — 45 Aniversario', 10, false],
    4 => ['Oficios de comisión — Ponentes y talleristas', 11, true],
];
foreach ($titulos as $filaTitulo => [$texto, $tam, $negrita]) {
    $hoja->mergeCells("B{$filaTitulo}:F{$filaTitulo}");
    $hoja->setCellValue("B{$filaTitulo}", $texto);
    $hoja->getStyle("B{$filaTitulo}")->getFont()->setBold($negrita)->setSize($tam);
    $hoja->getStyle("B{$filaTitulo}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
}

// --- Tabla ---------------------------------------------------------------

$encabezados = ['Tipo', 'Fecha', 'Tipo de evento', '# Trabajador', 'Nombre completo', 'Nombre del evento', 'Camisa talla'];
$filaEnc = 6;
$hoja->fromArray($encabezados, null, "A{$filaEnc}");
$hoja->getRowDimension($filaEnc)->setRowHeight(26);
$hoja->getStyle("A{$filaEnc}:G{$filaEnc}")->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F5C3A']],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
]);

$fila = $filaEnc + 1;
foreach ($filas as $r) {
    $hoja->setCellValue("A{$fila}", $r['dia']);
    $hoja->setCellValue("B{$fila}", $r['fecha']);
    $hoja->setCellValue("C{$fila}", $r['tipo']);
    $hoja->setCellValueExplicit("D{$fila}", '', DataType::TYPE_STRING);
    $hoja->setCellValue("E{$fila}", $r['nombre']);
    $hoja->setCellValue("F{$fila}", $r['evento']);
    $hoja->setCellValueExplicit("G{$fila}", '', DataType::TYPE_STRING);
    $hoja->getRowDimension($fila)->setRowHeight(30);
    $fila++;
}
$ultima = $fila - 1;
$primera = $filaEnc + 1;

$hoja->getStyle("A{$filaEnc}:G{$ultima}")->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B7C4BB']]],
]);
$hoja->getStyle("A{$primera}:G{$ultima}")->getAlignment()
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);
$hoja->getStyle("A{$primera}:D{$ultima}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
$hoja->getStyle("G{$primera}:G{$ultima}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Zebra por fila para no perder el renglón en una tabla ancha.
for ($f = $primera; $f <= $ultima; $f++) {
    if (($f - $filaEnc) % 2 === 0) {
        $hoja->getStyle("A{$f}:G{$f}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F2F7F3');
    }
}

// Las dos columnas que se llenan a mano se marcan en amarillo claro.
foreach (['D', 'G'] as $col) {
    $hoja->getStyle("{$col}{$primera}:{$col}{$ultima}")->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7DB');
}

$filaNota = $ultima + 2;
$hoja->mergeCells("A{$filaNota}:G{$filaNota}");
$hoja->setCellValue(
    "A{$filaNota}",
    'Las columnas sombreadas (# Trabajador y Camisa talla) no están en el sistema de registro y se capturan a mano. '
    . 'Total de comisiones: ' . count($filas) . '.'
);
$hoja->getStyle("A{$filaNota}")->getFont()->setItalic(true)->setSize(9);
$hoja->getRowDimension($filaNota)->setRowHeight(22);

$hoja->setAutoFilter("A{$filaEnc}:G{$ultima}");
$hoja->freezePane("A{$primera}");

$hoja->getPageSetup()
    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
    ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
    ->setFitToWidth(1)
    ->setFitToHeight(0);
$hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($filaEnc, $filaEnc);
$hoja->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
$hoja->setSelectedCell('A1');

(new Xlsx($libro))->save($salida);

echo 'Reporte generado: ' . $salida . ' (' . count($filas) . " comisiones)\n";
