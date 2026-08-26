-- Migración: control de camisas por jefe de grupo (app/camisas)
--
-- POR QUÉ EXISTE ESTE ARCHIVO: database/schema.sql está montado en
-- /docker-entrypoint-initdb.d/ (ver docker-compose.yml), y MariaDB SOLO corre
-- ese directorio cuando el volumen db_data está vacío. En una base que ya
-- existe, editar schema.sql no cambia nada — las columnas nuevas no aparecen y
-- la app truena con un 500 al consultarlas. Este archivo lleva los mismos
-- cambios a una base ya creada.
--
-- Cómo aplicarlo (Docker, desde app/):
--     docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" \
--         < database/migraciones/2026-08-26-camisas-jefe-grupo.sql
-- O pegando el contenido en Adminer (http://localhost:8081).
--
-- En una instalación nueva NO hace falta: schema.sql ya trae todo esto.
-- Es idempotente hasta donde MariaDB lo permite (IF NOT EXISTS en columnas e
-- índices); los triggers se recrean con DROP previo.

-- 1. Costo de la camisa — vive en `sistema` (tabla de una sola fila) para que
--    el staff lo pueda cambiar desde app/admin sin tocar código.
ALTER TABLE sistema
    ADD COLUMN IF NOT EXISTS camisa_costo DECIMAL(7,2) NOT NULL DEFAULT 150.00
        COMMENT 'Costo unitario de la camisa oficial del aniversario — tope de alumnos.camisa_pago (ver trg_alumnos_camisa_pago_*)';

ALTER TABLE sistema
    DROP CONSTRAINT IF EXISTS chk_sistema_camisa_costo;

ALTER TABLE sistema
    ADD CONSTRAINT chk_sistema_camisa_costo CHECK ( camisa_costo >= 0 );

-- 2. Columnas del alumno. camisa_pedir arranca en 1 (todos piden) porque el
--    pre-registro ya obliga a elegir talla: los reportes de tallas que ya
--    existen siguen dando el mismo número tras la migración.
ALTER TABLE alumnos
    ADD COLUMN IF NOT EXISTS camisa_pedir TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1 = sí encarga camisa (cuenta en el pedido al proveedor y en los reportes de app/admin); 0 = no la quiere. Arranca en 1 porque el pre-registro ya obliga a elegir talla — el jefe de grupo la baja a 0 si el alumno se arrepiente',
    ADD COLUMN IF NOT EXISTS camisa_pago DECIMAL(7,2) NOT NULL DEFAULT 0.00
        COMMENT 'Acumulado que lleva pagado de su camisa (no es un historial de abonos, es el total al día de hoy) — nunca mayor a sistema.camisa_costo, ver trg_alumnos_camisa_pago_*',
    ADD COLUMN IF NOT EXISTS es_jefe TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = jefe de grupo: el alumno que lleva el control de los pagos de camisa de SU grado+grupo desde app/camisas. Uno solo por grado+grupo (ver jefe_grupo)';

-- 3. La columna generada va en su propio ALTER: necesita que es_jefe ya exista.
--    Un UNIQUE (grado, grupo, es_jefe) NO serviría — prohibiría que dos alumnos
--    del mismo grupo tengan es_jefe = 0. jefe_grupo vale NULL para quien no es
--    jefe, y MariaDB permite NULLs repetidos en un índice único, así que el
--    UNIQUE termina restringiendo solo a los jefes: uno por grado+grupo.
ALTER TABLE alumnos
    ADD COLUMN IF NOT EXISTS jefe_grupo VARCHAR(2)
        AS ( IF(es_jefe = 1, CONCAT(grado, grupo), NULL) ) PERSISTENT
        COMMENT 'Derivada: "1A" si es jefe, NULL si no. Solo existe para poder poner el UNIQUE de abajo — ver nota';

ALTER TABLE alumnos
    ADD UNIQUE KEY IF NOT EXISTS uq_alumnos_jefe_grupo (jefe_grupo)
        COMMENT 'Un único jefe de grupo por grado+grupo — quitarle el cargo al anterior antes de nombrar al nuevo';

-- 4. Coherencia entre las dos columnas nuevas: no se puede tener un pago
--    registrado si el alumno no está pidiendo camisa. Las filas existentes
--    (camisa_pago = 0) ya cumplen.
--    El DROP previo es lo que hace re-ejecutable este paso: a diferencia de
--    columnas e índices, un CHECK no admite ADD ... IF NOT EXISTS y volver a
--    correr la migración fallaría con "Duplicate CHECK constraint name".
ALTER TABLE alumnos
    DROP CONSTRAINT IF EXISTS chk_alumnos_camisa_pago;

ALTER TABLE alumnos
    ADD CONSTRAINT chk_alumnos_camisa_pago
        CHECK ( camisa_pago >= 0 AND ( camisa_pedir = 1 OR camisa_pago = 0 ) );

-- 5. Tope camisa_pago <= sistema.camisa_costo. No puede ser un CHECK: MariaDB
--    no admite subconsultas ahí y el costo vive en otra tabla. Mismo criterio
--    que trg_equipos_limite_maximo — la app valida antes para dar un mensaje
--    decente, esto es la red por si la escritura llega por otra vía.
DROP TRIGGER IF EXISTS trg_alumnos_camisa_pago_insert;
DROP TRIGGER IF EXISTS trg_alumnos_camisa_pago_update;

DELIMITER $$
CREATE TRIGGER trg_alumnos_camisa_pago_insert
BEFORE INSERT ON alumnos
FOR EACH ROW
BEGIN
    DECLARE v_costo DECIMAL(7,2) DEFAULT NULL;

    IF NEW.camisa_pago > 0 THEN
        SELECT camisa_costo INTO v_costo FROM sistema WHERE id = 1;
        IF v_costo IS NOT NULL AND NEW.camisa_pago > v_costo THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El pago de la camisa no puede exceder su costo.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_alumnos_camisa_pago_update
BEFORE UPDATE ON alumnos
FOR EACH ROW
BEGIN
    DECLARE v_costo DECIMAL(7,2) DEFAULT NULL;

    IF NEW.camisa_pago <> OLD.camisa_pago AND NEW.camisa_pago > 0 THEN
        SELECT camisa_costo INTO v_costo FROM sistema WHERE id = 1;
        IF v_costo IS NOT NULL AND NEW.camisa_pago > v_costo THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El pago de la camisa no puede exceder su costo.';
        END IF;
    END IF;
END$$
DELIMITER ;
