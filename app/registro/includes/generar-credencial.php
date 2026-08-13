<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/pendalff/phpqrcode/qrlib.php';

const CREDENCIAL_ANCHO = 1080;
const CREDENCIAL_ALTO = 1920;
const CREDENCIAL_FUENTE_REGULAR = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
const CREDENCIAL_FUENTE_NEGRITA = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
const CREDENCIAL_LOGO = __DIR__ . '/../../assets/img/logo/UdeC_2L izq Negro.png';

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
    $y = dibujarTextoCentrado($lienzo, 'Aniversario #45 · Semana Cultural', CREDENCIAL_FUENTE_NEGRITA, 34, $negro, $y + 12);
    $y = dibujarTextoCentrado($lienzo, 'Credencial Digital', CREDENCIAL_FUENTE_REGULAR, 26, $gris, $y + 12);

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

    $y += 40;

    // --- Código QR (codifica únicamente el número de cuenta) --------------
    $archivoQrTemporal = sys_get_temp_dir() . '/qr_' . $numeroCuenta . '.png';
    \PHPQRCode\QRcode::png($numeroCuenta, $archivoQrTemporal, QR_ECLEVEL_M, 10, 2);
    $qr = imagecreatefrompng($archivoQrTemporal);
    $ladoQr = 420;
    $xQr = (int) ((CREDENCIAL_ANCHO - $ladoQr) / 2);
    imagecopyresampled($lienzo, $qr, $xQr, $y, 0, 0, $ladoQr, $ladoQr, imagesx($qr), imagesy($qr));
    imagedestroy($qr);
    unlink($archivoQrTemporal);

    $y += $ladoQr + 30;
    dibujarTextoCentrado($lienzo, 'Presenta este código QR el día de los eventos', CREDENCIAL_FUENTE_REGULAR, 22, $gris, $y);

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
