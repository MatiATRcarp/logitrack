<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['empleado', 'admin']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/EmpleadoController.php';

$controller = new EmpleadoController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'armar';

    if ($accion === 'eliminar_envio') {
        $id_envio = (int) ($_POST['id_envio'] ?? 0);
        if ($id_envio > 0) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM viaje_envio WHERE id_envio = :id");
                $stmt->execute([':id' => $id_envio]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $_SESSION['flash_msg']  = 'No se puede eliminar: el envío ya fue asignado a un viaje.';
                    $_SESSION['flash_tipo'] = 'error';
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM historial_estado WHERE id_envio = :id")->execute([':id' => $id_envio]);
                    $pdo->prepare("DELETE FROM envio WHERE id_envio = :id")->execute([':id' => $id_envio]);
                    $pdo->commit();
                    $_SESSION['flash_msg']  = 'Envío eliminado correctamente.';
                    $_SESSION['flash_tipo'] = 'success';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['flash_msg']  = 'Error al eliminar el envío.';
                $_SESSION['flash_tipo'] = 'error';
            }
        }
        header("Location: /logitrack/views/empleado/armar_viaje.php");
        exit;
    }

    // Acción: armar viaje
    $resultado = $controller->armarViajeConEnvios($_POST);
    $_SESSION['flash_msg']  = $resultado['mensaje'];
    $_SESSION['flash_tipo'] = $resultado['ok'] ? 'success' : 'error';
    header("Location: /logitrack/views/empleado/armar_viaje.php");
    exit;
}

