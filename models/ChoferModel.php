<?php
class ChoferModel {

    public function __construct(private PDO $pdo) {}

    public function getPerfil(int $usuario_id): array|false {
        $stmt = $this->pdo->prepare("
            SELECT c.id_chofer, c.dni, c.nombre, c.apellido, c.tipo_licencia, c.legajo
            FROM   chofer  c
            JOIN   usuario u ON c.dni = u.dni
            WHERE  u.id_usuario = :id
            LIMIT  1
        ");
        $stmt->execute([':id' => $usuario_id]);
        return $stmt->fetch();
    }

    public function getViajeActivo(int $id_chofer): array|false {
        $stmt = $this->pdo->prepare("
            SELECT v.id_viaje, v.fecha_salida, v.fecha_llegada_est, v.patente,
                   s_orig.nombre AS origen
            FROM   viaje    v
            JOIN   vehiculo  vh     ON v.patente      = vh.patente
            JOIN   sucursal  s_orig ON vh.id_sucursal = s_orig.id_sucursal
            WHERE  v.id_chofer = :id_chofer
            AND    (v.fecha_llegada_est IS NULL OR v.fecha_llegada_est >= NOW())
            ORDER  BY v.fecha_salida ASC
            LIMIT  1
        ");
        $stmt->execute([':id_chofer' => $id_chofer]);
        return $stmt->fetch();
    }

    public function getCargaViaje(int $id_viaje): array {
        $stmt = $this->pdo->prepare("
            SELECT e.id_envio, e.nro_tracking, e.peso_kg,
                   tc.nombre      AS tipo_contenido,
                   c_dest.nombre  AS dest_nombre, c_dest.apellido AS dest_apellido,
                   s_dest.nombre  AS sucursal_destino,
                   he_last.id_estado,
                   te.nombre      AS estado_nombre
            FROM   viaje_envio    ve
            JOIN   envio          e      ON ve.id_envio       = e.id_envio
            JOIN   tipo_contenido tc     ON e.id_tipo_cont    = tc.id_tipo_cont
            JOIN   cliente        c_dest ON e.id_destinatario = c_dest.id_cliente
            JOIN   sucursal       s_dest ON e.id_suc_destino  = s_dest.id_sucursal
            LEFT   JOIN historial_estado he_last ON he_last.id_hist = (
                SELECT MAX(h2.id_hist) FROM historial_estado h2 WHERE h2.id_envio = e.id_envio
            )
            LEFT   JOIN tipo_estado te ON he_last.id_estado = te.id_estado
            WHERE  ve.id_viaje = :id_viaje
        ");
        $stmt->execute([':id_viaje' => $id_viaje]);
        return $stmt->fetchAll();
    }

    /**
     * Viajes ya finalizados del chofer, con el detalle de cada envío transportado.
     */
    public function getViajesRealizados(int $id_chofer): array {
        $stmt = $this->pdo->prepare("
            SELECT v.id_viaje, v.fecha_salida, v.fecha_llegada_est, v.patente,
                   s_orig.nombre AS sucursal_origen,
                   s_dest.nombre AS sucursal_destino,
                   e.nro_tracking, e.peso_kg,
                   tc.nombre AS tipo_contenido,
                   c_dest.nombre AS dest_nombre, c_dest.apellido AS dest_apellido
            FROM   viaje    v
            JOIN   vehiculo vh     ON v.patente     = vh.patente
            JOIN   sucursal s_orig ON vh.id_sucursal = s_orig.id_sucursal
            LEFT   JOIN viaje_envio   ve     ON ve.id_viaje      = v.id_viaje
            LEFT   JOIN envio         e      ON ve.id_envio      = e.id_envio
            LEFT   JOIN sucursal      s_dest ON e.id_suc_destino = s_dest.id_sucursal
            LEFT   JOIN cliente       c_dest ON e.id_destinatario = c_dest.id_cliente
            LEFT   JOIN tipo_contenido tc    ON e.id_tipo_cont   = tc.id_tipo_cont
            WHERE  v.id_chofer = :id_chofer
              AND  v.fecha_llegada_est IS NOT NULL
              AND  v.fecha_llegada_est < NOW()
            ORDER  BY v.fecha_salida DESC, e.nro_tracking
        ");
        $stmt->execute([':id_chofer' => $id_chofer]);
        return $stmt->fetchAll();
    }
}
