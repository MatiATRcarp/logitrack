<?php
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/controllers/RegistroController.php';

$controller = new RegistroController($pdo);
$resultado  = $controller->registrar($_POST);

if ($resultado['ok']) {
    // Regenerar ID de sesión (previene Session Fixation)
    session_regenerate_id(true);

    $_SESSION['usuario_id']     = $resultado['id_usuario'];
    $_SESSION['usuario_nombre'] = $resultado['nombre'];
    $_SESSION['usuario_email']  = $resultado['email'];
    $_SESSION['usuario_rol']    = 'cliente';
    $_SESSION['ultimo_acceso']  = time();

    header("Location: /logitrack/views/cliente/dashboard.php");
    exit;
}

$_SESSION['flash_error'] = $resultado['error'];
header("Location: /logitrack/registro.php");
exit;
