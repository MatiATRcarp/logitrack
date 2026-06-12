<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['admin']);
require_once __DIR__ . '/../../config/db.php';

$hay_filtros = !empty(array_filter($_GET));

$msg      = '';
$msg_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $patente     = strtoupper(trim($_POST['patente']      ?? ''));
        $id_sucursal = (int) ($_POST['id_sucursal']           ?? 0);
        $id_tipo_veh = (int) ($_POST['id_tipo_veh']           ?? 0);

        if (empty($patente) || !$id_sucursal || !$id_tipo_veh) {
            $msg = 'Completá patente, sucursal y tipo de vehículo.'; $msg_tipo = 'error';
        } else {
            try {
                $pdo->prepare("INSERT INTO vehiculo (patente, id_tipo_veh, id_sucursal) VALUES (:p, :t, :s)")
                    ->execute([':p' => $patente, ':t' => $id_tipo_veh, ':s' => $id_sucursal]);
                $msg = "Vehículo {$patente} registrado correctamente.";
                $msg_tipo = 'success';
            } catch (PDOException $e) {
                $msg = $e->getCode() == 23000
                    ? 'Esa patente ya está registrada.'
                    : 'Error al registrar el vehículo.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'eliminar') {
        $patente = strtoupper(trim($_POST['patente'] ?? ''));
        if ($patente) {
            try {
                $pdo->prepare("DELETE FROM vehiculo WHERE patente = :p")->execute([':p' => $patente]);
                $msg = "Vehículo {$patente} eliminado."; $msg_tipo = 'success';
            } catch (PDOException $e) {
                $msg = 'No se puede eliminar: el vehículo tiene viajes registrados.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'cambiar_sucursal') {
        $patente     = strtoupper(trim($_POST['patente']  ?? ''));
        $id_sucursal = (int) ($_POST['id_sucursal']       ?? 0);
        if ($patente && $id_sucursal) {
            $pdo->prepare("UPDATE vehiculo SET id_sucursal = :s WHERE patente = :p")
                ->execute([':s' => $id_sucursal, ':p' => $patente]);
            $msg = "Sucursal del vehículo {$patente} actualizada."; $msg_tipo = 'success';
        }
    }
}

$f_sucursal = $_GET['sucursal'] ?? '';
$f_patente  = trim($_GET['patente'] ?? '');

$where  = [];
$params = [];

if ($f_sucursal !== '') {
    $where[] = 'v.id_sucursal = :sucursal';
    $params[':sucursal'] = $f_sucursal;
}
if ($f_patente !== '') {
    $where[] = 'v.patente LIKE :patente';
    $params[':patente'] = '%' . strtoupper($f_patente) . '%';
}

$sql = "
    SELECT v.patente, s.id_sucursal, s.nombre AS sucursal
    FROM   vehiculo v
    JOIN   sucursal s ON v.id_sucursal = s.id_sucursal";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY s.nombre, v.patente';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehiculos = $stmt->fetchAll();

$sucursales = $pdo->query("SELECT id_sucursal, nombre FROM sucursal ORDER BY nombre")->fetchAll();

$tipos_vehiculo = $pdo->query("SELECT id_tipo_veh, nombre, capacidad_kg_max FROM tipo_vehiculo ORDER BY capacidad_kg_max")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Vehículos</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .form-panel { background:var(--panel); border:1px solid var(--borde); border-radius:16px; padding:24px; max-width:420px; margin-bottom:28px; display:none; }
        .btn-sm { padding:4px 12px; font-size:12px; border-radius:8px; border:none; cursor:pointer; font-family:inherit; letter-spacing:1px; }
        .btn-delete { background:rgba(248,113,113,.1); color:#f87171; border:1px solid #f87171; }
        .btn-delete:hover { background:#f87171; color:#fff; }
        .inline-select { background:var(--fondo); color:var(--texto); border:1px solid var(--borde); border-radius:8px; padding:4px 8px; font-size:12px; font-family:inherit; }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <input type="checkbox" id="chk-panel-crear" class="toggle-checkbox">
        <input type="checkbox" id="chk-filtros" class="toggle-filtros" <?= $hay_filtros ? 'checked' : '' ?>>

        <div class="topbar">
            <div>
                <h1 class="page-title">VEHÍCULOS</h1>
                <p class="page-subtitle">Gestión de la flota de vehículos</p>
            </div>
            <div style="display:flex;gap:10px;">
                <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
                <label for="chk-panel-crear" class="btn btn-primary">
                    + Nuevo Vehículo
                </label>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_tipo === 'success' ? 'success' : 'error' ?>" style="max-width:420px;margin-bottom:16px;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <form method="GET" action="/logitrack/views/admin/vehiculos.php" class="filtros">
            <div class="form-group">
                <label>Sucursal</label>
                <select name="sucursal">
                    <option value="">Todas</option>
                    <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['id_sucursal'] ?>" <?= (string) $f_sucursal === (string) $s['id_sucursal'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Patente</label>
                <input type="text" name="patente" placeholder="Ej: ABC123" value="<?= htmlspecialchars($f_patente) ?>">
            </div>
            <div class="filtros-acciones">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="/logitrack/views/admin/vehiculos.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="form-panel" id="panel-crear">
            <h3 style="margin-bottom:16px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">NUEVO VEHÍCULO</h3>
            <form method="POST" action="/logitrack/views/admin/vehiculos.php">
                <input type="hidden" name="accion" value="crear">
                <div class="form-group">
                    <label>Patente</label>
                    <input type="text" name="patente" placeholder="Ej: ABC123" maxlength="10" required style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label>Sucursal asignada</label>
                    <select name="id_sucursal" required>
                        <option value="">Seleccioná...</option>
                        <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo de vehículo</label>
                    <select name="id_tipo_veh" required>
                        <option value="">Seleccioná...</option>
                        <?php foreach ($tipos_vehiculo as $t): ?>
                        <option value="<?= $t['id_tipo_veh'] ?>"><?= htmlspecialchars($t['nombre']) ?> (hasta <?= number_format((float) $t['capacidad_kg_max'], 0) ?> kg)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:44px;margin-top:12px;letter-spacing:2px;">
                    REGISTRAR VEHÍCULO
                </button>
            </form>
        </div>

        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>Patente</th><th>Sucursal</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($vehiculos as $v): ?>
                <tr>
                    <td><code><?= htmlspecialchars($v['patente']) ?></code></td>
                    <td>
                        <!-- Cambiar sucursal -->
                        <form method="POST" action="/logitrack/views/admin/vehiculos.php" style="margin:0;display:flex;gap:6px;align-items:center;">
                            <input type="hidden" name="accion"  value="cambiar_sucursal">
                            <input type="hidden" name="patente" value="<?= htmlspecialchars($v['patente']) ?>">
                            <select name="id_sucursal" class="inline-select">
                                <?php foreach ($sucursales as $s): ?>
                                <option value="<?= $s['id_sucursal'] ?>" <?= $s['id_sucursal'] == $v['id_sucursal'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-sm btn-secondary">Mover</button>
                        </form>
                    </td>
                    <td>
                        <a href="#del-vehiculo-<?= htmlspecialchars($v['patente']) ?>" class="btn-sm btn-delete">Eliminar</a>
                        <div class="modal-overlay" id="del-vehiculo-<?= htmlspecialchars($v['patente']) ?>">
                            <div class="modal-box">
                                <p>¿Eliminar el vehículo <strong><?= htmlspecialchars($v['patente']) ?></strong>? Esta acción no se puede deshacer.</p>
                                <div class="modal-actions">
                                    <a href="#" class="btn btn-secondary">Cancelar</a>
                                    <form method="POST" action="/logitrack/views/admin/vehiculos.php" style="margin:0;">
                                        <input type="hidden" name="accion"  value="eliminar">
                                        <input type="hidden" name="patente" value="<?= htmlspecialchars($v['patente']) ?>">
                                        <button type="submit" class="btn-sm btn-delete">Sí, eliminar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($vehiculos)): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--gris);padding:32px;">Sin vehículos registrados</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>
</body>
</html>
