<?php
// middleware/auth.php — Portero de sesión y roles
// Incluir al inicio de cada página protegida

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$TIMEOUT = 30 * 60; // 30 minutos de inactividad

/**
 * Verifica que el usuario esté logueado y su sesión no haya expirado.
 * Si no, redirige al login.
 */
function requireLogin(): void {
    global $TIMEOUT;

    if (!isset($_SESSION['usuario_id'])) {
        $_SESSION['flash_info'] = 'Iniciá sesión para continuar.';
        header("Location: /logitrack/index.php");
        exit;
    }

    // Verificar timeout de sesión
    if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso']) > $TIMEOUT) {
        session_destroy();
        session_start();
        $_SESSION['flash_info'] = '⏰ Tu sesión expiró por inactividad.';
        header("Location: /logitrack/index.php");
        exit;
    }

    $_SESSION['ultimo_acceso'] = time(); // Renovar timestamp
}

/**
 * Verifica que el usuario tenga uno de los roles permitidos.
 * Si no, muestra 403.
 * 
 * @param array $roles Ej: ['admin', 'empleado']
 */
function requireRol(array $roles): void {
    requireLogin();

    if (!in_array($_SESSION['usuario_rol'], $roles)) {
        http_response_code(403);
        include __DIR__ . '/../views/layout/403.php';
        exit;
    }
}

/**
 * Devuelve true si el usuario tiene el rol indicado.
 */
function tieneRol(string $rol): bool {
    return isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === $rol;
}
