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
        $dni           = trim($_POST['dni']               ?? '');
        $nombre        = trim($_POST['nombre']            ?? '');
        $apellido      = trim($_POST['apellido']          ?? '');
        $email         = trim($_POST['email']             ?? '');
        $password      = $_POST['password']               ?? '';
        $confirmar     = $_POST['confirmar_password']     ?? '';
        $legajo        = trim($_POST['legajo']            ?? '');
        $tipo_licencia = trim($_POST['tipo_licencia']     ?? '');

        if ($password !== $confirmar) {
            $msg = 'Las contraseñas no coinciden.'; $msg_tipo = 'error';
        } elseif (empty($dni) || empty($nombre) || empty($apellido) || empty($email) || empty($password) || empty($legajo) || empty($tipo_licencia)) {
            $msg = 'Completá todos los campos.'; $msg_tipo = 'error';
        } else {
            try {
                $model = new UsuarioModel($pdo);
                $model->registrar($dni, $nombre, $apellido, $email, $password, 'chofer', [
                    'legajo'        => $legajo,
                    'tipo_licencia' => $tipo_licencia,
                ]);
                $msg = "Chofer «{$nombre} {$apellido}» creado correctamente.";
                $msg_tipo = 'success';
            } catch (PDOException $e) {
                $msg = $e->getCode() == 23000
                    ? 'El DNI o email ya están registrados.'
                    : 'Error al crear el chofer.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'eliminar') {
        $id = (int) ($_POST['id_chofer'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("SELECT dni FROM chofer WHERE id_chofer = :id");
                $stmt->execute([':id' => $id]);
                $chofer = $stmt->fetch();
                if ($chofer) {
                    $pdo->prepare("DELETE FROM chofer  WHERE id_chofer = :id")->execute([':id' => $id]);
                    $pdo->prepare("DELETE FROM usuario WHERE dni = :dni")->execute([':dni' => $chofer['dni']]);
                    $msg = 'Chofer eliminado.'; $msg_tipo = 'success';
                }
            } catch (PDOException $e) {
                $msg = 'No se puede eliminar: el chofer tiene viajes registrados.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'toggle_activo') {
        $id = (int) ($_POST['id_chofer'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE chofer SET activo = 1 - activo WHERE id_chofer = :id")
                ->execute([':id' => $id]);

            $stmt = $pdo->prepare("SELECT dni, activo FROM chofer WHERE id_chofer = :id");
            $stmt->execute([':id' => $id]);
            if ($row = $stmt->fetch()) {
                $pdo->prepare("UPDATE usuario SET activo = :activo WHERE dni = :dni")
                    ->execute([':activo' => $row['activo'], ':dni' => $row['dni']]);
            }

            $msg = 'Estado actualizado.'; $msg_tipo = 'success';
        }
    }

    elseif ($accion === 'editar') {
        $id            = (int) ($_POST['id_chofer'] ?? 0);
        $dni           = trim($_POST['dni']           ?? '');
        $nombre        = trim($_POST['nombre']        ?? '');
        $apellido      = trim($_POST['apellido']      ?? '');
        $email         = trim($_POST['email']         ?? '');
        $legajo        = trim($_POST['legajo']        ?? '');
        $tipo_licencia = trim($_POST['tipo_licencia'] ?? '');

        if (!$id || empty($dni) || empty($nombre) || empty($apellido) || empty($email) || empty($legajo) || empty($tipo_licencia)) {
            $msg = 'Completá todos los campos obligatorios.'; $msg_tipo = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT dni FROM chofer WHERE id_chofer = :id");
                $stmt->execute([':id' => $id]);
                $actual = $stmt->fetch();

                if (!$actual) {
                    $msg = 'Chofer no encontrado.'; $msg_tipo = 'error';
                } else {
                    $pdo->beginTransaction();

                    $pdo->prepare("
                        UPDATE chofer
                        SET dni = :dni, nombre = :nombre, apellido = :apellido, email = :email,
                            legajo = :legajo, tipo_licencia = :tipo_licencia
                        WHERE id_chofer = :id
                    ")->execute([
                        ':dni'           => $dni,
                        ':nombre'        => $nombre,
                        ':apellido'      => $apellido,
                        ':email'         => $email,
                        ':legajo'        => $legajo,
                        ':tipo_licencia' => $tipo_licencia,
                        ':id'            => $id,
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
                    $msg = 'Chofer actualizado correctamente.'; $msg_tipo = 'success';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = $e->getCode() == 23000
                    ? 'El DNI, legajo o email ya están en uso por otro registro.'
                    : 'Error al actualizar el chofer.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'rendimiento') {
        $id = (int) ($_POST['id_chofer'] ?? 0);
        $periodo = trim($_POST['periodo'] ?? '');
        $puntaje = (int) ($_POST['puntaje'] ?? 0);
        $observacion = trim($_POST['observacion'] ?? '');

        if (!$id || !$periodo || $puntaje < 1 || $puntaje > 100) {
            $msg = 'Indicá período y puntaje entre 1 y 100.'; $msg_tipo = 'error';
        } else {
            $pdo->prepare("
                INSERT INTO rendimiento_personal (tipo_personal, id_personal, periodo, puntaje, observacion)
                VALUES ('chofer', :id, :periodo, :puntaje, :observacion)
                ON DUPLICATE KEY UPDATE puntaje = VALUES(puntaje), observacion = VALUES(observacion), fecha_carga = NOW()
            ")->execute([
                ':id' => $id,
                ':periodo' => $periodo . '-01',
                ':puntaje' => $puntaje,
                ':observacion' => $observacion !== '' ? $observacion : null,
            ]);
            $msg = 'Rendimiento guardado.'; $msg_tipo = 'success';
        }
    }

    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_tipo'] = $msg_tipo;
    header('Location: /logitrack/views/admin/choferes.php');
    exit;
}

if (isset($_SESSION['flash_msg'])) {
    $msg      = $_SESSION['flash_msg'];
    $msg_tipo = $_SESSION['flash_tipo'] ?? '';
    unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
}

$f_busqueda = trim($_GET['busqueda'] ?? '');
$f_licencia = $_GET['licencia'] ?? '';
$f_estado   = $_GET['estado']   ?? '';

$where  = [];
$params = [];

if ($f_busqueda !== '') {
    $where[] = '(c.nombre LIKE :busqueda1 OR c.apellido LIKE :busqueda2 OR c.dni LIKE :busqueda3 OR c.legajo LIKE :busqueda4)';
    $b = '%' . $f_busqueda . '%';
    $params[':busqueda1'] = $b;
    $params[':busqueda2'] = $b;
    $params[':busqueda3'] = $b;
    $params[':busqueda4'] = $b;
}
if ($f_licencia !== '') {
    $where[] = 'c.tipo_licencia = :licencia';
    $params[':licencia'] = $f_licencia;
}

if ($f_estado === 'activo')        { $where[] = 'c.activo = 1'; }
elseif ($f_estado === 'inactivo')  { $where[] = 'c.activo = 0'; }

$sql = "
    SELECT c.id_chofer, c.dni, c.nombre, c.apellido, c.legajo, c.tipo_licencia, c.email,
           c.activo AS activo,
           rp.puntaje AS rendimiento_puntaje,
           rp.periodo AS rendimiento_periodo
    FROM   chofer  c
    LEFT   JOIN rendimiento_personal rp ON rp.id_rendimiento = (
        SELECT rp2.id_rendimiento
        FROM rendimiento_personal rp2
        WHERE rp2.tipo_personal = 'chofer'
          AND rp2.id_personal = c.id_chofer
        ORDER BY rp2.periodo DESC, rp2.id_rendimiento DESC
        LIMIT 1
    )";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY c.apellido, c.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$choferes = $stmt->fetchAll();

$licencias = $pdo->query("SELECT DISTINCT tipo_licencia FROM chofer ORDER BY tipo_licencia")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Choferes</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .form-panel { background:var(--panel); border:1px solid var(--borde); border-radius:16px; padding:24px; max-width:580px; margin-bottom:28px; display:none; }
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
                <h1 class="page-title">CHOFERES</h1>
                <p class="page-subtitle">Gestión del personal de conducción</p>
            </div>
            <div style="display:flex;gap:10px;">
                <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
                <label for="chk-panel-crear" class="btn btn-primary">
                    + Nuevo Chofer
                </label>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_tipo === 'success' ? 'success' : 'error' ?>" style="max-width:580px;margin-bottom:16px;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <form method="GET" action="/logitrack/views/admin/choferes.php" class="filtros">
            <div class="form-group">
                <label>Buscar</label>
                <input type="text" name="busqueda" placeholder="Nombre, apellido, DNI o legajo" value="<?= htmlspecialchars($f_busqueda) ?>">
            </div>
            <div class="form-group">
                <label>Licencia</label>
                <select name="licencia">
                    <option value="">Todas</option>
                    <?php foreach ($licencias as $l): ?>
                    <option value="<?= htmlspecialchars($l) ?>" <?= $f_licencia === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
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
                <a href="/logitrack/views/admin/choferes.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="form-panel" id="panel-crear">
            <h3 style="margin-bottom:20px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">NUEVO CHOFER</h3>
            <form method="POST" action="/logitrack/views/admin/choferes.php">
                <input type="hidden" name="accion" value="crear">
                <div class="form-row">
                    <div class="form-group">
                        <label>DNI</label>
                        <input type="text" name="dni" placeholder="Ej: 30123456" required>
                    </div>
                    <div class="form-group">
                        <label>Legajo</label>
                        <input type="text" name="legajo" placeholder="Ej: LEG-001" required>
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
                        <label>Email (para login)</label>
                        <input type="email" name="email" placeholder="correo@ejemplo.com" required>
                    </div>
                    <div class="form-group full">
                        <label>Tipo de licencia</label>
                        <select name="tipo_licencia" required>
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
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" placeholder="Contraseña" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmar contraseña</label>
                        <input type="password" name="confirmar_password" placeholder="Repetir" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:44px;margin-top:16px;letter-spacing:2px;">
                    CREAR CHOFER
                </button>
            </form>
        </div>

        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>DNI</th><th>Nombre</th><th>Email</th><th>Legajo</th><th>Licencia</th><th>Rendimiento</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($choferes as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['dni']) ?></td>
                    <td><?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre']) ?></td>
                    <td style="font-size:13px;color:var(--gris);"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($c['legajo']) ?></td>
                    <td><span class="badge badge-transito"><?= htmlspecialchars($c['tipo_licencia']) ?></span></td>
                    <td>
                        <?= $c['rendimiento_puntaje'] !== null
                            ? '<span class="badge badge-activo">' . (int) $c['rendimiento_puntaje'] . '/100</span>'
                            : '<span style="color:var(--gris);font-size:13px;">—</span>' ?>
                    </td>
                    <td>
                        <?php if ($c['activo']): ?>
                            <span style="color:#4ade80;font-size:13px;">● Activo</span>
                        <?php else: ?>
                            <span style="color:#f87171;font-size:13px;">● Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <form method="POST" action="/logitrack/views/admin/choferes.php" style="margin:0;">
                            <input type="hidden" name="accion"    value="toggle_activo">
                            <input type="hidden" name="id_chofer" value="<?= $c['id_chofer'] ?>">
                            <button type="submit" class="btn-sm <?= $c['activo'] ? 'btn-toggle-on' : 'btn-toggle-off' ?>">
                                <?= $c['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <a href="#edit-chofer-<?= $c['id_chofer'] ?>" class="btn-sm btn-edit">Editar</a>
                        <a href="#rend-chofer-<?= $c['id_chofer'] ?>" class="btn-sm btn-edit">Rendimiento</a>
                        <a href="#del-chofer-<?= $c['id_chofer'] ?>" class="btn-sm btn-delete">Eliminar</a>
                        <div class="modal-overlay" id="rend-chofer-<?= $c['id_chofer'] ?>">
                            <div class="modal-box modal-box-edit">
                                <h3 style="margin-bottom:16px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">RENDIMIENTO</h3>
                                <form method="POST" action="/logitrack/views/admin/choferes.php">
                                    <input type="hidden" name="accion" value="rendimiento">
                                    <input type="hidden" name="id_chofer" value="<?= $c['id_chofer'] ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Período</label>
                                            <input type="month" name="periodo" value="<?= $c['rendimiento_periodo'] ? date('Y-m', strtotime($c['rendimiento_periodo'])) : date('Y-m') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Puntaje</label>
                                            <input type="number" name="puntaje" min="1" max="100" value="<?= htmlspecialchars($c['rendimiento_puntaje'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group full">
                                            <label>Observación</label>
                                            <textarea name="observacion" rows="3" placeholder="Detalle breve"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-actions" style="margin-top:16px;">
                                        <a href="#" class="btn btn-secondary">Cancelar</a>
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="modal-overlay" id="del-chofer-<?= $c['id_chofer'] ?>">
                            <div class="modal-box">
                                <p>¿Eliminar al chofer <strong><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido']) ?></strong>? Esta acción no se puede deshacer.</p>
                                <div class="modal-actions">
                                    <a href="#" class="btn btn-secondary">Cancelar</a>
                                    <form method="POST" action="/logitrack/views/admin/choferes.php" style="margin:0;">
                                        <input type="hidden" name="accion"    value="eliminar">
                                        <input type="hidden" name="id_chofer" value="<?= $c['id_chofer'] ?>">
                                        <button type="submit" class="btn-sm btn-delete">Sí, eliminar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="modal-overlay" id="edit-chofer-<?= $c['id_chofer'] ?>">
                            <div class="modal-box modal-box-edit">
                                <h3 style="margin-bottom:16px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">EDITAR CHOFER</h3>
                                <form method="POST" action="/logitrack/views/admin/choferes.php">
                                    <input type="hidden" name="accion"    value="editar">
                                    <input type="hidden" name="id_chofer" value="<?= $c['id_chofer'] ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>DNI</label>
                                            <input type="text" name="dni" value="<?= htmlspecialchars($c['dni']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Legajo</label>
                                            <input type="text" name="legajo" value="<?= htmlspecialchars($c['legajo']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Nombre</label>
                                            <input type="text" name="nombre" value="<?= htmlspecialchars($c['nombre']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Apellido</label>
                                            <input type="text" name="apellido" value="<?= htmlspecialchars($c['apellido']) ?>" required>
                                        </div>
                                        <div class="form-group full">
                                            <label>Email</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($c['email'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group full">
                                            <label>Tipo de licencia</label>
                                            <select name="tipo_licencia" required>
                                                <option value="B1" <?= $c['tipo_licencia'] === 'B1' ? 'selected' : '' ?>>B1 — Automóviles y camionetas</option>
                                                <option value="B2" <?= $c['tipo_licencia'] === 'B2' ? 'selected' : '' ?>>B2 — Automóviles con remolque / servicios</option>
                                                <option value="C1" <?= $c['tipo_licencia'] === 'C1' ? 'selected' : '' ?>>C1 — Camiones hasta 3.500 kg</option>
                                                <option value="C2" <?= $c['tipo_licencia'] === 'C2' ? 'selected' : '' ?>>C2 — Camiones de más de 3.500 kg</option>
                                                <option value="D1" <?= $c['tipo_licencia'] === 'D1' ? 'selected' : '' ?>>D1 — Transporte de pasajeros (-8 plazas)</option>
                                                <option value="D2" <?= $c['tipo_licencia'] === 'D2' ? 'selected' : '' ?>>D2 — Transporte de pasajeros (+8 plazas)</option>
                                                <option value="D3" <?= $c['tipo_licencia'] === 'D3' ? 'selected' : '' ?>>D3 — Transporte escolar</option>
                                                <option value="E1" <?= $c['tipo_licencia'] === 'E1' ? 'selected' : '' ?>>E1 — Camiones articulados / con acoplado</option>
                                                <option value="E2" <?= $c['tipo_licencia'] === 'E2' ? 'selected' : '' ?>>E2 — Maquinaria especial no agrícola</option>
                                                <option value="E3" <?= $c['tipo_licencia'] === 'E3' ? 'selected' : '' ?>>E3 — Maquinaria especial agrícola</option>
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
                <?php if (empty($choferes)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--gris);padding:32px;">Sin choferes registrados</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>
</body>
</html>
