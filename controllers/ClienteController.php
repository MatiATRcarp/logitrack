<?php
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/EnvioModel.php';

class ClienteController {

    private ClienteModel $model;
    private EnvioModel   $envioModel;
    private PDO          $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo        = $pdo;
        $this->model      = new ClienteModel($pdo);
        $this->envioModel = new EnvioModel($pdo);
    }

    public function getPerfil(int $usuario_id): array|false {
        return $this->model->getPerfil($usuario_id);
    }

    public function getDashboardData(int $usuario_id): array {
        $cliente  = $this->model->getPerfil($usuario_id);
        $enviados = [];

        if ($cliente) {
            $enviados = $this->model->getEnviados($usuario_id);
        }

        return [
            'cliente'  => $cliente,
            'enviados' => $enviados,
        ];
    }

    public function getFormData(): array {
        return $this->envioModel->getFormData();
    }

    public function solicitarEnvio(int $id_cliente, array $post): array {
        $id_destinatario = (int) ($post['id_destinatario'] ?? 0);
        $id_tipo_cont    = (int) ($post['id_tipo_cont']    ?? 0);
        $id_suc_origen   = (int) ($post['id_suc_origen']   ?? 0);
        $id_suc_destino  = (int) ($post['id_suc_destino']  ?? 0);
        $peso_kg         = filter_var($post['peso_kg'] ?? '', FILTER_VALIDATE_FLOAT);

        $errores = [];
        if ($id_destinatario === 0)             $errores[] = 'Seleccioná el destinatario.';
        if ($id_destinatario === $id_cliente)   $errores[] = 'No podés enviarte a vos mismo.';
        if ($id_tipo_cont === 0)                $errores[] = 'Seleccioná el tipo de contenido.';
        if ($id_suc_origen === 0)               $errores[] = 'Seleccioná la sucursal de origen.';
        if ($id_suc_destino === 0)              $errores[] = 'Seleccioná la sucursal de destino.';
        if ($id_suc_origen === $id_suc_destino) $errores[] = 'Origen y destino no pueden ser la misma sucursal.';
        if ($peso_kg === false || $peso_kg <= 0) $errores[] = 'El peso debe ser un número positivo.';

        if (!empty($errores)) {
            return ['ok' => false, 'mensaje' => implode(' ', $errores)];
        }

        try {
            $nro = $this->envioModel->crear([
                'id_remitente'    => $id_cliente,
                'id_destinatario' => $id_destinatario,
                'id_tipo_cont'    => $id_tipo_cont,
                'id_suc_origen'   => $id_suc_origen,
                'id_suc_destino'  => $id_suc_destino,
                'peso_kg'         => $peso_kg,
            ]);
            return ['ok' => true, 'nro_tracking' => $nro, 'mensaje' => "Envío registrado. Tracking: $nro"];
        } catch (PDOException $e) {
            error_log("ClienteController::solicitarEnvio — " . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'Error al registrar el envío. Intentá nuevamente.'];
        }
    }

    public function confirmarRecepcion(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /logitrack/views/cliente/a_recibir.php');
            exit;
        }

        $idEnvio = (int) ($_POST['id_envio'] ?? 0);
        if ($idEnvio === 0) {
            header('Location: /logitrack/views/cliente/a_recibir.php?error=' . urlencode('ID de envío inválido.'));
            exit;
        }

        // Verificar que el envío pertenece al cliente logueado (es el destinatario)
        $stmt = $this->pdo->prepare("
            SELECT e.id_envio, e.id_suc_destino
            FROM   envio   e
            JOIN   cliente c  ON e.id_destinatario = c.id_cliente
            JOIN   usuario u  ON c.dni             = u.dni
            WHERE  e.id_envio   = :id_envio
              AND  u.id_usuario = :usuario_id
            LIMIT  1
        ");
        $stmt->bindValue(':id_envio',    $idEnvio,                        PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id',  (int) $_SESSION['usuario_id'],   PDO::PARAM_INT);
        $stmt->execute();
        $envio = $stmt->fetch();

        if (!$envio) {
            header('Location: /logitrack/views/cliente/a_recibir.php?error=' . urlencode('No tenés permiso para confirmar este envío.'));
            exit;
        }

        try {
            $this->envioModel->confirmarRecepcion($idEnvio, (int) $envio['id_suc_destino']);
            header('Location: /logitrack/views/cliente/a_recibir.php?ok=' . urlencode('¡Recepción confirmada correctamente!'));
        } catch (\Exception $e) {
            error_log("ClienteController::confirmarRecepcion — " . $e->getMessage());
            header('Location: /logitrack/views/cliente/a_recibir.php?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public function cancelarEnvio(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /logitrack/views/cliente/dashboard.php');
            exit;
        }

        $idEnvio = (int) ($_POST['id_envio'] ?? 0);
        if ($idEnvio === 0) {
            header('Location: /logitrack/views/cliente/dashboard.php?error=' . urlencode('ID de envío inválido.'));
            exit;
        }

        $cliente = $this->model->getPerfil((int) $_SESSION['usuario_id']);
        if (!$cliente) {
            header('Location: /logitrack/views/cliente/dashboard.php?error=' . urlencode('No se encontró tu perfil de cliente.'));
            exit;
        }

        try {
            $this->envioModel->cancelarPorCliente($idEnvio, (int) $cliente['id_cliente']);
            header('Location: /logitrack/views/cliente/dashboard.php?ok=' . urlencode('Pedido cancelado correctamente.'));
        } catch (\Exception $e) {
            error_log("ClienteController::cancelarEnvio — " . $e->getMessage());
            header('Location: /logitrack/views/cliente/dashboard.php?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public function eliminarEnvio(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /logitrack/views/cliente/dashboard.php');
            exit;
        }

        $idEnvio = (int) ($_POST['id_envio'] ?? 0);
        if ($idEnvio === 0) {
            header('Location: /logitrack/views/cliente/dashboard.php?error=' . urlencode('ID de envío inválido.'));
            exit;
        }

        $cliente = $this->model->getPerfil((int) $_SESSION['usuario_id']);
        if (!$cliente) {
            header('Location: /logitrack/views/cliente/dashboard.php?error=' . urlencode('No se encontró tu perfil de cliente.'));
            exit;
        }

        // Verificar que el envío pertenece al cliente Y está en estado Pendiente
        $stmt = $this->pdo->prepare("
            SELECT e.id_envio
            FROM   envio e
            JOIN   historial_estado he ON he.id_hist = (
                SELECT MAX(h2.id_hist) FROM historial_estado h2 WHERE h2.id_envio = e.id_envio
            )
            JOIN   tipo_estado te ON he.id_estado = te.id_estado
            WHERE  e.id_envio     = :id_envio
              AND  e.id_remitente = :id_cliente
              AND  te.nombre      = 'Pendiente'
            LIMIT  1
        ");
        $stmt->execute([':id_envio' => $idEnvio, ':id_cliente' => $cliente['id_cliente']]);
        if (!$stmt->fetch()) {
            header('Location: /logitrack/views/cliente/dashboard.php?error=' . urlencode('No se puede eliminar: el envío no existe, no te pertenece, o ya está en tránsito.'));
            exit;
        }

        try {
            $this->pdo->beginTransaction();

            // Si el envío estaba asignado a un viaje, limpiarlo
            $stmtVe = $this->pdo->prepare("SELECT id_viaje FROM viaje_envio WHERE id_envio = :id LIMIT 1");
            $stmtVe->execute([':id' => $idEnvio]);
            $ve = $stmtVe->fetch();

            $this->pdo->prepare("DELETE FROM viaje_envio WHERE id_envio = :id")->execute([':id' => $idEnvio]);

            if ($ve) {
                // Si el viaje quedó sin envíos, eliminarlo para liberar al chofer
                $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM viaje_envio WHERE id_viaje = :id");
                $stmtCount->execute([':id' => $ve['id_viaje']]);
                if ((int) $stmtCount->fetchColumn() === 0) {
                    $this->pdo->prepare("DELETE FROM viaje WHERE id_viaje = :id")->execute([':id' => $ve['id_viaje']]);
                }
            }

            $this->pdo->prepare("DELETE FROM historial_estado WHERE id_envio = :id")->execute([':id' => $idEnvio]);
            $this->pdo->prepare("DELETE FROM envio WHERE id_envio = :id")->execute([':id' => $idEnvio]);

            $this->pdo->commit();
            header('Location: /logitrack/views/cliente/dashboard.php?ok=' . urlencode('Envío eliminado correctamente.'));
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log("ClienteController::eliminarEnvio — " . $e->getMessage());
            header('Location: /logitrack/views/cliente/dashboard.php?error=' . urlencode('Error al eliminar el envío.'));
        }
        exit;
    }
}

// ─── Punto de entrada directo (POST desde formularios) ────────────────────────
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../middleware/auth.php';
    requireRol(['cliente']);

    $ctrl   = new ClienteController($pdo);
    $action = $_GET['action'] ?? '';

    if ($action === 'confirmarRecepcion') {
        $ctrl->confirmarRecepcion();
    } elseif ($action === 'cancelarEnvio') {
        $ctrl->cancelarEnvio();
    } elseif ($action === 'eliminarEnvio') {
        $ctrl->eliminarEnvio();
    } else {
        header('Location: /logitrack/views/cliente/a_recibir.php');
        exit;
    }
}
