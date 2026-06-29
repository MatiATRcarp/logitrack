<?php
require_once __DIR__ . '/../../middleware/auth.php';
requireRol(['admin', 'empleado']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logitrack — Mapa de Tracking</title>
    <link rel="stylesheet" href="/logitrack/public/css/style.css?v=<?= filemtime(__DIR__ . '/../../public/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        .mapa-layout {
            display: flex;
            gap: 20px;
            height: calc(100vh - 140px);
            min-height: 500px;
        }
        #map {
            flex: 0 0 70%;
            border-radius: 16px;
            border: 1px solid var(--borde);
            background: #111;
            z-index: 1;
        }
        .panel-viajes {
            flex: 0 0 calc(30% - 20px);
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow-y: auto;
        }
        .panel-viajes h3 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 20px;
            color: var(--amarillo);
            margin: 0 0 4px;
            letter-spacing: 1px;
        }
        .viaje-item {
            background: var(--panel);
            border: 1px solid var(--borde);
            border-radius: 12px;
            padding: 14px 16px;
            cursor: pointer;
            transition: border-color .2s;
        }
        .viaje-item:hover, .viaje-item.activo { border-color: var(--amarillo); }
        .viaje-item .patente {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 18px;
            color: var(--amarillo);
        }
        .viaje-item .ruta {
            font-size: 13px;
            color: var(--gris);
            margin: 4px 0;
        }
        .viaje-item .chofer-label {
            font-size: 13px;
            color: #ccc;
        }
        .progress-wrap {
            margin-top: 8px;
        }
        .progress-bar-bg {
            background: var(--borde);
            border-radius: 6px;
            height: 6px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 6px;
            background: var(--amarillo);
            transition: width .4s;
        }
        .progress-bar-fill.completo { background: #6b7280; }
        .progress-label {
            font-size: 12px;
            color: var(--gris);
            margin-top: 4px;
        }
        .sin-viajes {
            color: var(--gris);
            font-size: 15px;
            text-align: center;
            padding: 32px 0;
        }
        .refresh-label {
            font-size: 12px;
            color: var(--gris);
            margin-top: 4px;
        }
        @media (max-width: 900px) {
            .mapa-layout { flex-direction: column; height: auto; }
            #map { flex: none; height: 400px; }
            .panel-viajes { flex: none; max-height: 300px; }
        }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <div>
                <h1 class="page-title">MAPA DE TRACKING</h1>
                <p class="page-subtitle">Posición en tiempo real de los viajes activos</p>
            </div>
            <div>
                <span class="refresh-label" id="ultimo-refresh">Cargando...</span>
            </div>
        </div>

        <div class="mapa-layout">
            <div id="map"></div>

            <div class="panel-viajes">
                <h3>🚛 Viajes activos</h3>
                <div id="lista-viajes">
                    <div class="sin-viajes">Cargando...</div>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const TRACKING_URL = '/logitrack/api/tracking.php';
const REFRESH_MS   = 30000;

const map = L.map('map').setView([-38.4161, -63.6167], 5);

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
    html: '<div style="font-size:22px;line-height:1;opacity:.5;">🚛</div>',
    className: '',
    iconAnchor: [11, 11]
});
const iconOrigen = L.divIcon({
    html: '<div style="width:12px;height:12px;background:#22c55e;border-radius:50%;border:2px solid #fff;"></div>',
    className: '',
    iconAnchor: [6, 6]
});
const iconDestino = L.divIcon({
    html: '<div style="width:12px;height:12px;background:#ef4444;border-radius:50%;border:2px solid #fff;"></div>',
    className: '',
    iconAnchor: [6, 6]
});

let capas = {};

function formatFecha(str) {
    if (!str) return '—';
    const d = new Date(str.replace(' ', 'T'));
    return d.toLocaleDateString('es-AR') + ' ' + d.toLocaleTimeString('es-AR', {hour:'2-digit',minute:'2-digit'});
}

