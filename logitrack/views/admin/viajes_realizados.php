<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['admin']);
require_once __DIR__ . '/../../config/db.php';

$hay_filtros = !empty(array_filter($_GET));

$f_desde   = $_GET['desde']   ?? '';
$f_hasta   = $_GET['hasta']   ?? '';
$f_chofer  = trim($_GET['chofer']  ?? '');
$f_patente = trim($_GET['patente'] ?? '');

$where  = ['v.fecha_llegada_est IS NOT NULL', 'v.fecha_llegada_est < NOW()'];
$params = [];

if ($f_desde !== '')   { $where[] = 'v.fecha_llegada_est >= :desde'; $params[':desde'] = $f_desde . ' 00:00:00'; }
if ($f_hasta !== '')   { $where[] = 'v.fecha_llegada_est <= :hasta'; $params[':hasta'] = $f_hasta . ' 23:59:59'; }
if ($f_chofer !== '')  { $where[] = '(c.nombre LIKE :chofer1 OR c.apellido LIKE :chofer2)'; $params[':chofer1'] = '%' . $f_chofer . '%'; $params[':chofer2'] = '%' . $f_chofer . '%'; }
if ($f_patente !== '') { $where[] = 'v.patente LIKE :patente'; $params[':patente'] = '%' . strtoupper($f_patente) . '%'; }

$sql = "
    SELECT v.id_viaje, v.patente, v.fecha_salida, v.fecha_llegada_est,
           c.nombre AS chofer_nombre, c.apellido AS chofer_apellido,
           s.nombre AS sucursal_origen
    FROM   viaje    v
    JOIN   chofer   c  ON v.id_chofer   = c.id_chofer
    JOIN   vehiculo vh ON v.patente     = vh.patente
    JOIN   sucursal s  ON vh.id_sucursal = s.id_sucursal
    WHERE  " . implode(' AND ', $where) . "
    ORDER  BY v.fecha_llegada_est DESC
    LIMIT  100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$viajes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Viajes Realizados</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <input type="checkbox" id="chk-filtros" class="toggle-filtros" <?= $hay_filtros ? 'checked' : '' ?>>

        <div class="topbar">
            <div>
                <h1 class="page-title">VIAJES REALIZADOS</h1>
                <p class="page-subtitle">Historial de viajes finalizados</p>
            </div>
            <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
        </div>

        <form method="GET" action="/logitrack/views/admin/viajes_realizados.php" class="filtros">
            <div class="form-group">
                <label>Llegada desde</label>
                <input type="date" name="desde" value="<?= htmlspecialchars($f_desde) ?>">
            </div>
            <div class="form-group">
                <label>Llegada hasta</label>
                <input type="date" name="hasta" value="<?= htmlspecialchars($f_hasta) ?>">
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
                <a href="/logitrack/views/admin/viajes_realizados.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>#</th><th>Chofer</th><th>Patente</th><th>Origen</th><th>Salida</th><th>Llegada</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($viajes as $v): ?>
                    <tr>
                        <td><?= $v['id_viaje'] ?></td>
                        <td><?= htmlspecialchars($v['chofer_apellido'] . ', ' . $v['chofer_nombre']) ?></td>
                        <td><code><?= htmlspecialchars($v['patente']) ?></code></td>
                        <td><?= htmlspecialchars($v['sucursal_origen']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['fecha_salida'])) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['fecha_llegada_est'])) ?></td>
                        <td><span class="badge badge-entregado">Finalizado</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($viajes)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--gris);padding:32px;">Sin viajes realizados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
