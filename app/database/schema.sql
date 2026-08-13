-- Esquema de base de datos — Semana Cultural B23
-- Fase actual: solo pre-registro de alumnos (Día Académico).
-- Las tablas de asistencia (escaneo QR), talleres e inscripciones se agregan
-- en una fase posterior (ver app/PROMPTS-DESARROLLO.md).

CREATE TABLE IF NOT EXISTS alumnos (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_cuenta           CHAR(8) NOT NULL,
    nombre_completo         VARCHAR(150) NOT NULL,
    grado                   ENUM('1','3','5') NOT NULL,
    grupo                   ENUM('A','B','C') NOT NULL,
    correo_institucional    VARCHAR(150) NOT NULL,
    foto_path               VARCHAR(255) NOT NULL,
    tema_interes            TEXT NULL,
    token_descarga          CHAR(32) NOT NULL,
    credencial_path         VARCHAR(255) NULL,
    credencial_generada     TINYINT(1) NOT NULL DEFAULT 0,
    fecha_envio_credencial  DATETIME NULL,
    fecha_registro          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alumnos_numero_cuenta (numero_cuenta),
    UNIQUE KEY uq_alumnos_token_descarga (token_descarga)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
