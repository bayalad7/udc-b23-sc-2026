<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/pendalff/phpqrcode/qrlib.php';

const CREDENCIAL_ANCHO = 1080;
const CREDENCIAL_ALTO = 1920;
// Fuentes DejaVu empacadas con la dependencia dompdf/dompdf (vendor/), no del
// sistema operativo -- así no dependen de tener el paquete de fuentes
// instalado (ej. fonts-dejavu-core) en cada servidor donde corra la app.
const CREDENCIAL_FUENTE_REGULAR = __DIR__ . '/../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';
const CREDENCIAL_FUENTE_NEGRITA = __DIR__ . '/../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
const CREDENCIAL_LOGO = __DIR__ . '/../../assets/img/logo/UdeC_2L izq Negro.png';
const CREDENCIAL_MARCA_AGUA = __DIR__ . '/../../assets/img/logo/A45.png';
const CREDENCIAL_MARCA_AGUA_LADO = 1000;
const CREDENCIAL_MARCA_AGUA_OPACIDAD = 12;

/**
 * Genera la credencial digital vertical (foto + datos + QR) de un alumno ya
 * guardado en la base de datos, y actualiza credencial_generada/credencial_path.
 */
function generarCredencial(PDO $pdo, string $numeroCuenta, string $token): void
{
    $consulta = $pdo->prepare('SELECT nombre_completo, grado, grupo, foto_path FROM alumnos WHERE numero_cuenta = :cuenta');
    $consulta->execute(['cuenta' => $numeroCuenta]);
    $alumno = $consulta->fetch();

    if ($alumno === false) {
        throw new RuntimeException('No se encontró al alumno ' . $numeroCuenta . ' para generar su credencial.');
    }

    $directorioUploads = __DIR__ . '/../public/uploads';
    $directorioCredenciales = __DIR__ . '/../public/credenciales';
    if (!is_dir($directorioCredenciales)) {
        mkdir($directorioCredenciales, 0755, true);
    }

    $lienzo = imagecreatetruecolor(CREDENCIAL_ANCHO, CREDENCIAL_ALTO);
    $blanco = imagecolorallocate($lienzo, 255, 255, 255);
    $negro = imagecolorallocate($lienzo, 17, 24, 39);       // slate-900
    $gris = imagecolorallocate($lienzo, 100, 116, 139);     // slate-500
    $bordeClaro = imagecolorallocate($lienzo, 226, 232, 240); // slate-200
    imagefill($lienzo, 0, 0, $blanco);

    // --- Marca de agua institucional (logo Aniversario #45) ---------------
    // Se dibuja primero, sobre el fondo blanco: como el logo también tiene
    // fondo blanco, imagecopymerge() se funde con el lienzo sin dejar un
    // recuadro visible. Todo lo que se dibuja después (foto, textos, QR) es
    // opaco y la tapa donde corresponde, así que nunca cae encima del QR.
    dibujarMarcaDeAgua($lienzo);

    $margen = 70;

    // --- Logo institucional -------------------------------------------
    $logo = cargarImagen(CREDENCIAL_LOGO);
    $anchoLogoDestino = 640;
    $altoLogoDestino = (int) round(imagesy($logo) * ($anchoLogoDestino / imagesx($logo)));
    $xLogo = (int) ((CREDENCIAL_ANCHO - $anchoLogoDestino) / 2);
    $yLogo = 60;
    imagesavealpha($lienzo, true);
    imagecopyresampled(
        $lienzo, $logo,
        $xLogo, $yLogo, 0, 0,
        $anchoLogoDestino, $altoLogoDestino, imagesx($logo), imagesy($logo)
    );
    imagedestroy($logo);

    $y = $yLogo + $altoLogoDestino + 30;

    // --- Título ----------------------------------------------------------
    $y = dibujarTextoCentrado($lienzo, 'Bachillerato 23', CREDENCIAL_FUENTE_NEGRITA, 34, $negro, $y);
    $y = dibujarTextoCentrado($lienzo, 'Aniversario #45', CREDENCIAL_FUENTE_NEGRITA, 34, $negro, $y + 12);
    $y = dibujarTextoCentrado($lienzo, 'Semana Académica, Cultural y Deportiva', CREDENCIAL_FUENTE_REGULAR, 26, $gris, $y + 12);

    $y += 40;
    imagefilledrectangle($lienzo, $margen, $y, CREDENCIAL_ANCHO - $margen, $y + 2, $bordeClaro);
    $y += 60;

    // --- Fotografía del alumno --------------------------------------------
    $ladoFoto = 460;
    $xFoto = (int) ((CREDENCIAL_ANCHO - $ladoFoto) / 2);
    $foto = cargarImagenRecortadaCuadrada($directorioUploads . '/' . basename($alumno['foto_path']), $ladoFoto);
    imagecopy($lienzo, $foto, $xFoto, $y, 0, 0, $ladoFoto, $ladoFoto);
    imagedestroy($foto);
    imagerectangle($lienzo, $xFoto, $y, $xFoto + $ladoFoto, $y + $ladoFoto, $bordeClaro);

    $y += $ladoFoto + 50;

    // --- Datos del alumno --------------------------------------------------
    $y = dibujarTextoCentrado($lienzo, $alumno['nombre_completo'], CREDENCIAL_FUENTE_NEGRITA, 38, $negro, $y, CREDENCIAL_ANCHO - 2 * $margen);
    $y = dibujarTextoCentrado($lienzo, $alumno['grado'] . '° ' . $alumno['grupo'], CREDENCIAL_FUENTE_REGULAR, 28, $gris, $y + 16);
    $y = dibujarTextoCentrado($lienzo, $numeroCuenta, CREDENCIAL_FUENTE_REGULAR, 26, $gris, $y + 10);

    $y += 20;

    // --- Código QR (codifica únicamente el número de cuenta) --------------
    $archivoQrTemporal = sys_get_temp_dir() . '/qr_' . $numeroCuenta . '.png';
    \PHPQRCode\QRcode::png($numeroCuenta, $archivoQrTemporal, QR_ECLEVEL_M, 10, 2);
    $qr = imagecreatefrompng($archivoQrTemporal);
    $ladoQr = 420;
    $xQr = (int) ((CREDENCIAL_ANCHO - $ladoQr) / 2);
    imagecopyresampled($lienzo, $qr, $xQr, $y, 0, 0, $ladoQr, $ladoQr, imagesx($qr), imagesy($qr));
    imagedestroy($qr);
    unlink($archivoQrTemporal);

    $y += $ladoQr + 20;

    dibujarTextoCentrado($lienzo, 'Credencial Digital', CREDENCIAL_FUENTE_REGULAR, 26, $gris, $y);
    dibujarTextoCentrado($lienzo, 'Presenta este código QR el día de los eventos', CREDENCIAL_FUENTE_REGULAR, 22, $gris, $y + 50);

    // --- Pie de página -------------------------------------------------
    dibujarTextoCentrado($lienzo, 'Universidad de Colima · Bachillerato 23', CREDENCIAL_FUENTE_REGULAR, 20, $gris, CREDENCIAL_ALTO - 70);

    // --- Guardar -------------------------------------------------------
    $rutaCredencialRelativa = 'credenciales/' . $token . '.png';
    imagepng($lienzo, $directorioCredenciales . '/' . $token . '.png');
    imagedestroy($lienzo);

    $actualizar = $pdo->prepare(
        'UPDATE alumnos SET credencial_generada = 1, credencial_path = :ruta WHERE numero_cuenta = :cuenta'
    );
    $actualizar->execute([
        'ruta' => $rutaCredencialRelativa,
        'cuenta' => $numeroCuenta,
    ]);
}

