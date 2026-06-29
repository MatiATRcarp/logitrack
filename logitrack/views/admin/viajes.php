<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['admin']);
require_once __DIR__ . '/../../config/db.php';

$msg      = '';
$msg_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cancelar') {
        $id_viaje = (int) ($_POST['id_viaje'] ?? 0);

        $stmt = $pdo->prepare("SELECT fecha_salida FROM viaje WHERE id_viaje = :id");
        $stmt->execute([':id' => $id_viaje]);
        $viaje = $stmt->fetch();

        if (!$viaje) {
            $msg = 'El viaje no existe.'; $msg_tipo = 'error';
        } elseif (strtotime($viaje['fecha_salida']) <= time()) {
            $msg = 'Solo se pueden cancelar viajes que todavía no salieron.'; $msg_tipo = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                $stmtEnvios = $pdo->prepare("
                    SELECT ve.id_envio, e.id_suc_origen
                    FROM   viaje_envio ve
                    JOIN   envio e ON e.id_envio = ve.id_envio
                    WHERE  ve.id_viaje = :id
                ");
                $stmtEnvios->execute([':id' => $id_viaje]);

                $stmtHist = $pdo->prepare("
                    INSERT INTO historial_estado (id_envio, id_estado, fecha_hora, id_sucursal_actual)
                    VALUES (:envio, (SELECT id_estado FROM tipo_estado WHERE nombre = 'Cancelado' LIMIT 1), NOW(), :suc)
                ");
                foreach ($stmtEnvios->fetchAll() as $e) {
                    $stmtHist->execute([':envio' => $e['id_envio'], ':suc' => $e['id_suc_origen']]);
                }

                $pdo->prepare("DELETE FROM viaje_envio WHERE id_viaje = :id")->execute([':id' => $id_viaje]);
                $pdo->prepare("DELETE FROM viaje WHERE id_viaje = :id")->execute([':id' => $id_viaje]);

                $pdo->commit();
                $msg = "Viaje #{$id_viaje} cancelado. Los envíos asignados pasaron a estado «Cancelado».";
                $msg_tipo = 'success';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = 'No se puede cancelar: el viaje tiene incidentes registrados.';
                $msg_tipo = 'error';
            }
        }
    }

    elseif ($accion === 'editar') {
        $id_viaje      = (int) ($_POST['id_viaje'] ?? 0);
        $id_chofer     = (int) ($_POST['id_chofer'] ?? 0);
        $patente       = strtoupper(trim($_POST['patente'] ?? ''));
        $fecha_salida  = trim($_POST['fecha_salida'] ?? '');
        $fecha_llegada = trim($_POST['fecha_llegada_est'] ?? '') ?: null;

        $stmt = $pdo->prepare("SELECT fecha_salida FROM viaje WHERE id_viaje = :id");
        $stmt->execute([':id' => $id_viaje]);
        $actual = $stmt->fetch();

        if (!$actual) {
            $msg = 'El viaje no existe.'; $msg_tipo = 'error';
        } elseif (strtotime($actual['fecha_salida']) <= time()) {
            $msg = 'Solo se pueden editar viajes que todavía no salieron.'; $msg_tipo = 'error';
        } elseif (!$id_chofer || !$patente || !$fecha_salida) {
            $msg = 'Completá todos los campos obligatorios.'; $msg_tipo = 'error';
        } elseif (strtotime($fecha_salida) <= time()) {
            $msg = 'La fecha de salida debe ser futura.'; $msg_tipo = 'error';
        } elseif ($fecha_llegada && strtotime($fecha_llegada) <= strtotime($fecha_salida)) {
            $msg = 'La llegada estimada debe ser posterior a la salida.'; $msg_tipo = 'error';
        } else {
            $stmt = $pdo->prepare("
                SELECT tv.capacidad_kg_max, tv.requiere_licencia_especial
                FROM   vehiculo v
                JOIN   tipo_vehiculo tv ON v.id_tipo_veh = tv.id_tipo_veh
                WHERE  v.patente = :p
            ");
            $stmt->execute([':p' => $patente]);
            $vehiculo = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT tipo_licencia FROM chofer WHERE id_chofer = :id");
            $stmt->execute([':id' => $id_chofer]);
            $chofer = $stmt->fetch();

            if (!$vehiculo) {
                $msg = 'El vehículo seleccionado no existe.'; $msg_tipo = 'error';
            } elseif (!$chofer) {
                $msg = 'El chofer seleccionado no existe.'; $msg_tipo = 'error';
            } elseif ((int) $vehiculo['requiere_licencia_especial'] === 1
                      && !in_array($chofer['tipo_licencia'], ['E1', 'E2', 'E3', 'C2'], true)) {
                $msg = 'El chofer no posee la licencia requerida para este vehículo.'; $msg_tipo = 'error';
            } else {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(e.peso_kg), 0) AS total
                    FROM   viaje_envio ve
                    JOIN   envio e ON e.id_envio = ve.id_envio
                    WHERE  ve.id_viaje = :id
                ");
                $stmt->execute([':id' => $id_viaje]);
                $peso_total = (float) $stmt->fetch()['total'];

                if ($peso_total > (float) $vehiculo['capacidad_kg_max']) {
                    $msg = sprintf(
                        'El peso de la carga asignada (%s kg) supera la capacidad del vehículo (%s kg).',
                        number_format($peso_total, 2),
                        number_format((float) $vehiculo['capacidad_kg_max'], 2)
                    );
                    $msg_tipo = 'error';
                } else {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) AS c
                        FROM   viaje
                        WHERE  id_chofer = :chofer AND id_viaje != :id
                          AND  :salida < fecha_llegada_est AND :llegada > fecha_salida
                    ");
                    $stmt->execute([
                        ':chofer'  => $id_chofer,
                        ':id'      => $id_viaje,
                        ':salida'  => $fecha_salida,
                        ':llegada' => $fecha_llegada,
                    ]);

                    if ((int) $stmt->fetch()['c'] > 0) {
                        $msg = 'El chofer ya tiene otro viaje asignado en ese rango de fechas.'; $msg_tipo = 'error';
                    } else {
                        try {
                            $pdo->prepare("
                                UPDATE viaje
                                SET fecha_salida = :salida, fecha_llegada_est = :llegada,
                                    patente = :patente, id_chofer = :chofer
                                WHERE id_viaje = :id
                            ")->execute([
                                ':salida'  => $fecha_salida,
                                ':llegada' => $fecha_llegada,
                                ':patente' => $patente,
                                ':chofer'  => $id_chofer,
                                ':id'      => $id_viaje,
                            ]);
                            $msg = "Viaje #{$id_viaje} actualizado correctamente."; $msg_tipo = 'success';
                        } catch (PDOException $e) {
                            $msg = 'Error al actualizar el viaje.'; $msg_tipo = 'error';
                        }
                    }
                }
            }
        }
    }

    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_tipo'] = $msg_tipo;
    header('Location: /logitrack/views/admin/viajes.php');
    exit;
}

