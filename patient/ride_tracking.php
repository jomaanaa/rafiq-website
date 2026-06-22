<?php
session_start();
require __DIR__ . '/../pgdb/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['user_id'])) {
    header('Location: ../general/login.php'); exit;
}

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) {
    die('<p style="font-family:sans-serif;padding:40px">Invalid booking ID.</p>');
}

// Load booking details
$stmt = $pdo->prepare("
    SELECT b.booking_id, b.address AS pickup_address, b.destination,
           b.pickup_lat, b.pickup_lng, b.dest_lat, b.dest_lng,
           b.payment_total, b.service_time, b.status AS booking_status,
           COALESCE(u.first_name || ' ' || u.last_name, 'Your driver') AS driver_name,
           pr.phone AS driver_phone
    FROM booking b
    LEFT JOIN provider prov ON prov.user_id = b.provider_id
    LEFT JOIN \"user\" u      ON u.user_id  = prov.user_id
    LEFT JOIN provider pr    ON pr.user_id  = prov.user_id
    WHERE b.booking_id = :bid
    LIMIT 1
");
$stmt->execute([':bid' => $booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) die('<p style="font-family:sans-serif;padding:40px">Booking not found.</p>');

$base = rtrim(dirname(dirname(str_replace('\\','/',$_SERVER['SCRIPT_NAME']))), '/');
$api  = $base . '/general/tracking_api.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Track Your Ride — Rafiq</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Manrope',sans-serif;overflow:hidden;background:#f6f8fd}

/* ── LAYOUT ── */
.app{display:flex;flex-direction:column;height:100vh}

/* ── TOPBAR ── */
.topbar{
    display:flex;align-items:center;gap:14px;
    padding:14px 18px;
    background:#fff;border-bottom:1px solid #e8ebf5;
    z-index:500;flex-shrink:0;
}
.back-btn{
    width:40px;height:40px;border-radius:12px;
    border:none;background:#f0f2fb;color:#4b4f83;
    font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;
    text-decoration:none;flex-shrink:0;
}
.topbar-title{font-size:16px;font-weight:800;color:#2B2C41;flex:1}
.topbar-booking{font-size:12px;color:#94a3b8;font-weight:700}

/* ── MAP ── */
#map{flex:1;z-index:0}

/* ── BOTTOM SHEET ── */
.sheet{
    position:fixed;bottom:0;left:0;right:0;
    background:#fff;border-radius:28px 28px 0 0;
    box-shadow:0 -8px 40px rgba(36,39,66,.14);
    z-index:1000;
    padding:20px 20px 36px;
}
.drag-bar{width:40px;height:4px;background:#e2e5f1;border-radius:4px;margin:0 auto 18px}

/* ── STATUS BANNER ── */
.status-banner{
    display:flex;align-items:center;gap:14px;
    padding:16px 18px;border-radius:18px;
    margin-bottom:16px;transition:background .4s,border-color .4s;
    border:2px solid transparent;
}
.status-icon-wrap{
    width:48px;height:48px;border-radius:16px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:24px;
}
.status-main{flex:1}
.status-label{font-size:17px;font-weight:800;color:#2B2C41}
.status-sub{font-size:12px;color:#6b7188;font-weight:600;margin-top:3px}

/* ── ETA ROW ── */
.eta-row{
    display:flex;gap:10px;margin-bottom:14px;
}
.eta-chip{
    flex:1;padding:12px 14px;border-radius:14px;
    background:#f8f9fd;border:1px solid #e8ebf5;text-align:center;
}
.eta-chip-label{font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.eta-chip-value{font-size:18px;font-weight:800;color:#2B2C41}

/* ── TRIP ROUTE ── */
.route-row{
    display:flex;gap:10px;align-items:flex-start;
    padding:14px;border-radius:14px;background:#f8f9fd;border:1px solid #e8ebf5;
    margin-bottom:14px;
}
.route-dots{display:flex;flex-direction:column;align-items:center;gap:0;padding-top:4px}
.rdot{width:10px;height:10px;border-radius:50%}
.rline{width:2px;flex:1;min-height:18px;background:#d1d5db}
.route-text{flex:1}
.route-label{font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em}
.route-addr{font-size:13px;font-weight:700;color:#2B2C41;margin-top:2px;margin-bottom:10px;line-height:1.4}

/* ── WAITING STATE ── */
.waiting-state{
    text-align:center;padding:24px 10px;
}
.waiting-spinner{
    width:48px;height:48px;border:4px solid #eef2ff;border-top-color:#4b4f83;
    border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 14px;
}
@keyframes spin{to{transform:rotate(360deg)}}
.waiting-title{font-size:17px;font-weight:800;color:#2B2C41;margin-bottom:6px}
.waiting-sub{font-size:13px;color:#6b7188;font-weight:600}

/* ── COMPLETED CARD ── */
.completed-card{
    background:linear-gradient(135deg,#353b69,#6470d2);
    border-radius:20px;padding:24px;text-align:center;color:#fff;
}
.completed-card h2{font-size:22px;font-weight:800;margin-bottom:8px}
.completed-card p{font-size:14px;color:rgba(255,255,255,.80);margin-bottom:18px}
.home-btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:12px 24px;border-radius:12px;
    background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.22);
    color:#fff;font-weight:800;font-size:14px;text-decoration:none;
    transition:background .18s;
}
.home-btn:hover{background:rgba(255,255,255,.28)}

/* ── DRIVER PULSE RING (on map) ── */
.driver-pin-wrap{position:relative;width:56px;height:56px}
.driver-pulse{
    position:absolute;inset:-8px;border-radius:50%;
    background:rgba(59,130,246,.20);animation:ripple 2s ease-out infinite;
}
@keyframes ripple{0%{transform:scale(.6);opacity:1}100%{transform:scale(1.6);opacity:0}}
.driver-car-icon{
    position:absolute;inset:4px;border-radius:50%;
    background:linear-gradient(135deg,#3b82f6,#1d4ed8);
    border:3px solid #fff;box-shadow:0 4px 18px rgba(0,0,0,.25);
    display:flex;align-items:center;justify-content:center;font-size:22px;
}

@media(min-width:640px){
    .sheet{max-width:400px;right:20px;bottom:20px;border-radius:24px;left:auto}
}
</style>
</head>
<body>

<div class="app">
    <!-- Topbar -->
    <div class="topbar">
        <a class="back-btn" href="my_bookings.php"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <div class="topbar-title">Live Tracking</div>
            <div class="topbar-booking">Booking #<?= $booking_id ?></div>
        </div>
        <div style="font-size:22px">🚗</div>
    </div>

    <div id="map"></div>

    <div class="sheet" id="sheet">
        <div class="drag-bar"></div>
        <div id="sheetContent">
            <!-- Filled by JS -->
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const BOOKING_ID = <?= $booking_id ?>;
const API        = <?= json_encode($api) ?>;
const PICKUP_LAT = <?= json_encode((float)($booking['pickup_lat'] ?? 30.0444)) ?>;
const PICKUP_LNG = <?= json_encode((float)($booking['pickup_lng'] ?? 31.2357)) ?>;
const DEST_LAT   = <?= json_encode((float)($booking['dest_lat'] ?? 0)) ?>;
const DEST_LNG   = <?= json_encode((float)($booking['dest_lng'] ?? 0)) ?>;
const PICKUP_ADDR = <?= json_encode($booking['pickup_address'] ?? '') ?>;
const DEST_ADDR   = <?= json_encode($booking['destination'] ?? '') ?>;
const DRIVER_NAME = <?= json_encode($booking['driver_name'] ?? 'Your Driver') ?>;
const FARE        = <?= json_encode($booking['payment_total'] ? number_format((float)$booking['payment_total'], 2) . ' EGP' : '—') ?>;

// ── Map ─────────────────────────────────────────────────────
const map = L.map('map', {zoomControl:false}).setView(
    [PICKUP_LAT || 30.0444, PICKUP_LNG || 31.2357], 14
);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom:19, attribution:'© OSM'
}).addTo(map);

// Pickup marker (passenger location)
function makeIcon(html, size = 48) {
    return L.divIcon({html, className:'', iconSize:[size,size], iconAnchor:[size/2,size/2]});
}

L.marker([PICKUP_LAT, PICKUP_LNG], {
    icon: makeIcon('<div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);border:3px solid #fff;box-shadow:0 4px 14px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;font-size:20px">🧑</div>', 44)
}).addTo(map).bindPopup('Your pickup point');

// Destination marker
if (DEST_LAT && DEST_LNG) {
    L.marker([DEST_LAT, DEST_LNG], {
        icon: makeIcon('<div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#7c3aed);border:3px solid #fff;box-shadow:0 4px 14px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;font-size:18px">📍</div>', 40)
    }).addTo(map).bindPopup('Destination');
}

// Driver marker (updated live)
let driverMarker = null;
let routeLine    = null;
let lastStatus   = null;
let etaInterval  = null;
let etaSeconds   = null;

function driverIcon() {
    return makeIcon(
        `<div class="driver-pin-wrap">
            <div class="driver-pulse"></div>
            <div class="driver-car-icon">🚗</div>
        </div>`, 56
    );
}

function setDriverPos(lat, lng) {
    if (!driverMarker) {
        driverMarker = L.marker([lat,lng], {icon:driverIcon(), title:'Driver'}).addTo(map);
    } else {
        driverMarker.setLatLng([lat,lng]);
    }

    // Draw route to pickup while arriving, to dest when in trip
    if (routeLine) map.removeLayer(routeLine);
    const dest = (lastStatus === 'in_progress' && DEST_LAT)
        ? [DEST_LAT, DEST_LNG]
        : [PICKUP_LAT, PICKUP_LNG];
    routeLine = L.polyline([[lat,lng], dest], {
        color: lastStatus === 'in_progress' ? '#10b981' : '#3b82f6',
        weight:5, dashArray:'10 6', opacity:.75
    }).addTo(map);

    // Auto-fit all markers
    const bounds = [[lat,lng],[PICKUP_LAT,PICKUP_LNG]];
    if (DEST_LAT && DEST_LNG) bounds.push([DEST_LAT,DEST_LNG]);
    map.fitBounds(bounds, {padding:[60,60]});
}

// ── Status UI config ─────────────────────────────────────────
const STATUS_UI = {
    waiting: {
        banner: '#eef2ff', border:'#c7d2fe', iconBg:'#eef2ff',
        icon:'⏳', label:'Waiting for driver',
        sub:'Your driver has not started yet. This page will update automatically.',
    },
    arriving: {
        banner: '#eff6ff', border:'#bfdbfe', iconBg:'#dbeafe',
        icon:'🚗', label:'Driver is on the way!',
        sub:'Your driver is heading to pick you up.',
    },
    arrived: {
        banner: '#fffbeb', border:'#fde68a', iconBg:'#fef3c7',
        icon:'🟡', label:'Driver has arrived!',
        sub:'Your driver is waiting at the pickup point.',
    },
    in_progress: {
        banner: '#f0fdf4', border:'#bbf7d0', iconBg:'#dcfce7',
        icon:'🟢', label:'Trip in progress',
        sub:'You\'re on your way. Enjoy the ride!',
    },
    completed: {
        banner: '#f5f3ff', border:'#ddd6fe', iconBg:'#ede9fe',
        icon:'✅', label:'Trip completed!',
        sub:'You have arrived at your destination.',
    },
};

function renderSheet(status, driverLat, driverLng) {
    const ui = STATUS_UI[status] || STATUS_UI.waiting;
    const content = document.getElementById('sheetContent');

    if (status === 'completed') {
        content.innerHTML = `
        <div class="completed-card">
            <div style="font-size:48px;margin-bottom:12px">✅</div>
            <h2>You've arrived!</h2>
            <p>Trip completed. Fare: <strong>${FARE}</strong></p>
            <a class="home-btn" href="my_bookings.php"><i class="fa-solid fa-list"></i> My Bookings</a>
        </div>`;
        if (etaInterval) clearInterval(etaInterval);
        return;
    }

    if (status === 'waiting' || !driverLat) {
        content.innerHTML = `
        <div class="waiting-state">
            <div class="waiting-spinner"></div>
            <div class="waiting-title">Waiting for driver</div>
            <div class="waiting-sub">We'll update this page the moment your driver starts moving.</div>
        </div>`;
        return;
    }

    // ETA calc (rough: haversine ÷ 40 km/h)
    function haversine(la1,lo1,la2,lo2){
        const R=6371,r=Math.PI/180;
        const dLa=(la2-la1)*r,dLo=(lo2-lo1)*r;
        const a=Math.sin(dLa/2)**2+Math.cos(la1*r)*Math.cos(la2*r)*Math.sin(dLo/2)**2;
        return 2*R*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
    }
    const targetLat = (status === 'in_progress' && DEST_LAT) ? DEST_LAT : PICKUP_LAT;
    const targetLng = (status === 'in_progress' && DEST_LNG) ? DEST_LNG : PICKUP_LNG;
    const distKm    = haversine(driverLat, driverLng, targetLat, targetLng);
    const etaMin    = Math.max(1, Math.round((distKm / 40) * 60));
    const distTxt   = distKm < 1 ? Math.round(distKm*1000) + ' m' : distKm.toFixed(1) + ' km';

    content.innerHTML = `
    <div class="status-banner" id="statusBanner"
         style="background:${ui.banner};border-color:${ui.border}">
        <div class="status-icon-wrap" style="background:${ui.iconBg}">${ui.icon}</div>
        <div class="status-main">
            <div class="status-label">${ui.label}</div>
            <div class="status-sub">${ui.sub}</div>
        </div>
    </div>

    <div class="eta-row">
        <div class="eta-chip">
            <div class="eta-chip-label">ETA</div>
            <div class="eta-chip-value" id="etaVal">${etaMin} min</div>
        </div>
        <div class="eta-chip">
            <div class="eta-chip-label">Distance</div>
            <div class="eta-chip-value">${distTxt}</div>
        </div>
        <div class="eta-chip">
            <div class="eta-chip-label">Fare</div>
            <div class="eta-chip-value" style="font-size:14px">${FARE}</div>
        </div>
    </div>

    <div class="route-row">
        <div class="route-dots">
            <div class="rdot" style="background:#3b82f6"></div>
            <div class="rline"></div>
            <div class="rdot" style="background:#8b5cf6"></div>
        </div>
        <div class="route-text">
            <div class="route-label">Pickup</div>
            <div class="route-addr">${PICKUP_ADDR || 'Your pickup point'}</div>
            <div class="route-label">Destination</div>
            <div class="route-addr">${DEST_ADDR || '—'}</div>
        </div>
    </div>`;

    // ETA countdown
    if (etaInterval) clearInterval(etaInterval);
    etaSeconds = etaMin * 60;
    etaInterval = setInterval(() => {
        if (etaSeconds > 60) etaSeconds -= 3;
        const m = Math.ceil(etaSeconds / 60);
        const el = document.getElementById('etaVal');
        if (el) el.textContent = m + ' min';
    }, 3000);
}

// ── Polling ──────────────────────────────────────────────────
let pollCount = 0;
async function poll() {
    try {
        const r = await fetch(`${API}?action=get&booking_id=${BOOKING_ID}&_=${Date.now()}`);
        const d = await r.json();
        if (!d.ok) return;

        const t = d.tracking;
        const status = t ? (t.trip_status || 'waiting') : 'waiting';
        const dLat   = t ? parseFloat(t.driver_lat) : null;
        const dLng   = t ? parseFloat(t.driver_lng) : null;

        if (status !== lastStatus || dLat) {
            lastStatus = status;
            if (dLat && dLng && !isNaN(dLat)) setDriverPos(dLat, dLng);
            renderSheet(status, dLat, dLng);
        }

        if (status === 'completed') return; // stop polling
    } catch(e) {}

    pollCount++;
    // Poll every 3 s for first 5 min, then every 6 s
    const delay = pollCount > 100 ? 6000 : 3000;
    setTimeout(poll, delay);
}

// ── Start ────────────────────────────────────────────────────
renderSheet('waiting', null, null);
poll();
</script>
</body>
</html>
