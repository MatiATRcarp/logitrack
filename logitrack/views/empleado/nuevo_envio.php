<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['empleado', 'admin']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/EmpleadoController.php';

$controller = new EmpleadoController($pdo);
$mensaje    = '';
$tipo_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->crearEnvio($_POST);
    if ($resultado['ok']) {
        $mensaje  = "✓ Envío creado. Tracking: <strong>{$resultado['nro_tracking']}</strong>";
        $tipo_msg = 'success';
    } else {
        $mensaje  = $resultado['mensaje'];
        $tipo_msg = 'error';
    }
}

$form            = $controller->getFormData();
$tipos_contenido = $form['tipos_contenido'];
$sucursales      = $form['sucursales'];
$clientes        = $form['clientes'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Nuevo Envío</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">NUEVO ENVÍO</h1>
                <p class="page-subtitle">Registrar un nuevo paquete en el sistema</p>
            </div>
            <a href="/logitrack/views/empleado/dashboard.php" class="btn btn-secondary">← Volver</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_msg === 'success' ? 'success' : 'error' ?>" style="max-width:640px;margin-bottom:20px;">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <div class="form-card" style="max-width:640px;">
            <form method="POST" action="/logitrack/views/empleado/nuevo_envio.php">

                <div class="form-grid">

                    <div class="form-group">
                        <label>Remitente (quien envía)</label>
                        <select name="id_remitente" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id_cliente'] ?>"
                                <?= (isset($_POST['id_remitente']) && $_POST['id_remitente'] == $c['id_cliente']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre'] . ' — DNI: ' . $c['dni']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Destinatario (quien recibe)</label>
                        <select name="id_destinatario" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id_cliente'] ?>"
                                <?= (isset($_POST['id_destinatario']) && $_POST['id_destinatario'] == $c['id_cliente']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre'] . ' — DNI: ' . $c['dni']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sucursal Origen</label>
                        <select name="id_suc_origen" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($sucursales as $s): ?>
                            <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sucursal Destino</label>
                        <select name="id_suc_destino" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($sucursales as $s): ?>
                            <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Contenido</label>
                        <select name="id_tipo_cont" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($tipos_contenido as $tc): ?>
                            <option value="<?= $tc['id_tipo_cont'] ?>"><?= htmlspecialchars($tc['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Peso (kg)</label>
                        <input type="number" name="peso_kg"
                               min="0.01" max="30000" step="0.01"
                               placeholder="Ej: 12.5"
                               value="<?= htmlspecialchars($_POST['peso_kg'] ?? '') ?>"
                               required>
                    </div>

                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;height:50px;margin-top:20px;font-size:15px;letter-spacing:2px;">
                    📦 REGISTRAR ENVÍO
                </button>

            </form>
        </div>
    </main>
</div>
</body>
</html>
