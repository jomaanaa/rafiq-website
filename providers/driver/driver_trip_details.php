<?php
session_start();
require __DIR__ . '/../../pgdb/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die("Invalid trip id"); }

$stmt = $pdo->prepare("
  SELECT booking_id, address, destination,
         pickup_lat, pickup_lng, dest_lat, dest_lng,
         date, service_time, booking_time, payment_status
  FROM booking
  WHERE booking_id = :id
  LIMIT 1
");
$stmt->execute([':id'=>$id]);
$r = $stmt->fetch();
if(!$r){ die("Trip not found"); }

function h($s){ return htmlspecialchars((string)$s); }

$pickupLat = $r['pickup_lat'];
$pickupLng = $r['pickup_lng'];
$destLat   = $r['dest_lat'];
$destLng   = $r['dest_lng'];

// لو مفيش coords، نعرض رسالة واضحة
$hasCoords = is_numeric($pickupLat) && is_numeric($pickupLng) && is_numeric($destLat) && is_numeric($destLng);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>RafiQ — Trip Map</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
  :root{--bg:#fff;--text:#1e1e2f;--muted:#7a7a99;--primary:#4b4a74;--line:#ececf5;--shadow:0 12px 26px rgba(27,27,60,.10);--rl:28px;--container:1100px;}
  *{box-sizing:border-box}
  body{margin:0;font-family:Nunito,system-ui;background:var(--bg);color:var(--text)}
  .container{width:min(var(--container),calc(100% - 40px));margin:0 auto}
  .top{padding:22px 0 10px;display:flex;justify-content:space-between;align-items:flex-end;gap:12px}
  h1{margin:0;font-size:28px;font-weight:900;color:#3c3b59}
  .muted{color:var(--muted);font-weight:800}
  .btn{display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 16px;border-radius:14px;background:var(--primary);color:#fff;font-weight:1000;text-decoration:none}
  .grid{display:grid;grid-template-columns: 1.1fr .9fr;gap:18px;align-items:start;margin:12px 0 34px}
  .card{background:#fff;border:1px solid rgba(236,236,245,.95);border-radius:var(--rl);box-shadow:var(--shadow);padding:18px}
  .mapWrap{height:520px;border-radius:22px;overflow:hidden;border:1px solid rgba(236,236,245,.95)}
  #map{height:100%;width:100%}
  .box{border:1px solid var(--line);border-radius:18px;padding:12px 14px;background:#fafafe;margin-bottom:12px}
  .k{font-size:12px;color:var(--muted);font-weight:900}
  .v{font-size:14px;color:#2a2a46;font-weight:1000;margin-top:6px}
  .warn{background:#fff2f2;border:1px solid #ffd2d2;color:#8a2c2c;padding:12px 14px;border-radius:16px;font-weight:900}
  @media(max-width:900px){ .grid{grid-template-columns:1fr} .mapWrap{height:420px} }
</style>
</head>

<body>
<main class="container">
  <div class="top">
    <div>
      <h1>Trip #<?= (int)$r['booking_id'] ?></h1>
      <div class="muted"><?= h($r['address']) ?> → <?= h($r['destination']) ?></div>
    </div>
    <a class="btn" href="driver_trips.php">Back</a>
  </div>

  <div class="grid">
    <div class="card">
      <div class="mapWrap">
        <?php if(!$hasCoords): ?>
          <div style="padding:18px">
            <div class="warn">
              No coordinates found for this trip.<br>
              لازم تحفظ pickup_lat/pickup_lng و dest_lat/dest_lng في جدول booking.
            </div>
          </div>
        <?php else: ?>
          <div id="map"></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="box">
        <div class="k">Status</div>
        <div class="v"><?= h($r['payment_status'] ?? '') ?></div>
      </div>
      <div class="box">
        <div class="k">When</div>
        <div class="v"><?= h($r['date'] ?? '') ?> <?= h($r['service_time'] ?? $r['booking_time'] ?? '') ?></div>
      </div>

      <div class="box">
        <div class="k">Pickup (Lat/Lng)</div>
        <div class="v"><?= h($pickupLat) ?> , <?= h($pickupLng) ?></div>
      </div>
      <div class="box">
        <div class="k">Destination (Lat/Lng)</div>
        <div class="v"><?= h($destLat) ?> , <?= h($destLng) ?></div>
      </div>
    </div>
  </div>
</main>

<?php if($hasCoords): ?>
<script>
  const pickup = { lat: <?= (float)$pickupLat ?>, lng: <?= (float)$pickupLng ?> };
  const dest   = { lat: <?= (float)$destLat ?>,   lng: <?= (float)$destLng ?> };

  function initMap() {
    const map = new google.maps.Map(document.getElementById("map"), {
      center: pickup,
      zoom: 13,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false
    });

    const pickupMarker = new google.maps.Marker({
      position: pickup,
      map,
      label: "P",
      title: "Pickup"
    });

    const destMarker = new google.maps.Marker({
      position: dest,
      map,
      label: "D",
      title: "Destination"
    });

    const bounds = new google.maps.LatLngBounds();
    bounds.extend(pickup);
    bounds.extend(dest);
    map.fitBounds(bounds);

    // Optional: route line
    const line = new google.maps.Polyline({
      path: [pickup, dest],
      geodesic: true,
      strokeOpacity: 0.9,
      strokeWeight: 4
    });
    line.setMap(map);
  }
</script>

<script async defer
  src="https://maps.googleapis.com/maps/api/js?key=PUT_YOUR_KEY_HERE&callback=initMap">
</script>
<?php endif; ?>

</body>
</html>