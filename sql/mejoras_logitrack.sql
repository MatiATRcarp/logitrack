-- =====================================================================
-- mejoras_logitrack.sql
-- Migración y datos demo para las mejoras solicitadas.
-- Base: dblogitrack
-- =====================================================================

USE dblogitrack;

-- Coordenadas de sucursales para tracking en Argentina.
ALTER TABLE sucursal
    ADD COLUMN IF NOT EXISTS latitud DECIMAL(10,6) NULL,
    ADD COLUMN IF NOT EXISTS longitud DECIMAL(10,6) NULL;

UPDATE sucursal SET latitud = CASE id_sucursal
    WHEN 1 THEN -34.603722 WHEN 2 THEN -34.592163 WHEN 3 THEN -34.921450 WHEN 4 THEN -38.005477
    WHEN 5 THEN -28.469581 WHEN 6 THEN -27.649662 WHEN 7 THEN -27.451862 WHEN 8 THEN -26.785220
    WHEN 9 THEN -43.300160 WHEN 10 THEN -45.864137 WHEN 11 THEN -31.420083 WHEN 12 THEN -33.123159
    WHEN 13 THEN -27.469213 WHEN 14 THEN -29.140030 WHEN 15 THEN -31.741320 WHEN 16 THEN -31.392960
    WHEN 17 THEN -26.184890 WHEN 18 THEN -25.284811 WHEN 19 THEN -24.185786 WHEN 20 THEN -24.231270
    WHEN 21 THEN -36.620346 WHEN 22 THEN -35.659324 WHEN 23 THEN -29.413454 WHEN 24 THEN -29.165814
    WHEN 25 THEN -32.889459 WHEN 26 THEN -32.925613 WHEN 27 THEN -27.362137 WHEN 28 THEN -25.597164
    WHEN 29 THEN -38.951678 WHEN 30 THEN -40.157892 WHEN 31 THEN -41.133472 WHEN 32 THEN -40.813450
    WHEN 33 THEN -24.782127 WHEN 34 THEN -22.516372 WHEN 35 THEN -31.537500 WHEN 36 THEN -31.651967
    WHEN 37 THEN -33.301727 WHEN 38 THEN -33.675711 WHEN 39 THEN -51.623049 WHEN 40 THEN -46.439285
    WHEN 41 THEN -32.944243 WHEN 42 THEN -31.633329 WHEN 43 THEN -27.795110 WHEN 44 THEN -27.733333
    WHEN 45 THEN -54.801912 WHEN 46 THEN -53.786037 WHEN 47 THEN -26.824140 WHEN 48 THEN -26.816667
    ELSE latitud
END,
longitud = CASE id_sucursal
    WHEN 1 THEN -58.381592 WHEN 2 THEN -58.374164 WHEN 3 THEN -57.954533 WHEN 4 THEN -57.542611
    WHEN 5 THEN -65.779544 WHEN 6 THEN -67.031958 WHEN 7 THEN -58.986714 WHEN 8 THEN -60.438760
    WHEN 9 THEN -65.102280 WHEN 10 THEN -67.482243 WHEN 11 THEN -64.188776 WHEN 12 THEN -64.349344
    WHEN 13 THEN -58.830635 WHEN 14 THEN -59.263432 WHEN 15 THEN -60.511547 WHEN 16 THEN -58.020890
    WHEN 17 THEN -58.173134 WHEN 18 THEN -57.718520 WHEN 19 THEN -65.299476 WHEN 20 THEN -64.866111
    WHEN 21 THEN -64.290577 WHEN 22 THEN -63.756821 WHEN 23 THEN -66.856458 WHEN 24 THEN -67.497396
    WHEN 25 THEN -68.845839 WHEN 26 THEN -68.844810 WHEN 27 THEN -55.900874 WHEN 28 THEN -54.578599
    WHEN 29 THEN -68.059189 WHEN 30 THEN -71.353370 WHEN 31 THEN -71.310278 WHEN 32 THEN -63.000003
    WHEN 33 THEN -65.423197 WHEN 34 THEN -63.801310 WHEN 35 THEN -68.536390 WHEN 36 THEN -68.281053
    WHEN 37 THEN -66.337752 WHEN 38 THEN -65.457830 WHEN 39 THEN -69.216829 WHEN 40 THEN -67.528140
    WHEN 41 THEN -60.650539 WHEN 42 THEN -60.700000 WHEN 43 THEN -64.261490 WHEN 44 THEN -64.250000
    WHEN 45 THEN -68.302951 WHEN 46 THEN -67.700224 WHEN 47 THEN -65.222600 WHEN 48 THEN -65.316667
    ELSE longitud