/** Dibuja el logo del Aniversario #45 centrado y semitransparente como marca de agua. */
function dibujarMarcaDeAgua(GdImage $lienzo): void
{
    $lado = CREDENCIAL_MARCA_AGUA_LADO;
    $marca = cargarImagen(CREDENCIAL_MARCA_AGUA);
    $marcaRedimensionada = imagecreatetruecolor($lado, $lado);
    imagecopyresampled($marcaRedimensionada, $marca, 0, 0, 0, 0, $lado, $lado, imagesx($marca), imagesy($marca));

    $x = (int) ((CREDENCIAL_ANCHO - $lado) / 2);
    $y = (int) ((CREDENCIAL_ALTO - $lado) / 2) - 140;
    imagecopymerge($lienzo, $marcaRedimensionada, $x, $y, 0, 0, $lado, $lado, CREDENCIAL_MARCA_AGUA_OPACIDAD);

    imagedestroy($marcaRedimensionada);
    imagedestroy($marca);
}

/** Carga un JPG o PNG según su tipo MIME real (no según la extensión). */
function cargarImagen(string $ruta): GdImage
{
    $mime = mime_content_type($ruta);
    $imagen = match ($mime) {
        'image/png' => imagecreatefrompng($ruta),
        'image/jpeg' => imagecreatefromjpeg($ruta),
        default => throw new RuntimeException('Formato de imagen no soportado: ' . $mime),
    };
    imagealphablending($imagen, true);
    imagesavealpha($imagen, true);
    return $imagen;
}

