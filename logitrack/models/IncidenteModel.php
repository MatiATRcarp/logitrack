<?php
class IncidenteModel {

    public function __construct(private PDO $pdo) {}

    public function reportar(int $id_viaje, string $tipo, string $descripcion): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO incidente (id_viaje, tipo_incidente, descripcion, fecha_hora)
            VALUES (:viaje, :tipo, :desc, NOW())
        ");
        $stmt->execute([
            ':viaje' => $id_viaje,
            ':tipo'  => $tipo,
            ':desc'  => $descripcion,
        ]);
    }
}
