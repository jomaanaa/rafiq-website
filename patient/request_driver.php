<?php
session_start();
date_default_timezone_set('Africa/Cairo');
require __DIR__ . '/../pgdb/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function has_col(PDO $pdo, string $table, string $col): bool {
  $q = $pdo->prepare("
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = :t
      AND column_name = :c
    LIMIT 1
  ");
  $q->execute([':t' => $table, ':c' => $col]);
  return (bool)$q->fetchColumn();
}



function is_inside_egypt_php($lat, $lng): bool {
  if (!is_numeric($lat) || !is_numeric($lng)) return false;
  $lat = (float)$lat;
  $lng = (float)$lng;
  return $lat >= 21.7 && $lat <= 31.8 && $lng >= 24.7 && $lng <= 36.9;
}


function normalize_place_query_php(string $value): string {
  $value = mb_strtolower(trim($value));
  $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
  return $value ?: '';
}

function known_destination_php(string $query): ?array {
  $compact = normalize_place_query_php($query);

  $places = [
    [
      'keys' => ['mallofegypt', 'مولمصر'],
      'name' => 'Mall of Egypt',
      'lat' => 29.9727,
      'lng' => 31.0169,
      'address' => 'Mall of Egypt, Al Wahat Road, 6th of October, Giza, Egypt'
    ],
    [
      'keys' => ['mallofarabia', 'مولالعرب'],
      'name' => 'Mall of Arabia',
      'lat' => 30.0069,
      'lng' => 30.9737,
      'address' => 'Mall of Arabia, Juhayna Square, 6th of October, Giza, Egypt'
    ],
    [
      'keys' => ['dreampark', 'دريمبارك'],
      'name' => 'Dream Park',
      'lat' => 29.9662,
      'lng' => 31.0498,
      'address' => 'Dream Park, 6th of October, Giza, Egypt'
    ],
  ];

  foreach ($places as $place) {
    foreach ($place['keys'] as $key) {
      if ($compact !== '' && (str_contains($compact, $key) || str_contains($key, $compact))) {
        return $place;
      }
    }
  }

  return null;
}


function haversine_km($lat1,$lon1,$lat2,$lon2){
  $R = 6371;
  $dLat = deg2rad($lat2-$lat1);
  $dLon = deg2rad($lon2-$lon1);
  $a = sin($dLat/2)*sin($dLat/2) +
       cos(deg2rad($lat1))*cos(deg2rad($lat2)) *
       sin($dLon/2)*sin($dLon/2);
  $c = 2 * atan2(sqrt($a), sqrt(1-$a));
  return $R * $c;
}

function calculate_driver_fare(float $km): float {
  // Pricing strategy:
  // minimum fare = 50 EGP for any trip up to 4 km
  // after 4 km, add 15 EGP per extra km
  $km = max(0, $km);
  $baseFare = 50.0;
  $includedKm = 4.0;
  $extraKmRate = 15.0;

  if ($km <= $includedKm) {
    return $baseFare;
  }

  return round($baseFare + (($km - $includedKm) * $extraKmRate), 2);
}

function calculate_platform_commission(float $total): float {
  return round($total * 0.15, 2);
}

function calculate_driver_earning(float $total): float {
  return round($total * 0.85, 2);
}

function road_distance_km_osrm($plat,$plng,$dlat,$dlng){
  $url = "https://router.project-osrm.org/route/v1/driving/"
       . rawurlencode($plng . "," . $plat . ";" . $dlng . "," . $dlat)
       . "?overview=false&alternatives=false&steps=false";

  $ctx = stream_context_create([
    "http" => [
      "method" => "GET",
      "header" => "User-Agent: RafiQ/1.0\r\nAccept: application/json\r\n",
      "timeout" => 8
    ]
  ]);

  $json = @file_get_contents($url, false, $ctx);
  if ($json === false) return null;

  $data = json_decode($json, true);
  if (!is_array($data) || ($data["code"] ?? "") !== "Ok") return null;

  $route = $data["routes"][0] ?? null;
  if (!$route) return null;

  $meters = (float)($route["distance"] ?? 0);
  if ($meters <= 0) return null;

  return round($meters / 1000, 2);
}

