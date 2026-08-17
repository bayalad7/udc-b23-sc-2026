-- Datos semilla — Semana Acádemica, Cultural y Deportiva B23
-- Ver app/database/schema.sql para la definición de las tablas.
--
-- Placeholders explícitos:
--   - facilitador/responsable/descripcion de los eventos académicos y del
--     taller cultural de fotografía: 'Por definir' — el catálogo real de
--     ponencias/talleres depende de los temas de interés recogidos en el
--     registro (ver 01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md).
--   - Espacio de los 3 talleres culturales (Aula 1/2/3): asumido por analogía
--     con el Día Académico — la Explanada queda libre para el show de las
--     16:00. Confirmar antes de dar por definitivo el itinerario del Día
--     Cultural.
--   - fecha_limite de las 5 competiciones: 2026-09-30 23:59:59 (mismo
--     criterio "día previo al cierre" ya usado en el registro del Día
--     Académico y en la inscripción de equipos del Día Deportivo).
--
-- hora_inicio/hora_fin: base para que la app detecte cruces de horario (ver
-- CHECK chk_eventos_horario / chk_competiciones_horario en schema.sql). Para
-- el Día Deportivo los 3 torneos comparten la misma ventana (07:30-11:30) a
-- propósito: por regla de negocio SÍ se permite inscribirse a más de uno
-- aunque se traslapen (ver torneos-deportivos.md) — la app NO debe rechazar
-- esa combinación solo por el traslape de horario.

-- ── eventos ──────────────────────────────────────────────────────────────
-- Día Académico, 09:00–10:00 — el alumno elige UNA sola de estas 4 ponencias
-- (ver regla de exclusividad en registro-asistencia.md).
INSERT INTO eventos (dia, tipo, hora_inicio, hora_fin, facilitador, nombre, descripcion, espacio, cupo_maximo, cupo_disponible, responsable) VALUES
('academico', 'ponencia', '09:00:00', '10:00:00', 'Por definir facilitador', 'Ponencia Magistral #1', 'Por definir descripcion', 'Auditorio Principal', 180, 180, 'Por definir responsable'),
('academico', 'ponencia', '09:00:00', '10:00:00', 'Por definir facilitador', 'Ponencia #2',           'Por definir descripcion', 'Aula 1',              40,  40,  'Por definir responsable'),
('academico', 'ponencia', '09:00:00', '10:00:00', 'Por definir facilitador', 'Ponencia #3',           'Por definir descripcion', 'Aula 2',              40,  40,  'Por definir responsable'),
('academico', 'ponencia', '09:00:00', '10:00:00', 'Por definir facilitador', 'Ponencia #4',           'Por definir descripcion', 'Aula 3',              40,  40,  'Por definir responsable'),
('academico', 'taller',   '09:00:00', '10:00:00', 'Por definir facilitador', 'Taller videncial #5',   'Por definir descripcion', 'Explanada',           40,  40,  'Por definir responsable');

-- Día Académico, 10:30–12:30 — el alumno elige UNA sola de estas 4 opciones,
-- o el Concurso del Conocimiento (ver competiciones más abajo), nunca ambos:
-- mismo horario exacto (10:30-12:30) que la competición, así que un cruce de
-- horario contra ella basta para bloquear la doble inscripción.
INSERT INTO eventos (dia, tipo, hora_inicio, hora_fin, facilitador, nombre, descripcion, espacio, cupo_maximo, cupo_disponible, responsable) VALUES
('academico', 'taller', '10:30:00', '12:30:00', 'Por definir facilitador', 'Taller #1', 'Por definir descripcion', 'Aula 1',    40, 40, 'Por definir'),
('academico', 'taller', '10:30:00', '12:30:00', 'Por definir facilitador', 'Taller #2', 'Por definir descripcion', 'Aula 2',    40, 40, 'Por definir'),
('academico', 'taller', '10:30:00', '12:30:00', 'Por definir facilitador', 'Taller #3', 'Por definir descripcion', 'Aula 3',    40, 40, 'Por definir'),
('academico', 'taller', '10:30:00', '12:30:00', 'Por definir facilitador', 'Taller #4', 'Por definir descripcion', 'Explanada', 40, 40, 'Por definir');

