<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['admin']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/UsuarioModel.php';

$hay_filtros = !empty(array_filter($_GET));

$msg     = '';
$msg_tipo = '';

// ─── Acciones POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $dni       = trim($_POST['dni']               ?? '');
        $nombre    = trim($_POST['nombre']            ?? '');
        $apellido  = trim($_POST['apellido']          ?? '');
        $email     = trim($_POST['email']             ?? '');
        $rol       = trim($_POST['rol']               ?? '');
        $password  = $_POST['password']               ?? '';
        $confirmar = $_POST['confirmar_password']     ?? '';

        if ($password !== $confirmar) {
            $msg = 'Las contraseñas no coinciden.';
            $msg_tipo = 'error';
        } elseif (empty($dni) || empty($nombre) || empty($apellido) || empty($email) || empty($rol) || empty($password)) {
            $msg = 'Completá todos los campos obligatorios.';
            $msg_tipo = 'error';
        } else {
            $extra = [];
            if ($rol === 'chofer') {
                $legajo        = trim($_POST['legajo']        ?? '');
                $tipo_licencia = trim($_POST['tipo_licencia'] ?? '');
                if (empty($legajo) || empty($tipo_licencia)) {
                    $msg = 'Completá legajo y tipo de licencia para choferes.';
                    $msg_tipo = 'error';
                } else {
                    $extra = ['legajo' => $legajo, 'tipo_licencia' => $tipo_licencia];
                }
            } elseif ($rol === 'empleado') {
                $id_sucursal_emp = (int) ($_POST['id_sucursal_empleado'] ?? 0);
                if (!$id_sucursal_emp) {
                    $msg = 'Seleccioná la sucursal del empleado.';
                    $msg_tipo = 'error';
                } else {
                    $extra = ['id_sucursal' => $id_sucursal_emp];
                }
            }
            if ($msg_tipo !== 'error') {
                try {
                    $model = new UsuarioModel($pdo);
                    $model->registrar($dni, $nombre, $apellido, $email, $password, $rol, $extra);
                    $msg = "Usuario «{$nombre} {$apellido}» creado correctamente.";
                    $msg_tipo = 'success';
                } catch (PDOException $e) {
                    $msg = $e->getCode() == 23000
                        ? 'El DNI o email ya están registrados.'
                        : 'Error al crear el usuario.';
                    $msg_tipo = 'error';
                } catch (InvalidArgumentException $e) {
                    $msg = $e->getMessage();
                    $msg_tipo = 'error';
                }
            }
        }
    }

    elseif ($accion === 'toggle_activo') {
        $id = (int) ($_POST['id_usuario'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE usuario SET activo = 1 - activo WHERE id_usuario = :id")
                ->execute([':id' => $id]);
            $msg = 'Estado del usuario actualizado.';
            $msg_tipo = 'success';
        }
    }

    elseif ($accion === 'editar') {
        $id       = (int) ($_POST['id_usuario'] ?? 0);
        $dni      = trim($_POST['dni']      ?? '');
        $nombre   = trim($_POST['nombre']   ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email    = trim($_POST['email']    ?? '');

        if (!$id || empty($dni) || empty($nombre) || empty($apellido) || empty($email)) {
            $msg = 'Completá todos los campos obligatorios.'; $msg_tipo = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT u.dni, r.nombre AS rol FROM usuario u JOIN rol r ON u.id_rol = r.id_rol WHERE u.id_usuario = :id");
                $stmt->execute([':id' => $id]);
                $actual = $stmt->fetch();

                if (!$actual) {
                    $msg = 'Usuario no encontrado.'; $msg_tipo = 'error';
                } else {
                    $pdo->beginTransaction();

                    $pdo->prepare("
                        UPDATE usuario
                        SET dni = :dni, nombre = :nombre, apellido = :apellido, email = :email
                        WHERE id_usuario = :id
                    ")->execute([
                        ':dni'      => $dni,
                        ':nombre'   => $nombre,
                        ':apellido' => $apellido,
                        ':email'    => $email,
                        ':id'       => $id,
                    ]);

                    if (in_array($actual['rol'], ['chofer', 'cliente'], true)) {
                        $pdo->prepare("
                            UPDATE {$actual['rol']}
                            SET dni = :dni, nombre = :nombre, apellido = :apellido, email = :email
                            WHERE dni = :dni_actual
                        ")->execute([
                            ':dni'        => $dni,
                            ':nombre'     => $nombre,
                            ':apellido'   => $apellido,
                            ':email'      => $email,
                            ':dni_actual' => $actual['dni'],
                        ]);
                    } elseif ($actual['rol'] === 'empleado') {
                        $pdo->prepare("
                            UPDATE empleado
                            SET dni = :dni, nombre = :nombre, apellido = :apellido
                            WHERE dni = :dni_actual
                        ")->execute([
                            ':dni'        => $dni,
                            ':nombre'     => $nombre,
                            ':apellido'   => $apellido,
                            ':dni_actual' => $actual['dni'],
                        ]);
                    }

                    $pdo->commit();
                    $msg = 'Usuario actualizado correctamente.'; $msg_tipo = 'success';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = $e->getCode() == 23000
                    ? 'El DNI o email ya están en uso por otro registro.'
                    : 'Error al actualizar el usuario.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'eliminar') {
        $id = (int) ($_POST['id_usuario'] ?? 0);
        if ($id) {
            try {
                // Obtener DNI y rol para limpiar tablas vinculadas
                $stmt = $pdo->prepare("SELECT u.dni, r.nombre AS rol FROM usuario u JOIN rol r ON u.id_rol = r.id_rol WHERE u.id_usuario = :id");
                $stmt->execute([':id' => $id]);
                $usr = $stmt->fetch();
                if ($usr) {
                    if ($usr['rol'] === 'chofer') {
                        $pdo->prepare("DELETE FROM chofer WHERE dni = :dni")->execute([':dni' => $usr['dni']]);
                    } elseif ($usr['rol'] === 'cliente') {
                        $pdo->prepare("DELETE FROM cliente WHERE dni = :dni")->execute([':dni' => $usr['dni']]);
                    }
                    $pdo->prepare("DELETE FROM usuario WHERE id_usuario = :id")->execute([':id' => $id]);
                    $msg = 'Usuario eliminado.';
                    $msg_tipo = 'success';
                }
            } catch (PDOException $e) {
                $msg = 'No se puede eliminar: el usuario tiene registros asociados (viajes, envíos).';
                $msg_tipo = 'error';
            }
        }
    }
}

