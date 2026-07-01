<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['cliente']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/ClienteModel.php';

$model   = new ClienteModel($pdo);
$perfil  = $model->getPerfil($_SESSION['usuario_id']);
$envios  = $perfil ? $model->getARecibir((int) $_SESSION['usuario_id']) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — A Recibir</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .btn-confirmar {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(34, 197, 94, .12);
            border: 1px solid #22c55e;
            border-radius: 8px;
            color: #22c55e;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: background .2s, color .2s;
            white-space: nowrap;
        }
        .btn-confirmar:hover { background: #22c55e; color: #0a0a0a; }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar">
            <div>
                <h1 class="page-title">A RECIBIR</h1>
                <p class="page-subtitle">Envíos que están en camino hacia vos</p>
            </div>
        </div>

        <?php if (!empty($_GET['ok'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['ok']) ?></div>
        <?php elseif (!empty($_GET['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <?php if (!$perfil): ?>
            <div class="alert alert-error">No se encontró tu perfil de cliente.</div>
        <?php else: ?>
        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>Tracking</th><th>Remitente</th><th>Origen</th><th>Destino</th><th>Estado</th><th>Último evento</th><th>Acción</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($envios as $e): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($e['nro_tracking']) ?></code></td>
                        <td><?= htmlspecialchars($e['rem_apellido'] . ', ' . $e['rem_nombre']) ?></td>
                        <td><?= htmlspecialchars($e['sucursal_origen']) ?></td>
                        <td><?= htmlspecialchars($e['sucursal_destino']) ?></td>
                        <td><span class="badge badge-transito"><?= htmlspecialchars($e['estado']) ?></span></td>
                        <td><?= date('d/m/Y H:i', strtotime($e['ultimo_evento'])) ?></td>
                        <td>
                            <?php if (in_array($e['estado'], ['En tránsito', 'Ingresado en sucursal destino'])): ?>
                            <form method="POST"
                                  action="/logitrack/controllers/ClienteController.php?action=confirmarRecepcion"
                                  style="margin:0;">
                                <input type="hidden" name="id_envio" value="<?= (int) $e['id_envio'] ?>">
                                <button type="submit" class="btn-confirmar"
                                        onclick="return confirm('¿Confirmás que recibiste este paquete?')">
                                    ✅ Confirmar recepción
                                </button>
                            </form>
                            <?php else: ?>
                            <span style="color:var(--gris);font-size:13px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($envios)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--gris);padding:32px;">No tenés envíos pendientes de recibir</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
