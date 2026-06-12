<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['cliente']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$controller = new ClienteController($pdo);
$data       = $controller->getDashboardData((int) $_SESSION['usuario_id']);
$cliente    = $data['cliente'];
$enviados   = $data['enviados'];

function badgeEstado(string $estado): string {
    return match($estado) {
        'Entregado' => 'badge-entregado',
        'En tránsito' => 'badge-transito',
        'Pendiente', 'Ingresado en sucursal destino' => 'badge-activo',
        'Cancelado', 'Con incidencias', 'Devuelto al remitente' => 'badge-inactivo',
        default => 'badge-inactivo'
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Mis Envíos</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">MIS ENVÍOS</h1>
                <p class="page-subtitle">Historial de paquetes enviados y a recibir</p>
            </div>
        </div>

        <?php if (!$cliente): ?>
            <div class="alert alert-error">No se encontró tu perfil de cliente vinculado a esta cuenta.</div>
        <?php else: ?>

        <div class="tabla-wrapper">
            <div class="tabla-header">
                <h3>📤 Envíos Realizados</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Destinatario</th>
                        <th>Destino</th>
                        <th>Peso</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Rastrear</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enviados as $e): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($e['nro_tracking']) ?></code></td>
                        <td><?= htmlspecialchars($e['dest_nombre'] . ' ' . $e['dest_apellido']) ?></td>
                        <td><?= htmlspecialchars($e['sucursal_destino']) ?></td>
                        <td><?= $e['peso_kg'] ?> kg</td>
                        <td><?= date('d/m/Y', strtotime($e['fecha_recepcion'])) ?></td>
                        <td>
                            <span class="badge <?= badgeEstado($e['estado']) ?>">
                                <?= htmlspecialchars($e['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="/logitrack/views/cliente/tracking.php?nro=<?= urlencode($e['nro_tracking']) ?>"
                               class="btn btn-secondary btn-sm">🔍 Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($enviados)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--gris);padding:32px;">Sin envíos registrados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </main>
</div>
</body>
</html>
