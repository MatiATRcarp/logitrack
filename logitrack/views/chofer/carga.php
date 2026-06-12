<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['chofer']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/ChoferModel.php';

$model  = new ChoferModel($pdo);
$perfil = $model->getPerfil($_SESSION['usuario_id']);
$carga  = [];

if ($perfil) {
    $viaje = $model->getViajeActivo($perfil['id_chofer']);
    if ($viaje) {
        $carga = $model->getCargaViaje($viaje['id_viaje']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Mi Carga</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar">
            <div>
                <h1 class="page-title">MI CARGA</h1>
                <p class="page-subtitle">Paquetes asignados al viaje activo</p>
            </div>
            <a href="/logitrack/views/chofer/dashboard.php" class="btn btn-secondary">← Volver</a>
        </div>

        <?php if (!$perfil): ?>
            <div class="alert alert-error">No se encontró tu perfil de chofer.</div>
        <?php elseif (empty($carga)): ?>
            <div class="alert alert-info" style="max-width:480px;">Sin paquetes asignados al viaje activo.</div>
        <?php else: ?>
        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>Tracking</th><th>Destinatario</th><th>Sucursal Destino</th><th>Tipo</th><th>Peso</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($carga as $p): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($p['nro_tracking']) ?></code></td>
                        <td><?= htmlspecialchars($p['dest_apellido'] . ', ' . $p['dest_nombre']) ?></td>
                        <td><?= htmlspecialchars($p['sucursal_destino']) ?></td>
                        <td><?= htmlspecialchars($p['tipo_contenido']) ?></td>
                        <td><?= $p['peso_kg'] ?> kg</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
