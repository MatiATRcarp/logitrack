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
        $nombre = trim($_POST['nombre'] ?? '');
        if (empty($nombre)) {
            $msg = 'El nombre de la sucursal no puede estar vacío.';
            $msg_tipo = 'error';
        } else {
            try {
                $pdo->prepare("INSERT INTO sucursal (nombre) VALUES (:nombre)")
                    ->execute([':nombre' => $nombre]);
                $id_nueva = (int) $pdo->lastInsertId();
                header("Location: /logitrack/views/admin/vehiculos.php?sucursal={$id_nueva}&crear=1");
                exit;
            } catch (PDOException $e) {
                $msg = 'Error al crear la sucursal.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'eliminar') {
        $id = (int) ($_POST['id_sucursal'] ?? 0);
        if ($id) {
            try {
                $pdo->prepare("DELETE FROM sucursal WHERE id_sucursal = :id")->execute([':id' => $id]);
                $msg = 'Sucursal eliminada.';
                $msg_tipo = 'success';
            } catch (PDOException $e) {
                $msg = 'No se puede eliminar: la sucursal tiene vehículos o envíos asociados.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'editar') {
        $id     = (int) ($_POST['id_sucursal'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if (!$id || empty($nombre)) {
            $msg = 'El nombre de la sucursal no puede estar vacío.';
            $msg_tipo = 'error';
        } else {
            try {
                $pdo->prepare("UPDATE sucursal SET nombre = :nombre WHERE id_sucursal = :id")
                    ->execute([':nombre' => $nombre, ':id' => $id]);
                $msg = 'Sucursal actualizada correctamente.';
                $msg_tipo = 'success';
            } catch (PDOException $e) {
                $msg = 'Error al actualizar la sucursal.';
                $msg_tipo = 'error';
            }
        }
    }
}

$f_busqueda = trim($_GET['busqueda'] ?? '');

$where  = '';
$params = [];
if ($f_busqueda !== '') {
    $where = 'WHERE s.nombre LIKE :busqueda';
    $params[':busqueda'] = '%' . $f_busqueda . '%';
}

$sql = "
    SELECT s.id_sucursal, s.nombre,
           COUNT(DISTINCT v.patente) AS cant_vehiculos
    FROM   sucursal s
    LEFT   JOIN vehiculo v ON v.id_sucursal = s.id_sucursal
    $where
    GROUP  BY s.id_sucursal
    ORDER  BY s.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sucursales = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Sucursales</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .form-panel { background:var(--panel); border:1px solid var(--borde); border-radius:16px; padding:24px; max-width:420px; margin-bottom:28px; display:none; }
        .btn-sm { padding:4px 12px; font-size:12px; border-radius:8px; border:none; cursor:pointer; font-family:inherit; letter-spacing:1px; }
        .btn-delete { background:rgba(248,113,113,.1); color:#f87171; border:1px solid #f87171; }
        .btn-delete:hover { background:#f87171; color:#fff; }
        .btn-edit { background:rgba(96,165,250,.1); color:#60a5fa; border:1px solid #60a5fa; }
        .btn-edit:hover { background:#60a5fa; color:#fff; }
        .modal-box-edit { max-width:420px; text-align:left; }
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
                <h1 class="page-title">SUCURSALES</h1>
                <p class="page-subtitle">Gestión de sucursales de la empresa</p>
            </div>
            <div style="display:flex;gap:10px;">
                <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
                <label for="chk-panel-crear" class="btn btn-primary">
                    + Nueva Sucursal
                </label>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_tipo === 'success' ? 'success' : 'error' ?>" style="max-width:420px;margin-bottom:16px;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <form method="GET" action="/logitrack/views/admin/sucursales.php" class="filtros">
            <div class="form-group">
                <label>Buscar</label>
                <input type="text" name="busqueda" placeholder="Nombre de sucursal" value="<?= htmlspecialchars($f_busqueda) ?>">
            </div>
            <div class="filtros-acciones">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="/logitrack/views/admin/sucursales.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="form-panel" id="panel-crear">
            <h3 style="margin-bottom:16px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">NUEVA SUCURSAL</h3>
            <form method="POST" action="/logitrack/views/admin/sucursales.php">
                <input type="hidden" name="accion" value="crear">
                <div class="form-group">
                    <label>Nombre de la sucursal</label>
                    <input type="text" name="nombre" placeholder="Ej: Sucursal Centro" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:44px;margin-top:12px;letter-spacing:2px;">
                    CREAR SUCURSAL
                </button>
            </form>
        </div>

        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>#</th><th>Nombre</th><th>Vehículos</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($sucursales as $s): ?>
                <tr>
                    <td><?= $s['id_sucursal'] ?></td>
                    <td><?= htmlspecialchars($s['nombre']) ?></td>
                    <td><?= $s['cant_vehiculos'] ?></td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a href="#edit-sucursal-<?= $s['id_sucursal'] ?>" class="btn-sm btn-edit">Editar</a>
                        <a href="#del-sucursal-<?= $s['id_sucursal'] ?>" class="btn-sm btn-delete">Eliminar</a>
                        <div class="modal-overlay" id="del-sucursal-<?= $s['id_sucursal'] ?>">
                            <div class="modal-box">
                                <p>¿Eliminar la sucursal «<strong><?= htmlspecialchars($s['nombre']) ?></strong>»? Esta acción no se puede deshacer.</p>
                                <div class="modal-actions">
                                    <a href="#" class="btn btn-secondary">Cancelar</a>
                                    <form method="POST" action="/logitrack/views/admin/sucursales.php" style="margin:0;">
                                        <input type="hidden" name="accion"      value="eliminar">
                                        <input type="hidden" name="id_sucursal" value="<?= $s['id_sucursal'] ?>">
                                        <button type="submit" class="btn-sm btn-delete">Sí, eliminar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="modal-overlay" id="edit-sucursal-<?= $s['id_sucursal'] ?>">
                            <div class="modal-box modal-box-edit">
                                <h3 style="margin-bottom:16px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">EDITAR SUCURSAL</h3>
                                <form method="POST" action="/logitrack/views/admin/sucursales.php">
                                    <input type="hidden" name="accion"      value="editar">
                                    <input type="hidden" name="id_sucursal" value="<?= $s['id_sucursal'] ?>">
                                    <div class="form-group">
                                        <label>Nombre de la sucursal</label>
                                        <input type="text" name="nombre" value="<?= htmlspecialchars($s['nombre']) ?>" required>
                                    </div>
                                    <div class="modal-actions" style="margin-top:16px;">
                                        <a href="#" class="btn btn-secondary">Cancelar</a>
                                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sucursales)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--gris);padding:32px;">Sin sucursales registradas</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>
</body>
</html>
