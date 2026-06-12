<?php
class EnvioModel {

    public function __construct(private PDO $pdo) {}

    public function getFormData(): array {
        return [
            'tipos_contenido' => $this->pdo->query(
                "SELECT id_tipo_cont, nombre FROM tipo_contenido"
            )->fetchAll(),
            'sucursales' => $this->pdo->query(
                "SELECT id_sucursal, nombre FROM sucursal ORDER BY nombre"
            )->fetchAll(),
            'clientes' => $this->pdo->query(
                "SELECT id_cliente, dni, nombre, apellido FROM cliente ORDER BY apellido"
            )->fetchAll(),
        ];
    }

    /**
     * Crea un envío y su estado inicial dentro de una transacción.
     * Devuelve el nro_tracking generado.
     * Lanza PDOException si algo falla (hace rollback automático).
     */
    public function crear(array $data): string {
        $nro_tracking = 'TRK-' . strtoupper(substr(md5(uniqid((string) rand(), true)), 0, 8));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO envio
                    (nro_tracking, fecha_recepcion, peso_kg, id_tipo_cont,
                     id_remitente, id_destinatario, id_suc_origen, id_suc_destino)
                VALUES
                    (:nro, NOW(), :peso, :tipo, :rem, :dest, :orig, :destino)
            ");
            $stmt->execute([
                ':nro'     => $nro_tracking,
                ':peso'    => $data['peso_kg'],
                ':tipo'    => $data['id_tipo_cont'],
                ':rem'     => $data['id_remitente'],
                ':dest'    => $data['id_destinatario'],
                ':orig'    => $data['id_suc_origen'],
                ':destino' => $data['id_suc_destino'],
            ]);

            $this->pdo->commit();
            return $nro_tracking;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("EnvioModel::crear — " . $e->getMessage());
            throw $e;
        }
    }
}
