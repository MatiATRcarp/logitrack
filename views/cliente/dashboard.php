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
    <style>
        .btn-sm { padding:4px 12px; font-size:12px; border-radius:8px; border:none; cursor:pointer; font-family:inherit; letter-spacing:1px; }
        .btn-delete { background:rgba(248,113,113,.1); color:#f87171; border:1px solid #f87171; }
        .btn-delete:hover { background:#f87171; color:#fff; }
        .td-acciones { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
    </style>
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

        <?php if (!empty($_GET['ok'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['ok']) ?></div>
        <?php elseif (!empty($_GET['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

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
                        <th>Acción</th>
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
                        <td>
                            <?php if ($e['estado'] === 'Pendiente'): ?>
                            <div class="td-acciones">
                                <form method="POST" action="/logitrack/controllers/ClienteController.php?action=cancelarEnvio" style="margin:0;">
                                    <input type="hidden" name="id_envio" value="<?= (int) $e['id_envio'] ?>">
                                    <button type="submit" class="btn-sm" style="background:rgba(251,191,36,.1);color:#fbbf24;border:1px solid #fbbf24;"
                                            onclick="return confirm('¿Cancelar este pedido? Quedará registrado como Cancelado.')">
                                        Cancelar
                                    </button>
                                </form>
                                <a href="#del-envio-<?= (int) $e['id_envio'] ?>" class="btn-sm btn-delete">Eliminar</a>
                            </div>
                            <div class="modal-overlay" id="del-envio-<?= (int) $e['id_envio'] ?>">
                                <div class="modal-box">
                                    <p>¿Eliminar el envío <strong><?= htmlspecialchars($e['nro_tracking']) ?></strong> definitivamente?</p>
                                    <p style="font-size:13px;color:var(--gris);margin-top:6px;">
                                        Se borrará de la base de datos. Si estaba asignado a un viaje y era el único paquete, el viaje también se eliminará y el chofer quedará libre.
                                    </p>
                                    <div class="modal-actions">
                                        <a href="#" class="btn btn-secondary">Cancelar</a>
                                        <form method="POST" action="/logitrack/controllers/ClienteController.php?action=eliminarEnvio" style="margin:0;">
                                            <input type="hidden" name="id_envio" value="<?= (int) $e['id_envio'] ?>">
                                            <button type="submit" class="btn-sm btn-delete">Sí, eliminar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--gris);font-size:13px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($enviados)): ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--gris);padding:32px;">Sin envíos registrados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </main>
</div>
</body>
</html>
