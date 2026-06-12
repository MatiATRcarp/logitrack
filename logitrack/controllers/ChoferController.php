<?php
require_once __DIR__ . '/../models/ChoferModel.php';
require_once __DIR__ . '/../models/IncidenteModel.php';

class ChoferController {

    private ChoferModel    $choferModel;
    private IncidenteModel $incidenteModel;

    public function __construct(PDO $pdo) {
        $this->choferModel    = new ChoferModel($pdo);
        $this->incidenteModel = new IncidenteModel($pdo);
    }

    public function getDashboardData(int $usuario_id): array {
        $chofer       = $this->choferModel->getPerfil($usuario_id);
        $viaje_activo = null;
        $envios_carga = [];

        if ($chofer) {
            $viaje_activo = $this->choferModel->getViajeActivo($chofer['id_chofer']);
            if ($viaje_activo) {
                $envios_carga = $this->choferModel->getCargaViaje($viaje_activo['id_viaje']);
            }
        }

        return [
            'chofer'       => $chofer,
            'viaje_activo' => $viaje_activo,
            'envios_carga' => $envios_carga,
        ];
    }

    public function getPerfilData(int $usuario_id): array {
        $chofer = $this->choferModel->getPerfil($usuario_id);
        $viajes = $chofer ? $this->choferModel->getViajesRealizados($chofer['id_chofer']) : [];

        return [
            'chofer' => $chofer,
            'viajes' => $viajes,
        ];
    }

    public function reportarIncidente(int $id_viaje, array $post): array {
        $tipo        = trim($post['tipo_incidente'] ?? '');
        $descripcion = trim($post['descripcion']    ?? '');

        if (empty($tipo) || empty($descripcion)) {
            return ['ok' => false, 'mensaje' => 'Completá todos los campos.'];
        }
        if (strlen($descripcion) > 200) {
            return ['ok' => false, 'mensaje' => 'La descripción no puede superar 200 caracteres.'];
        }

        try {
            $this->incidenteModel->reportar($id_viaje, $tipo, $descripcion);
            error_log("INCIDENTE · Viaje: $id_viaje · Tipo: $tipo");
            return ['ok' => true, 'mensaje' => 'Incidente reportado correctamente.'];
        } catch (PDOException $e) {
            error_log("ChoferController::reportarIncidente — " . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'Error al guardar el incidente.'];
        }
    }
}
