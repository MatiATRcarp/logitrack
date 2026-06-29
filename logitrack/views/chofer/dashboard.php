<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['chofer']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/ChoferController.php';

$controller   = new ChoferController($pdo);
$data         = $controller->getDashboardData((int) $_SESSION['usuario_id']);
$chofer       = $data['chofer'];
$viaje_activo = $data['viaje_activo'];
$envios_carga = $data['envios_carga'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Mi Viaje</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .viaje-card {
            background: var(--panel);
            border: 1px solid var(--amarillo);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .viaje-card h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 28px;
            margin-bottom: 16px;
            color: var(--amarillo);
        }
        .dato-viaje {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--borde);
            font-size: 15px;
        }
        .dato-viaje:last-child { border-bottom: none; }
        .dato-viaje .key { color: var(--gris); }
        .btn-incidente {
            width: 100%;
            height: 56px;
            background: rgba(248,113,113,.15);
            border: 2px solid var(--rojo);
            border-radius: 14px;
            color: var(--rojo);
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 2px;
            cursor: pointer;
            margin-top: 16px;
            font-family: 'DM Sans', sans-serif;
        }
        .btn-incidente:hover { background: var(--rojo); color: white; }
        .btn-entregar {
            padding: 5px 12px;
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
        .btn-entregar:hover { background: #22c55e; color: #0a0a0a; }
        .hamburger {
            display: none;
            position: fixed;
            top: 16px; left: 16px;
            z-index: 200;
            background: var(--panel);
            border: 1px solid var(--borde);
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer;
            font-size: 20px;
        }
        @media (max-width: 768px) {
            .hamburger { display: block; }
            .main-content { padding-top: 56px; }
        }
    </style>
</head>
<body>
<div class="layout">

    <input type="checkbox" id="sidebar-toggle" class="sidebar-toggle">
    <label for="sidebar-toggle" class="hamburger">☰</label>

    <?php include __DIR__ . '/../layout/sidebar.php'; ?>

    <main class="main-content">

        <?php if (!empty($_GET['ok'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['ok']) ?></div>
        <?php elseif (!empty($_GET['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <div class="topbar">
            <div>
                <h1 class="page-title">MI VIAJE ACTIVO</h1>
                <p class="page-subtitle">
                    <?= $chofer ? htmlspecialchars($chofer['nombre'] . ' ' . $chofer['apellido']) : '' ?>
                    <?= $chofer ? '· Legajo ' . htmlspecialchars($chofer['legajo']) : '' ?>
                </p>
            </div>
        </div>

        <?php if (!$chofer): ?>
            <div class="alert alert-error">No se encontró tu perfil de chofer vinculado a esta cuenta.</div>

        <?php elseif (!$viaje_activo): ?>
            <div class="alert alert-info">📭 No tenés ningún viaje activo en este momento.</div>

        <?php else: ?>

            <div class="viaje-card">
                <h2>🚛 VIAJE #<?= $viaje_activo['id_viaje'] ?></h2>
                <div class="dato-viaje">
                    <span class="key">Vehículo (Patente)</span>
                    <span><strong><?= htmlspecialchars($viaje_activo['patente']) ?></strong></span>
                </div>
                <div class="dato-viaje">
                    <span class="key">Sucursal Origen</span>
                    <span><?= htmlspecialchars($viaje_activo['origen']) ?></span>
                </div>
                <div class="dato-viaje">
                    <span class="key">Salida</span>
                    <span><?= date('d/m/Y H:i', strtotime($viaje_activo['fecha_salida'])) ?></span>
                </div>
                <div class="dato-viaje">
                    <span class="key">Llegada Estimada</span>
                    <span><?= $viaje_activo['fecha_llegada_est']
                        ? date('d/m/Y H:i', strtotime($viaje_activo['fecha_llegada_est']))
                        : '—' ?></span>
                </div>
                <div class="dato-viaje">
                    <span class="key">Bultos a bordo</span>
                    <span><strong><?= count($envios_carga) ?></strong></span>
                </div>

                <a href="/logitrack/views/chofer/incidente.php?viaje=<?= $viaje_activo['id_viaje'] ?>">
                    <button class="btn-incidente">🚨 REPORTAR INCIDENTE</button>
                </a>
            </div>

            <?php if (!empty($envios_carga)): ?>
            <div class="tabla-wrapper">
                <div class="tabla-header"><h3>📦 Carga a bordo</h3></div>
                <table>
                    <thead>
                        <tr>
                            <th>Tracking</th>
                            <th>Destinatario</th>
                            <th>Destino</th>
                            <th>Peso</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($envios_carga as $env): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($env['nro_tracking']) ?></code></td>
                            <td><?= htmlspecialchars($env['dest_nombre'] . ' ' . $env['dest_apellido']) ?></td>
                            <td><?= htmlspecialchars($env['sucursal_destino']) ?></td>
                            <td><?= $env['peso_kg'] ?> kg</td>
                            <td><?= htmlspecialchars($env['tipo_contenido']) ?></td>
                            <td><?= htmlspecialchars($env['estado_nombre'] ?? '—') ?></td>
                            <td>
                                <?php if (in_array((int)($env['id_estado'] ?? 0), [2, 3])): ?>
                                <form method="POST"
                                      action="/logitrack/controllers/ChoferController.php?action=confirmarRecepcion"
                                      style="margin:0;">
                                    <input type="hidden" name="id_envio" value="<?= (int) $env['id_envio'] ?>">
                                    <button type="submit" class="btn-entregar"
                                            onclick="return confirm('¿Confirmás la entrega de este paquete?')">
                                        ✅ Marcar entregado
                                    </button>
                                </form>
                                <?php else: ?>
                                <span style="color:var(--gris);font-size:13px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</div>
</body>
</html>
