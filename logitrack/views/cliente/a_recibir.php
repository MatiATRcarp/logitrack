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

        <?php if (!$perfil): ?>
            <div class="alert alert-error">No se encontró tu perfil de cliente.</div>
        <?php else: ?>
        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>Tracking</th><th>Remitente</th><th>Origen</th><th>Destino</th><th>Estado</th><th>Último evento</th></tr>
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
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($envios)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--gris);padding:32px;">No tenés envíos pendientes de recibir</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
