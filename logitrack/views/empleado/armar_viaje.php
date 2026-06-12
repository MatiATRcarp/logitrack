<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['empleado', 'admin']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/EmpleadoController.php';

$controller = new EmpleadoController($pdo);

$choferes   = $pdo->query("SELECT id_chofer, nombre, apellido FROM chofer ORDER BY apellido")->fetchAll();
$choferesOcupados = $pdo->query("
    SELECT id_chofer, MAX(fecha_llegada_est) AS disponible_desde
    FROM   viaje
    WHERE  fecha_llegada_est IS NOT NULL AND fecha_llegada_est >= NOW()
    GROUP  BY id_chofer
")->fetchAll(PDO::FETCH_KEY_PAIR);
$vehiculos  = $pdo->query("SELECT v.patente, s.nombre AS sucursal FROM vehiculo v JOIN sucursal s ON v.id_sucursal = s.id_sucursal ORDER BY s.nombre")->fetchAll();
$sucursales = $pdo->query("SELECT id_sucursal, nombre FROM sucursal ORDER BY nombre")->fetchAll();

$mensaje  = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->armarViajeConEnvios($_POST);
    $mensaje   = $resultado['mensaje'];
    $tipo_msg  = $resultado['ok'] ? 'success' : 'error';
}

$envios_disponibles = $controller->getEnviosDisponibles();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Armar Viaje</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar">
            <div>
                <h1 class="page-title">ARMAR VIAJE</h1>
                <p class="page-subtitle">Asignar chofer y vehículo a un nuevo viaje</p>
            </div>
            <a href="/logitrack/views/empleado/dashboard.php" class="btn btn-secondary">← Volver</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_msg === 'success' ? 'success' : 'error' ?>" style="max-width:560px;margin-bottom:20px;">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($choferesOcupados)): ?>
            <div class="alert alert-info" style="max-width:560px;margin-bottom:20px;">
                <strong>⚠️ Choferes con viaje en curso o programado:</strong>
                <ul style="margin:8px 0 0 18px;padding:0;">
                    <?php foreach ($choferes as $c): ?>
                        <?php if (!isset($choferesOcupados[$c['id_chofer']])) continue; ?>
                        <li>
                            <?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre']) ?>
                            — disponible desde <?= date('d/m/Y H:i', strtotime($choferesOcupados[$c['id_chofer']])) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/logitrack/views/empleado/armar_viaje.php">

            <div class="tabla-wrapper">
                <div class="tabla-header">
                    <h3>📦 Envíos en Almacén de Origen</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Tracking</th>
                            <th>Destinatario</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Peso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($envios_disponibles as $e): ?>
                        <tr>
                            <td><input type="checkbox" name="envios[]" value="<?= (int) $e['id_envio'] ?>"></td>
                            <td><code><?= htmlspecialchars($e['nro_tracking']) ?></code></td>
                            <td><?= htmlspecialchars($e['destinatario']) ?></td>
                            <td><?= htmlspecialchars($e['sucursal_origen']) ?></td>
                            <td><?= htmlspecialchars($e['sucursal_destino']) ?></td>
                            <td><?= number_format((float) $e['peso_kg'], 2) ?> kg</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($envios_disponibles)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--gris);padding:32px;">Sin envíos en almacén de origen</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-card" style="max-width:560px;">
                <div class="form-grid">

                    <div class="form-group">
                        <label>Chofer</label>
                        <select name="id_chofer" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($choferes as $c): ?>
                            <option value="<?= $c['id_chofer'] ?>">
                                <?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Vehículo (patente)</label>
                        <select name="patente" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($vehiculos as $v): ?>
                            <option value="<?= htmlspecialchars($v['patente']) ?>"><?= htmlspecialchars($v['patente'] . ' — ' . $v['sucursal']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Fecha y hora de salida</label>
                        <input type="datetime-local" name="fecha_salida" required
                               value="<?= htmlspecialchars($_POST['fecha_salida'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Llegada estimada (opcional)</label>
                        <input type="datetime-local" name="fecha_llegada_est"
                               value="<?= htmlspecialchars($_POST['fecha_llegada_est'] ?? '') ?>">
                    </div>

                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:50px;margin-top:20px;font-size:15px;letter-spacing:2px;">
                    🚚 CREAR VIAJE
                </button>
            </div>
        </form>
    </main>
</div>
</body>
</html>
