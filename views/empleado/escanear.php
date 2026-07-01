<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['empleado', 'admin']);
require_once __DIR__ . '/../../config/db.php';

$resultado = null;
$error     = '';

$nro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nro = trim($_POST['nro_tracking'] ?? '');
} elseif (!empty($_GET['nro'])) {
    $nro = trim($_GET['nro']);
}

if ($nro !== '') {
    if (!preg_match('/^[A-Z0-9\-]{6,30}$/i', $nro)) {
        $error = 'Número de tracking inválido.';
    } else {
        $stmt = $pdo->prepare("
            SELECT e.id_envio, e.nro_tracking, e.peso_kg,
                   tc.nombre  AS tipo_contenido,
                   c_rem.nombre  AS rem_nombre,  c_rem.apellido  AS rem_apellido,
                   c_dest.nombre AS dest_nombre, c_dest.apellido AS dest_apellido,
                   s_orig.nombre AS suc_origen,
                   s_dest.nombre AS suc_destino,
                   te.nombre AS estado, he.fecha_hora AS ultimo_estado
            FROM   envio           e
            JOIN   tipo_contenido  tc    ON e.id_tipo_cont    = tc.id_tipo_cont
            JOIN   cliente         c_rem  ON e.id_remitente   = c_rem.id_cliente
            JOIN   cliente         c_dest ON e.id_destinatario= c_dest.id_cliente
            JOIN   sucursal        s_orig ON e.id_suc_origen  = s_orig.id_sucursal
            JOIN   sucursal        s_dest ON e.id_suc_destino = s_dest.id_sucursal
            JOIN   historial_estado he    ON he.id_hist = (
                SELECT MAX(h2.id_hist) FROM historial_estado h2 WHERE h2.id_envio = e.id_envio
            )
            JOIN   tipo_estado      te    ON he.id_estado = te.id_estado
            WHERE  e.nro_tracking = :nro
            LIMIT  1
        ");
        $stmt->execute([':nro' => strtoupper($nro)]);
        $resultado = $stmt->fetch();
        if (!$resultado) {
            $error = 'No se encontró ningún envío con ese número.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Escanear Paquete</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar">
            <div>
                <h1 class="page-title">ESCANEAR PAQUETE</h1>
                <p class="page-subtitle">Consultá el estado de un envío por número de tracking</p>
            </div>
        </div>

        <div class="form-card" style="max-width:480px;margin-bottom:24px;">
            <form method="POST" action="/logitrack/views/empleado/escanear.php">
                <div class="form-group">
                    <label>Número de tracking</label>
                    <input type="text" name="nro_tracking" placeholder="Ej: TRK-ABC12345"
                           value="<?= htmlspecialchars($nro) ?>"
                           maxlength="30" required style="text-transform:uppercase;">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:46px;margin-top:12px;">
                    🔍 BUSCAR
                </button>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="max-width:480px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($resultado): ?>
        <div class="form-card" style="max-width:560px;">
            <h3 style="margin-bottom:16px;">📦 <?= htmlspecialchars($resultado['nro_tracking']) ?></h3>
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="color:var(--gris);padding:6px 0;">Remitente</td><td><?= htmlspecialchars($resultado['rem_apellido'] . ', ' . $resultado['rem_nombre']) ?></td></tr>
                <tr><td style="color:var(--gris);padding:6px 0;">Destinatario</td><td><?= htmlspecialchars($resultado['dest_apellido'] . ', ' . $resultado['dest_nombre']) ?></td></tr>
                <tr><td style="color:var(--gris);padding:6px 0;">Origen</td><td><?= htmlspecialchars($resultado['suc_origen']) ?></td></tr>
                <tr><td style="color:var(--gris);padding:6px 0;">Destino</td><td><?= htmlspecialchars($resultado['suc_destino']) ?></td></tr>
                <tr><td style="color:var(--gris);padding:6px 0;">Tipo contenido</td><td><?= htmlspecialchars($resultado['tipo_contenido']) ?></td></tr>
                <tr><td style="color:var(--gris);padding:6px 0;">Peso</td><td><?= $resultado['peso_kg'] ?> kg</td></tr>
                <tr><td style="color:var(--gris);padding:6px 0;">Estado actual</td><td><span class="badge badge-transito"><?= htmlspecialchars($resultado['estado']) ?></span></td></tr>
                <tr><td style="color:var(--gris);padding:6px 0;">Último movimiento</td><td><?= date('d/m/Y H:i', strtotime($resultado['ultimo_estado'])) ?></td></tr>
            </table>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