function renderViajes(viajes) {
    const listaEl = document.getElementById('lista-viajes');

    // Eliminar capas de viajes que ya no existen
    const idsActivos = new Set(viajes.map(v => v.id_viaje));
    for (const id in capas) {
        if (!idsActivos.has(Number(id))) {
            capas[id].forEach(c => map.removeLayer(c));
            delete capas[id];
        }
    }

    if (viajes.length === 0) {
        listaEl.innerHTML = '<div class="sin-viajes">No hay viajes activos en este momento.</div>';
        return;
    }

    let html = '';
    viajes.forEach(v => {
        const terminado = v.progreso_pct >= 100;
        const fillClass  = terminado ? 'completo' : '';
        const pct        = Math.min(100, v.progreso_pct);

        html += `
        <div class="viaje-item" id="item-${v.id_viaje}" onclick="centrarViaje(${v.id_viaje}, ${v.lat_actual}, ${v.lng_actual})">
            <div class="patente">${v.patente}</div>
            <div class="chofer-label">🧑‍✈️ ${v.chofer}</div>
            <div class="ruta">${v.sucursal_origen} → ${v.sucursal_destino}</div>
            <div class="ruta">📦 ${v.total_paquetes} paquete${v.total_paquetes !== 1 ? 's' : ''}</div>
            <div class="progress-wrap">
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill ${fillClass}" style="width:${pct}%"></div>
                </div>
                <div class="progress-label">${terminado ? 'Llegó a destino' : pct + '% completado · ETA ' + formatFecha(v.fecha_llegada_est)}</div>
            </div>
        </div>`;

        // Actualizar o crear capas en el mapa
        if (capas[v.id_viaje]) {
            capas[v.id_viaje].forEach(c => map.removeLayer(c));
        }

        const linea = L.polyline(
            [[v.lat_origen, v.lng_origen], [v.lat_destino, v.lng_destino]],
            { color: '#facc15', weight: 2, dashArray: '6 6', opacity: 0.5 }
        ).addTo(map);

        const mOrigen  = L.marker([v.lat_origen,  v.lng_origen],  { icon: iconOrigen  }).addTo(map)
            .bindPopup(`<b>${v.sucursal_origen}</b><br>Origen`);
        const mDestino = L.marker([v.lat_destino, v.lng_destino], { icon: iconDestino }).addTo(map)
            .bindPopup(`<b>${v.sucursal_destino}</b><br>Destino`);

        const popupTxt = terminado
            ? `<b>${v.patente}</b><br>Llegó a destino<br>${v.sucursal_destino}`
            : `<b>${v.patente}</b><br>
               🧑‍✈️ ${v.chofer}<br>
               ${v.sucursal_origen} → ${v.sucursal_destino}<br>
               📦 ${v.total_paquetes} paquete${v.total_paquetes !== 1 ? 's' : ''}<br>
               Progreso: ${v.progreso_pct}%<br>
               ETA: ${formatFecha(v.fecha_llegada_est)}`;

        const mCamion = L.marker([v.lat_actual, v.lng_actual], {
            icon: terminado ? iconCamionFin : iconCamion
        }).addTo(map).bindPopup(popupTxt);

        capas[v.id_viaje] = [linea, mOrigen, mDestino, mCamion];
    });

    listaEl.innerHTML = html;
}

function centrarViaje(id, lat, lng) {
    map.setView([lat, lng], 8, { animate: true });
    if (capas[id]) {
        const mCamion = capas[id][3];
        mCamion.openPopup();
    }
    document.querySelectorAll('.viaje-item').forEach(el => el.classList.remove('activo'));
    const item = document.getElementById('item-' + id);
    if (item) item.classList.add('activo');
}

async function cargarTracking() {
    try {
        const res  = await fetch(TRACKING_URL);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        renderViajes(data);
        const ahora = new Date();
        document.getElementById('ultimo-refresh').textContent =
            'Actualizado: ' + ahora.toLocaleTimeString('es-AR', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    } catch (e) {
        console.error('Error cargando tracking:', e);
    }
}

cargarTracking();
setInterval(cargarTracking, REFRESH_MS);
</script>
</body>
</html>