if (isset($_SESSION['flash_msg'])) {
    $msg      = $_SESSION['flash_msg'];
    $msg_tipo = $_SESSION['flash_tipo'] ?? '';
    unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
}

$hay_filtros = !empty(array_filter($_GET));

$f_estado  = $_GET['estado']  ?? '';
$f_chofer  = trim($_GET['chofer']  ?? '');
$f_patente = trim($_GET['patente'] ?? '');

$where  = ['(v.fecha_llegada_est IS NULL OR v.fecha_llegada_est >= NOW())'];
$params = [];

if ($f_estado === 'programado')   { $where[] = 'v.fecha_salida > NOW()'; }
elseif ($f_estado === 'en_ruta')  { $where[] = 'v.fecha_salida <= NOW()'; }

if ($f_chofer !== '')  { $where[] = '(c.nombre LIKE :chofer1 OR c.apellido LIKE :chofer2)'; $params[':chofer1'] = '%' . $f_chofer . '%'; $params[':chofer2'] = '%' . $f_chofer . '%'; }
if ($f_patente !== '') { $where[] = 'v.patente LIKE :patente'; $params[':patente'] = '%' . strtoupper($f_patente) . '%'; }

$sql = "
    SELECT v.id_viaje, v.id_chofer, v.patente, v.fecha_salida, v.fecha_llegada_est,
           c.nombre AS chofer_nombre, c.apellido AS chofer_apellido,
           s.nombre AS sucursal_origen
    FROM   viaje    v
    JOIN   chofer   c  ON v.id_chofer   = c.id_chofer
    JOIN   vehiculo vh ON v.patente     = vh.patente
    JOIN   sucursal s  ON vh.id_sucursal = s.id_sucursal
    WHERE  " . implode(' AND ', $where) . "
    ORDER  BY v.fecha_salida DESC
    LIMIT  100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$viajes = $stmt->fetchAll();

