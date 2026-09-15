-- Migración: requerimientos (qué llevar) por evento — app/admin, app/inscripciones
--
-- POR QUÉ EXISTE ESTE ARCHIVO: database/schema.sql está montado en
-- /docker-entrypoint-initdb.d/ (ver docker-compose.yml), y MariaDB SOLO corre
-- ese directorio cuando el volumen db_data está vacío. En una base que ya
-- existe, editar schema.sql no cambia nada — la columna nueva no aparece y la
-- app truena con un 500 al consultarla. Este archivo lleva el mismo cambio a
-- una base ya creada.
--
-- POR QUÉ EL CAMBIO: varios talleres piden que el alumno lleve algo (su
-- celular, una laptop, material de dibujo). Hoy eso no se puede decir en
-- ningún lado, así que el alumno se entera al llegar. La columna lo guarda y
-- el modal "Ver detalles" de app/inscripciones lo muestra ANTES de que se
-- inscriba.
--
-- FORMATO: un requerimiento por línea (separador: salto de línea), NO comas.
-- A diferencia de `etiquetas`, un requerimiento es prosa y lleva comas
-- propias ("Laptop, de preferencia con Windows"), así que la coma no sirve
-- como separador.
--
-- Cómo aplicarlo (Docker, desde app/):
--     docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" \
--         < database/migraciones/2026-09-14-eventos-requerimientos.sql
-- O pegando el contenido en Adminer (http://localhost:8081).
--
-- En una instalación nueva NO hace falta: schema.sql ya trae la columna.
-- Es re-ejecutable (ADD COLUMN IF NOT EXISTS, y el UPDATE solo toca la fila
-- que sigue en NULL).

ALTER TABLE eventos
    ADD COLUMN IF NOT EXISTS requerimientos TEXT NULL DEFAULT NULL
        COMMENT 'Qué tiene que llevar el alumno a este evento, según lo que pida el ponente/tallerista: un requerimiento por línea (separador: salto de línea, NO comas — un requerimiento es prosa y lleva comas propias). Se muestra como lista en el modal "Ver detalles" de app/inscripciones. NULL = el evento no pide nada, o el ponente todavía no lo indica'
        AFTER etiquetas;

-- Todos los eventos quedan en NULL salvo uno, A PROPÓSITO: qué hay que llevar
-- lo decide cada ponente/tallerista y no se puede deducir de la descripción.
-- Inventarlo aquí pondría instrucciones falsas frente al alumnado. Se capturan
-- desde app/admin/public/evento.php conforme cada quien las confirme.
--
-- La excepción es el taller de fotografía, cuya propia descripción ya dice que
-- se trabaja "utilizando únicamente su teléfono celular".
UPDATE eventos SET requerimientos = 'Teléfono celular con cámara'
WHERE nombre = 'Mira diferente: el arte de fotografiar con tu celular' AND requerimientos IS NULL;

-- Confirmado por el tallerista: los alumnos deben llegar con su cuenta de
-- GitHub ya creada. El aula de cómputo pone los equipos, así que no se les
-- pide laptop.
UPDATE eventos SET requerimientos = 'Cuenta de GitHub creada antes del taller (registro gratuito en github.com)'
WHERE nombre = 'Git + GitHub aplicado al desarrollo de software' AND requerimientos IS NULL;

-- Confirmado por el tallerista: la sesión es en la Explanada y se trabaja
-- sentado o acostado en el piso, así que cada quien lleva con qué.
UPDATE eventos SET requerimientos = 'Tapete o sábana para sentarse o acostarse en el piso'
WHERE nombre = 'Tu futuro, tus decisiones' AND requerimientos IS NULL;

-- Qué eventos siguen sin requerimientos capturados. No es un error: la mayoría
-- no pide nada. Sirve para ir palomeando conforme los ponentes respondan.
SELECT id, dia, hora_inicio, facilitador, nombre
FROM eventos
WHERE requerimientos IS NULL
ORDER BY dia, hora_inicio, id;
