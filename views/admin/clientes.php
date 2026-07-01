<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['admin']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/UsuarioModel.php';

$hay_filtros = !empty(array_filter($_GET));

$msg      = '';
$msg_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $dni      = trim($_POST['dni']      ?? '');
        $nombre   = trim($_POST['nombre']   ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($dni) || empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
            $msg = 'Completá todos los campos obligatorios.'; $msg_tipo = 'error';
        } else {
            try {
                $model = new UsuarioModel($pdo);
                $model->registrar($dni, $nombre, $apellido, $email, $password, 'cliente', []);

                // Guardar teléfono en cliente (UsuarioModel no lo maneja)
                $pdo->prepare("UPDATE cliente SET telefono = :tel WHERE dni = :dni")
                    ->execute([':tel' => $telefono ?: null, ':dni' => $dni]);

                $msg = "Cliente «{$nombre} {$apellido}» creado correctamente.";
                $msg_tipo = 'success';
            } catch (PDOException $e) {
                $msg = $e->getCode() == 23000
                    ? 'El DNI o email ya están registrados.'
                    : 'Error al crear el cliente.';
                $msg_tipo = 'error';
            } catch (InvalidArgumentException $e) {
                $msg = $e->getMessage(); $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'eliminar') {
        $id = (int) ($_POST['id_cliente'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("SELECT dni FROM cliente WHERE id_cliente = :id");
                $stmt->execute([':id' => $id]);
                $cli = $stmt->fetch();
                if ($cli) {
                    $pdo->prepare("DELETE FROM cliente WHERE id_cliente = :id")->execute([':id' => $id]);
                    $pdo->prepare("DELETE FROM usuario WHERE dni = :dni")->execute([':dni' => $cli['dni']]);
                    $msg = 'Cliente eliminado.'; $msg_tipo = 'success';
                }
            } catch (PDOException $e) {
                $msg = 'No se puede eliminar: el cliente tiene envíos registrados.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'toggle_activo') {
        $id = (int) ($_POST['id_cliente'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE cliente SET activo = 1 - activo WHERE id_cliente = :id")
                ->execute([':id' => $id]);

            $stmt = $pdo->prepare("SELECT dni, activo FROM cliente WHERE id_cliente = :id");
            $stmt->execute([':id' => $id]);
            if ($row = $stmt->fetch()) {
                $pdo->prepare("UPDATE usuario SET activo = :activo WHERE dni = :dni")
                    ->execute([':activo' => $row['activo'], ':dni' => $row['dni']]);
            }

            $msg = 'Estado actualizado.'; $msg_tipo = 'success';
        }
    }

    elseif ($accion === 'editar') {
        $id        = (int) ($_POST['id_cliente'] ?? 0);
        $dni       = trim($_POST['dni']       ?? '');
        $nombre    = trim($_POST['nombre']    ?? '');
        $apellido  = trim($_POST['apellido']  ?? '');
        $email     = trim($_POST['email']     ?? '');
        $telefono  = trim($_POST['telefono']  ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (!$id || empty($dni) || empty($nombre) || empty($apellido) || empty($email)) {
            $msg = 'Completá todos los campos obligatorios.'; $msg_tipo = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT dni FROM cliente WHERE id_cliente = :id");
                $stmt->execute([':id' => $id]);
                $actual = $stmt->fetch();

                if (!$actual) {
                    $msg = 'Cliente no encontrado.'; $msg_tipo = 'error';
                } else {
                    $pdo->beginTransaction();

                    $pdo->prepare("
                        UPDATE cliente
                        SET dni = :dni, nombre = :nombre, apellido = :apellido,
                            telefono = :telefono, direccion = :direccion, email = :email
                        WHERE id_cliente = :id
                    ")->execute([
                        ':dni'       => $dni,
                        ':nombre'    => $nombre,
                        ':apellido'  => $apellido,
                        ':telefono'  => $telefono ?: null,
                        ':direccion' => $direccion ?: null,
                        ':email'     => $email,
                        ':id'        => $id,
                    ]);

                    $pdo->prepare("
                        UPDATE usuario
                        SET dni = :dni, nombre = :nombre, apellido = :apellido, email = :email
                        WHERE dni = :dni_actual
                    ")->execute([
                        ':dni'        => $dni,
                        ':nombre'     => $nombre,
                        ':apellido'   => $apellido,
                        ':email'      => $email,
                        ':dni_actual' => $actual['dni'],
                    ]);

                    $pdo->commit();
                    $msg = 'Cliente actualizado correctamente.'; $msg_tipo = 'success';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = $e->getCode() == 23000
                    ? 'El DNI o email ya están en uso por otro registro.'
                    : 'Error al actualizar el cliente.';
                $msg_tipo = 'error';
            }
        }
    }

    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_tipo'] = $msg_tipo;
    header('Location: /logitrack/views/admin/clientes.php');
    exit;
}

if (isset($_SESSION['flash_msg'])) {
    $msg      = $_SESSION['flash_msg'];
    $msg_tipo = $_SESSION['flash_tipo'] ?? '';
    unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
}

$f_busqueda = trim($_GET['busqueda'] ?? '');
$f_estado   = $_GET['estado']   ?? '';

$where  = [];
$params = [];

if ($f_busqueda !== '') {
    $where[] = '(c.nombre LIKE :busqueda1 OR c.apellido LIKE :busqueda2 OR c.dni LIKE :busqueda3)';
    $b = '%' . $f_busqueda . '%';
    $params[':busqueda1'] = $b;
    $params[':busqueda2'] = $b;
    $params[':busqueda3'] = $b;
}
if ($f_estado === 'activo')        { $where[] = 'c.activo = 1'; }
elseif ($f_estado === 'inactivo')  { $where[] = 'c.activo = 0'; }

$sql = "
    SELECT c.id_cliente, c.dni, c.nombre, c.apellido, c.telefono, c.direccion,
           c.email  AS email,
           c.activo AS activo
    FROM   cliente c";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY c.apellido, c.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Clientes</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .form-panel { background:var(--panel); border:1px solid var(--borde); border-radius:16px; padding:24px; max-width:520px; margin-bottom:28px; display:none; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .form-row .full { grid-column:1/-1; }
        .btn-sm { padding:4px 12px; font-size:12px; border-radius:8px; border:none; cursor:pointer; font-family:inherit; letter-spacing:1px; }
        .btn-delete { background:rgba(248,113,113,.1); color:#f87171; border:1px solid #f87171; }
        .btn-delete:hover { background:#f87171; color:#fff; }
        .btn-toggle-on  { background:rgba(74,222,128,.1);  color:#4ade80; border:1px solid #4ade80; }
        .btn-toggle-off { background:rgba(248,113,113,.1); color:#f87171; border:1px solid #f87171; }
        .btn-edit { background:rgba(96,165,250,.1); color:#60a5fa; border:1px solid #60a5fa; }
        .btn-edit:hover { background:#60a5fa; color:#fff; }
        .modal-box-edit { max-width:520px; text-align:left; }
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
                <h1 class="page-title">CLIENTES</h1>
                <p class="page-subtitle">Gestión de clientes del sistema</p>
            </div>
            <div style="display:flex;gap:10px;">
                <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
                <label for="chk-panel-crear" class="btn btn-primary">
                    + Nuevo Cliente
                </label>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_tipo === 'success' ? 'success' : 'error' ?>" style="max-width:520px;margin-bottom:16px;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <form method="GET" action="/logitrack/views/admin/clientes.php" class="filtros">
            <div class="form-group">
                <label>Buscar</label>
                <input type="text" name="busqueda" placeholder="Nombre, apellido o DNI" value="<?= htmlspecialchars($f_busqueda) ?>">
            </div>
            <div class="form-group">
                <label>Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="activo"   <?= $f_estado === 'activo'   ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $f_estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <div class="filtros-acciones">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="/logitrack/views/admin/clientes.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="form-panel" id="panel-crear">
            <h3 style="margin-bottom:20px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">NUEVO CLIENTE</h3>
            <form method="POST" action="/logitrack/views/admin/clientes.php">
                <input type="hidden" name="accion" value="crear">
                <div class="form-row">
                    <div class="form-group">
                        <label>DNI</label>
                        <input type="text" name="dni" placeholder="Ej: 30123456" required>
                    </div>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" placeholder="Nombre" required>
                    </div>
                    <div class="form-group">
                        <label>Apellido</label>
                        <input type="text" name="apellido" placeholder="Apellido" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="correo@ejemplo.com" required>
                    </div>
                    <div class="form-group full">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" placeholder="Ej: 1122334455">
                    </div>
                    <div class="form-group full">
                        <label>Contraseña</label>
                        <input type="password" name="password" placeholder="Contraseña de acceso al sistema" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:44px;margin-top:16px;letter-spacing:2px;">
                    CREAR CLIENTE
                </button>
            </form>
        </div>

        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>DNI</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['dni']) ?></td>
                    <td><?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre']) ?></td>
                    <td style="font-size:13px;color:var(--gris);"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                    <td style="font-size:13px;color:var(--gris);"><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                    <td>
                        <?php if ($c['activo']): ?>
                            <span style="color:#4ade80;font-size:13px;">● Activo</span>
                        <?php else: ?>
                            <span style="color:#f87171;font-size:13px;">● Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a href="#edit-cliente-<?= $c['id_cliente'] ?>" class="btn-sm btn-edit">Editar</a>
                        <form method="POST" action="/logitrack/views/admin/clientes.php" style="margin:0;">
                            <input type="hidden" name="accion"     value="toggle_activo">
                            <input type="hidden" name="id_cliente" value="<?= $c['id_cliente'] ?>">
                            <button type="submit" class="btn-sm <?= $c['activo'] ? 'btn-toggle-on' : 'btn-toggle-off' ?>">
                                <?= $c['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <a href="#del-cliente-<?= $c['id_cliente'] ?>" class="btn-sm btn-delete">Eliminar</a>
                        <div class="modal-overlay" id="del-cliente-<?= $c['id_cliente'] ?>">
                            <div class="modal-box">
                                <p>¿Eliminar al cliente <strong><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido']) ?></strong>? Esta acción no se puede deshacer.</p>
                                <div class="modal-actions">
                                    <a href="#" class="btn btn-secondary">Cancelar</a>
                                    <form method="POST" action="/logitrack/views/admin/clientes.php" style="margin:0;">
                                        <input type="hidden" name="accion"     value="eliminar">
                                        <input type="hidden" name="id_cliente" value="<?= $c['id_cliente'] ?>">
                                        <button type="submit" class="btn-sm btn-delete">Sí, eliminar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="modal-overlay" id="edit-cliente-<?= $c['id_cliente'] ?>">
                            <div class="modal-box modal-box-edit">
                                <h3 style="margin-bottom:16px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">EDITAR CLIENTE</h3>
                                <form method="POST" action="/logitrack/views/admin/clientes.php">
                                    <input type="hidden" name="accion"     value="editar">
                                    <input type="hidden" name="id_cliente" value="<?= $c['id_cliente'] ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>DNI</label>
                                            <input type="text" name="dni" value="<?= htmlspecialchars($c['dni']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Nombre</label>
                                            <input type="text" name="nombre" value="<?= htmlspecialchars($c['nombre']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Apellido</label>
                                            <input type="text" name="apellido" value="<?= htmlspecialchars($c['apellido']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($c['email'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Teléfono</label>
                                            <input type="text" name="telefono" value="<?= htmlspecialchars($c['telefono'] ?? '') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Dirección</label>
                                            <input type="text" name="direccion" value="<?= htmlspecialchars($c['direccion'] ?? '') ?>">
                                        </div>
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
                <?php if (empty($clientes)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--gris);padding:32px;">Sin clientes registrados</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>
</body>
</html>
