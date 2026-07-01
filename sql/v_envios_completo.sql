-- =====================================================================
-- v_envios_completo.sql
-- Vista general de envíos: consolida datos del envío, remitente,
-- destinatario, sucursales, tipo de contenido, estado actual
-- (último registro en historial_estado) y ubicación actual.
-- =====================================================================


-- ---------------------------------------------------------------------
-- CREACIÓN DE LA VISTA
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_envios_completo AS
SELECT
    e.id_envio,
    e.nro_tracking,
    e.fecha_recepcion,
    e.peso_kg,
    tc.nombre                             AS tipo_contenido,

    -- Remitente
    CONCAT(cr.nombre, ' ', cr.apellido)   AS remitente,
    cr.dni                                AS dni_remitente,

    -- Destinatario
    CONCAT(cd.nombre, ' ', cd.apellido)   AS destinatario,
    cd.dni                                AS dni_destinatario,

    -- Sucursales del envío
    so.nombre                             AS sucursal_origen,
    sd.nombre                             AS sucursal_destino,

    -- Estado actual (último evento del historial)
    te.nombre                             AS estado_actual,
    he.fecha_hora                         AS fecha_ultimo_evento,

    -- Ubicación física actual (sucursal donde está el envío ahora)
    sc.nombre                             AS ubicacion_actual

FROM envio e
JOIN tipo_contenido   tc  ON e.id_tipo_cont     = tc.id_tipo_cont
JOIN cliente          cr  ON e.id_remitente     = cr.id_cliente
JOIN cliente          cd  ON e.id_destinatario  = cd.id_cliente
JOIN sucursal         so  ON e.id_suc_origen    = so.id_sucursal
JOIN sucursal         sd  ON e.id_suc_destino   = sd.id_sucursal
JOIN historial_estado he  ON he.id_hist = (
    SELECT MAX(h2.id_hist)
    FROM   historial_estado h2
    WHERE  h2.id_envio = e.id_envio
)
JOIN tipo_estado      te  ON he.id_estado          = te.id_estado
LEFT JOIN sucursal    sc  ON he.id_sucursal_actual  = sc.id_sucursal;


-- =====================================================================
-- CONSULTAS DE EJEMPLO
-- =====================================================================

-- 1. Ver todos los envíos con su estado actual
SELECT * FROM v_envios_completo
ORDER BY fecha_recepcion DESC;


-- 2. Filtrar por estado actual
SELECT * FROM v_envios_completo
WHERE estado_actual = 'En tránsito';

-- Otros valores posibles: 'Pendiente', 'Entregado', 'Cancelado', 'Devuelto al remitente'


-- 3. Buscar envíos de un cliente por nombre o DNI
SELECT * FROM v_envios_completo
WHERE remitente LIKE '%García%'
   OR destinatario LIKE '%García%';

SELECT * FROM v_envios_completo
WHERE dni_remitente = '12345678'
   OR dni_destinatario = '12345678';


-- 4. Buscar por número de tracking
SELECT * FROM v_envios_completo
WHERE nro_tracking = 'TRK-XXXXXXXX';


-- 5. Envíos pendientes en una sucursal de origen específica
SELECT * FROM v_envios_completo
WHERE estado_actual = 'Pendiente'
  AND sucursal_origen = 'Casa Central';


-- 6. Resumen: cantidad de envíos por estado actual
SELECT estado_actual, COUNT(*) AS total
FROM   v_envios_completo
GROUP  BY estado_actual
ORDER  BY total DESC;


-- 7. Resumen: peso total por sucursal destino
SELECT sucursal_destino,
       COUNT(*)          AS cantidad_envios,
       SUM(peso_kg)      AS peso_total_kg
FROM   v_envios_completo
GROUP  BY sucursal_destino
ORDER  BY peso_total_kg DESC;