// ─── Datos ────────────────────────────────────────────────────────────────────
$f_busqueda = trim($_GET['busqueda'] ?? '');
$f_rol      = $_GET['rol']      ?? '';
$f_estado   = $_GET['estado']   ?? '';

$where  = [];
$params = [];

if ($f_busqueda !== '') {
    $where[] = '(u.nombre LIKE :busqueda1 OR u.apellido LIKE :busqueda2 OR u.dni LIKE :busqueda3 OR u.email LIKE :busqueda4)';
    $b = '%' . $f_busqueda . '%';
    $params[':busqueda1'] = $b;
    $params[':busqueda2'] = $b;
    $params[':busqueda3'] = $b;
    $params[':busqueda4'] = $b;
}
if ($f_rol !== '') {
    $where[] = 'r.nombre = :rol';
    $params[':rol'] = $f_rol;
}
if ($f_estado === 'activo')        { $where[] = 'u.activo = 1'; }
elseif ($f_estado === 'inactivo')  { $where[] = 'u.activo = 0'; }

$sql = "
    SELECT u.id_usuario, u.dni, u.nombre, u.apellido, u.email, r.nombre AS rol, u.activo
    FROM   usuario u
    JOIN   rol     r ON u.id_rol = r.id_rol";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY r.nombre, u.apellido, u.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$sucursales = $pdo->query("SELECT id_sucursal, nombre FROM sucursal ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Usuarios</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .form-panel { background:var(--panel); border:1px solid var(--borde); border-radius:16px; padding:24px; max-width:680px; margin-bottom:28px; display:none; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .form-row .full { grid-column:1/-1; }
        .extra-chofer, .extra-empleado { display:none; }
        .form-row:has(#sel-rol option[value="chofer"]:checked) .extra-chofer { display:block; }
        .form-row:has(#sel-rol option[value="empleado"]:checked) .extra-empleado { display:block; }
        .badge-admin    { background:rgba(168,85,247,.15); color:#a855f7; border:1px solid #a855f7; padding:2px 10px; border-radius:6px; font-size:12px; }
        .badge-empleado { background:rgba(59,130,246,.15); color:#3b82f6; border:1px solid #3b82f6; padding:2px 10px; border-radius:6px; font-size:12px; }
        .badge-chofer   { background:rgba(234,179,8,.15);  color:#eab308; border:1px solid #eab308; padding:2px 10px; border-radius:6px; font-size:12px; }
        .badge-cliente  { background:rgba(34,197,94,.15);  color:#22c55e; border:1px solid #22c55e; padding:2px 10px; border-radius:6px; font-size:12px; }
        .btn-sm { padding:4px 12px; font-size:12px; border-radius:8px; border:none; cursor:pointer; font-family:inherit; letter-spacing:1px; }
        .btn-toggle-on  { background:rgba(74,222,128,.1); color:#4ade80; border:1px solid #4ade80; }
        .btn-toggle-off { background:rgba(248,113,113,.1); color:#f87171; border:1px solid #f87171; }
        .btn-delete { background:rgba(248,113,113,.1); color:#f87171; border:1px solid #f87171; }
        .btn-delete:hover { background:#f87171; color:#fff; }
        .btn-edit { background:rgba(96,165,250,.1); color:#60a5fa; border:1px solid #60a5fa; }
        .btn-edit:hover { background:#60a5fa; color:#fff; }
        .modal-box-edit { max-width:480px; text-align:left; }
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
                <h1 class="page-title">USUARIOS</h1>
                <p class="page-subtitle">Gestión de todos los usuarios del sistema</p>
            </div>
            <div style="display:flex;gap:10px;">
                <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
                <label for="chk-panel-crear" class="btn btn-primary">
                    + Nuevo Usuario
                </label>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_tipo === 'success' ? 'success' : 'error' ?>" style="max-width:680px;margin-bottom:16px;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <form method="GET" action="/logitrack/views/admin/usuarios.php" class="filtros">
            <div class="form-group">
                <label>Buscar</label>
                <input type="text" name="busqueda" placeholder="Nombre, apellido, DNI o email" value="<?= htmlspecialchars($f_busqueda) ?>">
            </div>
            <div class="form-group">
                <label>Rol</label>
                <select name="rol">
                    <option value="">Todos</option>
                    <option value="admin"    <?= $f_rol === 'admin'    ? 'selected' : '' ?>>Admin</option>
                    <option value="empleado" <?= $f_rol === 'empleado' ? 'selected' : '' ?>>Empleado</option>
                    <option value="chofer"   <?= $f_rol === 'chofer'   ? 'selected' : '' ?>>Chofer</option>
                    <option value="cliente"  <?= $f_rol === 'cliente'  ? 'selected' : '' ?>>Cliente</option>
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
                <a href="/logitrack/views/admin/usuarios.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <!-- Formulario de creación -->
        <div class="form-panel" id="panel-crear">
            <h3 style="margin-bottom:20px;font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:2px;">NUEVO USUARIO</h3>
            <form method="POST" action="/logitrack/views/admin/usuarios.php">
                <input type="hidden" name="accion" value="crear">
                <div class="form-row">
                    <div class="form-group">
                        <label>DNI</label>
                        <input type="text" name="dni" placeholder="Ej: 30123456" required>
                    </div>
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="rol" id="sel-rol" required>
                            <option value="">Seleccioná...</option>
                            <option value="admin">Admin</option>
                            <option value="empleado">Empleado</option>
                            <option value="chofer">Chofer</option>
                            <option value="cliente">Cliente</option>
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
                        <input type="password" name="confirmar_password" placeholder="Repetir contraseña" required>
                    </div>
                    <!-- Campos extra para chofer -->
                    <div class="form-group extra-chofer" id="campo-legajo">
                        <label>Legajo</label>
                        <input type="text" name="legajo" placeholder="Ej: LEG-001">
                    </div>
                    <div class="form-group extra-chofer" id="campo-licencia">
                        <label>Tipo de licencia</label>
                        <select name="tipo_licencia">
                            <option value="" disabled selected>Seleccioná...</option>
                            <option value="B1">B1 — Automóviles y camionetas</option>
                            <option value="B2">B2 — Automóviles con remolque / servicios</option>
                            <option value="C1">C1 — Camiones hasta 3.500 kg</option>
                            <option value="C2">C2 — Camiones de más de 3.500 kg</option>
                            <option value="D1">D1 — Transporte de pasajeros (-8 plazas)</option>
                            <option value="D2">D2 — Transporte de pasajeros (+8 plazas)</option>
                            <option value="D3">D3 — Transporte escolar</option>
                            <option value="E1">E1 — Camiones articulados / con acoplado</option>
                            <option value="E2">E2 — Maquinaria especial no agrícola</option>
                            <option value="E3">E3 — Maquinaria especial agrícola</option>
                        </select>
                    </div>
                    <!-- Campo extra para empleado -->
                    <div class="form-group extra-empleado full" id="campo-sucursal-empleado">
                        <label>Sucursal</label>
                        <select name="id_sucursal_empleado">
                            <option value="" disabled selected>Seleccioná...</option>
                            <?php foreach ($sucursales as $s): ?>
                            <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:46px;margin-top:16px;letter-spacing:2px;">
                    CREAR USUARIO
                </button>
            </form>
        </div>

        <!-- Tabla de usuarios -->
        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>DNI</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['dni']) ?></td>
                    <td><?= htmlspecialchars($u['apellido'] . ', ' . $u['nombre']) ?></td>
                    <td style="font-size:13px;color:var(--gris);"><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge-<?= htmlspecialchars($u['rol']) ?>"><?= htmlspecialchars(ucfirst($u['rol'])) ?></span></td>
                    <td>
                        <?php if ($u['activo']): ?>
                            <span style="color:#4ade80;font-size:13px;">● Activo</span>
                        <?php else: ?>
                            <span style="color:#f87171;font-size:13px;">● Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <!-- Toggle activo -->
                        <form method="POST" action="/logitrack/views/admin/usuarios.php" style="margin:0;">
                            <input type="hidden" name="accion"     value="toggle_activo">
                            <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                            <button type="submit" class="btn-sm <?= $u['activo'] ? 'btn-toggle-on' : 'btn-toggle-off' ?>">
                                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <!-- Editar -->
                        <a href="#edit-usuario-<?= $u['id_usuario'] ?>" class="btn-sm btn-edit">Editar</a>
                        <!-- Eliminar -->
                        <a href="#del-usuario-<?= $u['id_usuario'] ?>" class="btn-sm btn-delete">Eliminar</a>
                        <div class="modal-overlay" id="del-usuario-<?= $u['id_usuario'] ?>">
                            <div class="modal-box">
                                <p>¿Eliminar a <strong><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></strong>? Esta acción no se puede deshacer.</p>
                                <div class="modal-actions">
                                    <a href="#" class="btn btn-secondary">Cancelar</a>
                                    <form method="POST" action="/logitrack/views/admin/usuarios.php" style="margin:0;">
                                        <input type="hidden" name="accion"     value="eliminar">
                                        <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                        <button type="submit" class="btn-sm btn-delete">Sí, eliminar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="modal-overlay" id="edit-usuario-<?= $u['id_usuario'] ?>">
                            <div class="modal-box modal-box-edit">
                                <h3 style="margin-bottom:16px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">EDITAR USUARIO</h3>
                                <form method="POST" action="/logitrack/views/admin/usuarios.php">
                                    <input type="hidden" name="accion"     value="editar">
                                    <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>DNI</label>
                                            <input type="text" name="dni" value="<?= htmlspecialchars($u['dni']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Rol</label>
                                            <input type="text" value="<?= htmlspecialchars(ucfirst($u['rol'])) ?>" disabled>
                                        </div>
                                        <div class="form-group">
                                            <label>Nombre</label>
                                            <input type="text" name="nombre" value="<?= htmlspecialchars($u['nombre']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Apellido</label>
                                            <input type="text" name="apellido" value="<?= htmlspecialchars($u['apellido']) ?>" required>
                                        </div>
                                        <div class="form-group full">
                                            <label>Email</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" required>
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
                <?php if (empty($usuarios)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--gris);padding:32px;">Sin usuarios registrados</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>
</body>
</html>
