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
        $dni         = trim($_POST['dni']               ?? '');
        $nombre      = trim($_POST['nombre']            ?? '');
        $apellido    = trim($_POST['apellido']          ?? '');
        $email       = trim($_POST['email']             ?? '');
        $password    = $_POST['password']               ?? '';
        $confirmar   = $_POST['confirmar_password']     ?? '';
        $id_sucursal = (int) ($_POST['id_sucursal']     ?? 0);

        if ($password !== $confirmar) {
            $msg = 'Las contraseñas no coinciden.'; $msg_tipo = 'error';
        } elseif (empty($dni) || empty($nombre) || empty($apellido) || empty($email) || empty($password) || !$id_sucursal) {
            $msg = 'Completá todos los campos.'; $msg_tipo = 'error';
        } else {
            try {
                $model = new UsuarioModel($pdo);
                $model->registrar($dni, $nombre, $apellido, $email, $password, 'empleado', [
                    'id_sucursal' => $id_sucursal,
                ]);
                $msg = "Empleado «{$nombre} {$apellido}» creado correctamente.";
                $msg_tipo = 'success';
            } catch (PDOException $e) {
                $msg = $e->getCode() == 23000
                    ? 'El DNI o email ya están registrados.'
                    : 'Error al crear el empleado.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'eliminar') {
        $id = (int) ($_POST['id_empleado'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("SELECT dni FROM empleado WHERE id_empleado = :id");
                $stmt->execute([':id' => $id]);
                $emp = $stmt->fetch();
                if ($emp) {
                    $pdo->prepare("DELETE FROM empleado WHERE id_empleado = :id")->execute([':id' => $id]);
                    $pdo->prepare("DELETE FROM usuario  WHERE dni = :dni")->execute([':dni' => $emp['dni']]);
                    $msg = 'Empleado eliminado.'; $msg_tipo = 'success';
                }
            } catch (PDOException $e) {
                $msg = 'No se puede eliminar: el empleado tiene registros asociados.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'editar') {
        $id          = (int) ($_POST['id_empleado']   ?? 0);
        $dni         = trim($_POST['dni']             ?? '');
        $nombre      = trim($_POST['nombre']          ?? '');
        $apellido    = trim($_POST['apellido']        ?? '');
        $email       = trim($_POST['email']           ?? '');
        $legajo      = trim($_POST['legajo']          ?? '');
        $id_sucursal = (int) ($_POST['id_sucursal']   ?? 0);

        if (!$id || empty($dni) || empty($nombre) || empty($apellido) || empty($email) || empty($legajo) || !$id_sucursal) {
            $msg = 'Completá todos los campos obligatorios.'; $msg_tipo = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT dni FROM empleado WHERE id_empleado = :id");
                $stmt->execute([':id' => $id]);
                $actual = $stmt->fetch();

                if (!$actual) {
                    $msg = 'Empleado no encontrado.'; $msg_tipo = 'error';
                } else {
                    $pdo->beginTransaction();

                    $pdo->prepare("
                        UPDATE empleado
                        SET dni = :dni, nombre = :nombre, apellido = :apellido,
                            legajo = :legajo, id_sucursal = :id_sucursal
                        WHERE id_empleado = :id
                    ")->execute([
                        ':dni'         => $dni,
                        ':nombre'      => $nombre,
                        ':apellido'    => $apellido,
                        ':legajo'      => $legajo,
                        ':id_sucursal' => $id_sucursal,
                        ':id'          => $id,
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
                    $msg = 'Empleado actualizado correctamente.'; $msg_tipo = 'success';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = $e->getCode() == 23000
                    ? 'El DNI, legajo o email ya están en uso por otro registro.'
                    : 'Error al actualizar el empleado.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'toggle_activo') {
        $id = (int) ($_POST['id_empleado'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE empleado SET activo = 1 - activo WHERE id_empleado = :id")
                ->execute([':id' => $id]);

            $stmt = $pdo->prepare("SELECT dni, activo FROM empleado WHERE id_empleado = :id");
            $stmt->execute([':id' => $id]);
            if ($row = $stmt->fetch()) {
                $pdo->prepare("UPDATE usuario SET activo = :activo WHERE dni = :dni")
                    ->execute([':activo' => $row['activo'], ':dni' => $row['dni']]);
            }

            $msg = 'Estado actualizado.'; $msg_tipo = 'success';
        }
    }

    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_tipo'] = $msg_tipo;
    header('Location: /logitrack/views/admin/empleados.php');
    exit;
}

if (isset($_SESSION['flash_msg'])) {
    $msg      = $_SESSION['flash_msg'];
    $msg_tipo = $_SESSION['flash_tipo'] ?? '';
    unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
}

$f_busqueda = trim($_GET['busqueda'] ?? '');
$f_sucursal = $_GET['sucursal'] ?? '';
$f_estado   = $_GET['estado']   ?? '';

$where  = [];
$params = [];

if ($f_busqueda !== '') {
    $where[] = '(e.nombre LIKE :busqueda1 OR e.apellido LIKE :busqueda2 OR e.dni LIKE :busqueda3 OR e.legajo LIKE :busqueda4)';
    $b = '%' . $f_busqueda . '%';
    $params[':busqueda1'] = $b;
    $params[':busqueda2'] = $b;
    $params[':busqueda3'] = $b;
    $params[':busqueda4'] = $b;
}
if ($f_sucursal !== '') {
    $where[] = 'e.id_sucursal = :sucursal';
    $params[':sucursal'] = $f_sucursal;
}
if ($f_estado === 'activo')        { $where[] = 'e.activo = 1'; }
elseif ($f_estado === 'inactivo')  { $where[] = 'e.activo = 0'; }

$sql = "
    SELECT e.id_empleado, e.dni, e.nombre, e.apellido, e.legajo, e.id_sucursal,
           s.nombre AS sucursal,
           u.email  AS email,
           e.activo AS activo
    FROM   empleado e
    JOIN   sucursal s ON e.id_sucursal = s.id_sucursal
    LEFT   JOIN usuario u ON e.dni = u.dni";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY e.apellido, e.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll();

$sucursales = $pdo->query("SELECT id_sucursal, nombre FROM sucursal ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Empleados</title>
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
                <h1 class="page-title">EMPLEADOS</h1>
                <p class="page-subtitle">Gestión del personal administrativo</p>
            </div>
            <div style="display:flex;gap:10px;">
                <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
                <label for="chk-panel-crear" class="btn btn-primary">
                    + Nuevo Empleado
                </label>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_tipo === 'success' ? 'success' : 'error' ?>" style="max-width:520px;margin-bottom:16px;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <form method="GET" action="/logitrack/views/admin/empleados.php" class="filtros">
            <div class="form-group">
                <label>Buscar</label>
                <input type="text" name="busqueda" placeholder="Nombre, apellido, DNI o legajo" value="<?= htmlspecialchars($f_busqueda) ?>">
            </div>
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
                <label>Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="activo"   <?= $f_estado === 'activo'   ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $f_estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <div class="filtros-acciones">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="/logitrack/views/admin/empleados.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="form-panel" id="panel-crear">
            <h3 style="margin-bottom:20px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">NUEVO EMPLEADO</h3>
            <form method="POST" action="/logitrack/views/admin/empleados.php">
                <input type="hidden" name="accion" value="crear">
                <div class="form-row">
                    <div class="form-group">
                        <label>DNI</label>
                        <input type="text" name="dni" placeholder="Ej: 30123456" required>
                    </div>
                    <div class="form-group">
                        <label>Sucursal</label>
                        <select name="id_sucursal" required>
                            <option value="" disabled selected>Seleccioná...</option>
                            <?php foreach ($sucursales as $s): ?>
                            <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" placeholder="Nombre" required>
                    </div>
                    <div class="form-group">
                        <label>Apellido</label>
                        <input type="text" name="apellido" placeholder="Apellido" required>
                    </div>
                    <div class="form-group full">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="correo@ejemplo.com" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" placeholder="Contraseña" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmar contraseña</label>
                        <input type="password" name="confirmar_password" placeholder="Repetir" required>
                    </div>
                </div>
                <p style="color:var(--gris);font-size:12px;margin-top:8px;">El legajo se asigna automáticamente al crear el empleado.</p>
                <button type="submit" class="btn btn-primary" style="width:100%;height:44px;margin-top:8px;letter-spacing:2px;">
                    CREAR EMPLEADO
                </button>
            </form>
        </div>

        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>DNI</th><th>Nombre</th><th>Legajo</th><th>Sucursal</th><th>Email</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($empleados as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['dni']) ?></td>
                    <td><?= htmlspecialchars($e['apellido'] . ', ' . $e['nombre']) ?></td>
                    <td><code><?= htmlspecialchars($e['legajo']) ?></code></td>
                    <td><?= htmlspecialchars($e['sucursal']) ?></td>
                    <td style="font-size:13px;color:var(--gris);"><?= htmlspecialchars($e['email'] ?? '—') ?></td>
                    <td>
                        <?php if ($e['activo']): ?>
                            <span style="color:#4ade80;font-size:13px;">● Activo</span>
                        <?php else: ?>
                            <span style="color:#f87171;font-size:13px;">● Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <form method="POST" action="/logitrack/views/admin/empleados.php" style="margin:0;">
                            <input type="hidden" name="accion"      value="toggle_activo">
                            <input type="hidden" name="id_empleado" value="<?= $e['id_empleado'] ?>">
                            <button type="submit" class="btn-sm <?= $e['activo'] ? 'btn-toggle-on' : 'btn-toggle-off' ?>">
                                <?= $e['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <a href="#edit-empleado-<?= $e['id_empleado'] ?>" class="btn-sm btn-edit">Editar</a>
                        <a href="#del-empleado-<?= $e['id_empleado'] ?>" class="btn-sm btn-delete">Eliminar</a>
                        <div class="modal-overlay" id="del-empleado-<?= $e['id_empleado'] ?>">
                            <div class="modal-box">
                                <p>¿Eliminar al empleado <strong><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?></strong>? Esta acción no se puede deshacer.</p>
                                <div class="modal-actions">
                                    <a href="#" class="btn btn-secondary">Cancelar</a>
                                    <form method="POST" action="/logitrack/views/admin/empleados.php" style="margin:0;">
                                        <input type="hidden" name="accion"      value="eliminar">
                                        <input type="hidden" name="id_empleado" value="<?= $e['id_empleado'] ?>">
                                        <button type="submit" class="btn-sm btn-delete">Sí, eliminar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="modal-overlay" id="edit-empleado-<?= $e['id_empleado'] ?>">
                            <div class="modal-box modal-box-edit">
                                <h3 style="margin-bottom:16px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">EDITAR EMPLEADO</h3>
                                <form method="POST" action="/logitrack/views/admin/empleados.php">
                                    <input type="hidden" name="accion"      value="editar">
                                    <input type="hidden" name="id_empleado" value="<?= $e['id_empleado'] ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>DNI</label>
                                            <input type="text" name="dni" value="<?= htmlspecialchars($e['dni']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Legajo</label>
                                            <input type="text" name="legajo" value="<?= htmlspecialchars($e['legajo']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Nombre</label>
                                            <input type="text" name="nombre" value="<?= htmlspecialchars($e['nombre']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Apellido</label>
                                            <input type="text" name="apellido" value="<?= htmlspecialchars($e['apellido']) ?>" required>
                                        </div>
                                        <div class="form-group full">
                                            <label>Email</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($e['email'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group full">
                                            <label>Sucursal</label>
                                            <select name="id_sucursal" required>
                                                <?php foreach ($sucursales as $s): ?>
                                                <option value="<?= $s['id_sucursal'] ?>" <?= (int) $e['id_sucursal'] === (int) $s['id_sucursal'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
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
                <?php if (empty($empleados)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--gris);padding:32px;">Sin empleados registrados</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>
</body>
</html>
