<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['empleado']);
require_once __DIR__ . '/../../config/db.php';

$hay_filtros = !empty(array_filter($_GET));

$f_chofer  = trim($_GET['chofer']  ?? '');
$f_patente = trim($_GET['patente'] ?? '');
$f_estado  = $_GET['estado'] ?? '';

$where  = ['(v.fecha_llegada_est IS NULL OR v.fecha_llegada_est >= NOW())'];
$params = [];

if ($f_chofer !== '') {
    $where[] = '(c.nombre LIKE :chofer1 OR c.apellido LIKE :chofer2)';
    $params[':chofer1'] = '%' . $f_chofer . '%';
    $params[':chofer2'] = '%' . $f_chofer . '%';
}
if ($f_patente !== '') {
    $where[] = 'v.patente LIKE :patente';
    $params[':patente'] = '%' . strtoupper($f_patente) . '%';
}
if ($f_estado === 'programado') { $where[] = 'v.fecha_salida > NOW()'; }
elseif ($f_estado === 'en_ruta') { $where[] = 'v.fecha_salida <= NOW()'; }

$sql = "
    SELECT v.id_viaje, v.patente, v.fecha_salida, v.fecha_llegada_est,
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Viajes Activos</title>
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
                <h1 class="page-title">VIAJES ACTIVOS</h1>
                <p class="page-subtitle">Viajes en curso o programados</p>
            </div>
            <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
        </div>

        <form method="GET" action="/logitrack/views/empleado/viajes.php" class="filtros">
            <div class="form-group">
                <label>Chofer</label>
                <input type="text" name="chofer" placeholder="Nombre o apellido"
                       value="<?= htmlspecialchars($f_chofer) ?>">
            </div>
            <div class="form-group">
                <label>Patente</label>
                <input type="text" name="patente" placeholder="Ej: ABC123"
                       value="<?= htmlspecialchars($f_patente) ?>">
            </div>
            <div class="form-group">
                <label>Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="programado" <?= $f_estado === 'programado' ? 'selected' : '' ?>>Programado</option>
                    <option value="en_ruta"    <?= $f_estado === 'en_ruta'    ? 'selected' : '' ?>>En ruta</option>
                </select>
            </div>
            <div class="filtros-acciones">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="/logitrack/views/empleado/viajes.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>#</th><th>Chofer</th><th>Patente</th><th>Origen</th><th>Salida</th><th>Llegada Est.</th><th>Estado</th></tr>
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
                            if ($ahora < $salida): ?>
                                <span class="badge badge-inactivo">Programado</span>
                            <?php elseif (!$llegada || $ahora <= $llegada): ?>
                                <span class="badge badge-transito">En ruta</span>
                            <?php else: ?>
                                <span class="badge badge-entregado">Finalizado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($viajes)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--gris);padding:32px;">Sin viajes activos</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
