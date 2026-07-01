<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['admin']);
require_once __DIR__ . '/../../config/db.php';

$hay_filtros = !empty(array_filter($_GET));

$f_anio   = $_GET['anio']   ?? '';
$f_mes    = $_GET['mes']    ?? '';
$f_tipo   = $_GET['tipo']   ?? '';
$f_chofer = trim($_GET['chofer'] ?? '');

$where  = [];
$params = [];

if ($f_anio !== '')   { $where[] = 'YEAR(i.fecha_hora) = :anio';  $params[':anio'] = $f_anio; }
if ($f_mes  !== '')   { $where[] = 'MONTH(i.fecha_hora) = :mes';  $params[':mes']  = $f_mes; }
if ($f_tipo !== '')   { $where[] = 'i.tipo_incidente = :tipo';    $params[':tipo'] = $f_tipo; }
if ($f_chofer !== '') {
    $where[] = '(c.nombre LIKE :chofer1 OR c.apellido LIKE :chofer2)';
    $params[':chofer1'] = '%' . $f_chofer . '%';
    $params[':chofer2'] = '%' . $f_chofer . '%';
}

$sql = "
    SELECT i.id_incidente, i.tipo_incidente, i.descripcion, i.fecha_hora,
           i.id_viaje,
           c.nombre AS chofer_nombre, c.apellido AS chofer_apellido
    FROM   incidente i
    JOIN   viaje     v ON i.id_viaje  = v.id_viaje
    JOIN   chofer    c ON v.id_chofer = c.id_chofer";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY i.fecha_hora DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidentes = $stmt->fetchAll();

$tipos_incidente = $pdo->query("SELECT DISTINCT tipo_incidente FROM incidente ORDER BY tipo_incidente")->fetchAll(PDO::FETCH_COLUMN);
$anios_incidente = $pdo->query("SELECT DISTINCT YEAR(fecha_hora) AS anio FROM incidente ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Incidentes</title>
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
                <h1 class="page-title">INCIDENTES</h1>
                <p class="page-subtitle">Registro de incidentes reportados</p>
            </div>
            <label for="chk-filtros" class="btn btn-secondary">🔍 Filtrar</label>
        </div>

        <form method="GET" action="/logitrack/views/admin/incidentes.php" class="filtros">
            <div class="form-group">
                <label>Año</label>
                <select name="anio">
                    <option value="">Todos</option>
                    <?php foreach ($anios_incidente as $a): ?>
                    <option value="<?= $a ?>" <?= (string) $f_anio === (string) $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Mes</label>
                <select name="mes">
                    <option value="">Todos</option>
                    <?php foreach ($meses as $num => $nombre_mes): ?>
                    <option value="<?= $num ?>" <?= (string) $f_mes === (string) $num ? 'selected' : '' ?>><?= $nombre_mes ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tipo</label>
                <select name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos_incidente as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $f_tipo === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Chofer</label>
                <input type="text" name="chofer" placeholder="Nombre o apellido" value="<?= htmlspecialchars($f_chofer) ?>">
            </div>
            <div class="filtros-acciones">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="/logitrack/views/admin/incidentes.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr><th>#</th><th>Viaje</th><th>Chofer</th><th>Tipo</th><th>Descripción</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($incidentes as $i): ?>
                    <tr>
                        <td><?= $i['id_incidente'] ?></td>
                        <td>#<?= $i['id_viaje'] ?></td>
                        <td><?= htmlspecialchars($i['chofer_apellido'] . ', ' . $i['chofer_nombre']) ?></td>
                        <td><span class="badge badge-inactivo"><?= htmlspecialchars($i['tipo_incidente']) ?></span></td>
                        <td><?= htmlspecialchars($i['descripcion']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($i['fecha_hora'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($incidentes)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--gris);padding:32px;">Sin incidentes registrados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
