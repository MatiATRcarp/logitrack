<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['empleado']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/EmpleadoController.php';

$controller  = new EmpleadoController($pdo);
$id_sucursal = isset($_SESSION['id_sucursal']) ? (int) $_SESSION['id_sucursal'] : null;
$data        = $controller->getDashboardData($id_sucursal);
$envios      = $data['envios'];
$viajes_hoy  = $data['viajes_hoy'];
$total_pendientes = count($envios);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Logitrack — Empleado</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">PAQUETES PENDIENTES</h1>
                <p class="page-subtitle">Envíos esperando procesamiento en tu sucursal</p>
            </div>
            <a href="/logitrack/views/empleado/nuevo_envio.php" class="btn btn-primary">
                ➕ Nuevo Envío
            </a>
        </div>

        <div class="cards-grid" style="max-width:500px;margin-bottom:28px;">
            <div class="card card-metric">
                <div class="icono">📦</div>
                <div class="valor"><?= $total_pendientes ?></div>
                <div class="label">Pendientes hoy</div>
            </div>
            <div class="card card-metric">
                <div class="icono">🚚</div>
                <div class="valor"><?= $viajes_hoy ?></div>
                <div class="label">Viajes hoy</div>
            </div>
        </div>

        <div class="tabla-wrapper">
            <div class="tabla-header">
                <h3>📦 Envíos en espera</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Remitente</th>
                        <th>Destinatario</th>
                        <th>Destino</th>
                        <th>Peso</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($envios as $e): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($e['nro_tracking']) ?></code></td>
                        <td><?= htmlspecialchars($e['remitente'] . ' ' . $e['rem_apellido']) ?></td>
                        <td><?= htmlspecialchars($e['destinatario'] . ' ' . $e['dest_apellido']) ?></td>
                        <td><?= htmlspecialchars($e['sucursal_destino']) ?></td>
                        <td><?= $e['peso_kg'] ?> kg</td>
                        <td><?= htmlspecialchars($e['tipo_contenido']) ?></td>
                        <td><span class="badge badge-transito"><?= htmlspecialchars($e['estado']) ?></span></td>
                        <td>
                            <a href="/logitrack/views/empleado/escanear.php?nro=<?= urlencode($e['nro_tracking']) ?>"
                               class="btn btn-success btn-sm">Escanear</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($envios)): ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--gris);padding:32px;">✅ Sin paquetes pendientes</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
