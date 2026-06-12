<?php
require_once __DIR__ . '/../models/UsuarioModel.php';

class RegistroController {

    private UsuarioModel $model;

    public function __construct(PDO $pdo) {
        $this->model = new UsuarioModel($pdo);
    }

    public function registrar(array $post): array {
        $dni       = trim($post['dni']                ?? '');
        $nombre    = trim($post['nombre']             ?? '');
        $apellido  = trim($post['apellido']           ?? '');
        $email     = trim($post['email']              ?? '');
        $direccion = trim($post['direccion']          ?? '');
        $rol       = 'cliente';
        $password  = $post['password']                ?? '';
        $confirmar = $post['confirmar_password']       ?? '';

        if ($password !== $confirmar) {
            return ['ok' => false, 'error' => 'Las contraseñas no coinciden.'];
        }
        if (empty($dni) || empty($nombre) || empty($apellido) || empty($email) || empty($direccion)) {
            return ['ok' => false, 'error' => 'Completá todos los campos.'];
        }

        try {
            $id_usuario = $this->model->registrar($dni, $nombre, $apellido, $email, $password, $rol, ['direccion' => $direccion]);
            return ['ok' => true, 'id_usuario' => $id_usuario, 'nombre' => $nombre, 'email' => $email];
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return ['ok' => false, 'error' => 'El DNI o email ya están registrados.'];
            }
            error_log("RegistroController::registrar — " . $e->getMessage());
            return ['ok' => false, 'error' => 'Error al registrar. Intente nuevamente.'];
        }
    }
}
