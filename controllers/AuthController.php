<?php
// controllers/AuthController.php — Lógica de autenticación

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class AuthController {

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Procesa el formulario de login.
     * Devuelve array con 'error' o hace redirect.
     */
    public function login(string $email, string $password): string {

        // Sanitizar input
        $email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));

        if (empty($email) || empty($password)) {
            return 'Completá todos los campos.';
        }

        try {
            $sql = "SELECT u.id_usuario,
                           u.password_hash,
                           u.nombre,
                           u.email,
                           r.nombre AS rol,
                           emp.id_sucursal
                    FROM   usuario u
                    JOIN   rol          r   ON u.id_rol  = r.id_rol
                    LEFT   JOIN empleado emp ON emp.dni   = u.dni
                    WHERE  u.email  = :email
                    AND    u.activo = 1
                    LIMIT  1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("AuthController::login — " . $e->getMessage());
            return 'Error interno. Intente nuevamente.';
        }

        // Verificar contraseña con bcrypt
        if (!$user || !password_verify($password, $user['password_hash'])) {
            error_log("Login fallido para email: $email desde IP: " . $_SERVER['REMOTE_ADDR']);
            return 'Email o contraseña incorrectos.';
        }

        // Regenerar ID de sesión (previene Session Fixation)
        session_regenerate_id(true);

        // Guardar datos en sesión
        $_SESSION['usuario_id']    = $user['id_usuario'];
        $_SESSION['usuario_nombre']= $user['nombre'];
        $_SESSION['usuario_email'] = $user['email'];
        $_SESSION['usuario_rol']   = $user['rol'];
        $_SESSION['ultimo_acceso'] = time();

        if ($user['rol'] === 'empleado' && $user['id_sucursal']) {
            $_SESSION['id_sucursal'] = (int) $user['id_sucursal'];
        }

        // Log de acceso exitoso
        error_log("Login exitoso: {$user['email']} | Rol: {$user['rol']} | IP: " . $_SERVER['REMOTE_ADDR']);

        // Redirigir según rol
        $destino = match($user['rol']) {
            'admin'    => '/logitrack/views/admin/dashboard.php',
            'empleado' => '/logitrack/views/empleado/dashboard.php',
            'chofer'   => '/logitrack/views/chofer/dashboard.php',
            'cliente'  => '/logitrack/views/cliente/dashboard.php',
            default    => '/logitrack/index.php'
        };

        header("Location: $destino");
        exit;
    }

    /**
     * Cierra la sesión completamente.
     */
    public function logout(): void {
        // Registrar logout en log del servidor
        if (isset($_SESSION['usuario_email'])) {
            error_log("Logout: {$_SESSION['usuario_email']} | IP: " . $_SERVER['REMOTE_ADDR']);
        }

        // Paso 1: Vaciar array de sesión
        $_SESSION = [];

        // Paso 2: Eliminar cookie PHPSESSID del navegador
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        // Paso 3: Destruir sesión del servidor
        session_destroy();

        // Nueva sesión solo para mostrar el mensaje de confirmación una vez
        session_start();
        $_SESSION['flash_info'] = '✓ Cerraste sesión correctamente.';

        header('Cache-Control: no-store, no-cache, must-revalidate');
        header("Location: /logitrack/index.php");
        exit;
    }
}

// ─── Punto de entrada ─────────────────────────────────
// Este archivo actúa como controlador cuando recibe POST
$controller = new AuthController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'login') {
        $error = $controller->login(
            $_POST['email']    ?? '',
            $_POST['password'] ?? ''
        );
        // Si llegamos aquí, hubo error (el login exitoso hace redirect)
        $_SESSION['login_error'] = $error;
        header("Location: /logitrack/index.php");
        exit;
    }

    if ($accion === 'logout') {
        $controller->logout();
    }
}
