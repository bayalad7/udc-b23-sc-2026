-- Migración: eventos.descripcion pasa de VARCHAR(150) a TEXT
--
-- POR QUÉ EXISTE ESTE ARCHIVO: database/schema.sql está montado en
-- /docker-entrypoint-initdb.d/ (ver docker-compose.yml), y MariaDB SOLO corre
-- ese directorio cuando el volumen db_data está vacío. En una base que ya
-- existe, editar schema.sql no cambia nada — la columna sigue siendo
-- VARCHAR(150) y cargar una descripción más larga truena (o se trunca, según
-- el sql_mode). Este archivo lleva el mismo cambio a una base ya creada.
--
-- POR QUÉ EL CAMBIO: la descripción dejó de ser una línea junto al nombre. Las
-- descripciones reales de las ponencias/talleres son párrafos completos (la
-- de "¿Cómo piensa una IA?" en database/seeds.sql mide 542 caracteres), y
-- app/inscripciones ya las muestra recortadas a dos líneas en la tarjeta con
-- el texto íntegro en el modal "Ver detalles". El tope de 150 del formulario
-- de app/admin/public/evento.php sube en el mismo cambio (ahora <textarea>).
--
-- Cómo aplicarlo (Docker, desde app/):
--     docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" \
--         < database/migraciones/2026-09-10-eventos-descripcion-larga.sql
-- O pegando el contenido en Adminer (http://localhost:8081).
--
-- En una instalación nueva NO hace falta: schema.sql ya trae la columna como
-- TEXT. Es re-ejecutable: MODIFY COLUMN sobre una columna que ya es TEXT no
-- cambia nada, y no hay pérdida de datos al ensanchar el tipo.

ALTER TABLE eventos
    MODIFY COLUMN IF EXISTS descripcion TEXT NOT NULL
        COMMENT 'Descripción completa del evento — la tarjeta de app/inscripciones la recorta a dos líneas y el texto íntegro se lee en el modal "Ver detalles". TEXT y no VARCHAR(150) porque las descripciones reales son párrafos, no una línea';
