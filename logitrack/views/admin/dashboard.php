<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['admin']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

$controller = new AdminController($pdo);
$data       = $controller->getDashboardData();
$metricas   = $data['metricas'];
$viajes     = $data['viajes'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Dashboard Admin</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">

    <?php include __DIR__ . '/../layout/sidebar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">DASHBOARD</h1>
                <p class="page-subtitle">Resumen global del sistema · <?= date('d/m/Y') ?></p>
            </div>
        </div>

        <!-- Métricas -->
        <div class="cards-grid">
            <div class="card card-metric">
                <div class="icono">📦</div>
                <div class="valor"><?= $metricas['envios_mes'] ?></div>
                <div class="label">Envíos este mes</div>
            </div>
            <div class="card card-metric">
                <div class="icono">🚛</div>
                <div class="valor"><?= $metricas['vehiculos_activos'] ?></div>
                <div class="label">Vehículos en ruta</div>
            </div>
            <div class="card card-metric">
                <div class="icono">🧑‍✈️</div>
                <div class="valor"><?= $metricas['total_choferes'] ?></div>
                <div class="label">Choferes registrados</div>
            </div>
            <div class="card card-metric">
                <div class="icono">⚠️</div>
                <div class="valor" style="color:<?= $metricas['incidentes'] > 0 ? '#f87171' : '#4ade80' ?>">
                    <?= $metricas['incidentes'] ?>
                </div>
                <div class="label">Incidentes (30 días)</div>
            </div>
        </div>

        <!-- Viajes recientes -->
        <div class="tabla-wrapper">
            <div class="tabla-header">
                <h3>🗺️ Viajes Recientes</h3>
                <a href="/logitrack/views/admin/viajes.php" class="btn btn-secondary btn-sm">Ver todos</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Chofer</th>
                        <th>Patente</th>
                        <th>Salida</th>
                        <th>Llegada Est.</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($viajes as $v): ?>
                    <tr>
                        <td><?= $v['id_viaje'] ?></td>
                        <td><?= htmlspecialchars($v['chofer_nombre'] . ' ' . $v['chofer_apellido']) ?></td>
                        <td><code><?= htmlspecialchars($v['patente']) ?></code></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['fecha_salida'])) ?></td>
                        <td><?= $v['fecha_llegada_est'] ? date('d/m/Y H:i', strtotime($v['fecha_llegada_est'])) : '—' ?></td>
                        <td>
                            <?php
                            $ahora  = time();
                            $salida = strtotime($v['fecha_salida']);
                            $llegada = $v['fecha_llegada_est'] ? strtotime($v['fecha_llegada_est']) : null;
                            if ($ahora < $salida): ?>
                                <span class="badge badge-inactivo">Programado</span>
                            <?php elseif (!$llegada || $ahora <= $llegada): ?>
                                <span class="badge badge-transito">En ruta</span>
                            <?php else: ?>
                                <span class="badge badge-entregado">Finalizado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($viajes)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--gris);padding:32px;">Sin viajes registrados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>
</body>
</html>
