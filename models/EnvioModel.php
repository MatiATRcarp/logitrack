<?php
class EnvioModel {

    public function __construct(private PDO $pdo) {}

    private function getEstadoId(string $nombre): int {
        $stmt = $this->pdo->prepare("SELECT id_estado FROM tipo_estado WHERE nombre = :nombre LIMIT 1");
        $stmt->execute([':nombre' => $nombre]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new \RuntimeException("No existe el estado requerido: {$nombre}.");
        }

        return (int) $id;
    }

    private function getUltimoEstado(int $idEnvio): array|false {
        $stmt = $this->pdo->prepare("
            SELECT he.id_estado, te.nombre
            FROM historial_estado he
            JOIN tipo_estado te ON he.id_estado = te.id_estado
            WHERE he.id_envio = :id_envio
            ORDER BY he.id_hist DESC
            LIMIT 1
        ");
        $stmt->execute([':id_envio' => $idEnvio]);
        return $stmt->fetch();
    }

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
     * Confirma la recepción de un envío insertando estado Entregado.
     * Solo procede si el último estado es En tránsito o Ingresado en sucursal destino.
     */
    public function confirmarRecepcion(int $idEnvio, int $idSucursalActual): bool {
        $ultimo = $this->getUltimoEstado($idEnvio);

        if (!$ultimo || !in_array($ultimo['nombre'], ['En tránsito', 'Ingresado en sucursal destino'], true)) {
            throw new \RuntimeException(
                'El envío no está en un estado que permita confirmar la recepción (debe ser En tránsito o En sucursal destino).'
            );
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
                VALUES (:id_envio, :id_estado, NOW(), :id_sucursal)
            ");
            $stmt->bindValue(':id_envio',    $idEnvio,          PDO::PARAM_INT);
            $stmt->bindValue(':id_estado',   $this->getEstadoId('Entregado'), PDO::PARAM_INT);
            $stmt->bindValue(':id_sucursal', $idSucursalActual, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("EnvioModel::confirmarRecepcion — " . $e->getMessage());
            throw $e;
        }
    }

    public function verificarEnSucursalDestino(int $idEnvio, int $idSucursalDestino, int $idSucursalOrigen, bool $integridadOk): bool {
        $ultimo = $this->getUltimoEstado($idEnvio);

        if (!$ultimo || $ultimo['nombre'] !== 'En tránsito') {
            throw new \RuntimeException('El envío debe estar En tránsito para verificarlo al descargar del camión.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($integridadOk) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
                    VALUES (:id_envio, :id_estado, NOW(), :id_sucursal)
                ");
                $stmt->execute([
                    ':id_envio' => $idEnvio,
                    ':id_estado' => $this->getEstadoId('Ingresado en sucursal destino'),
                    ':id_sucursal' => $idSucursalDestino,
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
                    VALUES (:id_envio, :id_estado, NOW(), :id_sucursal)
                ");
                $stmt->execute([
                    ':id_envio' => $idEnvio,
                    ':id_estado' => $this->getEstadoId('Con incidencias'),
                    ':id_sucursal' => $idSucursalDestino,
                ]);
                $stmt->execute([
                    ':id_envio' => $idEnvio,
                    ':id_estado' => $this->getEstadoId('Devuelto al remitente'),
                    ':id_sucursal' => $idSucursalOrigen,
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("EnvioModel::verificarEnSucursalDestino — " . $e->getMessage());
            throw $e;
        }
    }

    public function cancelarPorCliente(int $idEnvio, int $idCliente): bool {
        $stmt = $this->pdo->prepare("
            SELECT id_envio, id_suc_origen
            FROM envio
            WHERE id_envio = :id_envio
              AND id_remitente = :id_cliente
            LIMIT 1
        ");
        $stmt->execute([':id_envio' => $idEnvio, ':id_cliente' => $idCliente]);
        $envio = $stmt->fetch();

        if (!$envio) {
            throw new \RuntimeException('No tenés permiso para cancelar este envío.');
        }

        $ultimo = $this->getUltimoEstado($idEnvio);
        if (!$ultimo || $ultimo['nombre'] !== 'Pendiente') {
            throw new \RuntimeException('Solo podés cancelar pedidos que todavía están pendientes.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
            VALUES (:id_envio, :id_estado, NOW(), :id_sucursal)
        ");
        $stmt->execute([
            ':id_envio' => $idEnvio,
            ':id_estado' => $this->getEstadoId('Cancelado'),
            ':id_sucursal' => (int) $envio['id_suc_origen'],
        ]);

        return $stmt->rowCount() > 0;
    }
}
