-- Migración: etiquetas (temas) por evento — app/admin, app/inscripciones
--
-- POR QUÉ EXISTE ESTE ARCHIVO: database/schema.sql está montado en
-- /docker-entrypoint-initdb.d/ (ver docker-compose.yml), y MariaDB SOLO corre
-- ese directorio cuando el volumen db_data está vacío. En una base que ya
-- existe, editar schema.sql no cambia nada — la columna nueva no aparece y la
-- app truena con un 500 al consultarla. Este archivo lleva el mismo cambio a
-- una base ya creada.
--
-- POR QUÉ EL CAMBIO: el nombre y la descripción dicen de qué trata un evento,
-- pero hay que leerlos enteros para saberlo. Las etiquetas dan el tema de un
-- vistazo y se muestran como chips en el modal "Ver detalles" de
-- app/inscripciones. Se guardan como lista separada por comas en la misma
-- fila, no en una tabla aparte, porque solo se leen para mostrarse: no hay
-- filtro ni agrupación por etiqueta (si algún día lo hay, ESE es el momento
-- de normalizarlas).
--
-- Cómo aplicarlo (Docker, desde app/):
--     docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" \
--         < database/migraciones/2026-09-14-eventos-etiquetas.sql
-- O pegando el contenido en Adminer (http://localhost:8081).
--
-- En una instalación nueva NO hace falta: schema.sql ya trae la columna y
-- seeds.sql ya trae estos valores. Es re-ejecutable: ADD COLUMN IF NOT EXISTS,
-- y cada UPDATE solo toca la fila que todavía no tiene etiquetas, así que
-- volver a correr el archivo no pisa lo que se haya editado después desde
-- app/admin/public/evento.php.

ALTER TABLE eventos
    ADD COLUMN IF NOT EXISTS etiquetas VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Temas del evento como lista separada por comas (ej. "Inteligencia artificial, Tecnología, Divulgación") — se muestran como chips en el modal "Ver detalles" de app/inscripciones. NULL mientras el evento no tenga tema definido. Texto plano y no una tabla aparte porque solo se leen para mostrarse: no se filtra ni se agrupa por etiqueta'
        AFTER descripcion;

-- Relleno de los eventos que ya tienen tema definido. Se identifica por
-- nombre y no por id: los ids dependen del orden de inserción de seeds.sql y
-- no son estables entre instalaciones.
--
-- Los eventos que siguen en "Por definir" (Ponencia Magistral #1, Taller #4
-- del Día Académico y Taller #7 del Día Cultural) se quedan en NULL a
-- propósito — todavía no se sabe de qué van.

UPDATE eventos SET etiquetas = 'Inteligencia artificial, Tecnología, LLM, ChatGPT, Divulgación'
WHERE nombre = '¿Cómo piensa una IA? Una introducción a los LLMS' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Emprendimiento, Negocios, Resiliencia, Desarrollo personal'
WHERE nombre = 'El Camino del Emprendedor' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Impuestos, SAT, Trámites, Educación financiera, Vida profesional'
WHERE nombre = 'Inscripción al RFC, e.firma y mis obligaciones fiscales' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Proyecto de vida, Autoconocimiento, Toma de decisiones, Desarrollo personal'
WHERE nombre = 'Tu futuro, tus decisiones' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Electrónica, Ingeniería, Sensores, Inteligencia artificial, Tecnología'
WHERE nombre = 'Del sensor a la inteligencia: ¿cómo la electrónica hace que las máquinas perciban el mundo?' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Proyecto de vida, Autoconocimiento, Metas, Desarrollo personal'
WHERE nombre = 'Manual de supervivencia: mi proyecto de vida' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Educación financiera, Presupuesto, Ahorro, Inversión, Impuestos'
WHERE nombre = 'Finanzas e impuestos personales' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Música, Emociones, Expresión artística, Bienestar'
WHERE nombre = 'Cuando las palabras no bastan: música para sentir y expresar' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Fotografía, Composición visual, Creatividad, Taller práctico'
WHERE nombre = 'Mira diferente: el arte de fotografiar con tu celular' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Arte, Pintura, Historia del arte, Van Gogh, Expresión artística'
WHERE nombre = 'Van Gogh: colores, emociones y libertad' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Programación, Git, GitHub, Control de versiones, Trabajo en equipo'
WHERE nombre = 'Git + GitHub aplicado al desarrollo de software' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Topografía, Ingeniería, Mapas, Medio ambiente, Taller práctico'
WHERE nombre = 'La topografía en nuestro entorno' AND etiquetas IS NULL;

UPDATE eventos SET etiquetas = 'Biología, Biodiversidad, Fauna silvestre, Ecosistemas costeros, Colima'
WHERE nombre = 'Colecciones biológicas y fauna silvestre de ecosistemas costeros' AND etiquetas IS NULL;

-- ── Verificación ─────────────────────────────────────────────────────────
-- Los UPDATE de arriba identifican cada evento por su nombre exacto. Si en la
-- base un nombre difiere aunque sea en un acento o un espacio, ese UPDATE no
-- afecta ninguna fila y no avisa. Esta consulta lo vuelve visible: lista lo
-- que quedó sin etiquetas al terminar.
--
-- Lo esperado son SOLO los 3 eventos que siguen en "Por definir" (Ponencia
-- Magistral #1, Taller #4 del Día Académico y Taller #7 del Día Cultural).
-- Cualquier otro nombre en esta lista es un evento que no se encontró: se le
-- ponen las etiquetas a mano desde app/admin/public/evento.php.
SELECT id, dia, hora_inicio, espacio, nombre
FROM eventos
WHERE etiquetas IS NULL
ORDER BY dia, hora_inicio, id;
