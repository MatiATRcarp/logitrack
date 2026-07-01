-- =====================================================================
-- stored_procedures.sql
-- Procedimientos almacenados para las operaciones críticas de LogiTrack
-- Ejecutar en phpMyAdmin (base de datos: dblogitrack)
-- =====================================================================

DELIMITER $$

-- ─────────────────────────────────────────────────────────────────────
-- sp_registrar_envio
-- Registra un nuevo envío y su estado inicial 'Pendiente' en una
-- sola transacción. Devuelve el id_envio generado y el nro_tracking.
-- ─────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_registrar_envio$$
CREATE PROCEDURE sp_registrar_envio(
    IN  p_peso_kg         DECIMAL(10,2),
    IN  p_id_tipo_cont    INT,
    IN  p_id_remitente    INT,
    IN  p_id_destinatario INT,
    IN  p_id_suc_origen   INT,
    IN  p_id_suc_destino  INT,
    OUT p_id_envio        INT,
    OUT p_nro_tracking    VARCHAR(30)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET p_nro_tracking = CONCAT('TRK-', UPPER(SUBSTRING(MD5(RAND()), 1, 8)));

    START TRANSACTION;

        INSERT INTO envio (
            nro_tracking, fecha_recepcion, peso_kg,
            id_tipo_cont, id_remitente, id_destinatario,
            id_suc_origen, id_suc_destino
        ) VALUES (
            p_nro_tracking, NOW(), p_peso_kg,
            p_id_tipo_cont, p_id_remitente, p_id_destinatario,
            p_id_suc_origen, p_id_suc_destino
        );

        SET p_id_envio = LAST_INSERT_ID();

        INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
        VALUES (
            p_id_envio,
            (SELECT id_estado FROM tipo_estado WHERE nombre = 'Pendiente' LIMIT 1),
            NOW(),
            p_id_suc_origen
        );

    COMMIT;
END$$


-- ─────────────────────────────────────────────────────────────────────
-- sp_cambiar_estado_envio
-- Cambia el estado de un envío, validando que el nuevo estado sea
-- un avance válido (no se puede retroceder ni repetir el mismo estado).
-- ─────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_cambiar_estado_envio$$
CREATE PROCEDURE sp_cambiar_estado_envio(
    IN  p_id_envio        INT,
    IN  p_nuevo_id_estado INT,
    IN  p_id_sucursal     INT,
    OUT p_ok              TINYINT,
    OUT p_mensaje         VARCHAR(255)
)
BEGIN
    DECLARE v_estado_actual INT DEFAULT 0;

    SELECT he.id_estado INTO v_estado_actual
    FROM   historial_estado he
    WHERE  he.id_envio = p_id_envio
    ORDER  BY he.id_hist DESC
    LIMIT  1;

    IF v_estado_actual = 0 THEN
        SET p_ok      = 0;
        SET p_mensaje = 'Envío no encontrado.';
    ELSEIF p_nuevo_id_estado <= v_estado_actual THEN
        SET p_ok      = 0;
        SET p_mensaje = 'El nuevo estado debe ser posterior al estado actual.';
    ELSE
        INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
        VALUES (p_id_envio, p_nuevo_id_estado, NOW(), p_id_sucursal);

        SET p_ok      = 1;
        SET p_mensaje = 'Estado actualizado correctamente.';
    END IF;
END$$


-- ─────────────────────────────────────────────────────────────────────
-- sp_confirmar_entrega
-- Confirma la entrega de un envío resolviendo los estados por nombre.
-- Verifica que el estado actual sea En tránsito o En sucursal destino.
-- ─────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_confirmar_entrega$$
CREATE PROCEDURE sp_confirmar_entrega(
    IN  p_id_envio    INT,
    IN  p_id_sucursal INT,
    OUT p_ok          TINYINT,
    OUT p_mensaje     VARCHAR(255)
)
BEGIN
    DECLARE v_estado_actual INT DEFAULT 0;
    DECLARE v_estado_transito INT DEFAULT 0;
    DECLARE v_estado_sucursal_destino INT DEFAULT 0;
    DECLARE v_estado_entregado INT DEFAULT 0;

    SELECT id_estado INTO v_estado_transito
    FROM tipo_estado WHERE nombre = 'En tránsito' LIMIT 1;

    SELECT id_estado INTO v_estado_sucursal_destino
    FROM tipo_estado WHERE nombre = 'Ingresado en sucursal destino' LIMIT 1;

    SELECT id_estado INTO v_estado_entregado
    FROM tipo_estado WHERE nombre = 'Entregado' LIMIT 1;

    SELECT he.id_estado INTO v_estado_actual
    FROM   historial_estado he
    WHERE  he.id_envio = p_id_envio
    ORDER  BY he.id_hist DESC
    LIMIT  1;

    IF v_estado_actual NOT IN (v_estado_transito, v_estado_sucursal_destino) THEN
        SET p_ok      = 0;
        SET p_mensaje = 'El envío debe estar En tránsito o en Sucursal Destino para confirmarse como entregado.';
    ELSE
        INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
        VALUES (p_id_envio, v_estado_entregado, NOW(), p_id_sucursal);

        SET p_ok      = 1;
        SET p_mensaje = 'Entrega confirmada correctamente.';
    END IF;
END$$


-- ─────────────────────────────────────────────────────────────────────
-- sp_crear_viaje
-- Crea un viaje con un chofer y vehículo asignados, verificando
-- que el chofer no tenga otro viaje activo en ese rango de fechas.
-- ─────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_crear_viaje$$
CREATE PROCEDURE sp_crear_viaje(
    IN  p_fecha_salida     DATETIME,
    IN  p_fecha_llegada    DATETIME,
    IN  p_patente          VARCHAR(10),
    IN  p_id_chofer        INT,
    OUT p_id_viaje         INT,
    OUT p_ok               TINYINT,
    OUT p_mensaje          VARCHAR(255)
)
BEGIN
    DECLARE v_conflicto INT DEFAULT 0;

    SELECT COUNT(*) INTO v_conflicto
    FROM   viaje
    WHERE  id_chofer = p_id_chofer
      AND  fecha_salida <= COALESCE(p_fecha_llegada, p_fecha_salida)
      AND  (fecha_llegada_est >= p_fecha_salida OR fecha_llegada_est IS NULL);

    IF v_conflicto > 0 THEN
        SET p_ok      = 0;
        SET p_id_viaje = 0;
        SET p_mensaje = 'El chofer ya tiene un viaje asignado en ese rango de fechas.';
    ELSE
        INSERT INTO viaje (fecha_salida, fecha_llegada_est, patente, id_chofer)
        VALUES (p_fecha_salida, p_fecha_llegada, p_patente, p_id_chofer);

        SET p_id_viaje = LAST_INSERT_ID();
        SET p_ok       = 1;
        SET p_mensaje  = 'Viaje creado correctamente.';
    END IF;
END$$


-- ─────────────────────────────────────────────────────────────────────
-- sp_resumen_envios_cliente
-- Devuelve todos los envíos de un cliente (como remitente o destinatario)
-- con su estado actual.
-- ─────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_resumen_envios_cliente$$
CREATE PROCEDURE sp_resumen_envios_cliente(
    IN p_id_cliente INT
)
BEGIN
    SELECT
        e.id_envio,
        e.nro_tracking,
        e.fecha_recepcion,
        e.peso_kg,
        CASE WHEN e.id_remitente    = p_id_cliente THEN 'Remitente'
             WHEN e.id_destinatario = p_id_cliente THEN 'Destinatario'
             ELSE 'N/A'
        END AS rol_cliente,
        te.nombre AS estado_actual,
        so.nombre AS sucursal_origen,
        sd.nombre AS sucursal_destino
    FROM   envio e
    JOIN   sucursal  so ON e.id_suc_origen  = so.id_sucursal
    JOIN   sucursal  sd ON e.id_suc_destino = sd.id_sucursal
    JOIN   historial_estado he ON he.id_hist = (
        SELECT MAX(h2.id_hist) FROM historial_estado h2 WHERE h2.id_envio = e.id_envio
    )
    JOIN   tipo_estado te ON he.id_estado = te.id_estado
    WHERE  e.id_remitente = p_id_cliente OR e.id_destinatario = p_id_cliente
    ORDER  BY e.fecha_recepcion DESC;
END$$

DELIMITER ;

-- =====================================================================
-- USO DE EJEMPLO:
--
-- CALL sp_registrar_envio(5.5, 1, 3, 7, 2, 14, @id, @nro);
-- SELECT @id, @nro;
--
-- CALL sp_cambiar_estado_envio(12, 2, 5, @ok, @msg);
-- SELECT @ok, @msg;
--
-- CALL sp_confirmar_entrega(12, 14, @ok, @msg);
-- SELECT @ok, @msg;
--
-- CALL sp_crear_viaje('2026-07-01 08:00:00', '2026-07-02 20:00:00', 'AB123CD', 1, @vid, @ok, @msg);
-- SELECT @vid, @ok, @msg;
--
-- CALL sp_resumen_envios_cliente(3);
-- =====================================================================
