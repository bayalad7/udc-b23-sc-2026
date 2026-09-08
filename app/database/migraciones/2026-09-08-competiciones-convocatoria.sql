-- Migración: convocatoria (imagen) por competición (app/admin, app/inscripciones)
--
-- POR QUÉ EXISTE ESTE ARCHIVO: database/schema.sql está montado en
-- /docker-entrypoint-initdb.d/ (ver docker-compose.yml), y MariaDB SOLO corre
-- ese directorio cuando el volumen db_data está vacío. En una base que ya
-- existe, editar schema.sql no cambia nada — la columna nueva no aparece y la
-- app truena con un 500 al consultarla. Este archivo lleva el mismo cambio a
-- una base ya creada.
--
-- Cómo aplicarlo (Docker, desde app/):
--     docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" \
--         < database/migraciones/2026-09-08-competiciones-convocatoria.sql
-- O pegando el contenido en Adminer (http://localhost:8081).
--
-- En una instalación nueva NO hace falta: schema.sql ya trae esta columna.
-- Es idempotente (ADD COLUMN IF NOT EXISTS; el UPDATE solo toca filas que
-- todavía no tienen convocatoria, así que reejecutar el archivo no pisa un
-- cambio hecho después desde app/admin).

ALTER TABLE competiciones
    ADD COLUMN IF NOT EXISTS convocatoria VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Ruta relativa a app/assets/img/ de la imagen de convocatoria (ej. "convocatorias/día-académico.png"), subida desde app/admin/public/competicion.php y mostrada al alumnado en app/inscripciones; NULL si esta competición todavía no tiene convocatoria publicada';

-- Convocatoria del Día Académico (Concurso del Conocimiento): la imagen ya
-- vive en app/assets/img/convocatorias/día-académico.png — se referencia
-- aquí en vez de obligar a resubirla desde el formulario. El resto de
-- competiciones se queda en NULL hasta que alguien suba la suya desde
-- app/admin/public/competicion.php.
UPDATE competiciones
SET convocatoria = 'convocatorias/día-académico.png'
WHERE dia = 'academico' AND convocatoria IS NULL;
