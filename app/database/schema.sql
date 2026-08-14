-- Esquema de base de datos — Semana Acádemica, Cultural y Deportiva B23
-- Ver app/PROMPTS-DESARROLLO.md para el detalle de cada tabla y su prompt de origen.

-- El contenedor de MariaDB crea la base (variable MYSQL_DATABASE, ver
-- docker-compose.yml) con el collation por defecto del servidor —
-- utf8mb4_uca1400_ai_ci en MariaDB 11 — pero todas las tablas de este
-- archivo se crean explícitamente en utf8mb4_unicode_ci. Sin este ALTER, esa
-- discrepancia entre el collation "de la base" y el de las tablas rompe
-- cualquier procedimiento/función almacenado que compare literales de texto
-- (error 1267 "Illegal mix of collations") aunque las tablas en sí queden
-- bien. Alinear aquí el collation por defecto de la base evita el problema
-- para cualquier rutina futura, no solo las de app/database/seeds.sql.
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Configuración general de la app: las contraseñas compartidas (hasheadas)
-- que piden app/asistencias (clave_acceso) y app/admin (clave_admin) antes de
-- dejar hacer nada — ver app/asistencias/public/evento.php y
-- app/admin/public/index.php. Una sola fila (id fijo en 1, forzado con el
-- CHECK) para que sea trivial de leer/actualizar sin tener que buscar cuál es
-- "la" fila. Ambas columnas son NULL hasta que alguien configura esa
-- contraseña por primera vez — y son independientes entre sí a propósito:
-- quien entra primero a asistencias puede crear la fila (id=1) sin conocer
-- todavía la clave de admin, y viceversa; cada módulo solo lee/escribe su
-- propia columna y nunca asume que la otra ya tiene valor.
CREATE TABLE IF NOT EXISTS sistema (
    id            TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Fijo en 1 (ver CHECK) — tabla de una sola fila',
    clave_acceso  VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Hash (password_hash) de la contraseña de acceso a app/asistencias — NULL si aún no se configura, nunca texto plano',
    clave_admin   VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Hash (password_hash) de la contraseña de acceso a app/admin — NULL si aún no se configura, nunca texto plano',
    PRIMARY KEY (id),
    CONSTRAINT chk_sistema_id CHECK ( id = 1 )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuración general de la app: contraseñas de acceso a app/asistencias y app/admin. Una sola fila.';

-- Alumnos pre-registrados (app/registro) antes de la Semana Acádemica, Cultural y Deportiva. Es el
-- padrón base del que cuelga todo lo demás: la credencial digital con QR que
-- cada alumno presenta el día del evento codifica ÚNICAMENTE numero_cuenta,
-- que es la llave con la que se busca aquí al escanear.
CREATE TABLE IF NOT EXISTS alumnos (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
        COMMENT 'Identificador interno — el QR de la credencial usa numero_cuenta, no este id',
    numero_cuenta           CHAR(8) NOT NULL
        COMMENT 'Identificador oficial del alumno (8 caracteres) — esto es lo único que codifica el QR de la credencial',
    nombre_completo         VARCHAR(150) NOT NULL
        COMMENT 'Nombre completo del alumno, tal como se muestra en la credencial',
    grado                   ENUM('1','3','5') NOT NULL
        COMMENT 'Grado escolar (1°, 3° o 5°)',
    grupo                   ENUM('A','B','C') NOT NULL
        COMMENT 'Grupo dentro del grado (ej. 1°A) — también sirve para el desayuno por grupo y reportes',
    correo_institucional    VARCHAR(150) NOT NULL
        COMMENT 'A dónde se envía la credencial digital generada (app/registro, Prompt 6)',
    foto_path               VARCHAR(255) NOT NULL
        COMMENT 'Ruta relativa dentro de app/registro/public/uploads/ — la foto no se guarda en la base de datos',
    camisa_corte            ENUM('Hombre','Mujer') NOT NULL
        COMMENT 'Corte de la camisa oficial del aniversario que se le encargará al alumno',
    camisa_talla            ENUM('S','M','L','XL','2XL') NOT NULL
        COMMENT 'Talla de la camisa oficial del aniversario que se le encargará al alumno',
    token_descarga          CHAR(32) NOT NULL
        COMMENT 'Identificador aleatorio (no el numero_cuenta) usado en la URL de exito.php/recuperar.php para volver a descargar la credencial',
    credencial_path         VARCHAR(255) NULL
        COMMENT 'Ruta relativa dentro de app/registro/public/credenciales/ de la credencial (imagen) ya compuesta',
    credencial_generada     TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'true una vez que generar-credencial.php compuso la imagen con foto + datos + QR',
    fecha_registro          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Cuándo se completó el registro',
    UNIQUE KEY uq_alumnos_numero_cuenta (numero_cuenta)
        COMMENT 'Un numero_cuenta no puede pre-registrarse dos veces',
    UNIQUE KEY uq_alumnos_token_descarga (token_descarga)
        COMMENT 'Cada token_descarga debe ser único para no exponer la credencial de otro alumno'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Padrón de alumnos pre-registrados — base de la credencial digital con QR (numero_cuenta).';

-- Catálogo de eventos individuales (a los que un alumno se inscribe uno por
-- uno, sin equipo): ponencias y talleres del Día Académico o del Día
-- Cultural. Lo que se organiza POR EQUIPO (concursos, torneos) NO va aquí:
-- ver equipos/integrantes más abajo. hora_inicio/hora_fin permiten validar
-- cruces de horario en la app (un alumno no puede estar físicamente en dos
-- eventos, o en un evento y una competición, que se traslapen el mismo
-- día — ver "Reglas de inscripción por franja horaria" en
-- 01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md).
CREATE TABLE IF NOT EXISTS eventos (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
        COMMENT 'Identificador interno — referenciado por inscripciones.id_evento',
    dia               ENUM('academico','cultural') NOT NULL
        COMMENT 'En qué día ocurre este evento — el Día Deportivo no tiene eventos individuales, solo equipos',
    tipo              ENUM('ponencia','taller') NOT NULL
        COMMENT '"ponencia" o "taller" — el horario real de cada uno vive en hora_inicio/hora_fin, no está fijo por tipo',
    hora_inicio       TIME NOT NULL
        COMMENT 'Hora de inicio, mismo día que dia — usada para detectar cruces de horario contra otros eventos/competiciones del alumno',
    hora_fin          TIME NOT NULL
        COMMENT 'Hora de fin — ver CHECK chk_eventos_horario',
    facilitador       VARCHAR(150) NOT NULL
        COMMENT 'Quién imparte la ponencia/taller',
    nombre            VARCHAR(150) NOT NULL
        COMMENT 'Nombre de la ponencia/taller tal como se muestra al alumno al elegir',
    descripcion       VARCHAR(150) NOT NULL
        COMMENT 'Descripción breve mostrada junto al nombre al elegir',
    espacio           VARCHAR(100) NOT NULL
        COMMENT 'Aula/salón donde ocurre — mismos nombres que espacios-y-capacidades.md',
    cupo_maximo       SMALLINT UNSIGNED NOT NULL
        COMMENT 'Cupo total del evento, fijo desde que se crea la fila',
    cupo_disponible   SMALLINT UNSIGNED NOT NULL
        COMMENT 'Se descuenta al inscribir (ver inscripciones) — controla el cupo en tiempo real',
    responsable       VARCHAR(150) NOT NULL
        COMMENT 'Staff/maestro responsable del evento (distinto del facilitador si aplica)',
    CONSTRAINT chk_eventos_horario CHECK ( hora_fin > hora_inicio )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de ponencias y talleres individuales (Día Académico/Cultural) — no incluye lo organizado por equipo.';

-- QUÉ evento tiene cada alumno (a qué ponencia/taller asiste) Y, en la misma
-- fila, su entrada/salida a ESE evento específico — son "todas las
-- combinaciones posibles": un alumno puede tener una fila de asistencia
-- general del día (ver asistencias_generales) más una fila aquí por cada
-- evento al que está inscrito, cada una con su propio control de
-- entrada/salida. hora_entrada es NULL hasta el primer escaneo en ese
-- evento — la fila puede existir desde antes (origen='previo', asignada por
-- el encargado semanas antes) sin que la persona haya llegado todavía.
CREATE TABLE IF NOT EXISTS inscripciones (
    id_evento               INT UNSIGNED NOT NULL
        COMMENT 'FK a eventos — a qué ponencia/taller está inscrito',
    id_alumno               INT UNSIGNED NOT NULL
        COMMENT 'FK a alumnos — quién está inscrito',
    origen                  ENUM('previo','orden_llegada') NOT NULL
        COMMENT '"previo": asignado de antemano por el encargado, antes del evento. "orden_llegada": elegido el día del evento tras el escaneo de entrada general',
    registrado_por          VARCHAR(150) NOT NULL
        COMMENT 'Quién hizo la asignación: el propio alumno/maestro (orden_llegada) o el encargado (previo)',
    hora_entrada            DATETIME NULL
        COMMENT 'NULL hasta el primer escaneo en ESTE evento — la fila puede existir desde antes (origen=previo) sin que la persona haya llegado',
    punto_control_entrada   VARCHAR(100) NULL
        COMMENT 'Ubicación física donde se escaneó la entrada a este evento (ej. el aula/salón del taller)',
    escaneado_por_entrada   VARCHAR(100) NULL
        COMMENT 'Maestro/staff que operó el escaneo de entrada — no es autoservicio',
    hora_salida             DATETIME NULL
        COMMENT 'Se sobreescribe en cada escaneo posterior al primero, así que siempre refleja el último escaneo',
    escaneado_por_salida    VARCHAR(100) NULL
        COMMENT 'Maestro/staff que operó el escaneo de salida',
    punto_control_salida    VARCHAR(100) NULL
        COMMENT 'Ubicación física donde se escaneó la salida de este evento',
    fecha_registro          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Cuándo se creó la inscripción (no confundir con hora_entrada, que es cuándo llegó físicamente al evento)',
    PRIMARY KEY(id_evento, id_alumno),
    CONSTRAINT fk_inscripciones_alumno FOREIGN KEY (id_alumno) REFERENCES alumnos(id),
    CONSTRAINT fk_inscripciones_taller FOREIGN KEY (id_evento) REFERENCES eventos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='A qué evento está inscrito cada alumno, con su propio control de entrada/salida A ESE evento.';

-- Semillas de qué competiciones existen (Concurso del Conocimiento, Concurso
-- de Talentos, y los 3 torneos deportivos) — equipos.id_competicion apunta
-- aquí en vez de repetir dia/tipo en cada equipo. hora_inicio/hora_fin
-- permiten validar cruces de horario contra eventos (ver misma nota en la
-- tabla eventos) — EXCEPTO para el Día Deportivo, donde por regla de
-- negocio SÍ se permite inscribirse a más de un torneo aunque se traslapen
-- (ver "Reglas de inscripción a más de un torneo" en
-- 03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md): esa exclusión se
-- aplica en la app, no aquí, para no bloquear una inscripción que sí es
-- válida.
CREATE TABLE IF NOT EXISTS competiciones (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
        COMMENT 'Identificador interno — referenciado por equipos.id_competicion',
    dia               ENUM('academico','cultural','deportivo') NOT NULL
        COMMENT 'Académico = Concurso del Conocimiento, Cultural = Concurso de Talentos, Deportivo = torneos',
    tipo              ENUM('concurso','torneo') NOT NULL
        COMMENT '"concurso" cubre tanto el Concurso del Conocimiento como el de Talentos — se distinguen entre sí por nombre',
    hora_inicio       TIME NOT NULL
        COMMENT 'Hora de inicio, mismo día que dia — para Deportivo es la ventana completa del torneo (07:30-11:30), no el horario de un partido específico',
    hora_fin          TIME NOT NULL
        COMMENT 'Hora de fin — ver CHECK chk_competiciones_horario',
    nombre            VARCHAR(150) NOT NULL
        COMMENT 'Nombre de la competición (ej. "Concurso del Conocimiento", "Torneo de Voleibol")',
    fecha_limite      DATETIME NOT NULL
        COMMENT 'Fecha límite de inscripción a esta competición',
    fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Cuándo se registró la competición',
    CONSTRAINT chk_competiciones_horario CHECK ( hora_fin > hora_inicio )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Concursos y torneos (Concurso del Conocimiento, Concurso de Talentos, torneos deportivos) — semillas de las que cuelgan los equipos.';

-- Equipos de todo lo que se organiza POR EQUIPO en vez de individualmente
-- por alumno: cada equipo pertenece a una competición (ver competiciones,
-- que ya trae dia/tipo). Viven aparte de eventos/inscripciones porque estos
-- equipos mezclan alumnos con padres y madres de familia (ver integrantes),
-- cosa que una ponencia/taller individual no necesita.
CREATE TABLE IF NOT EXISTS equipos (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
        COMMENT 'Identificador interno — referenciado por integrantes.id_equipo',
    id_competicion    INT UNSIGNED NOT NULL
        COMMENT 'Competición en la que participa el equipo (ver competiciones — de ahí sale el dia/tipo)',
    nombre            VARCHAR(150) NOT NULL
        COMMENT 'Nombre del equipo, elegido al inscribirse',
    id_alumno_capitan INT UNSIGNED NOT NULL
        COMMENT 'El capitán del equipo es siempre un alumno (FK a alumnos), nunca un padre/madre',
    color_camisa      VARCHAR(50) NULL
        COMMENT 'Solo aplica a los torneos deportivos, para que staff/árbitros distingan equipos a simple vista — NULL en concursos',
    fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Cuándo se inscribió el equipo',
    UNIQUE KEY uq_equipos_color (id_competicion, color_camisa)
        COMMENT 'Un color de camisa no se repite dentro de la misma competición',
    CONSTRAINT fk_equipos_competicion FOREIGN KEY (id_competicion) REFERENCES competiciones(id),
    CONSTRAINT fk_equipos_alumno_capitan FOREIGN KEY (id_alumno_capitan) REFERENCES alumnos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Equipos de concursos y torneos (Concurso del Conocimiento, Concurso de Talentos, torneos deportivos).';

-- Tope de 12 equipos para el Concurso del Conocimiento (regla de negocio
-- nueva) — se controla a nivel de base de datos, no solo en la app, para que
-- ninguna vía de inserción (la app, una carga manual, otro script) pueda
-- rebasarlo por accidente. No se modela como columna/UNIQUE porque es un
-- límite de CONTEO, no de unicidad — un trigger es la única forma de
-- expresarlo en MariaDB. Se identifica la competición por dia/tipo en vez de
-- un id fijo, porque el id real depende del orden de inserción de las
-- semillas (ver seeds.sql) y puede variar entre entornos.
DELIMITER $$
CREATE TRIGGER trg_equipos_limite_conocimiento
BEFORE INSERT ON equipos
FOR EACH ROW
BEGIN
    DECLARE es_conocimiento TINYINT DEFAULT 0;
    DECLARE total_equipos INT DEFAULT 0;

    SELECT (dia = 'academico' AND tipo = 'concurso') INTO es_conocimiento
    FROM competiciones WHERE id = NEW.id_competicion;

    IF es_conocimiento = 1 THEN
        SELECT COUNT(*) INTO total_equipos FROM equipos WHERE id_competicion = NEW.id_competicion;
        IF total_equipos >= 12 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El Concurso del Conocimiento ya alcanzó su límite de 12 equipos.';
        END IF;
    END IF;
END$$
DELIMITER ;

-- Integrantes de cada equipo: alumnos y padres/madres de familia, con su
-- propio control de entrada/salida (el evento de cada equipo es un solo
-- día, así que aquí no hace falta una tabla de asistencia aparte — a
-- diferencia de asistencias_generales, aquí SÍ se lleva la de padres y
-- madres). hora_entrada es NULL hasta el primer escaneo del día — la fila
-- existe desde la inscripción del equipo (antes del evento), no solo desde
-- que la persona llega.
--
-- OJO con id_alumno: es SIEMPRE el alumno de la familia — el "ancla" de la
-- relación — nunca un padre/madre tiene su propio id. Si el alumno 5
-- participa junto con su papá y su mamá, son 3 filas:
--   (equipo, 5, 'alumno'), (equipo, 5, 'padre'), (equipo, 5, 'madre')
-- y en las de tipo padre/madre, la columna `nombre` es el nombre de esa
-- persona (el papá o la mamá), NO el del alumno.
CREATE TABLE IF NOT EXISTS integrantes (
    id_equipo               INT UNSIGNED NOT NULL
        COMMENT 'FK a equipos — de qué equipo es integrante esta persona',
    id_alumno               INT UNSIGNED NOT NULL
        COMMENT 'SIEMPRE el alumno de la familia (el "ancla"), incluso en filas tipo=padre/madre — ver nota arriba de la tabla',
    tipo                    ENUM('alumno','padre', 'madre') NOT NULL
        COMMENT 'Quién es esta fila en relación al alumno-ancla (id_alumno): el propio alumno, su padre o su madre',
    nombre                  VARCHAR(150) NOT NULL
        COMMENT 'Nombre de la persona de ESTA fila: el del alumno si tipo=alumno, el del padre/madre si no',
    codigo_participante     VARCHAR(20) NOT NULL
        COMMENT 'Identificador único generado por la app — esto es lo único que codifica el QR del ticket (no numero_cuenta, porque los padres no tienen uno)',
    hora_entrada            DATETIME NULL
        COMMENT 'NULL hasta el primer escaneo del día — la fila existe desde la inscripción del equipo, antes del evento',
    punto_control_entrada   VARCHAR(100) NULL
        COMMENT 'Ubicación física donde se escaneó la entrada (ej. acceso al Polideportivo)',
    escaneado_por_entrada   VARCHAR(100) NULL
        COMMENT 'Maestro/staff que operó el escaneo de entrada — no es autoservicio',
    hora_salida             DATETIME NULL
        COMMENT 'Se sobreescribe en cada escaneo posterior al primero, así que siempre refleja el último escaneo del día',
    escaneado_por_salida    VARCHAR(100) NULL
        COMMENT 'Maestro/staff que operó el escaneo de salida',
    punto_control_salida    VARCHAR(100) NULL
        COMMENT 'Ubicación física donde se escaneó la salida',
    fecha_registro          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Cuándo se inscribió al equipo (no confundir con hora_entrada, que es cuándo llegó físicamente el día del evento)',
    PRIMARY KEY (id_equipo, id_alumno, tipo),
    UNIQUE KEY uq_integrantes_codigo (codigo_participante)
        COMMENT 'Cada ticket/QR debe identificar a un único integrante',
    CONSTRAINT fk_integrantes_equipo FOREIGN KEY (id_equipo) REFERENCES equipos(id),
    CONSTRAINT fk_integrantes_alumno FOREIGN KEY (id_alumno) REFERENCES alumnos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Integrantes de cada equipo (alumnos, padres y madres) con su propio control de entrada/salida al evento del equipo.';

-- Asistencia GENERAL (entrada/salida) de los 3 días del evento — un solo
-- concepto para Día Académico, Día Cultural y Día Deportivo: SOLO controla
-- si la persona ya entró/salió del evento de ese día. No es lo mismo que
-- "a qué asiste": eso vive aparte, en eventos->inscripciones (ponencias y
-- talleres) y en equipos->integrantes (concursos y torneos por equipo) —
-- cada una con su propio control de entrada/salida a ESE evento/equipo
-- específico. Solo alumnos: los integrantes de equipo que son padres/madres
-- no tienen asistencia general, solo la de equipos->integrantes.
CREATE TABLE IF NOT EXISTS asistencias_generales (
    id_alumno               INT UNSIGNED NOT NULL
        COMMENT 'FK a alumnos — solo alumnos tienen asistencia general, nunca padres/madres (ver equipos->integrantes para ellos)',
    dia                     ENUM('academico','cultural','deportivo') NOT NULL
        COMMENT 'El mismo alumno puede tener una fila por cada día que asiste (misma credencial/QR, entrada/salida independiente por día)',
    hora_entrada            DATETIME NOT NULL
        COMMENT 'Se llena en el primer escaneo de ESE día — dispara además la asignación de ponencia/taller si dia=academico (ver inscripciones)',
    punto_control_entrada   VARCHAR(100) NOT NULL
        COMMENT 'Ubicación física donde se escaneó la entrada (ej. Entrada principal)',
    escaneado_por_entrada   VARCHAR(100) NOT NULL
        COMMENT 'Maestro/staff que operó el escaneo de entrada — no es autoservicio',
    hora_salida             DATETIME NULL
        COMMENT 'Se sobreescribe en cada escaneo posterior al primero DEL MISMO día, así que siempre refleja el último escaneo de ese día',
    punto_control_salida    VARCHAR(100) NULL
        COMMENT 'Ubicación física donde se escaneó la salida',
    escaneado_por_salida    VARCHAR(100) NULL
        COMMENT 'Maestro/staff que operó el escaneo de salida',
    PRIMARY KEY(id_alumno, dia),
    CONSTRAINT fk_asistencia_general_alumno FOREIGN KEY (id_alumno) REFERENCES alumnos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Asistencia general (¿ya entró/salió del plantel ese día?) de los 3 días — solo alumnos, independiente de a qué evento asiste.';