-- Día Cultural, 14:00–16:00 — 3 talleres en paralelo (sin regla de
-- exclusividad indicada todavía — ver pendiente en 02-Dia-Cultural). No se
-- traslapan con el Escenario de Talentos (16:00-17:20, ver competiciones).
INSERT INTO eventos (dia, tipo, hora_inicio, hora_fin, facilitador, nombre, descripcion, espacio, cupo_maximo, cupo_disponible, responsable) VALUES
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir facilitador', 'Taller #1', 'Por definir descripcion', 'Aula X', 50, 50, 'Por definir responsable'),
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir facilitador', 'Taller #2', 'Por definir descripcion', 'Aula X', 50, 50, 'Por definir responsable'),
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir facilitador', 'Taller #3', 'Por definir descripcion', 'Aula X', 50, 50, 'Por definir responsable'),
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir facilitador', 'Taller #4', 'Por definir descripcion', 'Aula X', 50, 50, 'Por definir responsable'),
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir facilitador', 'Taller #5', 'Por definir descripcion', 'Aula X', 50, 50, 'Por definir responsable'),
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir facilitador', 'Taller #6', 'Por definir descripcion', 'Aula X', 50, 50, 'Por definir responsable'),
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir facilitador', 'Taller #7', 'Por definir descripcion', 'Aula X', 50, 50, 'Por definir responsable');

-- ── competiciones ────────────────────────────────────────────────────────
-- Concurso del Conocimiento (Académico), Escenario de Talentos (Cultural) y
-- los 3 torneos deportivos — cupo/capacidad de equipos vive en equipos, no
-- aquí (ver schema.sql).
-- max_equipos/tam_equipo (configurables desde app/admin — ver schema.sql):
-- Concurso del Conocimiento (12 equipos de 10) y los 3 torneos deportivos
-- (16 equipos de 10, resuelto — ver torneos-deportivos.md) los traen; el
-- Escenario de Talentos queda en NULL = sin esa regla (tamaño de acto libre,
-- sin tope de actos).
INSERT INTO competiciones (dia, tipo, hora_inicio, hora_fin, nombre, fecha_limite, max_equipos, tam_equipo) VALUES
('academico', 'concurso', '10:30:00', '12:30:00', 'Concurso del Conocimiento',                  '2026-09-30 23:59:59', 12,   10),
('cultural',  'concurso', '16:00:00', '17:20:00', 'Escenario de Talentos "Expresa tu esencia"', '2026-09-30 23:59:59', NULL, NULL),
('deportivo', 'torneo',   '07:30:00', '11:30:00', 'Torneo de Fútbol Rápido',                    '2026-09-30 23:59:59', 16,   10),
('deportivo', 'torneo',   '07:30:00', '11:30:00', 'Torneo de Voleibol',                         '2026-09-30 23:59:59', 16,   10),
('deportivo', 'torneo',   '07:30:00', '11:30:00', 'Torneo de Quemados',                         '2026-09-30 23:59:59', 16,   10);

-- ──────────────────────────────────────────────────────────────────────── ──
-- ── Carga masiva de datos de prueba (350 alumnos + inscripciones/equipos) ──
-- ──────────────────────────────────────────────────────────────────────── ──

