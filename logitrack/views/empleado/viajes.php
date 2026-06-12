<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['empleado']);
require_once __DIR__ . '/../../config/db.php';

$stmt = $pdo->query("
    SELECT v.id_viaje, v.patente, v.fecha_salida, v.fecha_llegada_est,
           c.nombre AS chofer_nombre, c.apellido AS chofer_apellido,
           s.nombre AS sucursal_origen
    FROM   viaje    v
    JOIN   chofer   c  ON v.id_chofer   = c.id_chofer
    JOIN   vehiculo vh ON v.patente     = vh.patente
    JOIN   sucursal s  ON vh.id_sucursal = s.id_sucursal
    WHERE  v.fecha_llegada_est IS NULL OR v.fecha_llegada_est >= NOW()
    ORDER  BY v.fecha_salida DESC
    LIMIT  100
");
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
        <div class="topbar">
            <div>
                <h1 class="page-title">VIAJES ACTIVOS</h1>
                <p class="page-subtitle">Viajes en curso o programados</p>
            </div>
        </div>
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
