<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

$model = new UsuarioModel($pdo);
$usuario = $model->getPorId((int) $_SESSION['usuario_id']);
$msg = '';
$msg_tipo = '';

$clienteExtra = null;
if ($usuario && $usuario['rol'] === 'cliente') {
    $stmt = $pdo->prepare("SELECT telefono, direccion FROM cliente WHERE dni = :dni LIMIT 1");
    $stmt->execute([':dni' => $usuario['dni']]);
    $clienteExtra = $stmt->fetch() ?: ['telefono' => '', 'direccion' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    $errores = [];
    if ($nombre === '' || $apellido === '')               $errores[] = 'Completá nombre y apellido.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $errores[] = 'Email inválido.';

    $nuevo_dni = null;
    if ($usuario['rol'] === 'admin') {
        $nuevo_dni = trim($_POST['dni'] ?? '');
        if (!preg_match('/^\d{7,8}$/', $nuevo_dni))      $errores[] = 'El DNI debe tener 7 u 8 dígitos numéricos.';
    }

    if ($errores) {
        $msg = implode(' ', $errores);
        $msg_tipo = 'error';
    } else {
        try {
            if ($usuario['rol'] === 'admin' && $nuevo_dni !== $usuario['dni']) {
                $pdo->prepare("UPDATE usuario SET dni = :dni WHERE id_usuario = :id")
                    ->execute([':dni' => $nuevo_dni, ':id' => $_SESSION['usuario_id']]);
            }
            $model->actualizarPerfil(
                (int) $_SESSION['usuario_id'],
                $nombre,
                $apellido,
                $email,
                $telefono !== '' ? $telefono : null,
                $direccion !== '' ? $direccion : null
            );
            $_SESSION['usuario_nombre'] = $nombre;
            $usuario = $model->getPorId((int) $_SESSION['usuario_id']);
            if ($usuario['rol'] === 'cliente') {
                $stmt = $pdo->prepare("SELECT telefono, direccion FROM cliente WHERE dni = :dni LIMIT 1");
                $stmt->execute([':dni' => $usuario['dni']]);
                $clienteExtra = $stmt->fetch() ?: ['telefono' => '', 'direccion' => ''];
            }
            $msg = 'Datos actualizados correctamente.';
            $msg_tipo = 'success';
        } catch (PDOException $e) {
            $msg = $e->getCode() == 23000 ? 'Ese DNI o email ya está en uso.' : 'No se pudo actualizar el perfil.';
            $msg_tipo = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Mi Perfil</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/layout/sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar">
            <div>
                <h1 class="page-title">MI PERFIL</h1>
                <p class="page-subtitle">Datos personales de la cuenta</p>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_tipo === 'success' ? 'success' : 'error' ?>" style="max-width:600px;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <?php if (!$usuario): ?>
            <div class="alert alert-error">No se encontró el usuario.</div>
        <?php else: ?>
        <div class="form-card">
            <form method="POST" action="/logitrack/views/perfil.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label>DNI<?= $usuario['rol'] === 'admin' ? ' <span style="font-size:11px;color:var(--gris);font-weight:400;">(editable)</span>' : '' ?></label>
                        <?php if ($usuario['rol'] === 'admin'): ?>
                        <input type="text" name="dni" value="<?= htmlspecialchars($usuario['dni']) ?>"
                               maxlength="8" pattern="\d{7,8}" inputmode="numeric" required>
                        <?php else: ?>
                        <input type="text" value="<?= htmlspecialchars($usuario['dni']) ?>" disabled>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Rol</label>
                        <input type="text" value="<?= htmlspecialchars(ucfirst($usuario['rol'])) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Apellido</label>
                        <input type="text" name="apellido" value="<?= htmlspecialchars($usuario['apellido']) ?>" required>
                    </div>
                    <div class="form-group full">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                    </div>
                    <?php if ($usuario['rol'] === 'cliente'): ?>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" value="<?= htmlspecialchars($clienteExtra['telefono'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="direccion" value="<?= htmlspecialchars($clienteExtra['direccion'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:18px;">Guardar cambios</button>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
