<?php
require_once __DIR__ . '/../models/ChoferModel.php';
require_once __DIR__ . '/../models/IncidenteModel.php';
require_once __DIR__ . '/../models/EnvioModel.php';

class ChoferController {

    private ChoferModel    $choferModel;
    private IncidenteModel $incidenteModel;
    private EnvioModel     $envioModel;
    private PDO            $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo            = $pdo;
        $this->choferModel    = new ChoferModel($pdo);
        $this->incidenteModel = new IncidenteModel($pdo);
        $this->envioModel     = new EnvioModel($pdo);
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

    public function confirmarRecepcion(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /logitrack/views/chofer/dashboard.php');
            exit;
        }

        $idEnvio = (int) ($_POST['id_envio'] ?? 0);
        if ($idEnvio === 0) {
            header('Location: /logitrack/views/chofer/dashboard.php?error=' . urlencode('ID de envío inválido.'));
            exit;
        }

        $chofer = $this->choferModel->getPerfil((int) $_SESSION['usuario_id']);
        if (!$chofer) {
            header('Location: /logitrack/views/chofer/dashboard.php?error=' . urlencode('Perfil de chofer no encontrado.'));
            exit;
        }

        // Verificar que el envío pertenece a un viaje del chofer y obtener id_suc_destino
        $stmt = $this->pdo->prepare("
            SELECT e.id_envio, e.id_suc_destino
            FROM   viaje_envio ve
            JOIN   viaje       v  ON ve.id_viaje  = v.id_viaje
            JOIN   envio       e  ON ve.id_envio  = e.id_envio
            WHERE  ve.id_envio  = :id_envio
              AND  v.id_chofer  = :id_chofer
            LIMIT  1
        ");
        $stmt->bindValue(':id_envio',  $idEnvio,                    PDO::PARAM_INT);
        $stmt->bindValue(':id_chofer', (int) $chofer['id_chofer'],  PDO::PARAM_INT);
        $stmt->execute();
        $envio = $stmt->fetch();

        if (!$envio) {
            header('Location: /logitrack/views/chofer/dashboard.php?error=' . urlencode('No tenés permiso para marcar este envío como entregado.'));
            exit;
        }

        try {
            $this->envioModel->confirmarRecepcion($idEnvio, (int) $envio['id_suc_destino']);
            header('Location: /logitrack/views/chofer/dashboard.php?ok=' . urlencode('Envío marcado como entregado.'));
        } catch (\Exception $e) {
            error_log("ChoferController::confirmarRecepcion — " . $e->getMessage());
            header('Location: /logitrack/views/chofer/dashboard.php?error=' . urlencode($e->getMessage()));
        }
        exit;
    }
}

// ─── Punto de entrada directo (POST desde formularios) ────────────────────────
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../middleware/auth.php';
    requireRol(['chofer']);

    $ctrl   = new ChoferController($pdo);
    $action = $_GET['action'] ?? '';

    if ($action === 'confirmarRecepcion') {
        $ctrl->confirmarRecepcion();
    } else {
        header('Location: /logitrack/views/chofer/dashboard.php');
        exit;
    }
}
