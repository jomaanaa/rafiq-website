<?php
session_start();
require __DIR__ . '/../../pgdb/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['user_id'])) {
    header('Location: ../../general/login.php'); exit;
}

$driver_id  = (int)$_SESSION['user_id'];
$booking_id = (int)($_GET['booking_id'] ?? 0);

if ($booking_id <= 0) {
    die('<p style="font-family:sans-serif;padding:40px">Invalid booking ID.</p>');
}

// Load booking details
$stmt = $pdo->prepare("
    SELECT b.booking_id, b.address AS pickup_address, b.destination,
           b.pickup_lat, b.pickup_lng, b.dest_lat, b.dest_lng,
           b.payment_total, b.service_time,
           u.first_name || ' ' || u.last_name AS patient_name,
           p.phone AS patient_phone
    FROM booking b
    JOIN patient pt ON pt.user_id = b.patient_id
    JOIN \"user\" u   ON u.user_id  = pt.user_id
    LEFT JOIN patient p ON p.user_id = pt.user_id
    WHERE b.booking_id = :bid
    LIMIT 1
");
$stmt->execute([':bid' => $booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) die('<p style="font-family:sans-serif;padding:40px">Booking not found.</p>');

// Compute base URL for API calls
$base = rtrim(dirname(dirname(dirname(str_replace('\\','/',$_SERVER['SCRIPT_NAME'])))), '/');
$api  = $base . '/general/tracking_api.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Live Tracking — Driver</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Manrope',sans-serif;background:#0f0f1a;color:#fff;overflow:hidden}

/* ── LAYOUT ── */
.app{display:flex;flex-direction:column;height:100vh}
#map{flex:1;z-index:0}

/* ── BOTTOM SHEET ── */
.sheet{
    position:fixed;bottom:0;left:0;right:0;
    background:linear-gradient(180deg,#1a1b2e,#12131f);
    border-radius:28px 28px 0 0;
    padding:20px 20px 36px;
    z-index:1000;
    box-shadow:0 -8px 40px rgba(0,0,0,.5);
    transition:transform .35s cubic-bezier(.22,.68,0,1.2);
}
.drag-bar{width:40px;height:4px;background:rgba(255,255,255,.18);border-radius:4px;margin:0 auto 18px}

/* ── STATUS BANNER ── */
.status-banner{
    display:flex;align-items:center;gap:12px;
    padding:14px 16px;border-radius:16px;
    margin-bottom:16px;
    transition:background .3s;
}
.status-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(.75)}}
.status-label{font-size:16px;font-weight:800}
.status-sub{font-size:12px;font-weight:600;opacity:.75;margin-top:2px}

/* ── TRIP INFO ── */
.trip-info{
    display:grid;grid-template-columns:1fr 1fr;gap:10px;
    margin-bottom:16px;
}
.info-tile{
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.08);
    border-radius:14px;padding:12px 14px;
}
.info-tile-label{font-size:10px;font-weight:800;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.info-tile-value{font-size:14px;font-weight:800;line-height:1.4;word-break:break-word}

/* ── PHASE BUTTONS ── */
.phase-btn{
    width:100%;height:58px;border:none;border-radius:18px;
    font-family:inherit;font-size:16px;font-weight:800;
    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;
    transition:all .2s;margin-bottom:10px;
}
.phase-btn:active{transform:scale(.97)}
.phase-btn.go     {background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;box-shadow:0 12px 28px rgba(59,130,246,.30)}
.phase-btn.arrived{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;box-shadow:0 12px 28px rgba(245,158,11,.30)}
.phase-btn.start  {background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 12px 28px rgba(16,185,129,.30)}
.phase-btn.done   {background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff;box-shadow:0 12px 28px rgba(139,92,246,.30)}
.phase-btn:disabled{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}

/* ── GPS INDICATOR ── */
.gps-row{
    display:flex;align-items:center;gap:8px;
    font-size:12px;font-weight:700;color:rgba(255,255,255,.45);
    margin-top:4px;
}
.gps-dot{width:7px;height:7px;border-radius:50%;background:#ef4444;flex-shrink:0}
.gps-dot.active{background:#10b981}

/* ── CUSTOM MARKER ── */
.driver-pin{
    width:48px;height:48px;border-radius:50%;
    background:linear-gradient(135deg,#3b82f6,#1d4ed8);
    border:3px solid #fff;box-shadow:0 4px 18px rgba(0,0,0,.35);
    display:flex;align-items:center;justify-content:center;
    font-size:22px;
}
.dest-pin{
    width:40px;height:40px;border-radius:50%;
    background:linear-gradient(135deg,#8b5cf6,#7c3aed);
    border:3px solid #fff;box-shadow:0 4px 18px rgba(0,0,0,.35);
    display:flex;align-items:center;justify-content:center;
    font-size:20px;
}

@media(min-width:640px){
    .sheet{max-width:420px;left:auto;right:20px;bottom:20px;border-radius:28px}
    #map{height:100vh}
}
</style>
</head>
<body>

<div class="app">
    <div id="map"></div>

    <div class="sheet" id="sheet">
        <div class="drag-bar"></div>

        <!-- Status banner -->
        <div class="status-banner" id="statusBanner">
            <div class="status-dot" id="statusDot"></div>
            <div>
                <div class="status-label" id="statusLabel">Ready to go</div>
                <div class="status-sub" id="statusSub">Press "I'm on my way" to start sharing your location</div>
            </div>
        </div>

        <!-- Trip info -->
        <div class="trip-info">
            <div class="info-tile" style="grid-column:span 2">
                <div class="info-tile-label">Passenger</div>
                <div class="info-tile-value"><?= h($booking['patient_name']) ?></div>
            </div>
            <div class="info-tile">
                <div class="info-tile-label">Pickup</div>
                <div class="info-tile-value" style="font-size:12px"><?= h(mb_substr($booking['pickup_address'] ?? 'See map', 0, 60)) ?></div>
            </div>
            <div class="info-tile">
                <div class="info-tile-label">Destination</div>
                <div class="info-tile-value" style="font-size:12px"><?= h(mb_substr($booking['destination'] ?? '—', 0, 60)) ?></div>
            </div>
            <?php if (!empty($booking['payment_total'])): ?>
            <div class="info-tile" style="grid-column:span 2">
                <div class="info-tile-label">Fare</div>
                <div class="info-tile-value"><?= number_format((float)$booking['payment_total'], 2) ?> EGP</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Phase action buttons -->
        <div id="phaseButtons">
            <button class="phase-btn go" id="btnGo" onclick="setPhase('arriving')">
                <i class="fa-solid fa-car"></i> I'm on my way
            </button>
        </div>

        <!-- GPS status -->
        <div class="gps-row">
            <div class="gps-dot" id="gpsDot"></div>
            <span id="gpsText">GPS not active</span>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const BOOKING_ID  = <?= $booking_id ?>;
const API         = <?= json_encode($api) ?>;
const PICKUP_LAT  = <?= json_encode((float)($booking['pickup_lat'] ?? 30.0444)) ?>;
const PICKUP_LNG  = <?= json_encode((float)($booking['pickup_lng'] ?? 31.2357)) ?>;
const DEST_LAT    = <?= json_encode((float)($booking['dest_lat'] ?? 0)) ?>;
const DEST_LNG    = <?= json_encode((float)($booking['dest_lng'] ?? 0)) ?>;

// ── Map setup
const map = L.map('map', {zoomControl:false}).setView([PICKUP_LAT || 30.0444, PICKUP_LNG || 31.2357], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'© OSM'}).addTo(map);

// Custom icon helper
function makeIcon(html) {
    return L.divIcon({html, className:'', iconSize:[48,48], iconAnchor:[24,24]});
}

// Pickup marker
L.marker([PICKUP_LAT, PICKUP_LNG], {
    icon: makeIcon('<div class="driver-pin" style="background:linear-gradient(135deg,#f59e0b,#d97706)">🧑</div>'),
    title:'Passenger pickup'
}).addTo(map).bindPopup('Pickup point');

// Destination marker
if (DEST_LAT && DEST_LNG) {
    L.marker([DEST_LAT, DEST_LNG], {
        icon: makeIcon('<div class="dest-pin">📍</div>'),
        title:'Destination'
    }).addTo(map).bindPopup('Destination');
}

// Driver marker (starts hidden)
let driverMarker = null;
let routeLine    = null;

function setDriverPos(lat, lng) {
    if (!driverMarker) {
        driverMarker = L.marker([lat, lng], {
            icon: makeIcon('<div class="driver-pin">🚗</div>'),
            title:'You'
        }).addTo(map);
    } else {
        driverMarker.setLatLng([lat, lng]);
    }
    map.panTo([lat, lng], {animate:true, duration:.5});

    // Draw/update route line to pickup
    if (routeLine) map.removeLayer(routeLine);
    if (PICKUP_LAT && PICKUP_LNG) {
        routeLine = L.polyline([[lat, lng],[PICKUP_LAT, PICKUP_LNG]], {
            color:'#3b82f6', weight:4, dashArray:'8 6', opacity:.7
        }).addTo(map);
    }
}

// ── State
let currentPhase  = 'waiting';
let gpsWatchId    = null;
let broadcastTimer = null;
let lastLat = null, lastLng = null;

const STATUS_CONFIG = {
    waiting:     { label:'Ready to go',       sub:'Press "I\'m on my way" to start',       bg:'rgba(100,116,139,.15)', dot:'#64748b' },
    arriving:    { label:'On the way',         sub:'Sharing your location with passenger',   bg:'rgba(59,130,246,.15)',  dot:'#3b82f6' },
    arrived:     { label:'You\'ve arrived',    sub:'Waiting for passenger to board',         bg:'rgba(245,158,11,.15)', dot:'#f59e0b' },
    in_progress: { label:'Trip in progress',   sub:'Driving to destination',                 bg:'rgba(16,185,129,.15)', dot:'#10b981' },
    completed:   { label:'Trip completed! 🎉', sub:'Thank you. Great job!',                  bg:'rgba(139,92,246,.15)', dot:'#8b5cf6' },
};

const PHASE_BUTTONS = {
    waiting:     '<button class="phase-btn go"      id="btnGo"      onclick="setPhase(\'arriving\')"><i class="fa-solid fa-car"></i> I\'m on my way</button>',
    arriving:    '<button class="phase-btn arrived"  id="btnArrived" onclick="setPhase(\'arrived\')"><i class="fa-solid fa-flag-checkered"></i> I\'ve Arrived</button>',
    arrived:     '<button class="phase-btn start"    id="btnStart"   onclick="setPhase(\'in_progress\')"><i class="fa-solid fa-play"></i> Start Trip</button>',
    in_progress: '<button class="phase-btn done"     id="btnDone"    onclick="setPhase(\'completed\')"><i class="fa-solid fa-check"></i> Complete Trip</button>',
    completed:   '<div style="text-align:center;padding:14px;font-size:15px;font-weight:800;color:#8b5cf6">Trip Complete ✓</div>',
};

function renderPhase(phase) {
    const cfg = STATUS_CONFIG[phase] || STATUS_CONFIG.waiting;
    document.getElementById('statusBanner').style.background = cfg.bg;
    document.getElementById('statusLabel').textContent  = cfg.label;
    document.getElementById('statusSub').textContent    = cfg.sub;
    document.getElementById('statusDot').style.background = cfg.dot;
    document.getElementById('phaseButtons').innerHTML   = PHASE_BUTTONS[phase] || '';
}

async function setPhase(newPhase) {
    currentPhase = newPhase;
    renderPhase(newPhase);

    // POST status to API
    await fetch(API + '?action=update_status', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({booking_id: BOOKING_ID, status: newPhase})
    }).catch(()=>{});

    if (newPhase === 'arriving') {
        startGPS();
    }
    if (newPhase === 'completed') {
        stopGPS();
        // Update route line to destination
        if (routeLine) map.removeLayer(routeLine);
        if (lastLat && DEST_LAT && DEST_LNG) {
            routeLine = L.polyline([[lastLat, lastLng],[DEST_LAT, DEST_LNG]], {
                color:'#8b5cf6', weight:4, dashArray:'8 6', opacity:.7
            }).addTo(map);
        }
    }
}

// ── GPS Broadcasting
async function broadcastLocation(lat, lng) {
    lastLat = lat; lastLng = lng;
    setDriverPos(lat, lng);
    await fetch(API + '?action=update_location', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({booking_id: BOOKING_ID, lat, lng})
    }).catch(()=>{});
}

function startGPS() {
    if (gpsWatchId !== null) return;

    document.getElementById('gpsDot').classList.add('active');
    document.getElementById('gpsText').textContent = 'GPS active — sharing location';

    gpsWatchId = navigator.geolocation.watchPosition(
        pos => {
            broadcastLocation(pos.coords.latitude, pos.coords.longitude);
        },
        err => {
            document.getElementById('gpsText').textContent = 'GPS error: ' + err.message;
            document.getElementById('gpsDot').classList.remove('active');
        },
        { enableHighAccuracy:true, maximumAge:0, timeout:10000 }
    );
}

function stopGPS() {
    if (gpsWatchId !== null) {
        navigator.geolocation.clearWatch(gpsWatchId);
        gpsWatchId = null;
    }
    document.getElementById('gpsDot').classList.remove('active');
    document.getElementById('gpsText').textContent = 'GPS stopped';
}

// ── Init: load existing state
async function init() {
    renderPhase('waiting');
    try {
        const r = await fetch(`${API}?action=get&booking_id=${BOOKING_ID}`);
        const d = await r.json();
        if (d.ok && d.tracking) {
            const phase = d.tracking.trip_status || 'waiting';
            currentPhase = phase;
            renderPhase(phase);
            if (d.tracking.driver_lat && d.tracking.driver_lng) {
                setDriverPos(parseFloat(d.tracking.driver_lat), parseFloat(d.tracking.driver_lng));
            }
            if (phase === 'arriving' || phase === 'arrived' || phase === 'in_progress') {
                startGPS();
            }
        }
    } catch(e) {}
}

init();

// Prevent phone sleep during active trip
if ('wakeLock' in navigator) {
    navigator.wakeLock.request('screen').catch(()=>{});
}
</script>
</body>
</html>