/** Carga una imagen y la recorta al centro para dejarla cuadrada, ya redimensionada a $lado. */
function cargarImagenRecortadaCuadrada(string $ruta, int $lado): GdImage
{
    $original = cargarImagen($ruta);
    $anchoOriginal = imagesx($original);
    $altoOriginal = imagesy($original);
    $ladoRecorte = min($anchoOriginal, $altoOriginal);
    $xOrigen = (int) (($anchoOriginal - $ladoRecorte) / 2);
    $yOrigen = (int) (($altoOriginal - $ladoRecorte) / 2);

    $destino = imagecreatetruecolor($lado, $lado);
    imagecopyresampled($destino, $original, 0, 0, $xOrigen, $yOrigen, $lado, $lado, $ladoRecorte, $ladoRecorte);
    imagedestroy($original);
    return $destino;
}

/**
 * Dibuja texto centrado horizontalmente, con salto de línea si excede
 * $anchoMaximo. Devuelve la coordenada Y justo debajo del texto dibujado.
 */
function dibujarTextoCentrado(GdImage $lienzo, string $texto, string $fuente, int $tamano, int $color, int $y, ?int $anchoMaximo = null): int
{
    $anchoLienzo = imagesx($lienzo);
    $lineas = $anchoMaximo === null ? [$texto] : partirTexto($texto, $fuente, $tamano, $anchoMaximo);

    foreach ($lineas as $linea) {
        $caja = imagettfbbox($tamano, 0, $fuente, $linea);
        $anchoTexto = $caja[2] - $caja[0];
        $altoLinea = $caja[1] - $caja[7];
        // imagettftext ubica el texto por su línea base; $caja[7] es la
        // distancia (negativa) del ascendente sobre esa línea base, así que
        // restarla ancla el TOPE del texto en $y en vez de la línea base.
        $x = (int) (($anchoLienzo - $anchoTexto) / 2);
        imagettftext($lienzo, $tamano, 0, $x, $y - $caja[7], $color, $fuente, $linea);
        $y += $altoLinea + 14;
    }

    return $y;
}

/** Parte un texto en varias líneas para que ninguna exceda $anchoMaximo px. */
function partirTexto(string $texto, string $fuente, int $tamano, int $anchoMaximo): array
{
    $palabras = explode(' ', $texto);
    $lineas = [];
    $lineaActual = '';

    foreach ($palabras as $palabra) {
        $candidata = $lineaActual === '' ? $palabra : $lineaActual . ' ' . $palabra;
        $caja = imagettfbbox($tamano, 0, $fuente, $candidata);
        $anchoCandidata = $caja[2] - $caja[0];

        if ($anchoCandidata > $anchoMaximo && $lineaActual !== '') {
            $lineas[] = $lineaActual;
            $lineaActual = $palabra;
        } else {
            $lineaActual = $candidata;
        }
    }

    if ($lineaActual !== '') {
        $lineas[] = $lineaActual;
    }

    return $lineas;
}