function resolve_patient_id(PDO $pdo): int {
  $candidates = [];

  foreach (['patient_id', 'user_id', 'ID', 'id'] as $key) {
    if (!empty($_SESSION[$key])) {
      $candidates[] = (int)$_SESSION[$key];
    }
  }

  $candidates = array_values(array_unique(array_filter($candidates, fn($v) => $v > 0)));

  foreach ($candidates as $candidateId) {
    $stmt = $pdo->prepare("
      SELECT user_id
      FROM public.patient
      WHERE user_id = :uid
      LIMIT 1
    ");
    $stmt->execute([':uid' => $candidateId]);
    $patient_id = (int)$stmt->fetchColumn();

    if ($patient_id > 0) {
      $_SESSION['patient_id'] = $patient_id;
      $_SESSION['user_id'] = $patient_id;
      return $patient_id;
    }
  }

  return 0;
}

$patient_id = resolve_patient_id($pdo);

$currentPatient = null;
$prefillAddress = '';

if ((int)($_SESSION['user_id'] ?? 0) > 0) {
  try {
    $stmt = $pdo->prepare('
      SELECT
        u.user_id,
        u.first_name,
        u.last_name,
        u.email,
        p.phone,
        p.address
      FROM public."user" u
      LEFT JOIN public.patient p ON p.user_id = u.user_id
      WHERE u.user_id = :uid
      LIMIT 1
    ');
    $stmt->execute([':uid' => (int)$_SESSION['user_id']]);
    $currentPatient = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($currentPatient) {
      $prefillAddress = trim((string)($currentPatient['address'] ?? ''));
      if ($prefillAddress !== '') $_SESSION['address'] = $prefillAddress;
    }
  } catch (Exception $e) {}
}

$success = "";
$error = "";

if ($patient_id <= 0) {
  $error = "Your session user ID was not found in the patient table. Please log out and log in again with the patient account.";
}

$driverCol = null;
if (has_col($pdo,'booking','driver_id')) $driverCol = 'driver_id';
elseif (has_col($pdo,'booking','provider_id')) $driverCol = 'provider_id';

$has_status        = has_col($pdo,'booking','payment_status');
$has_method        = has_col($pdo,'booking','payment_method');
$has_total         = has_col($pdo,'booking','payment_total');
$has_dist          = has_col($pdo,'booking','distance_km');
$has_payment_state = has_col($pdo,'booking','payment_state');
$has_pick          = has_col($pdo,'booking','pickup_lat') && has_col($pdo,'booking','pickup_lng');
$has_dest          = has_col($pdo,'booking','dest_lat') && has_col($pdo,'booking','dest_lng');
$has_platform_commission = has_col($pdo,'booking','platform_commission');
$has_driver_earning      = has_col($pdo,'booking','driver_earning');
$has_start_at      = has_col($pdo,'booking','start_at');
$has_end_at        = has_col($pdo,'booking','end_at');

if (isset($_POST['submit_request'])) {
  if ($patient_id <= 0) {
    $error = "Patient record was not found for this session. Please log out and log in again with the patient account.";
  } elseif (!$driverCol) {
    $error = "DB mismatch: booking table has no driver_id or provider_id column.";
  } else {
    $type = $_POST['request_type'] ?? 'instant';

    $pickup_lat = $_POST['pickup_lat'] ?? '';
    $pickup_lng = $_POST['pickup_lng'] ?? '';
    $dest_lat   = $_POST['dest_lat'] ?? '';
    $dest_lng   = $_POST['dest_lng'] ?? '';

    $pickup_address = trim($_POST['pickup_address'] ?? '');
    $destinationText = trim($_POST['destination_exact_name'] ?? ($_POST['destination'] ?? ''));
    if ($destinationText === '') {
      $destinationText = trim($_POST['destination'] ?? '');
    }

    $knownDestination = known_destination_php($destinationText);
    if ($knownDestination) {
      $dest_lat = (string)$knownDestination['lat'];
      $dest_lng = (string)$knownDestination['lng'];
      // Save the exact destination name for the driver, not only "Giza, Egypt".
      $destinationText = $knownDestination['name'];
    }

    $service_date = $_POST['service_date'] ?? '';
    $service_time = $_POST['service_time'] ?? '';

    if ($pickup_lat === '' || $pickup_lng === '') {
      $error = "Pickup location is missing.";
    } elseif (!is_inside_egypt_php($pickup_lat, $pickup_lng)) {
      $error = "Pickup location must be inside Egypt.";
    } elseif ($dest_lat === '' || $dest_lng === '' || $destinationText === '') {
      $error = "Please choose your destination first.";
    } elseif (!is_inside_egypt_php($dest_lat, $dest_lng)) {
      $error = "Destination must be inside Egypt.";
    } else {
      try {
        $date = date('Y-m-d');
        $booking_time = date('H:i:s');

        if ($type === 'scheduled') {
          if ($service_date === '' || $service_time === '') {
            throw new Exception("Please select date and time for scheduled request.");
          }

          $scheduledAt = DateTime::createFromFormat('Y-m-d H:i', $service_date . ' ' . $service_time);
          if (!$scheduledAt) {
            throw new Exception("Invalid scheduled date or time.");
          }
          if ($scheduledAt <= new DateTime()) {
            throw new Exception("Scheduled driver request must be in the future.");
          }

          $date = $service_date;
          $booking_time = $service_time . ":00";
          $service_time_db = $service_time . ":00";
        } else {
          $service_time_db = date('H:i:s', time() + 10 * 60);
        }

        $plat = (float)$pickup_lat;
        $plng = (float)$pickup_lng;
        $dlat = (float)$dest_lat;
        $dlng = (float)$dest_lng;

        $km = road_distance_km_osrm($plat,$plng,$dlat,$dlng);
        if ($km === null) {
          $km = round(haversine_km($plat,$plng,$dlat,$dlng), 2);
        }

        $total = calculate_driver_fare((float)$km);
        $platformCommission = calculate_platform_commission((float)$total);
        $driverEarning = calculate_driver_earning((float)$total);

        $cols = ['date','booking_time','service_time','address','destination','patient_id', $driverCol];
        $vals = [':date',':booking_time',':service_time',':address',':destination',':patient_id','NULL'];

        if (has_col($pdo, 'booking', 'service_type')) {
          $cols[] = 'service_type';
          $vals[] = ':service_type';
        }

        if (has_col($pdo, 'booking', 'status')) {
          $cols[] = 'status';
          $vals[] = ':status';
        }

        if ($has_status)        { $cols[] = 'payment_status'; $vals[] = ':payment_status'; }
        if ($has_method)        { $cols[] = 'payment_method'; $vals[] = ':payment_method'; }
        if ($has_payment_state) { $cols[] = 'payment_state';  $vals[] = ':payment_state'; }
        if ($has_total)         { $cols[] = 'payment_total';  $vals[] = ':payment_total'; }
        if ($has_dist)          { $cols[] = 'distance_km';    $vals[] = ':distance_km'; }
        if ($has_platform_commission) { $cols[] = 'platform_commission'; $vals[] = ':platform_commission'; }
        if ($has_driver_earning)      { $cols[] = 'driver_earning';      $vals[] = ':driver_earning'; }
        if ($has_pick)          { $cols[] = 'pickup_lat'; $cols[] = 'pickup_lng'; $vals[] = ':pickup_lat'; $vals[] = ':pickup_lng'; }
        if ($has_dest)          { $cols[] = 'dest_lat';   $cols[] = 'dest_lng';   $vals[] = ':dest_lat';   $vals[] = ':dest_lng'; }
        if ($has_start_at)      { $cols[] = 'start_at';  $vals[] = ':start_at'; }
        if ($has_end_at)        { $cols[] = 'end_at';    $vals[] = ':end_at'; }

        $sql = "INSERT INTO public.booking (" . implode(',', $cols) . ")
                VALUES (" . implode(',', $vals) . ")
                RETURNING booking_id";

        $stmt = $pdo->prepare($sql);

        $params = [
          ':date' => $date,
          ':booking_time' => $booking_time,
          ':service_time' => $service_time_db,
          ':address' => $pickup_address !== '' ? $pickup_address : "Pickup ($pickup_lat, $pickup_lng)",
          ':destination' => $destinationText,
          ':patient_id' => $patient_id,
        ];

        if (has_col($pdo, 'booking', 'service_type')) {
          $params[':service_type'] = 'Driver';
        }

        if (has_col($pdo, 'booking', 'status')) {
          $params[':status'] = 'pending';
        }

        if ($has_status)        $params[':payment_status'] = 'unpaid';
        if ($has_method)        $params[':payment_method'] = null;
        if ($has_payment_state) $params[':payment_state'] = 'payment_pending';
        if ($has_total)         $params[':payment_total'] = $total;
        if ($has_dist)          $params[':distance_km'] = $km;
        if ($has_pick)          { $params[':pickup_lat'] = $plat; $params[':pickup_lng'] = $plng; }
        if ($has_dest)          { $params[':dest_lat']   = $dlat; $params[':dest_lng']   = $dlng; }
        if ($has_start_at)      $params[':start_at'] = $date . ' ' . $booking_time;
        if ($has_end_at)        $params[':end_at']   = $date . ' ' . $service_time_db;

        $stmt->execute($params);
        $newId = (int)$stmt->fetchColumn();

        $_SESSION['last_booking_id'] = $newId;
        $_SESSION['patient_id'] = $patient_id;

        header("Location: payment.php?booking_id=" . $newId);
        exit;

      } catch (Exception $e) {
        $error = $e->getMessage();
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>RafiQ — Request Driver</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
  const requestFormEl = document.getElementById('requestForm');
  if (requestFormEl) {
    requestFormEl.addEventListener('submit', function(e){
      const reqType = document.getElementById('request_type')?.value || 'instant';
      if (reqType === 'scheduled') {
        const d = document.getElementById('service_date')?.value || '';
        const t = document.getElementById('service_time')?.value || '';
        if (!d || !t) {
          e.preventDefault();
          alert('Please select date and time for scheduled request.');
          return;
        }
        const selected = new Date(d + 'T' + t);
        if (selected <= new Date()) {
          e.preventDefault();
          alert('Scheduled driver request must be in the future.');
          return;
        }
      }
    });
  }

</script>

  <style>
    :root{
      --bg:#f7f8fc;
      --card:#ffffff;
      --text:#1f2340;
      --muted:#7b7f98;
      --primary:#5b59a6;
      --primary-2:#494788;
      --line:#e9eaf5;
      --shadow:0 14px 34px rgba(35,39,92,.08);
      --ok:#2f8f4e;
      --bad:#b53535;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:"Nunito",system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:
        radial-gradient(circle at top right, rgba(91,89,166,.08), transparent 22%),
        var(--bg);
      color:var(--text);
    }
    .container{width:min(1120px, calc(100% - 36px)); margin:0 auto}
    .panel{
      background:var(--card);
      border:1px solid rgba(233,234,245,.95);
      border-radius:28px;
      box-shadow:var(--shadow);
      padding:20px;
      margin:14px 0 30px;
    }
    .panel-head{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:14px;
      margin-bottom:18px;
    }
    .panel-head h2{margin:0; font-size:25px; font-weight:900}
    .panel-head p{margin:6px 0 0; color:var(--muted); font-weight:700}
    .layout{
      display:grid;
      grid-template-columns:1.28fr .72fr;
      gap:20px;
      align-items:start;
    }
    /* ── Google-Maps-style search bar ── */
    .search-hero{
      width:100%;
      margin-bottom:20px;
    }
    .search-hero-label{
      font-size:12px;
      font-weight:900;
      color:var(--muted);
      text-transform:uppercase;
      letter-spacing:.6px;
      margin-bottom:8px;
    }
    .search-bar-wrap{
      position:relative;
      display:flex;
      align-items:center;
      background:#fff;
      border-radius:18px;
      border:1.5px solid #e0e2f0;
      box-shadow:0 6px 28px rgba(35,39,92,.10), 0 1px 4px rgba(35,39,92,.06);
      transition:box-shadow .2s, border-color .2s;
      overflow:visible;
    }
    .search-bar-wrap:focus-within{
      border-color:var(--primary);
      box-shadow:0 8px 36px rgba(91,89,166,.18), 0 0 0 4px rgba(91,89,166,.08);
    }
    .search-bar-icon{
      flex-shrink:0;
      width:58px;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:20px;
      color:var(--primary);
      pointer-events:none;
    }
    .search-input{
      flex:1;
      height:62px;
      border:0;
      background:transparent;
      padding:0 8px 0 0;
      font-family:"Nunito",sans-serif;
      font-size:16px;
      font-weight:800;
      color:#202442;
      outline:none;
    }
    .search-input::placeholder{ color:#b0b3cc; font-weight:700; }
    .trash-btn{
      flex-shrink:0;
      width:48px;
      height:48px;
      margin-right:7px;
      border:1px solid #eeeff8;
      border-radius:14px;
      background:#fdf0f0;
      color:#b53535;
      font-size:18px;
      font-weight:900;
      cursor:pointer;
      display:flex;
      align-items:center;
      justify-content:center;
      transition:background .15s;
    }
    .trash-btn:hover{ background:#fde0e0; }
    /* autocomplete dropdown */
    .ac-dropdown{
      position:absolute;
      top:calc(100% + 8px);
      left:0;
      width:100%;
      background:#fff;
      border:1px solid rgba(100,112,210,.15);
      border-radius:18px;
      box-shadow:0 20px 48px rgba(35,39,92,.16);
      z-index:9999;
      overflow:hidden;
      display:none;
    }
    .ac-dropdown.open{ display:block; }
    .ac-item{
      padding:14px 18px;
      cursor:pointer;
      font-size:14px;
      font-weight:700;
      color:#202442;
      border-bottom:1px solid rgba(100,112,210,.07);
      display:flex;
      align-items:flex-start;
      gap:12px;
      transition:background .15s;
    }
    .ac-item:last-child{ border-bottom:none; }
    .ac-item:hover{ background:#f4f5ff; }
    .ac-icon{ font-size:18px; flex-shrink:0; margin-top:1px; }
    .ac-main{ font-size:14px; font-weight:800; color:#202442; line-height:1.3; }
    .ac-sub{ font-size:12px; font-weight:600; color:#7b7f98; margin-top:2px; }
    .map-tools{ display:none; }/* kept for JS ref, hidden */
    #map{
      height:560px;
      border-radius:24px;
      border:1px solid var(--line);
      overflow:hidden;
    }
    .hint{
      margin-top:12px;
      background:#f7f7fd;
      border:1px solid var(--line);
      border-radius:18px;
      padding:12px 14px;
      font-weight:900;
      color:#3f4168;
      font-size:13px;
    }
    .tabs{display:flex; gap:10px; margin:8px 0 16px}
    .tab{
      flex:1;
      border:1px solid var(--line);
      background:#f7f7fd;
      color:#2a2d4f;
      border-radius:18px;
      padding:12px 14px;
      font-weight:900;
      cursor:pointer;
    }
    .tab.active{
      background:linear-gradient(135deg, var(--primary), var(--primary-2));
      color:#fff;
      border-color:transparent;
      box-shadow:0 12px 24px rgba(91,89,166,.20);
    }
    label{
      display:block;
      margin:12px 0 7px;
      font-weight:900;
      color:#242848;
    }
    input{
      width:100%;
      height:50px;
      border-radius:16px;
      border:1.5px solid #d7d9eb;
      padding:0 14px;
      background:#fff;
      font-weight:800;
      color:#202442;
      outline:none;
    }
    .row{display:flex; gap:12px}
    .field{flex:1}
    .summary-box{
      margin-top:12px;
      padding:16px;
      border-radius:22px;
      border:1px solid var(--line);
      background:linear-gradient(180deg, #fff, #fafafe);
      box-shadow:0 10px 24px rgba(35,39,92,.04);
    }
    .sum-label{
      font-size:12px;
      color:var(--muted);
      font-weight:900;
      text-transform:uppercase;
      margin-bottom:4px;
    }
    .sum-value{
      font-size:15px;
      font-weight:900;
      color:#23274d;
      line-height:1.5;
    }
    .fare-box{
      margin-top:14px;
      padding:18px;
      border-radius:24px;
      background:linear-gradient(135deg, #2f304e 0%, #404066 45%, #5b59a6 100%);
      color:#fff;
      box-shadow:0 20px 34px rgba(64,64,102,.18);
    }
    .fare-small{
      font-size:12px;
      font-weight:900;
      opacity:.82;
      text-transform:uppercase;
      letter-spacing:.3px;
    }
    .fare-big{
      font-size:30px;
      font-weight:900;
      line-height:1.05;
      margin-top:6px;
    }
    .fare-note{
      margin-top:8px;
      font-size:13px;
      font-weight:700;
      color:rgba(255,255,255,.84);
    }
    .cta-box{
      margin-top:14px;
      border-radius:22px;
      padding:16px;
      background:#fafbff;
      border:1px solid var(--line);
    }
    .cta-title{
      font-size:17px;
      font-weight:900;
      margin:0;
    }
    .cta-sub{
      margin:6px 0 0;
      color:var(--muted);
      font-size:13px;
      font-weight:700;
      line-height:1.6;
    }
    .continue-btn{
      width:100%;
      height:56px;
      margin-top:14px;
      border:0;
      border-radius:18px;
      background:linear-gradient(135deg, var(--primary), var(--primary-2));
      color:#fff;
      font-size:15px;
      font-weight:900;
      cursor:pointer;
      box-shadow:0 14px 28px rgba(91,89,166,.22);
      transition:.2s ease;
    }
    .continue-btn:hover{
      transform:translateY(-1px);
    }
    .continue-btn[disabled]{
      opacity:.55;
      cursor:not-allowed;
      transform:none;
      box-shadow:none;
    }
    .cta-helper{
      margin-top:10px;
      font-size:12px;
      font-weight:800;
      color:var(--muted);
    }
    .msg{
      margin-top:12px;
      padding:13px 14px;
      border-radius:16px;
      font-weight:900;
    }
    .ok{background:#eaf7ef; color:var(--ok); border:1px solid #bfe6cc}
    .bad{background:#fdecec; color:#b53535; border:1px solid #f3bcbc}
    @media (max-width:980px){
      .layout{grid-template-columns:1fr}
      #map{height:430px}
    }
    @media (max-width:600px){
      .search-input{ font-size:14px; }
      .search-bar-wrap{ border-radius:14px; }
    }
  </style>
</head>
<body>

<?php include '../general/nav_patient.php'; ?>

<main class="container">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2>Request a driver</h2>
        <p>Choose your destination, review the fare, then continue to payment.</p>
      </div>
    </div>

    <!-- Google Maps-style hero search bar -->
    <div class="search-hero">
      <div class="search-hero-label">Where do you want to go?</div>
      <div class="search-bar-wrap">
        <div class="search-bar-icon">📍</div>
        <input
          type="text"
          id="destinationSearchInput"
          class="search-input"
          placeholder="Search destination in Egypt (e.g. Mall of Egypt, Cairo…)"
          autocomplete="off"
        >
        <div class="ac-dropdown" id="acDropdown"></div>
        <button type="button" id="resetBtn" class="trash-btn" aria-label="Reset trip" title="Reset trip">🗑️</button>
      </div>
    </div>

    <div class="layout">
      <div>
        <div class="map-tools"></div><!-- kept for structure; hidden via CSS -->

        <div id="map"></div>
        <div class="hint" id="mapHint">Loading pickup location…</div>
      </div>

      <div>
        <form method="post" id="requestForm">
          <div class="tabs">
            <button type="button" class="tab active" id="tabInstant">Instant</button>
            <button type="button" class="tab" id="tabScheduled">Scheduled</button>
          </div>

          <input type="hidden" name="request_type" id="request_type" value="instant">
          <input type="hidden" name="pickup_lat" id="pickup_lat">
          <input type="hidden" name="pickup_lng" id="pickup_lng">
          <input type="hidden" name="dest_lat" id="dest_lat">
          <input type="hidden" name="dest_lng" id="dest_lng">
          <input type="hidden" name="destination_exact_name" id="destination_exact_name">

          <label>Pickup address</label>
          <input type="text" name="pickup_address" id="pickup_address" readonly>

          <label>Destination</label>
          <input type="text" name="destination" id="destination" readonly placeholder="Choose destination from the map or search">

          <div class="row" id="scheduledFields" style="display:none;">
            <div class="field">
              <label>Date</label>
              <input type="date" name="service_date" id="service_date">
            </div>
            <div class="field">
              <label>Time</label>
              <input type="time" name="service_time" id="service_time">
            </div>
          </div>

          <div class="summary-box">
            <div class="sum-label">Current step</div>
            <div class="sum-value" id="currentStepText">Choose your destination to calculate the trip.</div>
          </div>

          <div class="fare-box" id="fareBox">
            <div class="fare-small">Estimated Total</div>
            <div class="fare-big"><span id="totalBox">—</span> EGP</div>
            <div class="fare-note">
              Distance: <span id="kmBox">—</span> km
              <span id="etaBox"></span>
            </div>
          </div>

          <div class="cta-box">
            <h3 class="cta-title">Continue to Payment</h3>
            <p class="cta-sub">You will choose Cash or Visa in the next page.</p>

            <button class="continue-btn" type="submit" name="submit_request" id="continueBtn">
              Continue to Payment
            </button>

            <div class="cta-helper" id="ctaHelper">Select a destination first.</div>
          </div>

          <?php if ($success): ?>
            <div class="msg ok"><?= h($success) ?></div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="msg bad"><?= h($error) ?></div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </section>
</main>

<script>
  const todayForSchedule = new Date().toISOString().slice(0,10);
  const scheduleDateInput = document.getElementById('service_date');
  if (scheduleDateInput) scheduleDateInput.min = todayForSchedule;

  const savedPatientAddress = <?= json_encode($prefillAddress, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const egyptBounds = L.latLngBounds(
    L.latLng(21.7, 24.7),
    L.latLng(31.8, 36.9)
  );

  const map = L.map('map', {
    scrollWheelZoom: true,
    doubleClickZoom: true,
    dragging: true,
    tap: true,
    maxBounds: egyptBounds,
    maxBoundsViscosity: 1.0
  }).setView([30.0444, 31.2357], 7);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    minZoom: 6,
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  const hint = document.getElementById('mapHint');
  const mapEl = document.getElementById('map');
  mapEl.addEventListener('wheel', (e) => e.preventDefault(), { passive:false });
  map.scrollWheelZoom.disable();
  mapEl.addEventListener('mouseenter', () => map.scrollWheelZoom.enable());
  mapEl.addEventListener('mouseleave', () => map.scrollWheelZoom.disable());

  let pickupMarker = null;
  let destMarker = null;

  const pickupLat = document.getElementById('pickup_lat');
  const pickupLng = document.getElementById('pickup_lng');
  const destLat   = document.getElementById('dest_lat');
  const destLng   = document.getElementById('dest_lng');
  const pickupAddress = document.getElementById('pickup_address');
  const destination   = document.getElementById('destination');
  const destinationExactName = document.getElementById('destination_exact_name');

  const kmBox = document.getElementById('kmBox');
  const totalBox = document.getElementById('totalBox');
  const etaBox = document.getElementById('etaBox');
  const currentStepText = document.getElementById('currentStepText');
  const continueBtn = document.getElementById('continueBtn');
  const ctaHelper = document.getElementById('ctaHelper');

  const destinationSearchInput = document.getElementById('destinationSearchInput');
  const acDropdown = document.getElementById('acDropdown');
  const resetBtn = document.getElementById('resetBtn');

  const KNOWN_DESTINATIONS = [
    {
      keys: ['mall of egypt', 'mallofegypt', 'مول مصر'],
      name: 'Mall of Egypt',
      exactName: 'Mall of Egypt',
      lat: 29.9727,
      lng: 31.0169,
      address: 'Mall of Egypt, Al Wahat Road, 6th of October, Giza, Egypt'
    },
    {
      keys: ['mall of arabia', 'mallofarabia', 'مول العرب'],
      name: 'Mall of Arabia',
      exactName: 'Mall of Arabia',
      lat: 30.0069,
      lng: 30.9737,
      address: 'Mall of Arabia, Juhayna Square, 6th of October, Giza, Egypt'
    },
    {
      keys: ['dream park', 'dreampark', 'دريم بارك'],
      name: 'Dream Park',
      exactName: 'Dream Park',
      lat: 29.9662,
      lng: 31.0498,
      address: 'Dream Park, 6th of October, Giza, Egypt'
    }
  ];

  function normalizePlaceQuery(value){
    return String(value || '')
      .toLowerCase()
      .replace(/[^\p{L}\p{N}]+/gu, '')
      .trim();
  }

  function knownDestinationMatch(query){
    const compact = normalizePlaceQuery(query);
    if(!compact) return null;

    for(const place of KNOWN_DESTINATIONS){
      for(const key of place.keys){
        const k = normalizePlaceQuery(key);
        if(compact.includes(k) || k.includes(compact)){
          return place;
        }
      }
    }

    return null;
  }

  function isInsideEgypt(lat, lng){
    lat = Number(lat);
    lng = Number(lng);
    return Number.isFinite(lat) && Number.isFinite(lng)
      && lat >= 21.7 && lat <= 31.8
      && lng >= 24.7 && lng <= 36.9;
  }


  function setHint(t){
    hint.textContent = t;
  }

  function refreshCtaState(){
    const hasDest = !!(destLat.value && destLng.value && destination.value.trim() !== '');

    continueBtn.disabled = false;

    if (hasDest) {
      currentStepText.textContent = 'Trip is ready. Continue to the payment page.';
      ctaHelper.textContent = 'Everything is ready.';
    } else {
      currentStepText.textContent = 'Choose your destination to calculate the trip.';
      ctaHelper.textContent = 'Select a destination first.';
      totalBox.textContent = '—';
      kmBox.textContent = '—';
      etaBox.textContent = '';
    }
  }

  async function reverseGeocode(lat, lng) {
    const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`;
    const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
    if (!res.ok) return '';
    const data = await res.json();
    return data.display_name || '';
  }

  async function geocodeAddress(address) {
    if (!address || !address.trim()) return null;

    const known = knownDestinationMatch(address);
    if (known) {
      return {
        lat: known.lat,
        lng: known.lng,
        display_name: known.address || known.name
      };
    }

    try {
      const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=eg&bounded=1&viewbox=24.7,31.8,36.9,21.7&q=${encodeURIComponent(address + ', Egypt')}`;
      const res = await fetch(url, {
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });

      if (!res.ok) return null;

      const data = await res.json();
      if (!Array.isArray(data) || !data.length) return null;

      return {
        lat: parseFloat(data[0].lat),
        lng: parseFloat(data[0].lon),
        display_name: data[0].display_name || address
      };
    } catch (e) {
      return null;
    }
  }

  function getBrowserLocation() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('Geolocation not supported'));
        return;
      }

      navigator.geolocation.getCurrentPosition(
        function(position) {
          resolve({
            lat: position.coords.latitude,
            lng: position.coords.longitude
          });
        },
        function(error) {
          reject(error);
        },
        {
          enableHighAccuracy: true,
          timeout: 12000,
          maximumAge: 0
        }
      );
    });
  }

  async function setPickupPoint(lat, lng, label = 'Pickup', explicitAddress = '') {
    if (!isInsideEgypt(lat, lng)) {
      setHint('Please choose a pickup location inside Egypt.');
      return;
    }

    const fixedLat = +Number(lat).toFixed(7);
    const fixedLng = +Number(lng).toFixed(7);

    if (pickupMarker) map.removeLayer(pickupMarker);

    const pickupIcon = L.divIcon({
      className:'',
      html:`<div style="width:20px;height:20px;border-radius:50%;background:#353b69;border:3px solid #fff;box-shadow:0 3px 10px rgba(53,59,105,.5);"></div>`,
      iconSize:[20,20],iconAnchor:[10,10],popupAnchor:[0,-12]
    });
    pickupMarker = L.marker([fixedLat, fixedLng], { icon: pickupIcon }).addTo(map).bindPopup(label).openPopup();
    pickupLat.value = fixedLat;
    pickupLng.value = fixedLng;
    map.setView([fixedLat, fixedLng], 15);

    if (explicitAddress && explicitAddress.trim() !== '') {
      pickupAddress.value = explicitAddress;
    } else {
      try {
        const addr = await reverseGeocode(fixedLat, fixedLng);
        if (addr) pickupAddress.value = addr;
        else if (savedPatientAddress) pickupAddress.value = savedPatientAddress;
      } catch (e) {
        if (savedPatientAddress) pickupAddress.value = savedPatientAddress;
      }
    }

    setHint('Pickup is ready. Search or choose the destination.');
    refreshCtaState();
  }

  async function setDestinationPoint(lat, lng, label = 'Destination', explicitAddress = '', exactName = '') {
    if (!isInsideEgypt(lat, lng)) {
      setHint('Please choose a destination inside Egypt.');
      return;
    }

    const fixedLat = +Number(lat).toFixed(7);
    const fixedLng = +Number(lng).toFixed(7);

    if (destMarker) map.removeLayer(destMarker);

    const destIcon = L.divIcon({
      className:'',
      html:`<div style="width:20px;height:20px;border-radius:50%;background:#6470d2;border:3px solid #fff;box-shadow:0 3px 10px rgba(100,112,210,.5);"></div>`,
      iconSize:[20,20],iconAnchor:[10,10],popupAnchor:[0,-12]
    });
    destMarker = L.marker([fixedLat, fixedLng], { icon: destIcon }).addTo(map).bindPopup(label).openPopup();
    destLat.value = fixedLat;
    destLng.value = fixedLng;

    if (exactName && destinationExactName) {
      destinationExactName.value = exactName;
    } else if (destinationExactName) {
      destinationExactName.value = explicitAddress || '';
    }

    if (explicitAddress && explicitAddress.trim() !== '') {
      destination.value = explicitAddress;
    } else {
      try {
        const addr = await reverseGeocode(fixedLat, fixedLng);
        if (addr) destination.value = addr;
      } catch (e) {}
    }

    setHint('Destination selected.');
    updatePrice(true);
  }

  async function initAutomaticPickup() {
    try {
      /* Try real GPS/browser location first — works correctly wherever the user is */
      const loc = await getBrowserLocation();
      const currentAddress = await reverseGeocode(loc.lat, loc.lng);
      await setPickupPoint(loc.lat, loc.lng, 'Pickup', currentAddress || savedPatientAddress || 'Current location');
    } catch (e) {
      /* Geolocation failed or denied — fall back to saved profile address */
      if (savedPatientAddress && savedPatientAddress.trim() !== '') {
        try {
          const result = await geocodeAddress(savedPatientAddress);
          if (result) {
            await setPickupPoint(result.lat, result.lng, 'Pickup', result.display_name || savedPatientAddress);
            return;
          }
        } catch (e2) {}
      }
      /* Last resort: default view (Cairo) */
      map.setView([30.0444, 31.2357], 13);
      setHint('Could not detect pickup automatically. Click the map to set your pickup.');
      refreshCtaState();
    }
  }

  /* ── Autocomplete: DB places first, Nominatim as fallback ── */
  let acDebounce = null;

  async function fetchDbPlaces(query) {
    try {
      const res = await fetch(`../general/place_search.php?q=${encodeURIComponent(query)}`, { cache: 'no-store' });
      if (!res.ok) return [];
      const rows = await res.json();
      return rows.map(r => {
        const known = knownDestinationMatch((r.name || '') + ' ' + (r.address || ''));
        if (known) {
          return {
            lat: known.lat,
            lng: known.lng,
            name: known.name,
            sub: known.address,
            source: 'fixed'
          };
        }

        const lat = parseFloat(r.latitude);
        const lng = parseFloat(r.longitude);

        if (!isInsideEgypt(lat, lng)) {
          return null;
        }

        return {
          lat,
          lng,
          name: r.name,
          sub: [r.type, r.address].filter(Boolean).join(' • '),
          source: 'db'
        };
      }).filter(Boolean);
    } catch(e) { return []; }
  }

  async function fetchNominatim(query) {
    try {
      const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&addressdetails=1&countrycodes=eg&bounded=1&viewbox=24.7,31.8,36.9,21.7&q=${encodeURIComponent(query.trim() + ', Egypt')}`;
      const res = await fetch(url, {
        headers: { 'Accept': 'application/json', 'Accept-Language': 'en' },
        cache: 'no-store'
      });
      if (!res.ok) return [];
      const rows = await res.json();
      return rows.map(r => {
        const parts = (r.display_name || '').split(', ');
        return {
          lat: parseFloat(r.lat),
          lng: parseFloat(r.lon),
          name: parts[0] || r.display_name,
          sub: parts.slice(1, 4).join(', '),
          source: 'osm'
        };
      }).filter(r => isInsideEgypt(r.lat, r.lng));
    } catch(e) { return []; }
  }

  async function fetchSuggestions(query) {
    if (!query || query.trim().length < 2) return [];

    const known = knownDestinationMatch(query);
    if (known) {
      return [{
        lat: known.lat,
        lng: known.lng,
        name: known.name,
        sub: known.address,
        source: 'fixed'
      }];
    }
    const [dbResults, osmResults] = await Promise.all([
      fetchDbPlaces(query),
      fetchNominatim(query)
    ]);
    /* DB results come first; deduplicate OSM results that duplicate a DB name */
    const dbNames = new Set(dbResults.map(r => r.name.toLowerCase()));
    const filtered = osmResults.filter(r => !dbNames.has(r.name.toLowerCase()));
    return [...dbResults, ...filtered].slice(0, 8);
  }

  function renderSuggestions(results) {
    if (!results.length) {
      acDropdown.classList.remove('open');
      return;
    }
    acDropdown.innerHTML = results.map((r, i) => {
      const icon = r.source === 'osm' ? '⌖' : '⌖';
      return `<div class="ac-item" data-idx="${i}" data-lat="${r.lat}" data-lng="${r.lng}" data-name="${encodeURIComponent(r.name)}" data-sub="${encodeURIComponent(r.sub || r.name)}">
        <div class="ac-icon">${icon}</div>
        <div><div class="ac-main">${r.name}</div>${r.sub ? `<div class="ac-sub">${r.sub}</div>` : ''}</div>
      </div>`;
    }).join('');
    acDropdown.classList.add('open');

    acDropdown.querySelectorAll('.ac-item').forEach(item => {
      item.addEventListener('click', async () => {
        const lat  = parseFloat(item.dataset.lat);
        const lng  = parseFloat(item.dataset.lng);
        const name = decodeURIComponent(item.dataset.name);
        const fullAddress = decodeURIComponent(item.dataset.sub || item.dataset.name || '');
        destinationSearchInput.value = name;
        acDropdown.classList.remove('open');

        // Save the exact selected destination name in the booking table.
        // The full address is kept only as extra context, not as the visible destination.
        await setDestinationPoint(lat, lng, 'Destination', name, name);
        map.setView([lat, lng], 15);
      });
    });
  }

  destinationSearchInput.addEventListener('input', function() {
    clearTimeout(acDebounce);
    const q = this.value.trim();
    if (q.length < 2) { acDropdown.classList.remove('open'); return; }
    acDebounce = setTimeout(async () => {
      const results = await fetchSuggestions(q);
      renderSuggestions(results);
    }, 280);
  });

  destinationSearchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') acDropdown.classList.remove('open');
    if (e.key === 'Enter') {
      e.preventDefault();
      const first = acDropdown.querySelector('.ac-item');
      if (first) first.click();
    }
  });

  document.addEventListener('click', function(e) {
    if (!acDropdown.contains(e.target) && e.target !== destinationSearchInput) {
      acDropdown.classList.remove('open');
    }
  });

  let inflight = false;
  let lastKey = "";

  async function updatePrice(force = false){
    const aLat = pickupLat.value;
    const aLng = pickupLng.value;
    const bLat = destLat.value;
    const bLng = destLng.value;

    if (!aLat || !aLng || !bLat || !bLng) {
      refreshCtaState();
      return;
    }

    const key = `${aLat},${aLng}->${bLat},${bLng}`;
    if (!force && (key === lastKey || inflight)) return;
    lastKey = key;
    inflight = true;

    try{
      const url = `https://router.project-osrm.org/route/v1/driving/${encodeURIComponent(aLng)},${encodeURIComponent(aLat)};${encodeURIComponent(bLng)},${encodeURIComponent(bLat)}?overview=false&alternatives=false&steps=false`;
      const r = await fetch(url, { cache:"no-store" });
      const j = await r.json();
      if ((j.code || "") !== "Ok") throw new Error("route failed");

      const meters = (j.routes && j.routes[0] && j.routes[0].distance) ? j.routes[0].distance : 0;
      const seconds = (j.routes && j.routes[0] && j.routes[0].duration) ? j.routes[0].duration : 0;

      const km = Math.round((meters / 1000) * 100) / 100;
      const total = Math.round(((km <= 4 ? 50 : (50 + ((km - 4) * 15)))) * 100) / 100;
      const min = Math.round(seconds / 60);

      kmBox.textContent = km.toFixed(2);
      totalBox.textContent = total.toFixed(2);
      etaBox.textContent = min ? ` • ${min} min` : '';
      currentStepText.textContent = 'Trip is ready. Continue to the payment page.';
      ctaHelper.textContent = 'Everything is ready.';
    } catch(e){
      kmBox.textContent = '—';
      totalBox.textContent = '—';
      etaBox.textContent = '';
      currentStepText.textContent = 'Trip is ready. Final total will be confirmed in payment.';
      ctaHelper.textContent = 'You can continue.';
    } finally {
      inflight = false;
    }
  }

  map.on('click', async (e) => {
    const lat = +e.latlng.lat.toFixed(7);
    const lng = +e.latlng.lng.toFixed(7);

    if (!pickupMarker) {
      await setPickupPoint(lat, lng, 'Pickup');
      return;
    }

    await setDestinationPoint(lat, lng, destMarker ? 'Destination updated' : 'Destination');
  });

  resetBtn.addEventListener('click', async () => {
    if (pickupMarker) {
      map.removeLayer(pickupMarker);
      pickupMarker = null;
    }
    if (destMarker) {
      map.removeLayer(destMarker);
      destMarker = null;
    }

    pickupLat.value = '';
    pickupLng.value = '';
    destLat.value = '';
    destLng.value = '';
    pickupAddress.value = '';
    destination.value = '';
    if (destinationExactName) destinationExactName.value = '';
    destinationSearchInput.value = '';

    kmBox.textContent = '—';
    totalBox.textContent = '—';
    etaBox.textContent = '';

    lastKey = "";
    setHint('Reloading pickup location...');
    await initAutomaticPickup();
  });


  const tabInstant = document.getElementById('tabInstant');
  const tabScheduled = document.getElementById('tabScheduled');
  const scheduledFields = document.getElementById('scheduledFields');
  const requestType = document.getElementById('request_type');

  tabInstant.addEventListener('click', () => {
    tabInstant.classList.add('active');
    tabScheduled.classList.remove('active');
    scheduledFields.style.display = 'none';
    requestType.value = 'instant';
  });

  tabScheduled.addEventListener('click', () => {
    tabScheduled.classList.add('active');
    tabInstant.classList.remove('active');
    scheduledFields.style.display = 'flex';
    requestType.value = 'scheduled';
  });

  document.getElementById('requestForm').addEventListener('submit', function(e){
    const known = knownDestinationMatch(destination.value || destinationSearchInput.value);
    if (known) {
      destLat.value = known.lat;
      destLng.value = known.lng;
      destination.value = known.exactName || known.name;
      if (destinationExactName) destinationExactName.value = known.exactName || known.name;
    }

    if (!destLat.value || !destLng.value || !destination.value.trim()) {
      e.preventDefault();
      alert('Please choose your destination first.');
    }
  });

  window.addEventListener('load', async function () {
    await initAutomaticPickup();
    refreshCtaState();
  });

  const requestFormEl = document.getElementById('requestForm');
  if (requestFormEl) {
    requestFormEl.addEventListener('submit', function(e){
      const reqType = document.getElementById('request_type')?.value || 'instant';
      if (reqType === 'scheduled') {
        const d = document.getElementById('service_date')?.value || '';
        const t = document.getElementById('service_time')?.value || '';
        if (!d || !t) {
          e.preventDefault();
          alert('Please select date and time for scheduled request.');
          return;
        }
        const selected = new Date(d + 'T' + t);
        if (selected <= new Date()) {
          e.preventDefault();
          alert('Scheduled driver request must be in the future.');
          return;
        }
      }
    });
  }

</script>

<?php include '../general/footer.php'; ?>
</body>
</html>