<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['empleado', 'admin']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/EmpleadoController.php';

$controller = new EmpleadoController($pdo);
$mensaje    = '';
$tipo_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->crearEnvio($_POST);
    if ($resultado['ok']) {
        $_SESSION['flash_msg']  = "✓ Envío creado. Tracking: <strong>{$resultado['nro_tracking']}</strong>";
        $_SESSION['flash_tipo'] = 'success';
        header("Location: /logitrack/views/empleado/nuevo_envio.php");
        exit;
    } else {
        $mensaje  = $resultado['mensaje'];
        $tipo_msg = 'error';
    }
}

if (!empty($_SESSION['flash_msg'])) {
    $mensaje  = $_SESSION['flash_msg'];
    $tipo_msg = $_SESSION['flash_tipo'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
}

$form            = $controller->getFormData();
$tipos_contenido = $form['tipos_contenido'];
$sucursales      = $form['sucursales'];
$clientes        = $form['clientes'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Nuevo Envío</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">NUEVO ENVÍO</h1>
                <p class="page-subtitle">Registrar un nuevo paquete en el sistema</p>
            </div>
            <a href="/logitrack/views/empleado/dashboard.php" class="btn btn-secondary">← Volver</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_msg === 'success' ? 'success' : 'error' ?>" style="max-width:640px;margin-bottom:20px;">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <div class="form-card" style="max-width:640px;">
            <form method="POST" action="/logitrack/views/empleado/nuevo_envio.php" autocomplete="off">

                <div class="form-grid">

                    <div class="form-group">
                        <label>DNI del remitente</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="dni-rem" placeholder="Ej: 30123456"
                                   maxlength="8" pattern="\d{7,8}" autocomplete="off"
                                   style="flex:1;" inputmode="numeric">
                            <button type="button" id="btn-buscar-rem"
                                    class="btn btn-secondary" style="height:42px;white-space:nowrap;">Buscar</button>
                        </div>
                        <div id="res-rem" style="margin-top:6px;font-size:13px;min-height:18px;"></div>
                        <input type="hidden" name="id_remitente" id="id-rem-hidden">
                    </div>

                    <div class="form-group">
                        <label>DNI del destinatario</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="dni-dest" placeholder="Ej: 30123456"
                                   maxlength="8" pattern="\d{7,8}" autocomplete="off"
                                   style="flex:1;" inputmode="numeric">
                            <button type="button" id="btn-buscar-dest"
                                    class="btn btn-secondary" style="height:42px;white-space:nowrap;">Buscar</button>
                        </div>
                        <div id="res-dest" style="margin-top:6px;font-size:13px;min-height:18px;"></div>
                        <input type="hidden" name="id_destinatario" id="id-dest-hidden">
                    </div>

                    <div class="form-group">
                        <label>Sucursal Origen</label>
                        <select name="id_suc_origen" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($sucursales as $s): ?>
                            <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sucursal Destino</label>
                        <select name="id_suc_destino" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($sucursales as $s): ?>
                            <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Contenido</label>
                        <select name="id_tipo_cont" required>
                            <option value="">Seleccioná...</option>
                            <?php foreach ($tipos_contenido as $tc): ?>
                            <option value="<?= $tc['id_tipo_cont'] ?>"><?= htmlspecialchars($tc['nombre']) ?></option>
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

                <button type="submit" class="btn btn-primary" style="width:100%;height:50px;margin-top:20px;font-size:15px;letter-spacing:2px;">
                    📦 REGISTRAR ENVÍO
                </button>

            </form>
        </div>
    </main>
</div>
<script>
function initBuscadorDNI(inputId, btnId, resultId, hiddenId) {
    const inp    = document.getElementById(inputId);
    const btn    = document.getElementById(btnId);
    const res    = document.getElementById(resultId);
    const hidden = document.getElementById(hiddenId);

    async function buscar() {
        const dni = inp.value.trim();
        if (!/^\d{7,8}$/.test(dni)) {
            res.innerHTML = '<span style="color:#f87171;">DNI inválido (7 u 8 dígitos).</span>';
            hidden.value = '';
            return;
        }
        res.textContent = 'Buscando...';
        try {
            const r    = await fetch('/logitrack/api/buscar_cliente.php?dni=' + encodeURIComponent(dni));
            const data = await r.json();
            if (data.found) {
                res.innerHTML = '<span style="color:#4ade80;">✓ ' + data.nombre + ' — DNI: ' + data.dni + '</span>';
                hidden.value  = data.id_cliente;
            } else {
                res.innerHTML = '<span style="color:#f87171;">No existe un cliente con ese DNI.</span>';
                hidden.value  = '';
            }
        } catch {
            res.innerHTML = '<span style="color:#f87171;">Error al buscar.</span>';
            hidden.value  = '';
        }
    }

    btn.addEventListener('click', buscar);
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); buscar(); } });
}

initBuscadorDNI('dni-rem',  'btn-buscar-rem',  'res-rem',  'id-rem-hidden');
initBuscadorDNI('dni-dest', 'btn-buscar-dest', 'res-dest', 'id-dest-hidden');
</script>
</body>
</html>
