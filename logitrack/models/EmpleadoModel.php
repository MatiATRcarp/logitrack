<?php
class EmpleadoModel {

    public function __construct(private PDO $pdo) {}

    public function getEnviosPendientes(?int $id_sucursal, int $limite = 50): array {
        $filtro = $id_sucursal ? "AND e.id_suc_origen = :suc" : "";

        $stmt = $this->pdo->prepare("
            SELECT e.id_envio, e.nro_tracking, e.peso_kg,
                   tc.nombre AS tipo_contenido,
                   c_rem.nombre  AS remitente,  c_rem.apellido  AS rem_apellido,
                   c_dest.nombre AS destinatario, c_dest.apellido AS dest_apellido,
                   s_dest.nombre AS sucursal_destino,
                   te.nombre AS estado, he.fecha_hora
            FROM   envio e
            JOIN   tipo_contenido   tc     ON e.id_tipo_cont    = tc.id_tipo_cont
            JOIN   cliente          c_rem  ON e.id_remitente    = c_rem.id_cliente
            JOIN   cliente          c_dest ON e.id_destinatario = c_dest.id_cliente
            JOIN   sucursal         s_dest ON e.id_suc_destino  = s_dest.id_sucursal
            JOIN   historial_estado he     ON he.id_hist = (
                SELECT MAX(h2.id_hist) FROM historial_estado h2 WHERE h2.id_envio = e.id_envio
            )
            JOIN   tipo_estado      te     ON he.id_estado = te.id_estado
            WHERE  te.nombre = 'Pendiente'
            $filtro
            ORDER  BY he.fecha_hora DESC
            LIMIT  :limite
        ");

        if ($id_sucursal) {
            $stmt->bindValue(':suc', $id_sucursal, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getViajesHoy(): int {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS c FROM viaje WHERE DATE(fecha_salida) = CURDATE()"
        );
        $stmt->execute();
        return (int) $stmt->fetch()['c'];
    }

    /**
     * Envíos en almacén de origen (estado actual = 'Pendiente'), disponibles
     * para asignar a un viaje.
     */
    public function getEnviosEnAlmacenOrigen(): array {
        $stmt = $this->pdo->query("
            SELECT e.id_envio, e.nro_tracking, e.peso_kg,
                   tc.nombre AS tipo_contenido,
                   CONCAT(cd.nombre, ' ', cd.apellido) AS destinatario,
                   so.nombre AS sucursal_origen,
                   sd.nombre AS sucursal_destino
            FROM   envio e
            JOIN   tipo_contenido tc ON e.id_tipo_cont    = tc.id_tipo_cont
            JOIN   cliente  cd ON e.id_destinatario = cd.id_cliente
            JOIN   sucursal so ON e.id_suc_origen   = so.id_sucursal
            JOIN   sucursal sd ON e.id_suc_destino  = sd.id_sucursal
            JOIN   historial_estado he ON he.id_hist = (
                SELECT MAX(h2.id_hist) FROM historial_estado h2 WHERE h2.id_envio = e.id_envio
            )
            JOIN   tipo_estado te ON he.id_estado = te.id_estado
            WHERE  te.nombre = 'Pendiente'
            ORDER  BY e.fecha_recepcion ASC
        ");
        return $stmt->fetchAll();
    }

    public function getPesoTotalEnvios(array $ids_envio): float {
        if (empty($ids_envio)) {
            return 0.0;
        }
        $placeholders = implode(',', array_fill(0, count($ids_envio), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(peso_kg), 0) AS total FROM envio WHERE id_envio IN ($placeholders)"
        );
        $stmt->execute($ids_envio);
        return (float) $stmt->fetch()['total'];
    }

    public function getVehiculo(string $patente): array|false {
        $stmt = $this->pdo->prepare("
            SELECT v.patente, tv.capacidad_kg_max, tv.requiere_licencia_especial
            FROM   vehiculo v
            JOIN   tipo_vehiculo tv ON v.id_tipo_veh = tv.id_tipo_veh
            WHERE  v.patente = :patente
        ");
        $stmt->execute([':patente' => $patente]);
        return $stmt->fetch();
    }

    public function getChofer(int $id_chofer): array|false {
        $stmt = $this->pdo->prepare("SELECT id_chofer, tipo_licencia FROM chofer WHERE id_chofer = :id");
        $stmt->execute([':id' => $id_chofer]);
        return $stmt->fetch();
    }

    /**
     * Crea un viaje y asigna los envíos seleccionados dentro de una transacción.
     * Por cada envío: lo vincula al viaje (viaje_envio) y registra su nuevo
     * estado "En Tránsito" en historial_estado.
     * Devuelve el id_viaje generado. Lanza PDOException si algo falla
     * (hace rollback automático, p.ej. si el trigger de chofer ocupado se dispara).
     */
    public function crearViajeConEnvios(array $data): int {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO viaje (fecha_salida, fecha_llegada_est, patente, id_chofer)
                VALUES (:salida, :llegada, :patente, :chofer)
            ");
            $stmt->execute([
                ':salida'  => $data['fecha_salida'],
                ':llegada' => $data['fecha_llegada_est'],
                ':patente' => $data['patente'],
                ':chofer'  => $data['id_chofer'],
            ]);
            $id_viaje = (int) $this->pdo->lastInsertId();

            $stmtViajeEnvio = $this->pdo->prepare(
                "INSERT INTO viaje_envio (id_viaje, id_envio) VALUES (:viaje, :envio)"
            );
            $stmtSucOrigen = $this->pdo->prepare(
                "SELECT id_suc_origen FROM envio WHERE id_envio = :envio"
            );
            $stmtHistorial = $this->pdo->prepare("
                INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
                VALUES (:envio, (SELECT id_estado FROM tipo_estado WHERE nombre = 'En Tránsito' LIMIT 1), NOW(), :suc)
            ");

            foreach ($data['envios'] as $id_envio) {
                $stmtViajeEnvio->execute([':viaje' => $id_viaje, ':envio' => $id_envio]);

                $stmtSucOrigen->execute([':envio' => $id_envio]);
                $id_suc_origen = $stmtSucOrigen->fetchColumn();

                $stmtHistorial->execute([':envio' => $id_envio, ':suc' => $id_suc_origen]);
            }

            $this->pdo->commit();
            return $id_viaje;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("EmpleadoModel::crearViajeConEnvios — " . $e->getMessage());
            throw $e;
        }
    }
}
