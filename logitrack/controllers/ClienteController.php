<?php
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/EnvioModel.php';

class ClienteController {

    private ClienteModel $model;
    private EnvioModel   $envioModel;

    public function __construct(PDO $pdo) {
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
}
