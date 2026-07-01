<?php
// api/tracking.php — Endpoint JSON: posición en tiempo real de viajes activos
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$filtrarCliente = isset($_GET['cliente']) && $_GET['cliente'] === '1';

// Solo clientes pueden usar el filtro por cliente
if ($filtrarCliente && $_SESSION['usuario_rol'] !== 'cliente') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// Roles sin acceso al endpoint completo
$rolesPermitidos = ['admin', 'empleado', 'chofer', 'cliente'];
if (!in_array($_SESSION['usuario_rol'], $rolesPermitidos)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

try {
    // Subquery: toma el id_envio mínimo por viaje para evitar GROUP BY ambiguo
    // con ONLY_FULL_GROUP_BY de MySQL 5.7+
    $sql = "
        SELECT
            v.id_viaje,
            v.fecha_salida,
            v.fecha_llegada_est,
            v.patente,
            CONCAT(ch.nombre, ' ', ch.apellido)   AS chofer,
            so.nombre                              AS sucursal_origen,
            so.latitud                             AS lat_origen,
            so.longitud                            AS lng_origen,
            sd.nombre                              AS sucursal_destino,
            sd.latitud                             AS lat_destino,
            sd.longitud                            AS lng_destino,
            (SELECT COUNT(*) FROM viaje_envio vc WHERE vc.id_viaje = v.id_viaje) AS total_paquetes
        FROM viaje v
        JOIN chofer ch ON v.id_chofer = ch.id_chofer
        JOIN (
            SELECT id_viaje, MIN(id_envio) AS id_envio
            FROM   viaje_envio
            GROUP  BY id_viaje
        ) primer_envio ON primer_envio.id_viaje = v.id_viaje
        JOIN envio    e  ON e.id_envio         = primer_envio.id_envio
        JOIN sucursal so ON e.id_suc_origen    = so.id_sucursal
        JOIN sucursal sd ON e.id_suc_destino   = sd.id_sucursal
        WHERE v.fecha_salida <= NOW()
          AND so.latitud IS NOT NULL
          AND sd.latitud IS NOT NULL
          AND NOT EXISTS (
              SELECT 1 FROM historial_estado he2
              JOIN   tipo_estado te2 ON he2.id_estado = te2.id_estado
              JOIN   viaje_envio ve2 ON he2.id_envio = ve2.id_envio
              WHERE  ve2.id_viaje = v.id_viaje
                AND  te2.nombre IN ('Entregado', 'Cancelado', 'Devuelto al remitente')
          )
    ";

    $params = [];

    if ($filtrarCliente) {
        $sql .= "
          AND EXISTS (
              SELECT 1
              FROM   viaje_envio ve2
              JOIN   envio e2 ON e2.id_envio = ve2.id_envio
              WHERE  ve2.id_viaje = v.id_viaje
                AND (
                    e2.id_remitente IN (
                        SELECT c2.id_cliente FROM cliente c2
                        JOIN   usuario u2 ON c2.dni = u2.dni
                        WHERE  u2.id_usuario = :uid1
                    )
                 OR e2.id_destinatario IN (
                        SELECT c2.id_cliente FROM cliente c2
                        JOIN   usuario u2 ON c2.dni = u2.dni
                        WHERE  u2.id_usuario = :uid2
                    )
                )
          )
        ";
        $params[':uid1'] = (int) $_SESSION['usuario_id'];
        $params[':uid2'] = (int) $_SESSION['usuario_id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultado = [];
    foreach ($viajes as $v) {
        $salida      = strtotime($v['fecha_salida']);
        $llegada     = strtotime($v['fecha_llegada_est']);
        $ahora       = time();
        $duracion    = $llegada - $salida;
        $transcurrido = $ahora - $salida;
        $progreso    = $duracion > 0 ? min(1, max(0, $transcurrido / $duracion)) : 0;

        $lat_actual = (float) $v['lat_origen'] + ((float) $v['lat_destino'] - (float) $v['lat_origen']) * $progreso;
        $lng_actual = (float) $v['lng_origen'] + ((float) $v['lng_destino'] - (float) $v['lng_origen']) * $progreso;

        $resultado[] = [
            'id_viaje'          => (int)   $v['id_viaje'],
            'patente'           =>          $v['patente'],
            'chofer'            =>          $v['chofer'],
            'sucursal_origen'   =>          $v['sucursal_origen'],
            'sucursal_destino'  =>          $v['sucursal_destino'],
            'lat_actual'        => round($lat_actual, 6),
            'lng_actual'        => round($lng_actual, 6),
            'lat_origen'        => (float) $v['lat_origen'],
            'lng_origen'        => (float) $v['lng_origen'],
            'lat_destino'       => (float) $v['lat_destino'],
            'lng_destino'       => (float) $v['lng_destino'],
            'progreso_pct'      => round($progreso * 100, 1),
            'total_paquetes'    => (int)   $v['total_paquetes'],
            'fecha_salida'      =>          $v['fecha_salida'],
            'fecha_llegada_est' =>          $v['fecha_llegada_est'],
        ];
    }

    echo json_encode($resultado);

} catch (PDOException $e) {
    error_log("api/tracking.php — " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
