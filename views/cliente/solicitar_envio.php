<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['cliente']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$controller = new ClienteController($pdo);
$cliente    = $controller->getPerfil((int) $_SESSION['usuario_id']);

if (!$cliente) {
    header('Location: /logitrack/views/cliente/dashboard.php');
    exit;
}

$mensaje  = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->solicitarEnvio($cliente['id_cliente'], $_POST);
    if ($resultado['ok']) {
        $_SESSION['flash_msg']  = "✓ Envío registrado. Tracking: <strong>" . htmlspecialchars($resultado['nro_tracking']) . "</strong>";
        $_SESSION['flash_tipo'] = 'success';
        header('Location: /logitrack/views/cliente/solicitar_envio.php');
        exit;
    }
    $mensaje  = $resultado['mensaje'];
    $tipo_msg = 'error';
}

if (!empty($_SESSION['flash_msg'])) {
    $mensaje  = $_SESSION['flash_msg'];
    $tipo_msg = $_SESSION['flash_tipo'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
}

$form            = $controller->getFormData();
$tipos_contenido = $form['tipos_contenido'];
$sucursales      = $form['sucursales'];
$destinatarios   = $form['clientes'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Solicitar Envío</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">SOLICITAR ENVÍO</h1>
                <p class="page-subtitle">Registrá un nuevo paquete a enviar</p>
            </div>
            <a href="/logitrack/views/cliente/dashboard.php" class="btn btn-secondary">← Volver</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_msg === 'success' ? 'success' : 'error' ?>" style="max-width:640px;margin-bottom:20px;">
                <?= htmlspecialchars($mensaje) ?>
                <?php if ($tipo_msg === 'success'): ?>
                    — <a href="/logitrack/views/cliente/dashboard.php" style="color:var(--verde);">Ver mis envíos</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="form-card" style="max-width:640px;">
            <form method="POST" action="/logitrack/views/cliente/solicitar_envio.php">

                <div class="form-grid">

                    <div class="form-group full">
                        <label>Remitente (vos)</label>
                        <input type="text"
                               value="<?= htmlspecialchars($cliente['apellido'] . ', ' . $cliente['nombre'] . ' — DNI: ' . $cliente['dni']) ?>"
                               disabled style="opacity:.6;">
                        <input type="hidden" name="id_remitente" value="<?= $cliente['id_cliente'] ?>">
                    </div>

                    <div class="form-group full">
                        <label>DNI del destinatario</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="dni-dest" placeholder="Ej: 30123456"
                                   maxlength="8" pattern="\d{7,8}" autocomplete="off"
                                   style="flex:1;" inputmode="numeric">
                            <button type="button" id="btn-buscar-dest"
                                    class="btn btn-secondary" style="height:42px;white-space:nowrap;">
                                Buscar
                            </button>
                        </div>
                        <div id="res-dest" style="margin-top:6px;font-size:13px;min-height:18px;"></div>
                        <input type="hidden" name="id_destinatario" id="id-dest-hidden"
                               value="<?= (int)($_POST['id_destinatario'] ?? 0) ?: '' ?>">
                    </div>

                    <div class="form-group">
                        <label>Sucursal Origen</label>
                        <select name="id_suc_origen" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= $s['id_sucursal'] ?>"
                                    <?= (isset($_POST['id_suc_origen']) && (int)$_POST['id_suc_origen'] === (int)$s['id_sucursal']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sucursal Destino</label>
                        <select name="id_suc_destino" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= $s['id_sucursal'] ?>"
                                    <?= (isset($_POST['id_suc_destino']) && (int)$_POST['id_suc_destino'] === (int)$s['id_sucursal']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Contenido</label>
                        <select name="id_tipo_cont" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($tipos_contenido as $tc): ?>
                                <option value="<?= $tc['id_tipo_cont'] ?>"
                                    <?= (isset($_POST['id_tipo_cont']) && (int)$_POST['id_tipo_cont'] === (int)$tc['id_tipo_cont']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tc['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Peso (kg)</label>
                        <input type="number" name="peso_kg"
                               min="0.01" max="30000" step="0.01"
                               placeholder="Ej: 12.5"
                               value="<?= htmlspecialchars($_POST['peso_kg'] ?? '') ?>"
                               required>
                    </div>

                </div>

                <button type="submit" class="btn btn-primary"
                        style="width:100%;height:50px;margin-top:20px;font-size:15px;letter-spacing:2px;">
                    📦 SOLICITAR ENVÍO
                </button>

            </form>
        </div>
    </main>
</div>
<script>
(function () {
    const dniBuscar  = document.getElementById('dni-dest');
    const btnBuscar  = document.getElementById('btn-buscar-dest');
    const resultado  = document.getElementById('res-dest');
    const idHidden   = document.getElementById('id-dest-hidden');

    async function buscar() {
        const dni = dniBuscar.value.trim();
        if (!/^\d{7,8}$/.test(dni)) {
            resultado.innerHTML = '<span style="color:#f87171;">Ingresá un DNI válido (7 u 8 dígitos).</span>';
            idHidden.value = '';
            return;
        }
        resultado.textContent = 'Buscando...';
        try {
            const res  = await fetch('/logitrack/api/buscar_cliente.php?dni=' + encodeURIComponent(dni));
            const data = await res.json();
            if (data.found) {
                resultado.innerHTML = '<span style="color:#4ade80;">✓ ' + data.nombre + ' — DNI: ' + data.dni + '</span>';
                idHidden.value = data.id_cliente;
            } else {
                resultado.innerHTML = '<span style="color:#f87171;">No existe un cliente con ese DNI.</span>';
                idHidden.value = '';
            }
        } catch {
            resultado.innerHTML = '<span style="color:#f87171;">Error al buscar. Intentá de nuevo.</span>';
            idHidden.value = '';
        }
    }

    btnBuscar.addEventListener('click', buscar);
    dniBuscar.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); buscar(); } });
})();
</script>
</body>
</html>