-- Todo lo de aquí en adelante es DATA DE PRUEBA/DESARROLLO, no información
-- real de alumnos — sirve para poder ver la app llena (cupos, listas de
-- inscritos, llaves de equipos) sin depender de que 350 personas se
-- registren a mano. numero_cuenta usa el prefijo 'B23' + folio (formato que
-- NO se cruza con números de cuenta reales de la universidad) y foto_path
-- apunta a un archivo placeholder que no existe físicamente en
-- uploads/ (no se sube ninguna imagen real) — si se abre una credencial de
-- estos alumnos desde app/registro, la foto saldrá rota; es esperado.
--
-- Para respetar la exclusividad por franja horaria (ver "Reglas de
-- inscripción por franja horaria" en registro-asistencia.md) sin repetir esa
-- lógica a mano decenas de veces, se usan 3 procedimientos almacenados
-- temporales que se BORRAN al final de este bloque — no quedan viviendo en
-- el esquema:
--   - sembrar_inscripcion_evento: inscribe N alumnos aleatorios a un evento
--     (ponencia/taller), excluyendo a quien ya tenga algo en ese mismo
--     dia+horario (otro evento o una competición, ej. Concurso del
--     Conocimiento).
--   - sembrar_equipo_individual: arma un equipo/acto 100% de alumnos
--     (Concurso del Conocimiento, Escenario de Talentos). p_exclusivo controla
--     si un alumno puede repetirse en más de un equipo de esa misma
--     competición (NO para el Concurso, SÍ para el Escenario de Talentos —
--     ver concurso-talentos.md) y p_excluir_franja controla si además se
--     excluye a quien ya tenga algo en ese mismo horario (SÍ para el
--     Concurso, que comparte horario con los talleres 10:30–12:30; NO para
--     el Escenario de Talentos, que no tiene esa restricción).
--   - sembrar_equipo_deportivo: arma un equipo de 10 para un torneo
--     deportivo, mezclando alumnos y padres/madres (ver comentario de la
--     tabla integrantes en schema.sql) — 5 alumnos distintos como "familias
--     ancla" (uno de ellos capitán) + 5 filas más de padre/madre repartidas
--     entre esas mismas 5 familias, para que la mezcla sea real sin
--     necesitar 10 alumnos distintos por equipo.
-- Los nombres de padres/madres se generan con la función auxiliar
-- nombre_familiar_aleatorio (también temporal, se borra al final).
--
-- SET NAMES fuerza el collation de esta sesión a utf8mb4_unicode_ci —
-- mismo collation explícito de las tablas en schema.sql. Es un respaldo por
-- si este bloque se corre contra una base cuyo collation por defecto no
-- quedó alineado por el ALTER DATABASE de schema.sql (ver ese archivo):
-- sin esto, los procedimientos de más abajo revientan con "Illegal mix of
-- collations" al comparar columnas ENUM/VARCHAR contra literales de texto.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ── 350 alumnos sintéticos ──────────────────────────────────────────────
INSERT INTO alumnos (numero_cuenta, nombre_completo, grado, grupo, correo_institucional, foto_path, camisa_corte, camisa_talla, token_descarga)
WITH RECURSIVE secuencia AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM secuencia WHERE n < 350
)
SELECT
    CONCAT('B23', LPAD(n, 5, '0')),
    CONCAT(
        ELT(1 + (n MOD 14), 'María','José','Juan','Ana','Luis','Fernanda','Diego','Camila','Miguel','Valeria','Andrés','Paola','Ricardo','Daniela'),
        ' ',
        ELT(1 + (n MOD 12), 'García','Hernández','López','Martínez','Rodríguez','Pérez','González','Sánchez','Ramírez','Torres','Flores','Vargas'),
        ' ',
        ELT(1 + ((n * 5 + 3) MOD 12), 'García','Hernández','López','Martínez','Rodríguez','Pérez','González','Sánchez','Ramírez','Torres','Flores','Vargas')
    ),
    ELT(1 + (n MOD 3), '1', '3', '5'),
    ELT(1 + (n MOD 3), 'A', 'B', 'C'),
    CONCAT('alumno', LPAD(n, 5, '0'), '@ucol.mx'),
    'uploads/semilla-generica.jpg',
    'Unisex',
    ELT(1 + (n MOD 6), 'XS', 'S', 'M', 'L', 'XL', '2XL'),
    MD5(CONCAT('semilla-b23-alumno-', n))
FROM secuencia;

-- ── Procedimientos/función temporales de siembra ──────────────────────────
DELIMITER $$

DROP FUNCTION IF EXISTS nombre_familiar_aleatorio$$
CREATE FUNCTION nombre_familiar_aleatorio(p_tipo VARCHAR(10)) RETURNS VARCHAR(150)
BEGIN
    DECLARE v_nombre VARCHAR(80);
    IF p_tipo = 'padre' THEN
        SET v_nombre = ELT(1 + FLOOR(RAND() * 10), 'Roberto','Francisco','Alejandro','Jorge','Manuel','Eduardo','Sergio','Rafael','Arturo','Alberto');
    ELSE
        SET v_nombre = ELT(1 + FLOOR(RAND() * 10), 'Leticia','Guadalupe','Patricia','Rosa','Alejandra','Beatriz','Cecilia','Martha','Silvia','Verónica');
    END IF;
    RETURN CONCAT(v_nombre, ' ', ELT(1 + FLOOR(RAND() * 12), 'García','Hernández','López','Martínez','Rodríguez','Pérez','González','Sánchez','Ramírez','Torres','Flores','Vargas'));
END$$

