-- Migración: llaves (brackets) de eliminación directa — tabla `partidos`
--
-- POR QUÉ EXISTE ESTE ARCHIVO: database/schema.sql está montado en
-- /docker-entrypoint-initdb.d/ (ver docker-compose.yml), y MariaDB SOLO corre
-- ese directorio cuando el volumen db_data está vacío. En una base que ya
-- existe, editar schema.sql no cambia nada — la tabla nueva no aparece y
-- app/admin/public/llave.php truena con un 500 al consultarla. Este archivo
-- crea la misma tabla en una base ya creada.
--
-- Cómo aplicarlo (Docker, desde app/):
--     docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" \
--         < database/migraciones/2026-09-21-partidos-llaves.sql
-- O pegando el contenido en Adminer (http://localhost:8081).
--
-- En una instalación nueva NO hace falta: schema.sql ya trae esta tabla.
-- Al ser una tabla completamente nueva, el CREATE TABLE IF NOT EXISTS ya es
-- idempotente por sí solo — no hace falta el patrón DROP CONSTRAINT IF
-- EXISTS que usan las migraciones que agregan columnas a tablas existentes.
--
-- OJO con el reseteo del padrón (app/admin/includes/resetear-alumnos.php):
-- esta tabla apunta a equipos, y ninguna FK del esquema usa ON DELETE
-- CASCADE, así que el reseteo ahora borra `partidos` ANTES que `equipos`.
-- Si se aplica esta migración sin actualizar también ese archivo, el reseteo
-- falla con error de llave foránea en cuanto exista una llave generada.

-- Llaves (brackets) de eliminación directa de cada competición: un partido
-- por enfrentamiento, armado desde app/admin/public/llave.php. Los 3 torneos
-- del Día Deportivo son de eliminación directa (ver
-- 03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md#formato-de-llaves) y
-- la llave se publica el 2 de octubre, cerradas ya las inscripciones.
--
-- Por qué una tabla y no una columna JSON en competiciones: cada partido
-- tiene vida propia (hora, cancha, marcador, ganador) y se consulta/edita
-- uno por uno conforme avanza el torneo.
--
-- CÓMO SE IDENTIFICA UN PARTIDO: (ronda, posicion) dentro de la competición
-- — ronda 1 es la primera y la ÚLTIMA ronda es la final (no hay un número
-- fijo: depende de cuántos equipos se hayan inscrito). posicion numera los
-- partidos de esa ronda de arriba hacia abajo, empezando en 1. El ganador
-- del partido (ronda R, posición P) pasa al partido (R+1, CEIL(P/2)), al
-- lado A si P es impar y al lado B si es par — esa aritmética es lo único
-- que hace falta para avanzar la llave (ver llavesPropagar en
-- app/admin/includes/llaves.php).
--
-- EQUIPOS NULL a propósito: al generar la llave solo la primera ronda trae
-- equipos; las rondas siguientes nacen vacías y se van llenando con los
-- ganadores. Además, como el número real de equipos casi nunca es potencia
-- de 2 (el tope de 16 por torneo es un máximo, no una meta), los partidos
-- sobrantes de la primera ronda quedan con un solo equipo: es un "bye"
-- (pase directo), y se graba con id_equipo_ganador ya puesto desde la
-- generación.
CREATE TABLE IF NOT EXISTS partidos (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
        COMMENT 'Identificador interno del partido',
    id_competicion    INT UNSIGNED NOT NULL
        COMMENT 'FK a competiciones — de qué torneo/concurso es esta llave',
    ronda             TINYINT UNSIGNED NOT NULL
        COMMENT '1 = primera ronda; la ronda más alta de la competición es la final (ver nota arriba)',
    posicion          SMALLINT UNSIGNED NOT NULL
        COMMENT 'Posición del partido dentro de su ronda, de arriba hacia abajo, empezando en 1',
    id_equipo_a       INT UNSIGNED NULL
        COMMENT 'Equipo del lado A — NULL si todavía no se sabe (ronda por jugarse) o si es el hueco de un bye',
    id_equipo_b       INT UNSIGNED NULL
        COMMENT 'Equipo del lado B — mismas reglas que id_equipo_a',
    id_equipo_ganador INT UNSIGNED NULL
        COMMENT 'Quién avanza. NULL = partido sin jugar. En un bye se graba desde que se genera la llave',
    marcador_a        SMALLINT UNSIGNED NULL
        COMMENT 'Marcador del equipo A — opcional, el que avanza lo define id_equipo_ganador y no el marcador (hay desempates por penales/sets que no se reflejan en el tanteador)',
    marcador_b        SMALLINT UNSIGNED NULL
        COMMENT 'Marcador del equipo B — ver nota de marcador_a',
    hora_programada   TIME NULL
        COMMENT 'Hora aproximada del partido dentro de la ventana de la competición (ej. 07:30-11:30 en el Día Deportivo) — NULL mientras no se programe',
    cancha            VARCHAR(100) NULL
        COMMENT 'Dónde se juega (ej. "Cancha 1") — texto libre porque las canchas del Polideportivo no están catalogadas en el sistema',
    fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Cuándo se generó la llave a la que pertenece este partido',
    UNIQUE KEY uq_partidos_slot (id_competicion, ronda, posicion)
        COMMENT 'Una competición no puede tener dos partidos en la misma casilla de la llave',
    CONSTRAINT fk_partidos_competicion FOREIGN KEY (id_competicion) REFERENCES competiciones(id),
    CONSTRAINT fk_partidos_equipo_a FOREIGN KEY (id_equipo_a) REFERENCES equipos(id),
    CONSTRAINT fk_partidos_equipo_b FOREIGN KEY (id_equipo_b) REFERENCES equipos(id),
    CONSTRAINT fk_partidos_equipo_ganador FOREIGN KEY (id_equipo_ganador) REFERENCES equipos(id),
    CONSTRAINT chk_partidos_ronda CHECK ( ronda >= 1 AND posicion >= 1 ),
    CONSTRAINT chk_partidos_equipos_distintos CHECK ( id_equipo_a IS NULL OR id_equipo_b IS NULL OR id_equipo_a <> id_equipo_b ),
    -- El ganador solo puede ser uno de los dos equipos del propio partido:
    -- sin esto, un POST manipulado podría meter a cualquier equipo de la
    -- competición en la siguiente ronda. La app valida antes para dar un
    -- mensaje decente; esto es la red de seguridad, mismo criterio que
    -- trg_equipos_limite_maximo.
    CONSTRAINT chk_partidos_ganador CHECK (
        id_equipo_ganador IS NULL
        OR id_equipo_ganador = id_equipo_a
        OR id_equipo_ganador = id_equipo_b
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Partidos de la llave de eliminación directa de cada competición — se generan de golpe desde app/admin y se van resolviendo ronda por ronda.';
