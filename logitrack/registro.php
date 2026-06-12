<?php
session_start();

$msg_error = '';

if (isset($_SESSION['flash_error'])) {
    $msg_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack - Registro de Cliente</title>
    <link rel="stylesheet" href="registro.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
</head>
<body>
<div class="contenedor">
    <div class="izquierda">
        <div class="info-izquierda">
            <p class="subtitulo">SISTEMA DE GESTIÓN LOGÍSTICA</p>
            <h1 class="logo-grande">LOGI<span>TRACK</span></h1>
            <p class="descripcion">
                Plataforma especializada en el seguimiento de envíos,
                gestión de rutas y control de sucursales en tiempo real.
            </p>
        </div>
    </div>
    <div class="login">
        <div class="logo">
            <h1 class="titulo">LOGI<span>TRACK</span></h1>
            <img src="/logitrack/public/img/camion.png" alt="Camión" class="camion">
        </div>
        <p class="bienvenida">¡Creá tu cuenta!</p>
        <h2>REGISTRARSE</h2>
        <form action="procesar_registro.php" method="post" id="form-registro">
            <input type="hidden" name="rol" value="cliente">
            <input type="text"     name="dni"                  placeholder="DNI"               required>
            <input type="text"     name="nombre"               placeholder="Nombre"            required>
            <input type="text"     name="apellido"             placeholder="Apellido"          required>
            <input type="email"    name="email"                placeholder="Email"             required>
            <input type="text"     name="direccion"            placeholder="Dirección"         required>
            <input type="password" name="password"             placeholder="Contraseña"        required>
            <input type="password" name="confirmar_password"   placeholder="Confirmar contraseña" required>
            <?php if ($msg_error): ?>
            <div class="error-msg"><?= htmlspecialchars($msg_error) ?></div>
            <?php endif; ?>
            <button type="submit">REGISTRARSE</button>
            <p class="link-login">¿Ya tenés cuenta? <a href="/logitrack/index.php">Iniciá sesión</a></p>
        </form>
    </div>
</div>
</body>
</html>
