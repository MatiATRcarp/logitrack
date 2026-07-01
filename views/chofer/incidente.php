<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['chofer']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/ChoferController.php';

$controller   = new ChoferController($pdo);
$data         = $controller->getDashboardData((int) $_SESSION['usuario_id']);
$viaje_activo = $data['viaje_activo'];
$id_viaje     = $viaje_activo ? (int) $viaje_activo['id_viaje'] : 0;
$mensaje      = '';
$tipo_msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($id_viaje > 0) {
        $resultado = $controller->reportarIncidente($id_viaje, $_POST);
        $mensaje   = $resultado['mensaje'];
        $tipo_msg  = $resultado['ok'] ? 'success' : 'error';
    } else {
        $mensaje  = 'No tenés un viaje activo asignado.';
        $tipo_msg = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Reportar Incidente</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">🚨 REPORTAR INCIDENTE</h1>
                <p class="page-subtitle"><?= $id_viaje > 0 ? "Viaje #{$id_viaje}" : 'Sin viaje activo' ?></p>
            </div>
            <a href="/logitrack/views/chofer/dashboard.php" class="btn btn-secondary">← Volver</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_msg === 'success' ? 'success' : 'error' ?>" style="max-width:560px;">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($id_viaje === 0): ?>
            <div class="alert alert-error" style="max-width:560px;">
                No tenés un viaje activo asignado en este momento. No es posible reportar un incidente.
            </div>
        <?php else: ?>
        <div class="form-card">
            <form method="POST" action="/logitrack/views/chofer/incidente.php">

                <div style="display:flex;flex-direction:column;gap:18px;">

                    <div class="form-group">
                        <label>Tipo de Incidente</label>
                        <select name="tipo_incidente" required>
                            <option value="" disabled selected>Seleccioná el tipo</option>
                            <option value="Accidente">🚗 Accidente</option>
                            <option value="Rotura de vehículo">🔧 Rotura de vehículo</option>
                            <option value="Piquete / Corte de ruta">🚧 Piquete / Corte de ruta</option>
                            <option value="Demora por clima">🌧️ Demora por clima</option>
                            <option value="Paquete dañado">📦 Paquete dañado</option>
                            <option value="Robo">🚨 Robo</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" rows="4"
                                  placeholder="Describí brevemente qué ocurrió..."
                                  maxlength="200" required></textarea>
                        <small style="color:var(--gris);font-size:12px;">Máximo 200 caracteres</small>
                    </div>

                    <button type="submit" class="btn btn-danger" style="width:100%;height:52px;font-size:15px;letter-spacing:2px;">
                        🚨 ENVIAR REPORTE
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
