<?php
declare(strict_types=1);

// Zona horaria: SE GUARDA en la del servidor y SE MUESTRA en la del plantel.
//
// En el VPS, PHP y MariaDB corren en la zona del servidor (UTC), y con ella se
// guardaron los escaneos del evento: quien entró a las 07:30 quedó a las 13:30.
// En vez de mover lo guardado, la app convierte al mostrar, así que lo que ya
// está en la base se lee bien sin migrar nada. Por eso hay dos zonas:
//
// - ZONA_HORARIA_SERVIDOR: en la que se GUARDA. Es la que PHP trae de fábrica
//   en ese servidor (date.timezone del php.ini; UTC si no se define), leída
//   ANTES de cambiarla. Todo lo que se escribe a la base con la hora actual va
//   con ahoraServidor(), y los DEFAULT CURRENT_TIMESTAMP los pone MariaDB con
//   su propio reloj, que en el mismo servidor es la misma zona.
// - ZONA_HORARIA_APP: la de Manzanillo, Colima (hora del centro, UTC-6 todo el
//   año desde que México quitó el horario de verano en 2022). Es la zona por
//   defecto de PHP para todo lo demás —date(), "Generado el...", "hoy"—, y a
//   ella se convierte todo lo leído de la base con horaLocal().
//
// En Docker, docker/php.ini ya pone America/Mexico_City, así que las dos zonas
// coinciden y la conversión no mueve nada.
//
// OJO: si un día se cambia el date.timezone del php.ini del servidor, las
// horas YA guardadas se empezarían a leer con la zona nueva y se recorrerían.
//
// Se carga desde config/rutas.php, que incluyen —directo o vía config/db.php—
// todas las páginas de la app.

const ZONA_HORARIA_APP = 'America/Mexico_City';

if (!defined('ZONA_HORARIA_SERVIDOR')) {
    define('ZONA_HORARIA_SERVIDOR', date_default_timezone_get());
}

date_default_timezone_set(ZONA_HORARIA_APP);

if (!function_exists('ahoraServidor')) {
    /** Hora actual en la zona en que se guarda en la base ("2026-10-05 13:30:00"). */
    function ahoraServidor(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(ZONA_HORARIA_SERVIDOR)))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('horaLocal')) {
    /**
     * Pasa una fecha/hora leída de la base (DATETIME, guardada en la zona del
     * servidor) a la del plantel, con el formato que se pida. NULL o vacío se
     * devuelve como NULL, para que cada pantalla siga pintando su guion.
     */
    function horaLocal(?string $guardada, string $formato = 'Y-m-d H:i:s'): ?string
    {
        if ($guardada === null || $guardada === '') {
            return null;
        }

        return (new DateTimeImmutable($guardada, new DateTimeZone(ZONA_HORARIA_SERVIDOR)))
            ->setTimezone(new DateTimeZone(ZONA_HORARIA_APP))
            ->format($formato);
    }
}
