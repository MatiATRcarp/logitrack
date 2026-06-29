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

            $id_envio = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare("
                INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
                VALUES (:envio,
                        (SELECT id_estado FROM tipo_estado WHERE nombre = 'Pendiente' LIMIT 1),
                        NOW(),
                        :suc)
            ")->execute([':envio' => $id_envio, ':suc' => $data['id_suc_origen']]);

            $this->pdo->commit();
            return $nro_tracking;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("EnvioModel::crear — " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Confirma la recepción de un envío insertando estado Entregado (id_estado=4).
     * Solo procede si el último estado es 2 (En tránsito) o 3 (Ingresado en sucursal destino).
     */
    public function confirmarRecepcion(int $idEnvio, int $idSucursalActual): bool {
        $stmt = $this->pdo->prepare("
            SELECT id_estado FROM historial_estado
            WHERE id_envio = :id_envio
            ORDER BY id_hist DESC
            LIMIT 1
        ");
        $stmt->execute([':id_envio' => $idEnvio]);
        $ultimo = $stmt->fetch();

        if (!$ultimo || !in_array((int) $ultimo['id_estado'], [2, 3])) {
            throw new \RuntimeException(
                'El envío no está en un estado que permita confirmar la recepción (debe ser En tránsito o En sucursal destino).'
            );
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
                VALUES (:id_envio, 4, NOW(), :id_sucursal)
            ");
            $stmt->bindValue(':id_envio',    $idEnvio,          PDO::PARAM_INT);
            $stmt->bindValue(':id_sucursal', $idSucursalActual, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("EnvioModel::confirmarRecepcion — " . $e->getMessage());
            throw $e;
        }
    }
}
