-- =====================================================================
-- indices_rendimiento.sql
-- Análisis y creación de índices de rendimiento para la consulta de
-- seguimiento de envíos (remitente, destinatario, sucursales, tipo de
-- contenido y último estado registrado en historial_estado).
-- =====================================================================


-- ---------------------------------------------------------------------
-- PASO 1: Consulta compleja con JOINs
-- ---------------------------------------------------------------------
SELECT
    e.id_envio, e.nro_tracking, e.fecha_recepcion, e.peso_kg,
    CONCAT(cr.nombre, ' ', cr.apellido) AS remitente,
    CONCAT(cd.nombre, ' ', cd.apellido) AS destinatario,
    so.nombre AS sucursal_origen,
    sd.nombre AS sucursal_destino,
    te.nombre AS tipo_contenido,
    ts.nombre AS estado_actual
FROM envio e
JOIN cliente cr ON e.id_remitente = cr.id_cliente
JOIN cliente cd ON e.id_destinatario = cd.id_cliente
JOIN sucursal so ON e.id_suc_origen = so.id_sucursal
JOIN sucursal sd ON e.id_suc_destino = sd.id_sucursal
JOIN tipo_contenido te ON e.id_tipo_cont = te.id_tipo_cont
LEFT JOIN historial_estado he ON he.id_envio = e.id_envio
LEFT JOIN tipo_estado ts ON he.id_estado = ts.id_estado
ORDER BY he.fecha_hora DESC;


-- ---------------------------------------------------------------------
-- PASO 2: EXPLAIN ANTES de crear los índices
-- ---------------------------------------------------------------------
EXPLAIN SELECT
    e.id_envio, e.nro_tracking, e.fecha_recepcion, e.peso_kg,
    CONCAT(cr.nombre, ' ', cr.apellido) AS remitente,
    CONCAT(cd.nombre, ' ', cd.apellido) AS destinatario,
    so.nombre AS sucursal_origen,
    sd.nombre AS sucursal_destino,
    te.nombre AS tipo_contenido,
    ts.nombre AS estado_actual
FROM envio e
JOIN cliente cr ON e.id_remitente = cr.id_cliente
JOIN cliente cd ON e.id_destinatario = cd.id_cliente
JOIN sucursal so ON e.id_suc_origen = so.id_sucursal
JOIN sucursal sd ON e.id_suc_destino = sd.id_sucursal
JOIN tipo_contenido te ON e.id_tipo_cont = te.id_tipo_cont
LEFT JOIN historial_estado he ON he.id_envio = e.id_envio
LEFT JOIN tipo_estado ts ON he.id_estado = ts.id_estado
ORDER BY he.fecha_hora DESC;

/*
Resultado real obtenido (dataset actual: 1 fila en envio, 1 en historial_estado):

 table  type    possible_keys                                              key   rows  Extra
 -----  ------  ---------------------------------------------------------  ----  ----  --------------------------------------------
 e      ALL     fk_envio_tipo_contenido, fk_envio_remitente,                NULL  1     Using temporary; Using filesort
                 fk_envio_destinatario, fk_envio_suc_origen,
                 fk_envio_suc_destino
 he     ALL     fk_hist_envio                                               NULL  1     Using where; Using join buffer (flat, BNL join)
 te     eq_ref  PRIMARY                                                     PRI   1
 ts     eq_ref  PRIMARY                                                     PRI   1     Using where
 so     eq_ref  PRIMARY                                                     PRI   1
 sd     eq_ref  PRIMARY                                                     PRI   1
 cr     eq_ref  PRIMARY                                                     PRI   1
 cd     eq_ref  PRIMARY                                                     PRI   1

Columnas con type: ALL y por qué son un problema de rendimiento:

  - e (envio): la consulta no tiene WHERE sobre envio, por lo que SIEMPRE
    se recorre la tabla completa fila por fila (Full Table Scan). Con
    miles de envíos esto implica leer cada registro aunque no haga falta
    filtrar nada. Además aparece "Using temporary; Using filesort": como
    el ORDER BY usa una columna de una tabla unida por LEFT JOIN
    (he.fecha_hora), MySQL arma una tabla temporal con el resultado
    completo del JOIN antes de poder ordenarlo.

  - he (historial_estado): aunque ya existe un índice por la FK sobre
    id_envio (fk_hist_envio), aparece en "possible_keys" pero "key" es
    NULL: con un dataset de 1 fila el optimizador descarta el índice y
    resuelve el JOIN con "join buffer (BNL)" porque leer la tabla entera
    es más barato que recorrer el árbol B-Tree para una sola fila. Con
    miles de filas en historial_estado (varias por envío, una por cada
    cambio de estado), este mismo plan se convierte en un escaneo
    completo de historial_estado por cada fila de envio del JOIN,
    multiplicando el costo de forma cuadrática.
*/


