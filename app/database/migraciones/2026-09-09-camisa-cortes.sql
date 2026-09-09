-- Migración: cortes de caja de camisas (entrega jefe de grupo → admin)
--
-- POR QUÉ EXISTE ESTE ARCHIVO: database/schema.sql está montado en
-- /docker-entrypoint-initdb.d/ (ver docker-compose.yml), y MariaDB SOLO corre
-- ese directorio cuando el volumen db_data está vacío. En una base que ya
-- existe, editar schema.sql no cambia nada — la tabla nueva no aparece y la
-- app truena con un 500 al consultarla. Este archivo crea la misma tabla en
-- una base ya creada.
--
-- Cómo aplicarlo (Docker, desde app/):
--     docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" \
--         < database/migraciones/2026-09-09-camisa-cortes.sql
-- O pegando el contenido en Adminer (http://localhost:8081).
--
-- En una instalación nueva NO hace falta: schema.sql ya trae esta tabla.
-- Al ser una tabla completamente nueva, el CREATE TABLE IF NOT EXISTS ya es
-- idempotente por sí solo — no hace falta el patrón DROP CONSTRAINT IF
-- EXISTS que usan otras migraciones que agregan columnas a tablas existentes.

CREATE TABLE IF NOT EXISTS camisa_cortes (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
        COMMENT 'Identificador interno — también el folio impreso en el recibo',
    grado             ENUM('1','3','5') NOT NULL
        COMMENT 'Grado del grupo que entrega — mismo dominio que alumnos.grado',
    grupo             ENUM('A','B','C') NOT NULL
        COMMENT 'Grupo que entrega — mismo dominio que alumnos.grupo',
    monto             DECIMAL(7,2) NOT NULL
        COMMENT 'Lo que el jefe entregó físicamente — no se topa contra lo recaudado según el sistema, ver nota de la tabla en schema.sql',
    entregado_por     VARCHAR(150) NOT NULL
        COMMENT 'Nombre de quien entrega el dinero (normalmente el jefe) — texto libre, mismo criterio que inscripciones.registrado_por',
    recibido_por      VARCHAR(150) NOT NULL
        COMMENT 'Nombre de quién del staff recibió el dinero — texto libre porque app/admin no tiene usuarios individuales (contraseña compartida)',
    fecha_movimiento  DATE NOT NULL
        COMMENT 'Fecha real de la entrega en efectivo, tecleada por el admin — puede no ser hoy',
    fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Cuándo quedó capturado en el sistema (auditoría) — no confundir con fecha_movimiento',
    CONSTRAINT chk_camisa_cortes_monto CHECK ( monto > 0 ),
    KEY idx_camisa_cortes_grupo (grado, grupo, fecha_movimiento)
        COMMENT 'La consulta más frecuente es el histórico de un grado+grupo ordenado por fecha'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cortes (entregas de efectivo) de cada jefe de grupo al staff por lo cobrado de la camisa — inmutable, sin UPDATE/DELETE desde la UI.';
