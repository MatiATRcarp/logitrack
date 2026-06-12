<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['chofer']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/ChoferController.php';

$controller = new ChoferController($pdo);
$data       = $controller->getPerfilData((int) $_SESSION['usuario_id']);
$chofer     = $data['chofer'];
$viajes     = $data['viajes'];

$viaje_anterior = null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Mi Perfil</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .perfil-card {
            background: var(--panel);
            border: 1px solid var(--borde);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
        }
        .perfil-dato .key { color: var(--gris); font-size: 12px; letter-spacing: 1px; text-transform: uppercase; }
        .perfil-dato .val { font-size: 18px; font-weight: 500; margin-top: 4px; }
        .viaje-grupo td { border-top: 2px solid var(--borde); }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">MI PERFIL</h1>
                <p class="page-subtitle">Datos personales y viajes realizados</p>
            </div>
        </div>

        <?php if (!$chofer): ?>
            <div class="alert alert-error">No se encontró tu perfil de chofer vinculado a esta cuenta.</div>
        <?php else: ?>

        <div class="perfil-card">
            <div class="perfil-dato">
                <div class="key">Nombre</div>
                <div class="val"><?= htmlspecialchars($chofer['nombre'] . ' ' . $chofer['apellido']) ?></div>
            </div>
            <div class="perfil-dato">
                <div class="key">DNI</div>
                <div class="val"><?= htmlspecialchars($chofer['dni']) ?></div>
            </div>
            <div class="perfil-dato">
                <div class="key">Legajo</div>
                <div class="val"><?= htmlspecialchars($chofer['legajo']) ?></div>
            </div>
            <div class="perfil-dato">
                <div class="key">Licencia</div>
                <div class="val"><span class="badge badge-transito"><?= htmlspecialchars($chofer['tipo_licencia']) ?></span></div>
            </div>
        </div>

        <div class="tabla-header"><h3>📜 Viajes realizados</h3></div>
        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Viaje</th>
                        <th>Salida</th>
                        <th>Llegada</th>
                        <th>Sucursal Origen</th>
                        <th>Sucursal Destino</th>
                        <th>Destinatario</th>
                        <th>Tracking</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($viajes as $v): ?>
                    <?php $nuevo_viaje = $v['id_viaje'] !== $viaje_anterior; $viaje_anterior = $v['id_viaje']; ?>
                    <tr<?= $nuevo_viaje ? ' class="viaje-grupo"' : '' ?>>
                        <td>#<?= $v['id_viaje'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['fecha_salida'])) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['fecha_llegada_est'])) ?></td>
                        <td><?= htmlspecialchars($v['sucursal_origen']) ?></td>
                        <td><?= htmlspecialchars($v['sucursal_destino'] ?? '—') ?></td>
                        <td><?= $v['dest_nombre'] ? htmlspecialchars($v['dest_apellido'] . ', ' . $v['dest_nombre']) : '—' ?></td>
                        <td><?= $v['nro_tracking'] ? '<code>' . htmlspecialchars($v['nro_tracking']) . '</code>' : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($viajes)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--gris);padding:32px;">Todavía no realizaste ningún viaje</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </main>
</div>
</body>
</html>