-- ---------------------------------------------------------------------
-- PASO 3: Creación de índices de rendimiento
-- ---------------------------------------------------------------------

-- Índices en envio
CREATE INDEX idx_envio_remitente    ON envio(id_remitente);
CREATE INDEX idx_envio_destinatario ON envio(id_destinatario);
CREATE INDEX idx_envio_suc_origen   ON envio(id_suc_origen);
CREATE INDEX idx_envio_suc_destino  ON envio(id_suc_destino);
CREATE INDEX idx_envio_tipo_cont    ON envio(id_tipo_cont);

-- Índices en historial_estado
CREATE INDEX idx_hist_envio         ON historial_estado(id_envio);
CREATE INDEX idx_hist_estado        ON historial_estado(id_estado);

-- Índices en viaje_envio
CREATE INDEX idx_viaje_envio_envio  ON viaje_envio(id_envio);

-- Índices en viaje
CREATE INDEX idx_viaje_chofer       ON viaje(id_chofer);


-- ---------------------------------------------------------------------
-- PASO 4: EXPLAIN DESPUÉS de crear los índices
-- ---------------------------------------------------------------------
EXPLAIN SELECT
    e.id_envio, e.nro_tracking, e.fecha_recepcion, e.peso_kg,
    CONCAT(cr.nombre, ' ', cr.apellido) AS remitente,
    CONCAT(cd.nombre, ' ', cd.apellido) AS destinatario,
    so.nombre AS sucursal_origen,
    sd.nombre AS sucursal_destino,
    te.nombre AS tipo_contenido,
    ts.nombre AS estado_actual
FROM envio e
JOIN cliente cr ON e.id_remitente = cr.id_cliente
JOIN cliente cd ON e.id_destinatario = cd.id_cliente
JOIN sucursal so ON e.id_suc_origen = so.id_sucursal
JOIN sucursal sd ON e.id_suc_destino = sd.id_sucursal
JOIN tipo_contenido te ON e.id_tipo_cont = te.id_tipo_cont
LEFT JOIN historial_estado he ON he.id_envio = e.id_envio
LEFT JOIN tipo_estado ts ON he.id_estado = ts.id_estado
ORDER BY he.fecha_hora DESC;

/*
Resultado real obtenido (mismo dataset de 1 fila, después de crear los índices):

 table  type    possible_keys                                              key   rows  Extra
 -----  ------  ---------------------------------------------------------  ----  ----  --------------------------------------------
 e      ALL     idx_envio_remitente, idx_envio_destinatario,                NULL  1     Using temporary; Using filesort
                 idx_envio_suc_origen, idx_envio_suc_destino,
                 idx_envio_tipo_cont
 he     ALL     idx_hist_envio                                              NULL  1     Using where; Using join buffer (flat, BNL join)
 te / ts / so / sd / cr / cd: sin cambios (eq_ref por PRIMARY)

Comparación ANTES -> DESPUÉS:

  - Con el dataset actual (1 envío, 1 historial_estado) el plan NO cambia:
    el optimizador sigue eligiendo type: ALL para "e" y "he", porque para
    1 fila un Full Table Scan es más barato que atravesar un índice
    B-Tree. Esto es el comportamiento ESPERADO y correcto del optimizador
    para tablas pequeñas: el índice existe y aparece en "possible_keys",
    pero no conviene usarlo todavía.

  - El beneficio de estos índices se vuelve visible a partir de volúmenes
    de producción reales (miles de envíos, varias filas de
    historial_estado por envío). En ese escenario, para "he" el plan
    pasaría de:
        ANTES:    type: ALL,  rows: ~miles   (escanea TODO historial_estado
                  por cada fila de envio del JOIN)
    a:
        DESPUÉS:  type: ref,  rows: ~decenas (usa idx_hist_envio para
                  buscar solo las filas de historial cuyo id_envio
                  coincide con la fila actual de envio)

  - Además, estos índices son aprovechados directamente por OTRAS
    consultas del sistema que sí filtran por estas columnas con WHERE,
    donde el cambio de ALL a ref/range ya es relevante con el dataset
    actual y se vuelve crítico a medida que crecen las tablas:
      - ClienteModel::getEnviados() / getARecibir()
            -> WHERE e.id_remitente = ? / e.id_destinatario = ?
            -> idx_envio_remitente / idx_envio_destinatario
      - EmpleadoModel::getEnviosPendientes()
            -> WHERE e.id_suc_origen = ?
            -> idx_envio_suc_origen
      - viajes.php / viajes_realizados.php / getViajesHoy()
            -> filtros y joins sobre viaje.id_chofer
            -> idx_viaje_chofer
      - Despacho masivo (armar_viaje.php)
            -> JOIN viaje_envio ON viaje_envio.id_envio = envio.id_envio
            -> idx_viaje_envio_envio
*/