$mensaje  = '';
$tipo_msg = '';
if (!empty($_SESSION['flash_msg'])) {
    $mensaje  = $_SESSION['flash_msg'];
    $tipo_msg = $_SESSION['flash_tipo'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
}

$choferes   = $pdo->query("SELECT id_chofer, nombre, apellido FROM chofer ORDER BY apellido")->fetchAll();
$choferesOcupados = $pdo->query("
    SELECT id_chofer, MAX(fecha_llegada_est) AS disponible_desde
    FROM   viaje
    WHERE  fecha_llegada_est IS NOT NULL AND fecha_llegada_est >= NOW()
    GROUP  BY id_chofer
")->fetchAll(PDO::FETCH_KEY_PAIR);
$vehiculos  = $pdo->query("
    SELECT v.patente, s.nombre AS sucursal, tv.capacidad_kg_max
    FROM   vehiculo v
    JOIN   sucursal    s  ON v.id_sucursal  = s.id_sucursal
    JOIN   tipo_vehiculo tv ON v.id_tipo_veh = tv.id_tipo_veh
    ORDER  BY tv.capacidad_kg_max DESC, s.nombre
")->fetchAll();
$tipos_contenido = $pdo->query("SELECT id_tipo_cont, nombre FROM tipo_contenido ORDER BY nombre")->fetchAll();
$sucursales = $pdo->query("SELECT id_sucursal, nombre FROM sucursal ORDER BY nombre")->fetchAll();

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
    <style>
        .btn-sm { padding:4px 12px; font-size:12px; border-radius:8px; border:none; cursor:pointer; font-family:inherit; letter-spacing:1px; }
        .btn-delete { background:rgba(248,113,113,.1); color:#f87171; border:1px solid #f87171; }
        .btn-delete:hover { background:#f87171; color:#fff; }
    </style>
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
                <?= $mensaje ?>
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

        <!-- Tabla de envíos FUERA del form principal; los checkboxes se asocian via form="form-viaje" -->
        <div class="tabla-wrapper">
            <div class="tabla-header" style="flex-wrap:wrap;gap:10px;">
                <h3>📦 Envíos en Almacén de Origen</h3>
                <div style="display:flex;align-items:center;gap:10px;">
                    <label style="font-size:13px;color:var(--gris);">Filtrar por tipo:</label>
                    <select id="filtro-tipo" style="padding:6px 10px;background:var(--panel);border:1px solid var(--borde);border-radius:8px;color:var(--texto);font-size:13px;">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_contenido as $tc): ?>
                        <option value="<?= htmlspecialchars($tc['nombre']) ?>"><?= htmlspecialchars($tc['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span id="peso-seleccionado" style="font-size:13px;color:var(--amarillo);">Seleccionado: 0 kg</span>
                </div>
            </div>
            <table id="tabla-envios">
                <thead>
                    <tr>
                        <th></th>
                        <th>Tracking</th>
                        <th>Tipo</th>
                        <th>Destinatario</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Peso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($envios_disponibles as $e): ?>
                    <tr data-tipo="<?= htmlspecialchars($e['tipo_contenido'] ?? '') ?>" data-peso="<?= (float)$e['peso_kg'] ?>">
                        <td><input type="checkbox" name="envios[]" value="<?= (int) $e['id_envio'] ?>" form="form-viaje" class="chk-envio"></td>
                        <td><code><?= htmlspecialchars($e['nro_tracking']) ?></code></td>
                        <td style="font-size:12px;color:var(--gris);"><?= htmlspecialchars($e['tipo_contenido'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($e['destinatario']) ?></td>
                        <td><?= htmlspecialchars($e['sucursal_origen']) ?></td>
                        <td><?= htmlspecialchars($e['sucursal_destino']) ?></td>
                        <td><?= number_format((float) $e['peso_kg'], 2) ?> kg</td>
                        <td>
                            <a href="#del-envio-<?= (int) $e['id_envio'] ?>" class="btn-sm btn-delete">Eliminar</a>
                            <div class="modal-overlay" id="del-envio-<?= (int) $e['id_envio'] ?>">
                                <div class="modal-box">
                                    <p>¿Eliminar el envío <strong><?= htmlspecialchars($e['nro_tracking']) ?></strong>?<br>
                                    Se eliminará permanentemente de la base de datos.</p>
                                    <div class="modal-actions">
                                        <a href="#" class="btn btn-secondary">Cancelar</a>
                                        <form method="POST" action="/logitrack/views/empleado/armar_viaje.php" style="margin:0;">
                                            <input type="hidden" name="accion"   value="eliminar_envio">
                                            <input type="hidden" name="id_envio" value="<?= (int) $e['id_envio'] ?>">
                                            <button type="submit" class="btn-sm btn-delete">Sí, eliminar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($envios_disponibles)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--gris);padding:32px;">Sin envíos en almacén de origen</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <form method="POST" action="/logitrack/views/empleado/armar_viaje.php" id="form-viaje">
            <input type="hidden" name="accion" value="armar">
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
                        <select name="patente" id="sel-vehiculo" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($vehiculos as $v): ?>
                            <option value="<?= htmlspecialchars($v['patente']) ?>"
                                    data-cap="<?= (float)$v['capacidad_kg_max'] ?>">
                                <?= htmlspecialchars($v['patente'] . ' — ' . $v['sucursal']) ?>
                                (cap. <?= number_format((float)$v['capacidad_kg_max'], 0) ?> kg)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="aviso-capacidad" style="font-size:12px;margin-top:4px;"></div>
                    </div>

                    <div class="form-group">
                        <label>Fecha y hora de salida</label>
                        <input type="datetime-local" name="fecha_salida" required>
                    </div>

                    <div class="form-group">
                        <label>Llegada estimada (opcional)</label>
                        <input type="datetime-local" name="fecha_llegada_est">
                    </div>

                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:50px;margin-top:20px;font-size:15px;letter-spacing:2px;">
                    🚚 CREAR VIAJE
                </button>
            </div>
        </form>
    </main>
</div>
<script>
(function () {
    const filtroTipo   = document.getElementById('filtro-tipo');
    const pesoLabel    = document.getElementById('peso-seleccionado');
    const selVehiculo  = document.getElementById('sel-vehiculo');
    const avisoCapac   = document.getElementById('aviso-capacidad');
    const tablaFilas   = document.querySelectorAll('#tabla-envios tbody tr[data-peso]');

    function actualizarPeso() {
        let total = 0;
        tablaFilas.forEach(row => {
            const chk = row.querySelector('.chk-envio');
            if (chk && chk.checked) total += parseFloat(row.dataset.peso || 0);
        });
        pesoLabel.textContent = 'Seleccionado: ' + total.toFixed(2) + ' kg';
        verificarCapacidad(total);
    }

    function verificarCapacidad(pesoTotal) {
        const opt = selVehiculo.options[selVehiculo.selectedIndex];
        if (!opt || !opt.dataset.cap) { avisoCapac.textContent = ''; return; }
        const cap = parseFloat(opt.dataset.cap);
        if (pesoTotal > cap) {
            avisoCapac.innerHTML = '<span style="color:#f87171;">⚠ Peso total (' + pesoTotal.toFixed(2) + ' kg) supera la capacidad del vehículo (' + cap.toFixed(0) + ' kg).</span>';
        } else if (pesoTotal > 0) {
            avisoCapac.innerHTML = '<span style="color:#4ade80;">✓ Dentro de capacidad (' + pesoTotal.toFixed(2) + ' / ' + cap.toFixed(0) + ' kg).</span>';
        } else {
            avisoCapac.textContent = '';
        }
    }

    filtroTipo.addEventListener('change', function () {
        const tipo = this.value.toLowerCase();
        tablaFilas.forEach(row => {
            if (!tipo || row.dataset.tipo.toLowerCase() === tipo) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                const chk = row.querySelector('.chk-envio');
                if (chk) chk.checked = false;
            }
        });
        actualizarPeso();
    });

    document.querySelectorAll('.chk-envio').forEach(chk => {
        chk.addEventListener('change', actualizarPeso);
    });

    selVehiculo.addEventListener('change', function () {
        let total = 0;
        tablaFilas.forEach(row => {
            const chk = row.querySelector('.chk-envio');
            if (chk && chk.checked) total += parseFloat(row.dataset.peso || 0);
        });
        verificarCapacidad(total);
    });
})();
</script>
</body>
</html>