DROP PROCEDURE IF EXISTS sembrar_inscripcion_evento$$
CREATE PROCEDURE sembrar_inscripcion_evento(IN p_id_evento INT UNSIGNED, IN p_cantidad INT UNSIGNED)
BEGIN
    DECLARE v_dia VARCHAR(20);
    DECLARE v_inicio TIME;
    DECLARE v_fin TIME;

    SELECT dia, hora_inicio, hora_fin INTO v_dia, v_inicio, v_fin
    FROM eventos WHERE id = p_id_evento;

    INSERT INTO inscripciones (id_evento, id_alumno, origen, registrado_por)
    SELECT p_id_evento, a.id, 'previo', 'Semilla de datos'
    FROM alumnos a
    WHERE a.id NOT IN (
        SELECT i.id_alumno FROM inscripciones i
        JOIN eventos e ON e.id = i.id_evento
        WHERE e.dia = v_dia AND e.hora_inicio = v_inicio AND e.hora_fin = v_fin
    )
    AND a.id NOT IN (
        SELECT it.id_alumno FROM integrantes it
        JOIN equipos eq ON eq.id = it.id_equipo
        JOIN competiciones c ON c.id = eq.id_competicion
        WHERE it.tipo = 'alumno' AND c.dia = v_dia AND c.hora_inicio = v_inicio AND c.hora_fin = v_fin
    )
    ORDER BY RAND()
    LIMIT p_cantidad;

    UPDATE eventos
    SET cupo_disponible = cupo_maximo - (SELECT COUNT(*) FROM inscripciones WHERE id_evento = p_id_evento)
    WHERE id = p_id_evento;
END$$

DROP PROCEDURE IF EXISTS sembrar_equipo_individual$$
CREATE PROCEDURE sembrar_equipo_individual(
    IN p_id_competicion INT UNSIGNED,
    IN p_nombre_equipo VARCHAR(150),
    IN p_color VARCHAR(50),
    IN p_num_alumnos INT UNSIGNED,
    IN p_exclusivo_competicion TINYINT(1),
    IN p_excluir_franja TINYINT(1)
)
BEGIN
    DECLARE v_id_equipo INT UNSIGNED;
    DECLARE v_id_capitan INT UNSIGNED;
    DECLARE v_dia VARCHAR(20);
    DECLARE v_inicio TIME;
    DECLARE v_fin TIME;

    SELECT dia, hora_inicio, hora_fin INTO v_dia, v_inicio, v_fin
    FROM competiciones WHERE id = p_id_competicion;

    DROP TEMPORARY TABLE IF EXISTS tmp_candidatos;
    CREATE TEMPORARY TABLE tmp_candidatos (
        orden INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_alumno INT UNSIGNED NOT NULL
    );

    INSERT INTO tmp_candidatos (id_alumno)
    SELECT a.id FROM alumnos a
    WHERE (p_exclusivo_competicion = 0 OR a.id NOT IN (
              SELECT it.id_alumno FROM integrantes it
              JOIN equipos eq ON eq.id = it.id_equipo
              WHERE eq.id_competicion = p_id_competicion
          ))
      AND (p_excluir_franja = 0 OR (
              a.id NOT IN (
                  SELECT i.id_alumno FROM inscripciones i
                  JOIN eventos e ON e.id = i.id_evento
                  WHERE e.dia = v_dia AND e.hora_inicio = v_inicio AND e.hora_fin = v_fin
              )
              AND a.id NOT IN (
                  SELECT it2.id_alumno FROM integrantes it2
                  JOIN equipos eq2 ON eq2.id = it2.id_equipo
                  JOIN competiciones c2 ON c2.id = eq2.id_competicion
                  WHERE it2.tipo = 'alumno' AND c2.dia = v_dia AND c2.hora_inicio = v_inicio AND c2.hora_fin = v_fin
              )
          ))
    ORDER BY RAND()
    LIMIT p_num_alumnos;

    SELECT id_alumno INTO v_id_capitan FROM tmp_candidatos WHERE orden = 1;

    INSERT INTO equipos (id_competicion, nombre, id_alumno_capitan, color_camisa)
    VALUES (p_id_competicion, p_nombre_equipo, v_id_capitan, p_color);
    SET v_id_equipo = LAST_INSERT_ID();

    INSERT INTO integrantes (id_equipo, id_alumno, tipo, nombre, codigo_participante)
    SELECT v_id_equipo, tc.id_alumno, 'alumno', a.nombre_completo, CONCAT('EQ', v_id_equipo, '-A', tc.orden)
    FROM tmp_candidatos tc JOIN alumnos a ON a.id = tc.id_alumno;

    DROP TEMPORARY TABLE tmp_candidatos;
