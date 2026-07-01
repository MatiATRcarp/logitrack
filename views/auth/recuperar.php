<?php
// views/auth/recuperar.php — Recuperación de contraseña (DNI + Email)

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/UsuarioModel.php';

$model = new UsuarioModel($pdo);

$step      = 'buscar'; // buscar | cambiar | listo
$msg_error = '';
$dni       = '';
$email     = '';
$nombre    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $dni    = trim($_POST['dni']   ?? '');
    $email  = trim($_POST['email'] ?? '');

    $usuario = (!empty($dni) && !empty($email)) ? $model->buscarPorDniEmail($dni, $email) : false;

    if ($accion === 'buscar') {
        if (empty($dni) || empty($email)) {
            $msg_error = 'Completá ambos campos.';
        } elseif (!$usuario) {
            $msg_error = 'No se encontró ningún usuario con ese DNI y Email.';
        } else {
            $step   = 'cambiar';
            $nombre = $usuario['nombre'];
        }
    } elseif ($accion === 'cambiar') {
        $password  = $_POST['password']             ?? '';
        $confirmar = $_POST['confirmar_password']    ?? '';

        if (!$usuario) {
            $msg_error = 'No se encontró ningún usuario con ese DNI y Email.';
        } elseif (strlen($password) < 6) {
            $step      = 'cambiar';
            $nombre    = $usuario['nombre'];
            $msg_error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $confirmar) {
            $step      = 'cambiar';
            $nombre    = $usuario['nombre'];
            $msg_error = 'Las contraseñas no coinciden.';
        } else {
            $model->actualizarPassword($dni, $email, $password);
            $step = 'listo';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Recuperar contraseña</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>

<div class="login-page">

    <div class="login-izquierda">
        <div class="info">
            <p class="subtitulo">SISTEMA DE GESTIÓN LOGÍSTICA</p>
            <h1 class="logo-grande">LOGI<span>TRACK</span></h1>
            <p class="descripcion">
                Plataforma especializada en el seguimiento de envíos,
                gestión de rutas y control de sucursales en tiempo real.
            </p>
        </div>
    </div>

    <div class="login-panel">

        <div class="login-logo">
            <span>LOGI<span>TRACK</span></span>
            <img src="/logitrack/public/img/camion.png" alt="Camión">
        </div>

        <p class="login-bienvenida">¿Olvidaste tu contraseña?</p>
        <h2 class="login-titulo">RECUPERAR ACCESO</h2>

        <?php if ($msg_error): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><?= htmlspecialchars($msg_error) ?></div>
        <?php endif; ?>

        <?php if ($step === 'buscar'): ?>

            <p style="color:var(--gris);font-size:13px;margin-bottom:16px;">
                Ingresá tu DNI y tu Email para verificar tu identidad.
            </p>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="accion" value="buscar">
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <input type="text" name="dni" placeholder="DNI" required
                           value="<?= htmlspecialchars($dni) ?>">
                    <input type="email" name="email" placeholder="Email" required
                           value="<?= htmlspecialchars($email) ?>">

                    <button type="submit" class="btn btn-primary" style="width:100%;height:52px;font-size:15px;letter-spacing:3px;">
                        VERIFICAR
                    </button>

                    <p style="color:var(--gris);font-size:13px;text-align:center;">
                        <a href="/logitrack/index.php" style="color:var(--amarillo);text-decoration:none;">Volver al inicio de sesión</a>
                    </p>
                </div>
            </form>

        <?php elseif ($step === 'cambiar'): ?>

            <p style="color:var(--gris);font-size:13px;margin-bottom:16px;">
                Hola, <?= htmlspecialchars($nombre) ?>. Ingresá tu nueva contraseña.
            </p>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="accion" value="cambiar">
                <input type="hidden" name="dni" value="<?= htmlspecialchars($dni) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <input type="password" name="password" placeholder="Nueva contraseña" required autocomplete="new-password">
                    <input type="password" name="confirmar_password" placeholder="Confirmar contraseña" required autocomplete="new-password">

                    <button type="submit" class="btn btn-primary" style="width:100%;height:52px;font-size:15px;letter-spacing:3px;">
                        CAMBIAR CONTRASEÑA
                    </button>
                </div>
            </form>

        <?php else: ?>

            <div class="alert alert-success" style="margin-bottom:16px;">
                ✓ Tu contraseña fue actualizada correctamente.
            </div>

            <a href="/logitrack/index.php" class="btn btn-primary" style="width:100%;height:52px;font-size:15px;letter-spacing:3px;justify-content:center;">
                INICIAR SESIÓN
            </a>

        <?php endif; ?>

    </div>
</div>
</body>
</html>
