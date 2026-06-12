<?php
class AdminModel {

    public function __construct(private PDO $pdo) {}

    public function getMetricas(): array {
        $metricas = [];

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total FROM envio
             WHERE MONTH(fecha_recepcion) = MONTH(NOW())
               AND YEAR(fecha_recepcion)  = YEAR(NOW())"
        );
        $stmt->execute();
        $metricas['envios_mes'] = (int) $stmt->fetch()['total'];

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT patente) AS total FROM viaje
             WHERE fecha_salida <= NOW()
               AND (fecha_llegada_est >= NOW() OR fecha_llegada_est IS NULL)"
        );
        $stmt->execute();
        $metricas['vehiculos_activos'] = (int) $stmt->fetch()['total'];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM chofer");
        $stmt->execute();
        $metricas['total_choferes'] = (int) $stmt->fetch()['total'];

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total FROM incidente
             WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute();
        $metricas['incidentes'] = (int) $stmt->fetch()['total'];

        return $metricas;
    }

    public function getViajesRecientes(int $limite = 10): array {
        $stmt = $this->pdo->prepare("
            SELECT v.id_viaje, v.fecha_salida, v.fecha_llegada_est, v.patente,
                   c.nombre AS chofer_nombre, c.apellido AS chofer_apellido,
                   s_orig.nombre AS origen
            FROM   viaje v
            JOIN   chofer   c       ON v.id_chofer    = c.id_chofer
            JOIN   vehiculo vh      ON v.patente       = vh.patente
            JOIN   sucursal s_orig  ON vh.id_sucursal  = s_orig.id_sucursal
            ORDER  BY v.fecha_salida DESC
            LIMIT  :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
