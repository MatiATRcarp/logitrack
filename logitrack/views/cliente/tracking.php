<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['cliente']);
require_once __DIR__ . '/../../config/db.php';

$historial = [];
$error     = '';
$nro       = trim($_GET['nro'] ?? $_POST['nro_tracking'] ?? '');

if ($nro) {
    if (!preg_match('/^[A-Z0-9\-]{6,30}$/i', $nro)) {
        $error = 'Número de tracking inválido.';
    } else {
        $stmt = $pdo->prepare("
            SELECT te.nombre AS estado, he.fecha_hora, s.nombre AS sucursal
            FROM   historial_estado he
            JOIN   envio        e  ON he.id_envio = e.id_envio
            JOIN   sucursal     s  ON he.id_sucursal_actual = s.id_sucursal
            JOIN   tipo_estado  te ON he.id_estado = te.id_estado
            WHERE  e.nro_tracking = :nro
            ORDER  BY he.fecha_hora ASC
        ");
        $stmt->execute([':nro' => strtoupper($nro)]);
        $historial = $stmt->fetchAll();
        if (empty($historial)) {
            $error = 'No se encontró ningún envío con ese número de tracking.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Rastrear Paquete</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar">
            <div>
                <h1 class="page-title">RASTREAR PAQUETE</h1>
                <p class="page-subtitle">Seguí el estado de un envío</p>
            </div>
        </div>

        <div class="form-card" style="max-width:480px;margin-bottom:24px;">
            <form method="GET" action="/logitrack/views/cliente/tracking.php">
                <div class="form-group">
                    <label>Número de tracking</label>
                    <input type="text" name="nro" placeholder="Ej: TRK-ABC12345"
                           value="<?= htmlspecialchars($nro) ?>"
                           maxlength="30" style="text-transform:uppercase;">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:46px;margin-top:12px;">
                    🔍 RASTREAR
                </button>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="max-width:480px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($historial)): ?>
        <div class="form-card" style="max-width:560px;">
            <h3 style="margin-bottom:20px;">📦 <?= htmlspecialchars(strtoupper($nro)) ?></h3>
            <div class="timeline">
                <?php foreach ($historial as $h): ?>
                <div class="timeline-item">
                    <div class="timeline-dot">📍</div>
                    <div class="timeline-body">
                        <div class="estado"><?= htmlspecialchars($h['estado']) ?></div>
                        <div class="fecha"><?= date('d/m/Y H:i', strtotime($h['fecha_hora'])) ?></div>
                        <div class="lugar"><?= htmlspecialchars($h['sucursal']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
