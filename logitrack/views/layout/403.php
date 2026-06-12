<?php
$rol_403 = $_SESSION['usuario_rol'] ?? '';
$volver_403 = match ($rol_403) {
    'admin'    => '/logitrack/views/admin/dashboard.php',
    'empleado' => '/logitrack/views/empleado/dashboard.php',
    'chofer'   => '/logitrack/views/chofer/dashboard.php',
    'cliente'  => '/logitrack/views/cliente/dashboard.php',
    default    => '/logitrack/index.php'
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>403 — Acceso Denegado</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
</head>
<body style="display:flex;align-items:center;justify-content:center;height:100vh;text-align:center;">
    <div>
        <div style="font-size:64px;margin-bottom:16px;">🔒</div>
        <h1 style="font-family:'Bebas Neue',sans-serif;font-size:60px;color:#FFCA18;">403</h1>
        <p style="color:#8b92a8;font-size:16px;margin:8px 0 24px;">No tenés permisos para acceder a esta sección.</p>
        <a href="<?= htmlspecialchars($volver_403) ?>" class="btn btn-secondary">← Volver</a>
    </div>
</body>
</html>