END$$

DROP PROCEDURE IF EXISTS sembrar_equipo_deportivo$$
CREATE PROCEDURE sembrar_equipo_deportivo(
    IN p_id_competicion INT UNSIGNED,
    IN p_nombre_equipo VARCHAR(150),
    IN p_color VARCHAR(50)
)
BEGIN
    DECLARE v_id_equipo INT UNSIGNED;
    DECLARE v_id_capitan INT UNSIGNED;

    DROP TEMPORARY TABLE IF EXISTS tmp_familias;
    CREATE TEMPORARY TABLE tmp_familias (
        orden INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_alumno INT UNSIGNED NOT NULL,
        nombre_alumno VARCHAR(150) NOT NULL
    );

    -- 5 "familias ancla" distintas (alumnos que no estén ya en otro equipo
    -- de ESTE mismo torneo) — cada una aporta 1 fila alumno + 1 fila
    -- padre/madre (ver abajo), para sumar 10 integrantes sin necesitar 10
    -- alumnos distintos por equipo.
    INSERT INTO tmp_familias (id_alumno, nombre_alumno)
    SELECT a.id, a.nombre_completo
    FROM alumnos a
    WHERE a.id NOT IN (
        SELECT it.id_alumno FROM integrantes it
        JOIN equipos eq ON eq.id = it.id_equipo
        WHERE eq.id_competicion = p_id_competicion
    )
    ORDER BY RAND() LIMIT 5;

    SELECT id_alumno INTO v_id_capitan FROM tmp_familias WHERE orden = 1;

    INSERT INTO equipos (id_competicion, nombre, id_alumno_capitan, color_camisa)
    VALUES (p_id_competicion, p_nombre_equipo, v_id_capitan, p_color);
    SET v_id_equipo = LAST_INSERT_ID();

    INSERT INTO integrantes (id_equipo, id_alumno, tipo, nombre, codigo_participante)
    SELECT v_id_equipo, id_alumno, 'alumno', nombre_alumno, CONCAT('EQ', v_id_equipo, '-A', orden)
    FROM tmp_familias;

    INSERT INTO integrantes (id_equipo, id_alumno, tipo, nombre, codigo_participante)
    SELECT v_id_equipo, id_alumno,
           IF(orden % 2 = 1, 'padre', 'madre'),
           nombre_familiar_aleatorio(IF(orden % 2 = 1, 'padre', 'madre')),
           CONCAT('EQ', v_id_equipo, '-P', orden)
    FROM tmp_familias;

    DROP TEMPORARY TABLE tmp_familias;
END$$

DELIMITER ;

-- ── Día Académico, 09:00–10:00 — 4 ponencias + 1 taller (cupos aleatorios) ─
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'academico' AND nombre = 'Ponencia Magistral #1'), 150);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'academico' AND nombre = 'Ponencia #2'), 28);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'academico' AND nombre = 'Ponencia #3'), 33);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'academico' AND nombre = 'Ponencia #4'), 19);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'academico' AND nombre = 'Taller videncial #5'), 37);

-- ── Día Académico, 10:30–12:30 — 4 talleres (cupos aleatorios) + Concurso
-- del Conocimiento (10 equipos de 10 alumnos, comparte horario con los
-- talleres así que también excluye por franja) ─────────────────────────────
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'academico' AND nombre = 'Taller #1'), 25);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'academico' AND nombre = 'Taller #2'), 30);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'academico' AND nombre = 'Taller #3'), 18);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'academico' AND nombre = 'Taller #4'), 22);

CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Los Newtonianos', NULL, 10, 1, 1);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Mentes Brillantes', NULL, 10, 1, 1);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Los Einstein', NULL, 10, 1, 1);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Cerebritos B23', NULL, 10, 1, 1);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Los Sabios', NULL, 10, 1, 1);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Neuronas Activas', NULL, 10, 1, 1);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Los Curiosos', NULL, 10, 1, 1);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Génesis del Saber', NULL, 10, 1, 1);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Los Ilustrados', NULL, 10, 1, 1);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'academico' AND tipo = 'concurso'), 'Enigma B23', NULL, 10, 1, 1);

