<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['admin']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

$controller = new AdminController($pdo);
$data       = $controller->getDashboardData();
$metricas   = $data['metricas'];
$viajes     = $data['viajes'];

// Vista v_envios_completo (requiere haber ejecutado sql/v_envios_completo.sql)
$envios_vista      = [];
$vista_disponible  = true;
$f_estado_env      = trim($_GET['estado_env'] ?? '');
$f_busq_env        = trim($_GET['busq_env']   ?? '');
try {
    $where_env  = [];
    $params_env = [];
    if ($f_estado_env !== '') {
        $where_env[]             = 'estado_actual = :estado';
        $params_env[':estado']   = $f_estado_env;
    }
    if ($f_busq_env !== '') {
        $where_env[]             = '(nro_tracking LIKE :busq OR remitente LIKE :busq2 OR destinatario LIKE :busq3)';
        $b = '%' . $f_busq_env . '%';
        $params_env[':busq']  = $b;
        $params_env[':busq2'] = $b;
        $params_env[':busq3'] = $b;
    }
    $sql_env = "SELECT * FROM v_envios_completo";
    if ($where_env) $sql_env .= ' WHERE ' . implode(' AND ', $where_env);
    $sql_env .= ' ORDER BY fecha_recepcion DESC LIMIT 100';
    $stmt_env = $pdo->prepare($sql_env);
    $stmt_env->execute($params_env);
    $envios_vista = $stmt_env->fetchAll();
} catch (PDOException $e) {
    $vista_disponible = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Dashboard Admin</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">

    <?php include __DIR__ . '/../layout/sidebar.php'; ?>

    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">DASHBOARD</h1>
                <p class="page-subtitle">Resumen global del sistema · <?= date('d/m/Y') ?></p>
            </div>
        </div>

        <!-- Métricas -->
        <div class="cards-grid">
            <div class="card card-metric">
                <div class="icono">📦</div>
                <div class="valor"><?= $metricas['envios_mes'] ?></div>
                <div class="label">Envíos este mes</div>
            </div>
            <div class="card card-metric">
                <div class="icono">🚛</div>
                <div class="valor"><?= $metricas['vehiculos_activos'] ?></div>
                <div class="label">Vehículos en ruta</div>
            </div>
            <div class="card card-metric">
                <div class="icono">🧑‍✈️</div>
                <div class="valor"><?= $metricas['total_choferes'] ?></div>
                <div class="label">Choferes registrados</div>
            </div>
            <div class="card card-metric">
                <div class="icono">⚠️</div>
                <div class="valor" style="color:<?= $metricas['incidentes'] > 0 ? '#f87171' : '#4ade80' ?>">
                    <?= $metricas['incidentes'] ?>
                </div>
                <div class="label">Incidentes (30 días)</div>
            </div>
        </div>

        <!-- Viajes recientes -->
        <div class="tabla-wrapper">
            <div class="tabla-header">
                <h3>🗺️ Viajes Recientes</h3>
                <a href="/logitrack/views/admin/viajes.php" class="btn btn-secondary btn-sm">Ver todos</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Chofer</th>
                        <th>Patente</th>
                        <th>Salida</th>
                        <th>Llegada Est.</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($viajes as $v): ?>
                    <tr>
                        <td><?= $v['id_viaje'] ?></td>
                        <td><?= htmlspecialchars($v['chofer_nombre'] . ' ' . $v['chofer_apellido']) ?></td>
                        <td><code><?= htmlspecialchars($v['patente']) ?></code></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['fecha_salida'])) ?></td>
                        <td><?= $v['fecha_llegada_est'] ? date('d/m/Y H:i', strtotime($v['fecha_llegada_est'])) : '—' ?></td>
                        <td>
                            <?php
                            $ahora  = time();
                            $salida = strtotime($v['fecha_salida']);
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
                    <tr><td colspan="6" style="text-align:center;color:var(--gris);padding:32px;">Sin viajes registrados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Vista v_envios_completo -->
        <div class="tabla-wrapper" style="margin-top:32px;">
            <div class="tabla-header">
                <h3>📋 Todos los Envíos</h3>
            </div>

            <?php if (!$vista_disponible): ?>
            <div class="alert alert-error" style="margin:16px 0;">
                La vista <code>v_envios_completo</code> no existe aún. Ejecutá
                <code>sql/v_envios_completo.sql</code> en phpMyAdmin para activar esta sección.
            </div>
            <?php else: ?>

            <!-- Filtros inline -->
            <form method="GET" action="/logitrack/views/admin/dashboard.php"
                  style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                <input type="text" name="busq_env" placeholder="Tracking, remitente o destinatario"
                       value="<?= htmlspecialchars($f_busq_env) ?>"
                       style="flex:1;min-width:200px;padding:8px 12px;background:var(--panel);border:1px solid var(--borde);border-radius:8px;color:var(--texto);font-size:13px;">
                <select name="estado_env"
                        style="padding:8px 12px;background:var(--panel);border:1px solid var(--borde);border-radius:8px;color:var(--texto);font-size:13px;">
                    <option value="">Todos los estados</option>
                    <option value="Pendiente"                      <?= $f_estado_env === 'Pendiente'                      ? 'selected' : '' ?>>Pendiente</option>
                    <option value="En Tránsito"                    <?= $f_estado_env === 'En Tránsito'                    ? 'selected' : '' ?>>En Tránsito</option>
                    <option value="Ingresado en sucursal destino"  <?= $f_estado_env === 'Ingresado en sucursal destino'  ? 'selected' : '' ?>>En sucursal destino</option>
                    <option value="Entregado"                      <?= $f_estado_env === 'Entregado'                      ? 'selected' : '' ?>>Entregado</option>
                </select>
                <button type="submit" class="btn btn-primary" style="height:38px;">Filtrar</button>
                <a href="/logitrack/views/admin/dashboard.php" class="btn btn-secondary" style="height:38px;line-height:38px;">Limpiar</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Remitente</th>
                        <th>Destinatario</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Peso</th>
                        <th>Estado</th>
                        <th>Ubicación actual</th>
                        <th>Últ. evento</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($envios_vista as $ev): ?>
                <tr>
                    <td><code style="font-size:12px;"><?= htmlspecialchars($ev['nro_tracking']) ?></code></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($ev['remitente']) ?></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($ev['destinatario']) ?></td>
                    <td style="font-size:12px;color:var(--gris);"><?= htmlspecialchars($ev['sucursal_origen']) ?></td>
                    <td style="font-size:12px;color:var(--gris);"><?= htmlspecialchars($ev['sucursal_destino']) ?></td>
                    <td style="font-size:13px;"><?= number_format((float)$ev['peso_kg'], 2) ?> kg</td>
                    <td>
                        <?php
                        $badgeColor = match($ev['estado_actual']) {
                            'Entregado'  => '#4ade80',
                            'En Tránsito'=> '#facc15',
                            'Pendiente'  => '#60a5fa',
                            default      => '#9ca3af',
                        };
                        ?>
                        <span style="color:<?= $badgeColor ?>;font-size:12px;">● <?= htmlspecialchars($ev['estado_actual']) ?></span>
                    </td>
                    <td style="font-size:12px;color:var(--gris);"><?= htmlspecialchars($ev['ubicacion_actual'] ?? '—') ?></td>
                    <td style="font-size:12px;color:var(--gris);"><?= $ev['fecha_ultimo_evento'] ? date('d/m/Y H:i', strtotime($ev['fecha_ultimo_evento'])) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($envios_vista)): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--gris);padding:32px;">Sin envíos que coincidan con los filtros</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </main>
</div>
</body>
</html>
