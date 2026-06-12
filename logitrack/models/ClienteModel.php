<?php
class ClienteModel {

    public function __construct(private PDO $pdo) {}

    public function getPerfil(int $usuario_id): array|false {
        $stmt = $this->pdo->prepare("
            SELECT c.id_cliente, c.nombre, c.apellido, c.dni
            FROM   cliente c
            JOIN   usuario u ON c.dni = u.dni
            WHERE  u.id_usuario = :id LIMIT 1
        ");
        $stmt->execute([':id' => $usuario_id]);
        return $stmt->fetch();
    }

    public function getEnviados(int $usuario_id, int $limite = 20): array {
        $stmt = $this->pdo->prepare("
            SELECT e.id_envio, e.nro_tracking, e.peso_kg, e.fecha_recepcion,
                   c_dest.nombre AS dest_nombre, c_dest.apellido AS dest_apellido,
                   s_dest.nombre AS sucursal_destino,
                   te.nombre AS estado
            FROM   envio e
            JOIN   cliente  c_dest  ON e.id_destinatario = c_dest.id_cliente
            JOIN   sucursal s_dest  ON e.id_suc_destino  = s_dest.id_sucursal
            JOIN   historial_estado he ON he.id_hist = (
                SELECT MAX(h2.id_hist) FROM historial_estado h2 WHERE h2.id_envio = e.id_envio
            )
            JOIN   tipo_estado      te ON he.id_estado = te.id_estado
            WHERE  e.id_remitente IN (
                SELECT c2.id_cliente FROM cliente c2
                JOIN   usuario u2 ON c2.dni = u2.dni
                WHERE  u2.id_usuario = :usuario_id
            )
            ORDER  BY e.fecha_recepcion DESC
            LIMIT  :limite
        ");
        $stmt->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->bindValue(':limite',     $limite,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getARecibir(int $usuario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT e.id_envio, e.nro_tracking, e.peso_kg,
                   c_rem.nombre AS rem_nombre, c_rem.apellido AS rem_apellido,
                   s_orig.nombre AS sucursal_origen,
                   s_dest.nombre AS sucursal_destino,
                   te.nombre AS estado, he.fecha_hora AS ultimo_evento
            FROM   envio e
            JOIN   cliente  c_rem   ON e.id_remitente   = c_rem.id_cliente
            JOIN   sucursal s_orig  ON e.id_suc_origen  = s_orig.id_sucursal
            JOIN   sucursal s_dest  ON e.id_suc_destino = s_dest.id_sucursal
            JOIN   historial_estado he ON he.id_hist = (
                SELECT MAX(h2.id_hist) FROM historial_estado h2 WHERE h2.id_envio = e.id_envio
            )
            JOIN   tipo_estado      te ON he.id_estado = te.id_estado
            WHERE  e.id_destinatario IN (
                SELECT c2.id_cliente FROM cliente c2
                JOIN   usuario u2 ON c2.dni = u2.dni
                WHERE  u2.id_usuario = :usuario_id
            )
            AND    te.nombre NOT IN ('Entregado', 'Cancelado', 'Devuelto al remitente')
            ORDER  BY he.fecha_hora DESC
        ");
        $stmt->execute([':usuario_id' => $usuario_id]);
        return $stmt->fetchAll();
    }
}