$choferes_lista = $pdo->query("SELECT id_chofer, nombre, apellido FROM chofer ORDER BY apellido, nombre")->fetchAll();
$vehiculos_lista = $pdo->query("SELECT patente FROM vehiculo ORDER BY patente")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Viajes</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .btn-sm { padding:4px 12px; font-size:12px; border-radius:8px; border:none; cursor:pointer; font-family:inherit; letter-spacing:1px; }
        .btn-delete { background:rgba(248,113,113,.1); color:#f87171; border:1px solid #f87171; }
        .btn-delete:hover { background:#f87171; color:#fff; }
        .btn-edit { background:rgba(96,165,250,.1); color:#60a5fa; border:1px solid #60a5fa; }
        .btn-edit:hover { background:#60a5fa; color:#fff; }
        .modal-box-edit { max-width:480px; text-align:left; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .form-row .full { grid-column:1 / -1; }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <input type="checkbox" id="chk-filtros" class="toggle-filtros" <?= $hay_filtros ? 'checked' : '' ?>>

        <div class="topbar">
            <div>
                <h1 class="page-title">VIAJES</h1>
                <p class="page-subtitle">Historial de todos los viajes</p>
            </div>
            <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_tipo === 'success' ? 'success' : 'error' ?>" style="max-width:480px;margin-bottom:16px;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <form method="GET" action="/logitrack/views/admin/viajes.php" class="filtros">
            <div class="form-group">
                <label>Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="programado" <?= $f_estado === 'programado' ? 'selected' : '' ?>>Programado</option>
                    <option value="en_ruta"    <?= $f_estado === 'en_ruta'    ? 'selected' : '' ?>>En ruta</option>
                </select>
            </div>
            <div class="form-group">
                <label>Chofer</label>
                <input type="text" name="chofer" placeholder="Nombre o apellido" value="<?= htmlspecialchars($f_chofer) ?>">
            </div>
            <div class="form-group">
                <label>Patente</label>
                <input type="text" name="patente" placeholder="Ej: ABC123" value="<?= htmlspecialchars($f_patente) ?>">
            </div>
            <div class="filtros-acciones">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="/logitrack/views/admin/viajes.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>#</th><th>Chofer</th><th>Patente</th><th>Origen</th><th>Salida</th><th>Llegada Est.</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($viajes as $v): ?>
                    <tr>
                        <td><?= $v['id_viaje'] ?></td>
                        <td><?= htmlspecialchars($v['chofer_apellido'] . ', ' . $v['chofer_nombre']) ?></td>
                        <td><code><?= htmlspecialchars($v['patente']) ?></code></td>
                        <td><?= htmlspecialchars($v['sucursal_origen']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['fecha_salida'])) ?></td>
                        <td><?= $v['fecha_llegada_est'] ? date('d/m/Y H:i', strtotime($v['fecha_llegada_est'])) : '—' ?></td>
                        <td>
                            <?php
                            $ahora   = time();
                            $salida  = strtotime($v['fecha_salida']);
                            $llegada = $v['fecha_llegada_est'] ? strtotime($v['fecha_llegada_est']) : null;
                            $es_programado = $ahora < $salida;
                            if ($es_programado): ?>
                                <span class="badge badge-inactivo">Programado</span>
                            <?php elseif (!$llegada || $ahora <= $llegada): ?>
                                <span class="badge badge-transito">En ruta</span>
                            <?php else: ?>
                                <span class="badge badge-entregado">Finalizado</span>
                            <?php endif; ?>
                        </td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($es_programado): ?>
                            <a href="#edit-viaje-<?= $v['id_viaje'] ?>" class="btn-sm btn-edit">Editar</a>
                            <a href="#cancel-viaje-<?= $v['id_viaje'] ?>" class="btn-sm btn-delete">Cancelar</a>

                            <div class="modal-overlay" id="cancel-viaje-<?= $v['id_viaje'] ?>">
                                <div class="modal-box">
                                    <p>¿Cancelar el viaje <strong>#<?= $v['id_viaje'] ?></strong>? Los envíos asignados pasarán a estado «Cancelado» y deberán reasignarse manualmente.</p>
                                    <div class="modal-actions">
                                        <a href="#" class="btn btn-secondary">Volver</a>
                                        <form method="POST" action="/logitrack/views/admin/viajes.php" style="margin:0;">
                                            <input type="hidden" name="accion"   value="cancelar">
                                            <input type="hidden" name="id_viaje" value="<?= $v['id_viaje'] ?>">
                                            <button type="submit" class="btn-sm btn-delete">Sí, cancelar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-overlay" id="edit-viaje-<?= $v['id_viaje'] ?>">
                                <div class="modal-box modal-box-edit">
                                    <h3 style="margin-bottom:16px;font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;">EDITAR VIAJE #<?= $v['id_viaje'] ?></h3>
                                    <form method="POST" action="/logitrack/views/admin/viajes.php">
                                        <input type="hidden" name="accion"   value="editar">
                                        <input type="hidden" name="id_viaje" value="<?= $v['id_viaje'] ?>">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Chofer</label>
                                                <select name="id_chofer" required>
                                                    <?php foreach ($choferes_lista as $c): ?>
                                                    <option value="<?= $c['id_chofer'] ?>" <?= $c['id_chofer'] == $v['id_chofer'] ? 'selected' : '' ?>><?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Vehículo</label>
                                                <select name="patente" required>
                                                    <?php foreach ($vehiculos_lista as $veh): ?>
                                                    <option value="<?= htmlspecialchars($veh['patente']) ?>" <?= $veh['patente'] === $v['patente'] ? 'selected' : '' ?>><?= htmlspecialchars($veh['patente']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Fecha de salida</label>
                                                <input type="datetime-local" name="fecha_salida" value="<?= date('Y-m-d\TH:i', strtotime($v['fecha_salida'])) ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Llegada estimada</label>
                                                <input type="datetime-local" name="fecha_llegada_est" value="<?= $v['fecha_llegada_est'] ? date('Y-m-d\TH:i', strtotime($v['fecha_llegada_est'])) : '' ?>">
                                            </div>
                                        </div>
                                        <div class="modal-actions" style="margin-top:16px;">
                                            <a href="#" class="btn btn-secondary">Cancelar</a>
                                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--gris);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($viajes)): ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--gris);padding:32px;">Sin viajes activos</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
