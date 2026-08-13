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
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir', 'Taller de pintura — "El arte de Van Gogh"', 'Por definir', 'Explanada', 80, 80, 'Por definir'),
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir', 'Taller de cuento corto',                    'Por definir', 'Aula 8', 50, 50, 'Por definir'),
('cultural', 'taller', '14:00:00', '16:00:00', 'Por definir', 'Taller de fotografía básica',               'Por definir', 'Aula 9', 50, 50, 'Por definir');

-- ── competiciones ────────────────────────────────────────────────────────
-- Concurso del Conocimiento (Académico), Escenario de Talentos (Cultural) y
-- los 3 torneos deportivos — cupo/capacidad de equipos vive en equipos, no
-- aquí (ver schema.sql).
INSERT INTO competiciones (dia, tipo, hora_inicio, hora_fin, nombre, fecha_limite) VALUES
('academico', 'concurso', '10:30:00', '12:30:00', 'Concurso del Conocimiento',                  '2026-09-30 23:59:59'),
('cultural',  'concurso', '16:00:00', '17:20:00', 'Escenario de Talentos "Expresa tu esencia"', '2026-09-30 23:59:59'),
('deportivo', 'torneo',   '07:30:00', '11:30:00', 'Torneo de Fútbol Rápido',                    '2026-09-30 23:59:59'),
('deportivo', 'torneo',   '07:30:00', '11:30:00', 'Torneo de Voleibol',                         '2026-09-30 23:59:59'),
('deportivo', 'torneo',   '07:30:00', '11:30:00', 'Torneo de Quemados',                         '2026-09-30 23:59:59');
