<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['cliente']);
require_once __DIR__ . '/../../config/db.php';

// ── Búsqueda por número de tracking (funcionalidad original) ──────────────────
$historial = [];
$errorBusq = '';
$nro       = trim($_GET['nro'] ?? $_POST['nro_tracking'] ?? '');

if ($nro) {
    if (!preg_match('/^[A-Z0-9\-]{6,30}$/i', $nro)) {
        $errorBusq = 'Número de tracking inválido.';
    } else {
        $stmt = $pdo->prepare("
            SELECT te.nombre AS estado, he.fecha_hora,
                   COALESCE(s.nombre, 'En tránsito') AS sucursal
            FROM   historial_estado he
            JOIN   envio        e  ON he.id_envio          = e.id_envio
            LEFT JOIN sucursal  s  ON he.id_sucursal_actual = s.id_sucursal
            JOIN   tipo_estado  te ON he.id_estado          = te.id_estado
            WHERE  e.nro_tracking = :nro
            ORDER  BY he.fecha_hora ASC
        ");
        $stmt->execute([':nro' => strtoupper($nro)]);
        $historial = $stmt->fetchAll();
        if (empty($historial)) {
            $errorBusq = 'No se encontró ningún envío con ese número de tracking.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Rastrear mis envíos</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        #map-cliente {
            width: 100%;
            height: 440px;
            border-radius: 16px;
            border: 1px solid var(--borde);
            background: #111;
            margin-bottom: 20px;
            z-index: 1;
        }
        .viajes-lista {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 28px;
        }
        .viaje-chip {
            background: var(--panel);
            border: 1px solid var(--borde);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            cursor: pointer;
            transition: border-color .2s;
        }
        .viaje-chip:hover { border-color: var(--amarillo); }
        .viaje-chip .chip-patente {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 16px;
            color: var(--amarillo);
        }
        .viaje-chip .chip-ruta { color: var(--gris); }
        .progress-bar-bg {
            background: var(--borde);
            border-radius: 6px;
            height: 5px;
            overflow: hidden;
            margin-top: 6px;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 6px;
            background: var(--amarillo);
        }
        .progress-bar-fill.completo { background: #6b7280; }
        .sin-envios {
            color: var(--gris);
            font-size: 15px;
            padding: 24px 0;
        }
        .seccion-titulo {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 20px;
            color: var(--amarillo);
            margin: 0 0 12px;
            letter-spacing: 1px;
        }
        .divider {
            border: none;
            border-top: 1px solid var(--borde);
            margin: 28px 0;
        }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">RASTREAR MIS ENVÍOS</h1>
                <p class="page-subtitle">Posición en tiempo real de tus paquetes en camino</p>
            </div>
        </div>

        <!-- ── MAPA ──────────────────────────────────────────────────── -->
        <p class="seccion-titulo">🗺️ Mapa en vivo</p>
        <div id="map-cliente"></div>

        <div id="lista-envios-activos">
            <div class="sin-envios">Cargando...</div>
        </div>

        <hr class="divider">

        <!-- ── BÚSQUEDA POR TRACKING ─────────────────────────────────── -->
        <p class="seccion-titulo">🔍 Buscar por número de tracking</p>
        <div class="form-card" style="max-width:480px;margin-bottom:24px;">
            <form method="GET" action="/logitrack/views/cliente/tracking.php">
                <div class="form-group">
                    <label>Número de tracking</label>
                    <input type="text" name="nro" placeholder="Ej: TRK-ABC12345"
                           value="<?= htmlspecialchars($nro) ?>"
                           maxlength="30" style="text-transform:uppercase;">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;height:46px;margin-top:12px;">
                    🔍 RASTREAR
                </button>
            </form>
        </div>

        <?php if ($errorBusq): ?>
            <div class="alert alert-error" style="max-width:480px;"><?= htmlspecialchars($errorBusq) ?></div>
        <?php endif; ?>

        <?php if (!empty($historial)): ?>
        <div class="form-card" style="max-width:560px;">
            <h3 style="margin-bottom:20px;">📦 <?= htmlspecialchars(strtoupper($nro)) ?></h3>
            <div class="timeline">
                <?php foreach ($historial as $h): ?>
                <div class="timeline-item">
                    <div class="timeline-dot">📍</div>
                    <div class="timeline-body">
                        <div class="estado"><?= htmlspecialchars($h['estado']) ?></div>
                        <div class="fecha"><?= date('d/m/Y H:i', strtotime($h['fecha_hora'])) ?></div>
                        <div class="lugar"><?= htmlspecialchars($h['sucursal']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const TRACKING_URL = '/logitrack/api/tracking.php?cliente=1';
const REFRESH_MS   = 30000;

const map = L.map('map-cliente').setView([-38.4161, -63.6167], 5);

L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap, © CARTO',
    subdomains: 'abcd',
    maxZoom: 18
}).addTo(map);

const iconCamion = L.divIcon({
    html: '<div style="font-size:22px;line-height:1;filter:drop-shadow(0 0 4px #facc15);">🚛</div>',
    className: '',
    iconAnchor: [11, 11]
});
const iconCamionFin = L.divIcon({
    html: '<div style="font-size:22px;line-height:1;opacity:.45;">🚛</div>',
    className: '',
    iconAnchor: [11, 11]
});
const iconOrigen = L.divIcon({
    html: '<div style="width:10px;height:10px;background:#22c55e;border-radius:50%;border:2px solid #fff;"></div>',
    className: '',
    iconAnchor: [5, 5]
});
const iconDestino = L.divIcon({
    html: '<div style="width:10px;height:10px;background:#ef4444;border-radius:50%;border:2px solid #fff;"></div>',
    className: '',
    iconAnchor: [5, 5]
});

let capas = {};

function formatFecha(str) {
    if (!str) return '—';
    const d = new Date(str.replace(' ', 'T'));
    return d.toLocaleDateString('es-AR') + ' ' + d.toLocaleTimeString('es-AR', {hour:'2-digit', minute:'2-digit'});
}

function renderViajes(viajes) {
    const listaEl = document.getElementById('lista-envios-activos');

    const idsActivos = new Set(viajes.map(v => v.id_viaje));
    for (const id in capas) {
        if (!idsActivos.has(Number(id))) {
            capas[id].forEach(c => map.removeLayer(c));
            delete capas[id];
        }
    }

    if (viajes.length === 0) {
        listaEl.innerHTML = '<div class="sin-envios">No tenés envíos en camino en este momento.</div>';
        return;
    }

    let html = '<div class="viajes-lista">';
    viajes.forEach(v => {
        const terminado = v.progreso_pct >= 100;
        const pct       = Math.min(100, v.progreso_pct);

        html += `
        <div class="viaje-chip" onclick="centrar(${v.lat_actual}, ${v.lng_actual}, ${v.id_viaje})">
            <div class="chip-patente">${v.patente}</div>
            <div class="chip-ruta">${v.sucursal_origen} → ${v.sucursal_destino}</div>
            <div class="chip-ruta">${terminado ? 'Llegó a destino' : pct + '% · ETA ' + formatFecha(v.fecha_llegada_est)}</div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill ${terminado ? 'completo' : ''}" style="width:${pct}%"></div>
            </div>
        </div>`;

        if (capas[v.id_viaje]) {
            capas[v.id_viaje].forEach(c => map.removeLayer(c));
        }

        const linea    = L.polyline(
            [[v.lat_origen, v.lng_origen], [v.lat_destino, v.lng_destino]],
            { color: '#facc15', weight: 2, dashArray: '6 6', opacity: 0.45 }
        ).addTo(map);

        const mOrigen  = L.marker([v.lat_origen,  v.lng_origen],  { icon: iconOrigen  }).addTo(map)
            .bindPopup(`<b>${v.sucursal_origen}</b>`);
        const mDestino = L.marker([v.lat_destino, v.lng_destino], { icon: iconDestino }).addTo(map)
            .bindPopup(`<b>${v.sucursal_destino}</b>`);

        const popupTxt = terminado
            ? `<b>${v.patente}</b><br>Llegó a destino<br>${v.sucursal_destino}`
            : `<b>${v.nro_tracking || v.patente}</b><br>
               ${v.sucursal_origen} → ${v.sucursal_destino}<br>
               Progreso: ${v.progreso_pct}%<br>
               ETA: ${formatFecha(v.fecha_llegada_est)}`;

        const mCamion  = L.marker([v.lat_actual, v.lng_actual], {
            icon: terminado ? iconCamionFin : iconCamion
        }).addTo(map).bindPopup(popupTxt);

        capas[v.id_viaje] = [linea, mOrigen, mDestino, mCamion];
    });

    html += '</div>';
    listaEl.innerHTML = html;
}

function centrar(lat, lng, id) {
    map.setView([lat, lng], 8, { animate: true });
    if (capas[id]) capas[id][3].openPopup();
}

async function cargar() {
    try {
        const res  = await fetch(TRACKING_URL);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        renderViajes(data);
    } catch (e) {
        console.error('Error cargando tracking:', e);
        document.getElementById('lista-envios-activos').innerHTML =
            '<div class="sin-envios">Error al cargar los datos. Reintentando...</div>';
    }
}

cargar();
setInterval(cargar, REFRESH_MS);
</script>
</body>
</html>
