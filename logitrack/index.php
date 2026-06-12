<?php
// index.php — Login + Tracking público
session_start();

// Si ya está logueado, redirigir a su dashboard
if (isset($_SESSION['usuario_id'])) {
    $rol = $_SESSION['usuario_rol'];
    $destino = match($rol) {
        'admin'    => '/logitrack/views/admin/dashboard.php',
        'empleado' => '/logitrack/views/empleado/dashboard.php',
        'chofer'   => '/logitrack/views/chofer/dashboard.php',
        'cliente'  => '/logitrack/views/cliente/dashboard.php',
        default    => '/logitrack/index.php'
    };
    header("Location: $destino");
    exit;
}

// Mensajes flash guardados en sesión (se muestran una sola vez y se descartan,
// así no quedan pegados si el usuario recarga la página)
$error = '';
$info  = '';

if (isset($_SESSION['flash_info'])) {
    $info = $_SESSION['flash_info'];
    unset($_SESSION['flash_info']);
}

if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// ─── Tracking público ───────────────────────────
$tracking_resultado = null;
$tracking_error     = '';

if (isset($_GET['tracking']) && !empty($_GET['tracking'])) {
    require_once __DIR__ . '/config/db.php';

    $nro = trim($_GET['tracking']);
    // Validar formato básico (alfanumérico, hasta 30 chars)
    if (!preg_match('/^[A-Z0-9]{6,30}$/i', $nro)) {
        $tracking_error = 'Número de tracking inválido.';
    } else {
        // Solo mostramos historial de estados — NUNCA datos personales
        $sql = "SELECT te.nombre AS estado,
                       he.fecha_hora,
                       s.nombre AS sucursal
                FROM   historial_estado he
                JOIN   envio            e  ON he.id_envio = e.id_envio
                JOIN   sucursal         s  ON he.id_sucursal_actual = s.id_sucursal
                JOIN   tipo_estado      te ON he.id_estado = te.id_estado
                WHERE  e.nro_tracking = :nro
                ORDER  BY he.fecha_hora ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nro' => $nro]);
        $tracking_resultado = $stmt->fetchAll();

        if (empty($tracking_resultado)) {
            $tracking_error = 'No se encontró ningún envío con ese número de tracking.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Acceso al Sistema</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>

<div class="login-page">

    <!-- Lado izquierdo — Info y tracking público -->
    <div class="login-izquierda">
        <div class="info">
            <p class="subtitulo">SISTEMA DE GESTIÓN LOGÍSTICA</p>
            <h1 class="logo-grande">LOGI<span>TRACK</span></h1>
            <p class="descripcion">
                Plataforma especializada en el seguimiento de envíos,
                gestión de rutas y control de sucursales en tiempo real.
            </p>

            <!-- Tracking público -->
            <div class="tracking-box" style="margin-top:32px;">
                <label>🔍 RASTREAR PAQUETE (SIN INICIAR SESIÓN)</label>
                <form method="GET" action="/logitrack/index.php" class="tracking-row">
                    <input type="text" name="tracking"
                           placeholder="Ej: TRK-ABC123"
                           value="<?= htmlspecialchars($_GET['tracking'] ?? '') ?>"
                           maxlength="30">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </form>

                <?php if ($tracking_error): ?>
                    <p style="color:#f87171;font-size:13px;margin-top:10px;"><?= htmlspecialchars($tracking_error) ?></p>
                <?php endif; ?>

                <?php if ($tracking_resultado): ?>
                <div style="margin-top:16px;">
                    <div class="timeline">
                        <?php foreach ($tracking_resultado as $evento): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot">📍</div>
                            <div class="timeline-body">
                                <div class="estado"><?= htmlspecialchars($evento['estado']) ?></div>
                                <div class="fecha"><?= date('d/m/Y H:i', strtotime($evento['fecha_hora'])) ?></div>
                                <div class="lugar"><?= htmlspecialchars($evento['sucursal']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel derecho — Login -->
    <div class="login-panel">

        <div class="login-logo">
            <span>LOGI<span>TRACK</span></span>
            <img src="/logitrack/public/img/camion.png" alt="Camión">
        </div>

        <p class="login-bienvenida">¡Bienvenido de nuevo!</p>
        <h2 class="login-titulo">INICIAR SESIÓN</h2>

        <?php if ($info): ?>
            <div class="alert alert-info" style="margin-bottom:16px;"><?= htmlspecialchars($info) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="/logitrack/controllers/AuthController.php" method="POST" autocomplete="off">
            <input type="hidden" name="accion" value="login">

            <div style="display:flex;flex-direction:column;gap:16px;">
                <input type="text" name="email" placeholder="Email" required autocomplete="off"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

                <input type="password" name="password" placeholder="Contraseña" required autocomplete="new-password">

                <button type="submit" class="btn btn-primary" style="width:100%;height:52px;font-size:15px;letter-spacing:3px;">
                    INGRESAR AL SISTEMA
                </button>

                <p style="color:var(--gris);font-size:13px;text-align:center;">
                    ¿No tenés cuenta?
                    <a href="/logitrack/registro.php" style="color:var(--amarillo);text-decoration:none;">Registrate</a>
                </p>

                <p style="color:var(--gris);font-size:13px;text-align:center;">
                    <a href="/logitrack/views/auth/recuperar.php" style="color:var(--amarillo);text-decoration:none;">¿Olvidaste tu contraseña?</a>
                </p>
            </div>
        </form>

    </div>
</div>
</body>
</html>
