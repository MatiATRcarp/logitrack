<?php
// api/buscar_cliente.php — Busca un cliente por DNI (AJAX)
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$rolesPermitidos = ['admin', 'empleado', 'cliente'];
if (!in_array($_SESSION['usuario_rol'] ?? '', $rolesPermitidos)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

$dni = trim($_GET['dni'] ?? '');
if (!preg_match('/^\d{7,8}$/', $dni)) {
    http_response_code(400);
    echo json_encode(['error' => 'DNI inválido. Debe tener 7 u 8 dígitos.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id_cliente, nombre, apellido, dni
        FROM   cliente
        WHERE  dni = :dni AND activo = 1
        LIMIT  1
    ");
    $stmt->execute([':dni' => $dni]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        echo json_encode(['found' => false]);
        exit;
    }

    echo json_encode([
        'found'      => true,
        'id_cliente' => (int) $cliente['id_cliente'],
        'nombre'     => htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']),
        'dni'        => $cliente['dni'],
    ]);

} catch (PDOException $e) {
    error_log("api/buscar_cliente.php — " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
