<?php
require_once __DIR__ . '/../models/EmpleadoModel.php';
require_once __DIR__ . '/../models/EnvioModel.php';

class EmpleadoController {

    private EmpleadoModel $empleadoModel;
    private EnvioModel    $envioModel;

    public function __construct(PDO $pdo) {
        $this->empleadoModel = new EmpleadoModel($pdo);
        $this->envioModel    = new EnvioModel($pdo);
    }

    public function getDashboardData(?int $id_sucursal): array {
        return [
            'envios'      => $this->empleadoModel->getEnviosPendientes($id_sucursal),
            'viajes_hoy'  => $this->empleadoModel->getViajesHoy(),
        ];
    }

    public function getFormData(): array {
        return $this->envioModel->getFormData();
    }

    public function getEnviosDisponibles(): array {
        return $this->empleadoModel->getEnviosEnAlmacenOrigen();
    }

    public function armarViajeConEnvios(array $post): array {
        $id_chofer        = filter_var($post['id_chofer'] ?? '', FILTER_VALIDATE_INT);
        $patente          = filter_var(trim($post['patente'] ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $fecha_salida     = trim($post['fecha_salida'] ?? '');
        $fecha_llegada    = trim($post['fecha_llegada_est'] ?? '') ?: null;
        $envios           = array_values(array_filter(
            array_map('intval', (array) ($post['envios'] ?? [])),
            fn($id) => $id > 0
        ));

        // a. Validaciones previas
        if (!$id_chofer || !$patente || !$fecha_salida || empty($envios)) {
            return ['ok' => false, 'mensaje' => 'Completá todos los campos obligatorios y seleccioná al menos un envío.'];
        }

        $vehiculo = $this->empleadoModel->getVehiculo($patente);
        if (!$vehiculo) {
            return ['ok' => false, 'mensaje' => 'El vehículo seleccionado no existe.'];
        }

        // b. Validación de capacidad
        $peso_total = $this->empleadoModel->getPesoTotalEnvios($envios);
        $capacidad  = (float) $vehiculo['capacidad_kg_max'];
        if ($peso_total > $capacidad) {
            return ['ok' => false, 'mensaje' => sprintf(
                'El peso total (%s kg) supera la capacidad máxima del vehículo (%s kg).',
                number_format($peso_total, 2),
                number_format($capacidad, 2)
            )];
        }

        // c. Validación de licencia
        $chofer = $this->empleadoModel->getChofer($id_chofer);
        if (!$chofer) {
            return ['ok' => false, 'mensaje' => 'El chofer seleccionado no existe.'];
        }
        if ((int) $vehiculo['requiere_licencia_especial'] === 1
            && !in_array($chofer['tipo_licencia'], ['E1', 'E2', 'E3', 'C2'], true)) {
            return ['ok' => false, 'mensaje' => 'El chofer no posee la licencia requerida para este vehículo.'];
        }

        // 2. Transacción ACID
        try {
            $id_viaje = $this->empleadoModel->crearViajeConEnvios([
                'fecha_salida'      => $fecha_salida,
                'fecha_llegada_est' => $fecha_llegada,
                'patente'           => $patente,
                'id_chofer'         => $id_chofer,
                'envios'            => $envios,
            ]);
            return [
                'ok'      => true,
                'mensaje' => "✓ Viaje #{$id_viaje} creado con " . count($envios) . ' paquete(s).',
            ];
        } catch (PDOException $e) {
            if ($e->getCode() === '45000') {
                return ['ok' => false, 'mensaje' => $e->errorInfo[2] ?? 'El chofer ya tiene un viaje asignado en ese rango de fechas.'];
            }
            return ['ok' => false, 'mensaje' => 'Error al crear el viaje. Operación revertida.'];
        }
    }

    public function crearEnvio(array $post): array {
        $id_remitente    = (int) ($post['id_remitente']    ?? 0);
        $id_destinatario = (int) ($post['id_destinatario'] ?? 0);
        $id_tipo_cont    = (int) ($post['id_tipo_cont']    ?? 0);
        $id_suc_origen   = (int) ($post['id_suc_origen']   ?? 0);
        $id_suc_destino  = (int) ($post['id_suc_destino']  ?? 0);
        $peso_kg         = filter_var($post['peso_kg'] ?? '', FILTER_VALIDATE_FLOAT);

        $errores = [];
        if ($id_remitente === 0)                        $errores[] = 'Seleccioná el remitente.';
        if ($id_destinatario === 0)                     $errores[] = 'Seleccioná el destinatario.';
        if ($id_remitente === $id_destinatario)         $errores[] = 'Remitente y destinatario no pueden ser la misma persona.';
        if ($id_tipo_cont === 0)                        $errores[] = 'Seleccioná el tipo de contenido.';
        if ($id_suc_origen === 0)                       $errores[] = 'Seleccioná la sucursal de origen.';
        if ($id_suc_destino === 0)                      $errores[] = 'Seleccioná la sucursal de destino.';
        if ($id_suc_origen === $id_suc_destino)         $errores[] = 'Origen y destino no pueden ser la misma sucursal.';
        if ($peso_kg === false || $peso_kg <= 0)        $errores[] = 'El peso debe ser un número positivo.';
        if (is_float($peso_kg) && $peso_kg > 30000)    $errores[] = 'El peso no puede superar 30.000 kg.';

        if (!empty($errores)) {
            return ['ok' => false, 'mensaje' => implode('<br>', $errores)];
        }

        try {
            $nro = $this->envioModel->crear([
                'id_remitente'    => $id_remitente,
                'id_destinatario' => $id_destinatario,
                'id_tipo_cont'    => $id_tipo_cont,
                'id_suc_origen'   => $id_suc_origen,
                'id_suc_destino'  => $id_suc_destino,
                'peso_kg'         => $peso_kg,
            ]);
            return ['ok' => true, 'nro_tracking' => $nro];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => 'Error al guardar el envío. Intente nuevamente.'];
        }
    }
}
