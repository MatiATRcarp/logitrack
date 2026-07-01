<?php
// views/layout/sidebar.php — Menú lateral dinámico por rol
// Requiere que $_SESSION['usuario_rol'] y $_SESSION['usuario_nombre'] existan

$rol    = $_SESSION['usuario_rol']    ?? '';
$nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$pagina = basename($_SERVER['PHP_SELF']); // Para marcar enlace activo
?>
<aside class="sidebar">

    <div class="sidebar-logo">
        LOGI<span>TRACK</span>
    </div>

    <div class="sidebar-user">
        <div class="nombre"><?= htmlspecialchars($nombre) ?></div>
        <span class="rol-badge badge-<?= htmlspecialchars($rol) ?>"><?= htmlspecialchars(ucfirst($rol)) ?></span>
    </div>

    <nav class="sidebar-nav">

        <?php if ($rol === 'admin'): ?>
        <!-- ── MENÚ ADMINISTRADOR ── -->
        <div class="nav-section-title">General</div>
        <a href="/logitrack/views/admin/dashboard.php"
           class="nav-link <?= $pagina === 'dashboard.php' ? 'active' : '' ?>">
            <span class="icon">📊</span> Dashboard
        </a>

        <div class="nav-section-title">Gestión del Negocio</div>
        <a href="/logitrack/views/admin/sucursales.php"
           class="nav-link <?= $pagina === 'sucursales.php' ? 'active' : '' ?>">
            <span class="icon">🏢</span> Sucursales
        </a>
        <a href="/logitrack/views/admin/usuarios.php"
           class="nav-link <?= $pagina === 'usuarios.php' ? 'active' : '' ?>">
            <span class="icon">👥</span> Usuarios
        </a>
        <a href="/logitrack/views/admin/clientes.php"
           class="nav-link <?= $pagina === 'clientes.php' ? 'active' : '' ?>">
            <span class="icon">🧑</span> Clientes
        </a>
        <a href="/logitrack/views/admin/empleados.php"
           class="nav-link <?= $pagina === 'empleados.php' ? 'active' : '' ?>">
            <span class="icon">🧑‍💼</span> Empleados
        </a>

        <div class="nav-section-title">Control de Flota</div>
        <a href="/logitrack/views/admin/vehiculos.php"
           class="nav-link <?= $pagina === 'vehiculos.php' ? 'active' : '' ?>">
            <span class="icon">🚛</span> Vehículos
        </a>
        <a href="/logitrack/views/admin/choferes.php"
           class="nav-link <?= $pagina === 'choferes.php' ? 'active' : '' ?>">
            <span class="icon">🧑‍✈️</span> Choferes
        </a>

        <div class="nav-section-title">Auditoría</div>
        <a href="/logitrack/views/admin/viajes.php"
           class="nav-link <?= $pagina === 'viajes.php' ? 'active' : '' ?>">
            <span class="icon">🗺️</span> Viajes Activos
        </a>
        <a href="/logitrack/views/admin/mapa_tracking.php"
           class="nav-link <?= $pagina === 'mapa_tracking.php' ? 'active' : '' ?>">
            <span class="icon">📡</span> Mapa de Tracking
        </a>
        <a href="/logitrack/views/admin/viajes_realizados.php"
           class="nav-link <?= $pagina === 'viajes_realizados.php' ? 'active' : '' ?>">
            <span class="icon">📜</span> Viajes Realizados
        </a>
        <a href="/logitrack/views/admin/incidentes.php"
           class="nav-link <?= $pagina === 'incidentes.php' ? 'active' : '' ?>">
            <span class="icon">⚠️</span> Incidentes
        </a>
        <div class="nav-section-title">Mi Cuenta</div>
        <a href="/logitrack/views/perfil.php"
           class="nav-link <?= $_SERVER['PHP_SELF'] === '/logitrack/views/perfil.php' ? 'active' : '' ?>">
            <span class="icon">👤</span> Mi Perfil
        </a>

        <?php elseif ($rol === 'empleado'): ?>
        <!-- ── MENÚ EMPLEADO ── -->
        <div class="nav-section-title">Operación</div>
        <a href="/logitrack/views/empleado/dashboard.php"
           class="nav-link <?= $pagina === 'dashboard.php' ? 'active' : '' ?>">
            <span class="icon">📦</span> Paquetes Pendientes
        </a>
        <a href="/logitrack/views/empleado/nuevo_envio.php"
           class="nav-link <?= $pagina === 'nuevo_envio.php' ? 'active' : '' ?>">
            <span class="icon">➕</span> Nuevo Envío
        </a>
        <a href="/logitrack/views/empleado/armar_viaje.php"
           class="nav-link <?= $pagina === 'armar_viaje.php' ? 'active' : '' ?>">
            <span class="icon">🚚</span> Armar Viaje
        </a>
        <a href="/logitrack/views/empleado/escanear.php"
           class="nav-link <?= $pagina === 'escanear.php' ? 'active' : '' ?>">
            <span class="icon">📷</span> Escanear Paquete
        </a>
        <a href="/logitrack/views/empleado/viajes.php"
           class="nav-link <?= $pagina === 'viajes.php' ? 'active' : '' ?>">
            <span class="icon">🗺️</span> Viajes Activos
        </a>
        <a href="/logitrack/views/admin/mapa_tracking.php"
           class="nav-link <?= $pagina === 'mapa_tracking.php' ? 'active' : '' ?>">
            <span class="icon">📡</span> Mapa de Tracking
        </a>
        <div class="nav-section-title">Mi Cuenta</div>
        <a href="/logitrack/views/perfil.php"
           class="nav-link <?= $_SERVER['PHP_SELF'] === '/logitrack/views/perfil.php' ? 'active' : '' ?>">
            <span class="icon">👤</span> Mi Perfil
        </a>

        <?php elseif ($rol === 'chofer'): ?>
        <!-- ── MENÚ CHOFER ── -->
        <div class="nav-section-title">Mi Jornada</div>
        <a href="/logitrack/views/chofer/dashboard.php"
           class="nav-link <?= $pagina === 'dashboard.php' ? 'active' : '' ?>">
            <span class="icon">🗺️</span> Mi Viaje Activo
        </a>
        <a href="/logitrack/views/chofer/carga.php"
           class="nav-link <?= $pagina === 'carga.php' ? 'active' : '' ?>">
            <span class="icon">📦</span> Mi Carga
        </a>
        <a href="/logitrack/views/chofer/incidente.php"
           class="nav-link <?= $pagina === 'incidente.php' ? 'active' : '' ?>">
            <span class="icon">🚨</span> Reportar Incidente
        </a>

        <div class="nav-section-title">Mi Cuenta</div>
        <a href="/logitrack/views/chofer/perfil.php"
           class="nav-link <?= $pagina === 'perfil.php' ? 'active' : '' ?>">
            <span class="icon">👤</span> Mi Perfil
        </a>

        <?php elseif ($rol === 'cliente'): ?>
        <!-- ── MENÚ CLIENTE ── -->
        <div class="nav-section-title">Mis Paquetes</div>
        <a href="/logitrack/views/cliente/solicitar_envio.php"
           class="nav-link <?= $pagina === 'solicitar_envio.php' ? 'active' : '' ?>">
            <span class="icon">➕</span> Solicitar Envío
        </a>
        <a href="/logitrack/views/cliente/dashboard.php"
           class="nav-link <?= $pagina === 'dashboard.php' ? 'active' : '' ?>">
            <span class="icon">📤</span> Envíos Realizados
        </a>
        <a href="/logitrack/views/cliente/a_recibir.php"
           class="nav-link <?= $pagina === 'a_recibir.php' ? 'active' : '' ?>">
            <span class="icon">📥</span> A Recibir
        </a>
        <a href="/logitrack/views/cliente/tracking.php"
           class="nav-link <?= $pagina === 'tracking.php' ? 'active' : '' ?>">
            <span class="icon">📍</span> Rastrear mis envíos
        </a>
        <div class="nav-section-title">Mi Cuenta</div>
        <a href="/logitrack/views/perfil.php"
           class="nav-link <?= $_SERVER['PHP_SELF'] === '/logitrack/views/perfil.php' ? 'active' : '' ?>">
            <span class="icon">👤</span> Mi Perfil
        </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <form action="/logitrack/controllers/AuthController.php" method="POST" style="margin:0;">
            <input type="hidden" name="accion" value="logout">
            <button type="submit" class="btn-logout">🚪 Cerrar sesión</button>
        </form>
    </div>

</aside>