END;

-- Tabla intermedia para normalizar el vínculo usuario -> entidad real.
-- Se mantiene compatible con las columnas viejas de usuario mientras se migra el código.
CREATE TABLE IF NOT EXISTS usuario_persona (
    id_usuario INT NOT NULL,
    tipo_persona ENUM('cliente','chofer','empleado') NOT NULL,
    id_persona INT NOT NULL,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uk_usuario_persona_tipo_id (tipo_persona, id_persona),
    CONSTRAINT fk_usuario_persona_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO usuario_persona (id_usuario, tipo_persona, id_persona)
SELECT id_usuario, 'cliente', id_cliente FROM usuario WHERE id_cliente IS NOT NULL
ON DUPLICATE KEY UPDATE tipo_persona = VALUES(tipo_persona), id_persona = VALUES(id_persona);

INSERT INTO usuario_persona (id_usuario, tipo_persona, id_persona)
SELECT id_usuario, 'chofer', id_chofer FROM usuario WHERE id_chofer IS NOT NULL
ON DUPLICATE KEY UPDATE tipo_persona = VALUES(tipo_persona), id_persona = VALUES(id_persona);

INSERT INTO usuario_persona (id_usuario, tipo_persona, id_persona)
SELECT id_usuario, 'empleado', id_empleado FROM usuario WHERE id_empleado IS NOT NULL
ON DUPLICATE KEY UPDATE tipo_persona = VALUES(tipo_persona), id_persona = VALUES(id_persona);

-- Rendimiento cargable para empleados y choferes desde panel administrativo.
CREATE TABLE IF NOT EXISTS rendimiento_personal (
    id_rendimiento INT NOT NULL AUTO_INCREMENT,
    tipo_personal ENUM('empleado','chofer') NOT NULL,
    id_personal INT NOT NULL,
    periodo DATE NOT NULL,
    puntaje TINYINT UNSIGNED NOT NULL,
    observacion VARCHAR(255) NULL,
    fecha_carga DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_rendimiento),
    UNIQUE KEY uk_rendimiento_personal_periodo (tipo_personal, id_personal, periodo),
    CONSTRAINT chk_rendimiento_puntaje CHECK (puntaje BETWEEN 1 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Trigger solicitado: evita viajes activos superpuestos para el mismo chofer o vehículo.
DELIMITER $$
DROP TRIGGER IF EXISTS trg_viaje_sin_superposicion$$
CREATE TRIGGER trg_viaje_sin_superposicion
BEFORE INSERT ON viaje
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1
        FROM viaje v
        WHERE (v.id_chofer = NEW.id_chofer OR v.patente = NEW.patente)
          AND NEW.fecha_salida <= COALESCE(v.fecha_llegada_est, NEW.fecha_salida)
          AND COALESCE(NEW.fecha_llegada_est, v.fecha_salida) >= v.fecha_salida
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Chofer o vehículo con viaje superpuesto.';
    END IF;
END$$
DELIMITER ;

-- Empleados de carga inicial EMP-3001 a EMP-3070, repartidos en todas las sucursales.
DROP PROCEDURE IF EXISTS sp_seed_empleados_demo;
DELIMITER $$
CREATE PROCEDURE sp_seed_empleados_demo()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE v_dni VARCHAR(15);
    DECLARE v_legajo VARCHAR(20);
    DECLARE v_email VARCHAR(100);
    DECLARE v_nombre VARCHAR(50);
    DECLARE v_apellido VARCHAR(50);
    DECLARE v_id_empleado INT;
    DECLARE v_id_usuario INT;
    DECLARE v_rol_empleado INT;
    DECLARE v_hash VARCHAR(255) DEFAULT '$2y$10$lISN5C5K5AGOEZ8xkvPVfunZg9cFLtzxCZ4WoV9Q5px9umbjLi/4O';

    SELECT id_rol INTO v_rol_empleado FROM rol WHERE nombre = 'empleado' LIMIT 1;

    WHILE i <= 70 DO
        SET v_dni = CAST(80300000 + i AS CHAR);
        SET v_legajo = CONCAT('EMP-', 3000 + i);
        SET v_nombre = CASE i
            WHEN 1 THEN 'Lucía' WHEN 2 THEN 'Martín' WHEN 3 THEN 'Sofía' WHEN 4 THEN 'Nicolás' WHEN 5 THEN 'Valentina'
            WHEN 6 THEN 'Federico' WHEN 7 THEN 'Camila' WHEN 8 THEN 'Agustín' WHEN 9 THEN 'Julieta' WHEN 10 THEN 'Tomás'
            WHEN 11 THEN 'Florencia' WHEN 12 THEN 'Sebastián' WHEN 13 THEN 'Milagros' WHEN 14 THEN 'Matías' WHEN 15 THEN 'Rocío'
            WHEN 16 THEN 'Ignacio' WHEN 17 THEN 'Carolina' WHEN 18 THEN 'Franco' WHEN 19 THEN 'Micaela' WHEN 20 THEN 'Joaquín'
            WHEN 21 THEN 'Natalia' WHEN 22 THEN 'Pablo' WHEN 23 THEN 'Antonella' WHEN 24 THEN 'Gonzalo' WHEN 25 THEN 'Daniela'
            WHEN 26 THEN 'Ezequiel' WHEN 27 THEN 'Belén' WHEN 28 THEN 'Leandro' WHEN 29 THEN 'Marina' WHEN 30 THEN 'Rodrigo'
            WHEN 31 THEN 'Paula' WHEN 32 THEN 'Emiliano' WHEN 33 THEN 'Victoria' WHEN 34 THEN 'Maximiliano' WHEN 35 THEN 'Josefina'
            WHEN 36 THEN 'Cristian' WHEN 37 THEN 'Aldana' WHEN 38 THEN 'Diego' WHEN 39 THEN 'Melina' WHEN 40 THEN 'Facundo'
            WHEN 41 THEN 'Gabriela' WHEN 42 THEN 'Lucas' WHEN 43 THEN 'Romina' WHEN 44 THEN 'Andrés' WHEN 45 THEN 'Noelia'
            WHEN 46 THEN 'Santiago' WHEN 47 THEN 'Lorena' WHEN 48 THEN 'Bruno' WHEN 49 THEN 'Pilar' WHEN 50 THEN 'Damián'
            WHEN 51 THEN 'Andrea' WHEN 52 THEN 'Lautaro' WHEN 53 THEN 'Verónica' WHEN 54 THEN 'Esteban' WHEN 55 THEN 'Cecilia'
            WHEN 56 THEN 'Mauricio' WHEN 57 THEN 'Claudia' WHEN 58 THEN 'Ramiro' WHEN 59 THEN 'Eliana' WHEN 60 THEN 'Iván'
            WHEN 61 THEN 'Silvina' WHEN 62 THEN 'Germán' WHEN 63 THEN 'Malena' WHEN 64 THEN 'Hernán' WHEN 65 THEN 'Marisol'
            WHEN 66 THEN 'Leonardo' WHEN 67 THEN 'Patricia' WHEN 68 THEN 'Javier' WHEN 69 THEN 'Ana' WHEN 70 THEN 'Rubén'
        END;
        SET v_apellido = CASE i
            WHEN 1 THEN 'Fernández' WHEN 2 THEN 'Gómez' WHEN 3 THEN 'Pereyra' WHEN 4 THEN 'Rodríguez' WHEN 5 THEN 'Sosa'
            WHEN 6 THEN 'López' WHEN 7 THEN 'Martínez' WHEN 8 THEN 'García' WHEN 9 THEN 'Romero' WHEN 10 THEN 'Díaz'
            WHEN 11 THEN 'Álvarez' WHEN 12 THEN 'Ruiz' WHEN 13 THEN 'Torres' WHEN 14 THEN 'Castro' WHEN 15 THEN 'Molina'
            WHEN 16 THEN 'Herrera' WHEN 17 THEN 'Vega' WHEN 18 THEN 'Aguirre' WHEN 19 THEN 'Rojas' WHEN 20 THEN 'Silva'
            WHEN 21 THEN 'Acosta' WHEN 22 THEN 'Medina' WHEN 23 THEN 'Cabrera' WHEN 24 THEN 'Morales' WHEN 25 THEN 'Ortega'
            WHEN 26 THEN 'Núñez' WHEN 27 THEN 'Suárez' WHEN 28 THEN 'Mansilla' WHEN 29 THEN 'Benítez' WHEN 30 THEN 'Ponce'
            WHEN 31 THEN 'Figueroa' WHEN 32 THEN 'Navarro' WHEN 33 THEN 'Luna' WHEN 34 THEN 'Coronel' WHEN 35 THEN 'Peralta'
            WHEN 36 THEN 'Godoy' WHEN 37 THEN 'Campos' WHEN 38 THEN 'Villalba' WHEN 39 THEN 'Ferreyra' WHEN 40 THEN 'Miranda'
            WHEN 41 THEN 'Quiroga' WHEN 42 THEN 'Vargas' WHEN 43 THEN 'Giménez' WHEN 44 THEN 'Cáceres' WHEN 45 THEN 'Méndez'
            WHEN 46 THEN 'Roldán' WHEN 47 THEN 'Ibarra' WHEN 48 THEN 'Farías' WHEN 49 THEN 'Bravo' WHEN 50 THEN 'Costa'
            WHEN 51 THEN 'Escobar' WHEN 52 THEN 'Carrizo' WHEN 53 THEN 'Ocampo' WHEN 54 THEN 'Paz' WHEN 55 THEN 'Arias'
            WHEN 56 THEN 'Leiva' WHEN 57 THEN 'Toledo' WHEN 58 THEN 'Montiel' WHEN 59 THEN 'Bustos' WHEN 60 THEN 'Salinas'
            WHEN 61 THEN 'Palacios' WHEN 62 THEN 'Delgado' WHEN 63 THEN 'Ledesma' WHEN 64 THEN 'Moyano' WHEN 65 THEN 'Córdoba'
            WHEN 66 THEN 'Rivero' WHEN 67 THEN 'Villarreal' WHEN 68 THEN 'Serrano' WHEN 69 THEN 'Ojeda' WHEN 70 THEN 'Maldonado'
        END;
        SET v_email = CASE i
            WHEN 1 THEN 'lucia.fernandez3001@logitrack.local' WHEN 2 THEN 'martin.gomez3002@logitrack.local'
            WHEN 3 THEN 'sofia.pereyra3003@logitrack.local' WHEN 4 THEN 'nicolas.rodriguez3004@logitrack.local'
            WHEN 5 THEN 'valentina.sosa3005@logitrack.local' WHEN 6 THEN 'federico.lopez3006@logitrack.local'
            WHEN 7 THEN 'camila.martinez3007@logitrack.local' WHEN 8 THEN 'agustin.garcia3008@logitrack.local'
            WHEN 9 THEN 'julieta.romero3009@logitrack.local' WHEN 10 THEN 'tomas.diaz3010@logitrack.local'
            WHEN 11 THEN 'florencia.alvarez3011@logitrack.local' WHEN 12 THEN 'sebastian.ruiz3012@logitrack.local'
            WHEN 13 THEN 'milagros.torres3013@logitrack.local' WHEN 14 THEN 'matias.castro3014@logitrack.local'
            WHEN 15 THEN 'rocio.molina3015@logitrack.local' WHEN 16 THEN 'ignacio.herrera3016@logitrack.local'
            WHEN 17 THEN 'carolina.vega3017@logitrack.local' WHEN 18 THEN 'franco.aguirre3018@logitrack.local'
            WHEN 19 THEN 'micaela.rojas3019@logitrack.local' WHEN 20 THEN 'joaquin.silva3020@logitrack.local'
            WHEN 21 THEN 'natalia.acosta3021@logitrack.local' WHEN 22 THEN 'pablo.medina3022@logitrack.local'
            WHEN 23 THEN 'antonella.cabrera3023@logitrack.local' WHEN 24 THEN 'gonzalo.morales3024@logitrack.local'
            WHEN 25 THEN 'daniela.ortega3025@logitrack.local' WHEN 26 THEN 'ezequiel.nunez3026@logitrack.local'
            WHEN 27 THEN 'belen.suarez3027@logitrack.local' WHEN 28 THEN 'leandro.mansilla3028@logitrack.local'
            WHEN 29 THEN 'marina.benitez3029@logitrack.local' WHEN 30 THEN 'rodrigo.ponce3030@logitrack.local'
            WHEN 31 THEN 'paula.figueroa3031@logitrack.local' WHEN 32 THEN 'emiliano.navarro3032@logitrack.local'
            WHEN 33 THEN 'victoria.luna3033@logitrack.local' WHEN 34 THEN 'maximiliano.coronel3034@logitrack.local'
            WHEN 35 THEN 'josefina.peralta3035@logitrack.local' WHEN 36 THEN 'cristian.godoy3036@logitrack.local'
            WHEN 37 THEN 'aldana.campos3037@logitrack.local' WHEN 38 THEN 'diego.villalba3038@logitrack.local'
            WHEN 39 THEN 'melina.ferreyra3039@logitrack.local' WHEN 40 THEN 'facundo.miranda3040@logitrack.local'
            WHEN 41 THEN 'gabriela.quiroga3041@logitrack.local' WHEN 42 THEN 'lucas.vargas3042@logitrack.local'
            WHEN 43 THEN 'romina.gimenez3043@logitrack.local' WHEN 44 THEN 'andres.caceres3044@logitrack.local'
            WHEN 45 THEN 'noelia.mendez3045@logitrack.local' WHEN 46 THEN 'santiago.roldan3046@logitrack.local'
            WHEN 47 THEN 'lorena.ibarra3047@logitrack.local' WHEN 48 THEN 'bruno.farias3048@logitrack.local'
            WHEN 49 THEN 'pilar.bravo3049@logitrack.local' WHEN 50 THEN 'damian.costa3050@logitrack.local'
            WHEN 51 THEN 'andrea.escobar3051@logitrack.local' WHEN 52 THEN 'lautaro.carrizo3052@logitrack.local'
            WHEN 53 THEN 'veronica.ocampo3053@logitrack.local' WHEN 54 THEN 'esteban.paz3054@logitrack.local'
            WHEN 55 THEN 'cecilia.arias3055@logitrack.local' WHEN 56 THEN 'mauricio.leiva3056@logitrack.local'
            WHEN 57 THEN 'claudia.toledo3057@logitrack.local' WHEN 58 THEN 'ramiro.montiel3058@logitrack.local'
            WHEN 59 THEN 'eliana.bustos3059@logitrack.local' WHEN 60 THEN 'ivan.salinas3060@logitrack.local'
            WHEN 61 THEN 'silvina.palacios3061@logitrack.local' WHEN 62 THEN 'german.delgado3062@logitrack.local'
            WHEN 63 THEN 'malena.ledesma3063@logitrack.local' WHEN 64 THEN 'hernan.moyano3064@logitrack.local'
            WHEN 65 THEN 'marisol.cordoba3065@logitrack.local' WHEN 66 THEN 'leonardo.rivero3066@logitrack.local'
            WHEN 67 THEN 'patricia.villarreal3067@logitrack.local' WHEN 68 THEN 'javier.serrano3068@logitrack.local'
            WHEN 69 THEN 'ana.ojeda3069@logitrack.local' WHEN 70 THEN 'ruben.maldonado3070@logitrack.local'
        END;

        IF NOT EXISTS (SELECT 1 FROM empleado WHERE legajo = v_legajo) THEN
            INSERT INTO empleado (dni, nombre, apellido, legajo, id_sucursal, activo)
            VALUES (v_dni, v_nombre, v_apellido, v_legajo, ((i - 1) MOD 48) + 1, 1);
            SET v_id_empleado = LAST_INSERT_ID();
        ELSE
            SELECT id_empleado INTO v_id_empleado FROM empleado WHERE legajo = v_legajo LIMIT 1;
            UPDATE empleado
            SET dni = v_dni,
                nombre = v_nombre,
                apellido = v_apellido,
                id_sucursal = ((i - 1) MOD 48) + 1,
                activo = 1
            WHERE id_empleado = v_id_empleado;
        END IF;

        IF NOT EXISTS (SELECT 1 FROM usuario WHERE email = v_email OR dni = v_dni) THEN
            INSERT INTO usuario (email, dni, nombre, apellido, password_hash, id_rol, id_empleado, activo)
            VALUES (v_email, v_dni, v_nombre, v_apellido, v_hash, v_rol_empleado, v_id_empleado, 1);
            SET v_id_usuario = LAST_INSERT_ID();
        ELSE
            SELECT id_usuario INTO v_id_usuario FROM usuario WHERE email = v_email OR dni = v_dni LIMIT 1;
            UPDATE usuario
            SET email = v_email,
                nombre = v_nombre,
                apellido = v_apellido,
                id_empleado = v_id_empleado,
                activo = 1
            WHERE id_usuario = v_id_usuario;
        END IF;

        INSERT INTO usuario_persona (id_usuario, tipo_persona, id_persona)
        VALUES (v_id_usuario, 'empleado', v_id_empleado)
        ON DUPLICATE KEY UPDATE tipo_persona = VALUES(tipo_persona), id_persona = VALUES(id_persona);

        SET i = i + 1;
    END WHILE;
END$$
DELIMITER ;

CALL sp_seed_empleados_demo();

-- Datos demo de envíos de junio 2026 y viajes activos visibles en mapa.
DROP PROCEDURE IF EXISTS sp_seed_operacion_junio_2026;
DELIMITER $$
CREATE PROCEDURE sp_seed_operacion_junio_2026()
BEGIN
    DECLARE v_junio_actual INT DEFAULT 0;
    DECLARE v_activos_actual INT DEFAULT 0;
    DECLARE v_obj_envios INT DEFAULT 130;
    DECLARE v_obj_activos INT DEFAULT 75;
    DECLARE v_i INT DEFAULT 1;
    DECLARE v_id_envio INT;
    DECLARE v_id_viaje INT;
    DECLARE v_origen INT;
    DECLARE v_destino INT;
    DECLARE v_rem INT;
    DECLARE v_des INT;
    DECLARE v_tipo INT;
    DECLARE v_patente VARCHAR(20);
    DECLARE v_chofer INT;
    DECLARE v_estado_pendiente INT;
    DECLARE v_estado_transito INT;
    DECLARE v_salida DATETIME;
    DECLARE v_llegada DATETIME;
    DECLARE v_offset_rem INT;
    DECLARE v_offset_des INT;
    DECLARE v_offset_tipo INT;
    DECLARE v_disponibles INT;

    SELECT id_estado INTO v_estado_pendiente FROM tipo_estado WHERE nombre = 'Pendiente' LIMIT 1;
    SELECT id_estado INTO v_estado_transito FROM tipo_estado WHERE nombre = 'En tránsito' LIMIT 1;

    SELECT COUNT(*) INTO v_junio_actual
    FROM envio
    WHERE fecha_recepcion >= '2026-06-01' AND fecha_recepcion < '2026-07-01';

    WHILE v_junio_actual < v_obj_envios DO
        SET v_origen = ((v_i - 1) MOD 48) + 1;
        SET v_destino = (v_origen MOD 48) + 1;
        SET v_offset_rem = v_i;
        SET v_offset_des = v_i + 11;
        SET v_offset_tipo = (v_i - 1) MOD 6;
        SELECT id_cliente INTO v_rem FROM cliente ORDER BY id_cliente LIMIT v_offset_rem, 1;
        SELECT id_cliente INTO v_des FROM cliente ORDER BY id_cliente LIMIT v_offset_des, 1;
        SELECT id_tipo_cont INTO v_tipo FROM tipo_contenido ORDER BY id_tipo_cont LIMIT v_offset_tipo, 1;

        INSERT INTO envio (nro_tracking, fecha_recepcion, peso_kg, id_tipo_cont, id_remitente, id_destinatario, id_suc_origen, id_suc_destino)
        VALUES (
            CONCAT('JUN26-', LPAD(v_junio_actual + 1, 5, '0')),
            TIMESTAMP('2026-06-01') + INTERVAL ((v_junio_actual MOD 29)) DAY + INTERVAL ((v_junio_actual MOD 12) + 8) HOUR,
            1 + ((v_i * 7) MOD 45),
            v_tipo,
            v_rem,
            v_des,
            v_origen,
            v_destino
        );
        SET v_id_envio = LAST_INSERT_ID();

        INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
        VALUES (v_id_envio, v_estado_pendiente, TIMESTAMP('2026-06-01') + INTERVAL ((v_junio_actual MOD 29)) DAY, v_origen);

        SET v_i = v_i + 1;
        SET v_junio_actual = v_junio_actual + 1;
    END WHILE;

    SELECT COUNT(*) INTO v_activos_actual
    FROM viaje
    WHERE fecha_salida <= NOW()
      AND (fecha_llegada_est >= NOW() OR fecha_llegada_est IS NULL);

    SET v_i = 1;
    WHILE v_activos_actual < v_obj_activos DO
        SELECT COUNT(*) INTO v_disponibles
        FROM envio e
        WHERE e.fecha_recepcion >= '2026-06-01'
          AND e.fecha_recepcion < '2026-07-01'
          AND NOT EXISTS (SELECT 1 FROM viaje_envio ve WHERE ve.id_envio = e.id_envio);

        IF v_disponibles = 0 THEN
            SET v_activos_actual = v_obj_activos;
        ELSE

            SELECT e.id_envio, e.id_suc_origen, e.id_suc_destino
            INTO v_id_envio, v_origen, v_destino
            FROM envio e
            WHERE e.fecha_recepcion >= '2026-06-01'
              AND e.fecha_recepcion < '2026-07-01'
              AND NOT EXISTS (SELECT 1 FROM viaje_envio ve WHERE ve.id_envio = e.id_envio)
            ORDER BY e.id_envio
            LIMIT 1;

            SELECT vh.patente INTO v_patente
            FROM vehiculo vh
            JOIN tipo_vehiculo tv ON vh.id_tipo_veh = tv.id_tipo_veh
            WHERE NOT EXISTS (
                SELECT 1 FROM viaje vx
                WHERE vx.patente = vh.patente
                  AND vx.fecha_salida <= NOW() + INTERVAL 2 DAY
                  AND COALESCE(vx.fecha_llegada_est, NOW() + INTERVAL 2 DAY) >= NOW() - INTERVAL 2 DAY
            )
            ORDER BY vh.patente
            LIMIT 1;

            SELECT ch.id_chofer INTO v_chofer
            FROM chofer ch
            WHERE ch.activo = 1
              AND NOT EXISTS (
                  SELECT 1 FROM viaje vx
                  WHERE vx.id_chofer = ch.id_chofer
                    AND vx.fecha_salida <= NOW() + INTERVAL 2 DAY
                    AND COALESCE(vx.fecha_llegada_est, NOW() + INTERVAL 2 DAY) >= NOW() - INTERVAL 2 DAY
              )
              AND (
                  (SELECT tv.requiere_licencia_especial
                   FROM vehiculo vh JOIN tipo_vehiculo tv ON vh.id_tipo_veh = tv.id_tipo_veh
                   WHERE vh.patente = v_patente) = 0
                  OR ch.tipo_licencia REGEXP '^[CDE]'
              )
            ORDER BY ch.id_chofer
            LIMIT 1;

            SET v_salida = NOW() - INTERVAL (2 + (v_i MOD 18)) HOUR;
            SET v_llegada = NOW() + INTERVAL (8 + (v_i MOD 36)) HOUR;

            INSERT INTO viaje (fecha_salida, fecha_llegada_est, patente, id_chofer)
            VALUES (v_salida, v_llegada, v_patente, v_chofer);
            SET v_id_viaje = LAST_INSERT_ID();

            INSERT INTO viaje_envio (id_viaje, id_envio)
            VALUES (v_id_viaje, v_id_envio);

            INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
            VALUES (v_id_envio, v_estado_transito, v_salida, v_origen);

            SET v_activos_actual = v_activos_actual + 1;
            SET v_i = v_i + 1;
        END IF;
    END WHILE;
END$$
DELIMITER ;

CALL sp_seed_operacion_junio_2026();

-- Vista/índice/procedimiento ya existentes en scripts separados:
--   vista: sql/v_envios_completo.sql
--   procedimientos: sql/stored_procedures.sql
--   índices: sql/indices_rendimiento.sql
-- Este script agrega además el trigger trg_viaje_sin_superposicion.