-- ── Día Cultural, 14:00–16:00 — 7 talleres (cupos exactos pedidos) ────────
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'cultural' AND nombre = 'Taller #1'), 32);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'cultural' AND nombre = 'Taller #2'), 12);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'cultural' AND nombre = 'Taller #3'), 18);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'cultural' AND nombre = 'Taller #4'), 9);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'cultural' AND nombre = 'Taller #5'), 43);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'cultural' AND nombre = 'Taller #6'), 23);
CALL sembrar_inscripcion_evento((SELECT id FROM eventos WHERE dia = 'cultural' AND nombre = 'Taller #7'), 6);

-- ── Escenario de Talentos "Expresa tu esencia" — 4 actos individuales + 10
-- equipos de tamaños distintos. Sin exclusividad: un alumno puede repetirse
-- en más de un acto y no hay conflicto de horario con los talleres (ver
-- concurso-talentos.md), así que p_exclusivo_competicion y p_excluir_franja
-- van en 0 aquí. ────────────────────────────────────────────────────────
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Solo — Canto', NULL, 1, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Solo — Declamación', NULL, 1, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Solo — Baile', NULL, 1, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Solo — Stand up', NULL, 1, 0, 0);

CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Dueto Acústico', NULL, 2, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Trío Vocal', NULL, 3, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Banda Escolar', NULL, 4, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Grupo de Danza Folclórica', NULL, 5, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Sketch Cómico', NULL, 6, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Ensamble Musical', NULL, 7, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Cuadro de Danza Moderna', NULL, 8, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Coro B23', NULL, 9, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Generación 45 en Escena', NULL, 10, 0, 0);
CALL sembrar_equipo_individual((SELECT id FROM competiciones WHERE dia = 'cultural' AND tipo = 'concurso'), 'Talento Mixto', NULL, 3, 0, 0);

-- ── Día Deportivo — equipos de 10 (5 alumnos + 5 padres/madres), un color
-- de camisa distinto por equipo dentro de cada torneo (ver
-- COLORES_CAMISA en app/inscripciones/includes/colores-camisa.php). El
-- Torneo de Voleibol no traía una cantidad de equipos pedida explícitamente
-- — se eligieron 10, la misma magnitud que los otros dos torneos. ─────────

-- Fútbol Rápido — 14 equipos
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Furia B23', 'Blanco');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Los Halcones', 'Rojo');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Rayo Escolar', 'Azul Marino');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Tigres del 23', 'Azul Rey');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Águilas Bachiller', 'Amarillo');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Panteras FC', 'Verde');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Los Invencibles', 'Negro');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Familia Deportiva', 'Gris');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Relámpagos B23', 'Naranja');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Unidos FC', 'Celeste');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Guerreros Aniversario', 'Tinto');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Leones del Bachiller', 'Rosa');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Cometas FC', 'Morado');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Fútbol Rápido'), 'Vendaval B23', 'Verde Lima');

-- Voleibol — 10 equipos
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Set y Punto', 'Blanco');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Bloqueo Familiar', 'Rojo');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Rematadores B23', 'Azul Marino');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Saque Ganador', 'Azul Rey');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Voladores del Bachiller', 'Amarillo');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Red Alta', 'Verde');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Familia al Ataque', 'Negro');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Los Recibidores', 'Gris');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Golpe Certero', 'Naranja');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Voleibol'), 'Aniversario Volley Club', 'Celeste');

-- Quemados — 12 equipos
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Esquiva y Gana', 'Blanco');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Balazo Familiar', 'Rojo');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Los Esquivadores', 'Azul Marino');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Punteria B23', 'Azul Rey');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Sobrevivientes', 'Amarillo');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Los Intocables', 'Verde');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Escuadrón Quemón', 'Negro');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Reflejos de Familia', 'Gris');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Los Certeros', 'Naranja');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Impacto B23', 'Celeste');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Última Línea', 'Tinto');
CALL sembrar_equipo_deportivo((SELECT id FROM competiciones WHERE dia = 'deportivo' AND nombre = 'Torneo de Quemados'), 'Generación Quemada', 'Rosa');

-- ── Limpieza: los procedimientos/función de siembra eran solo para este
-- bloque, no forman parte del esquema permanente. ─────────────────────────
DROP PROCEDURE IF EXISTS sembrar_inscripcion_evento;
DROP PROCEDURE IF EXISTS sembrar_equipo_individual;
DROP PROCEDURE IF EXISTS sembrar_equipo_deportivo;
DROP FUNCTION IF EXISTS nombre_familiar_aleatorio;
