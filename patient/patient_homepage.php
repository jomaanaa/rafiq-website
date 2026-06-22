<?php
session_start();

/* =========================
   1) DB CONNECTION
========================= */
$DB_HOST = "localhost";
$DB_PORT = "5432";
$DB_NAME = "rafiq";
$DB_USER = "postgres";
$DB_PASS = "123456789";

$dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

/* =========================
   2) USER NAME FROM DB
========================= */
$userName = 'Guest';

if (isset($_SESSION['user_id'])) {
    $stmtUser = $pdo->prepare('SELECT first_name FROM "user" WHERE user_id = :user_id LIMIT 1');
    $stmtUser->execute(['user_id' => $_SESSION['user_id']]);
    $userRow = $stmtUser->fetch();

    if ($userRow && !empty($userRow['first_name'])) {
        $userName = $userRow['first_name'];
        $_SESSION['Name'] = $userRow['first_name'];
    }
} else {
    $userName = $_SESSION['Name'] ?? 'Guest';
}

/* =========================
   3) ASSETS
========================= */
$PIC = "../pictures/";

$assets = [
    "logo"      => $PIC . "rafiq_logo.png",
    "user_icon" => $PIC . "provider.jpeg",
];

/* =========================
   4) LOCATION INPUTS
========================= */
$userLat = isset($_GET["lat"]) && is_numeric($_GET["lat"]) ? (float) $_GET["lat"] : null;
$userLng = isset($_GET["lng"]) && is_numeric($_GET["lng"]) ? (float) $_GET["lng"] : null;

/* =========================
   5) PREMIUM SERVICE SVG IMAGES
========================= */
function serviceSvg(string $type): string {
    $type = strtolower(trim($type));

    $palette = [
        'caregiver' => ['#353b69', '#6470d2', '#eef2ff', 'care'],
        'driver' => ['#353b69', '#6470d2', '#eef2ff', 'ride'],
        'doctor' => ['#353b69', '#6470d2', '#eef2ff', 'health'],
        'interpreter' => ['#353b69', '#6470d2', '#eef2ff', 'talk'],
    ];

    [$main, $accent, $soft, $label] = $palette[$type] ?? $palette['caregiver'];

    if ($type === 'driver') {
        $mainShape = '
            <path d="M86 145h178c16 0 29 13 29 29v12H57v-12c0-16 13-29 29-29z" fill="'.$main.'"/>
            <path d="M104 105h113c25 0 42 13 55 39l4 8H80l16-31c4-9 10-16 22-16z" fill="'.$accent.'"/>
            <rect x="118" y="116" width="52" height="25" rx="10" fill="#fff" opacity=".86"/>
            <rect x="181" y="116" width="52" height="25" rx="10" fill="#fff" opacity=".86"/>
            <circle cx="112" cy="188" r="24" fill="#20243f"/>
            <circle cx="238" cy="188" r="24" fill="#20243f"/>
            <circle cx="112" cy="188" r="10" fill="#dbeafe"/>
            <circle cx="238" cy="188" r="10" fill="#dbeafe"/>
        ';
        $icon = '<path d="M282 63h37M282 86h24" stroke="'.$main.'" stroke-width="9" stroke-linecap="round"/>';
    } elseif ($type === 'doctor') {
        $mainShape = '
            <circle cx="118" cy="83" r="31" fill="#f2c4a7"/>
            <path d="M86 82c0-21 14-35 32-35s32 14 32 35v9H86z" fill="#26324a"/>
            <path d="M75 202c0-43 19-70 43-70s43 27 43 70" fill="#fff" stroke="#d8e1ef" stroke-width="3"/>
            <path d="M118 143v50M95 168h46" stroke="'.$main.'" stroke-width="9" stroke-linecap="round"/>
        ';
        $icon = '<rect x="214" y="74" width="86" height="64" rx="24" fill="#fff" stroke="#d8e1ef" stroke-width="3"/><path d="M257 91v30M242 106h30" stroke="'.$accent.'" stroke-width="9" stroke-linecap="round"/>';
    } elseif ($type === 'interpreter') {
        $mainShape = '
            <rect x="69" y="57" width="126" height="112" rx="34" fill="#fff" stroke="#e1def8" stroke-width="3"/>
            <circle cx="118" cy="100" r="18" fill="'.$soft.'"/>
            <path d="M95 137c10-14 23-21 39-21 15 0 29 7 39 21" stroke="'.$main.'" stroke-width="8" stroke-linecap="round" fill="none"/>
            <path d="M92 158c15 10 30 15 46 15s31-5 46-15" stroke="'.$accent.'" stroke-width="8" stroke-linecap="round" fill="none"/>
        ';
        $icon = '<path d="M226 79h58c17 0 30 12 30 28s-13 28-30 28h-16l-25 23v-23h-17c-17 0-30-12-30-28s13-28 30-28z" fill="'.$main.'"/><circle cx="233" cy="107" r="5" fill="#fff"/><circle cx="255" cy="107" r="5" fill="#fff"/><circle cx="277" cy="107" r="5" fill="#fff"/>';
    } else {
        $mainShape = '
            <circle cx="116" cy="82" r="31" fill="#f2c4a7"/>
            <path d="M84 80c0-20 14-34 32-34s32 14 32 34v10H84z" fill="#3f3455"/>
            <rect x="72" y="128" width="88" height="76" rx="32" fill="#fff" stroke="#d8e1ef" stroke-width="3"/>
            <path d="M116 143v46M94 166h44" stroke="'.$main.'" stroke-width="9" stroke-linecap="round"/>
        ';
        $icon = '<path d="M221 74c27-23 72-4 72 36 0 42-44 62-72 88-28-26-72-46-72-88 0-40 45-59 72-36z" fill="'.$main.'" opacity=".95"/><path d="M198 121h47M221 98v47" stroke="#fff" stroke-width="9" stroke-linecap="round"/>';
    }

    $svg = '
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 260">
      <defs>
        <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#ffffff"/>
          <stop offset="100%" stop-color="'.$soft.'"/>
        </linearGradient>
        <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
          <feDropShadow dx="0" dy="16" stdDeviation="12" flood-color="#1f2540" flood-opacity=".14"/>
        </filter>
      </defs>
      <rect width="360" height="260" rx="38" fill="url(#bg)"/>
      <circle cx="298" cy="42" r="72" fill="'.$accent.'" opacity=".13"/>
      <circle cx="54" cy="224" r="78" fill="'.$main.'" opacity=".08"/>
      <g filter="url(#shadow)">'.$mainShape.'</g>
      <g>'.$icon.'</g>
      <rect x="32" y="28" width="86" height="30" rx="15" fill="#fff" opacity=".82"/>
      <text x="75" y="48" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" font-weight="700" fill="'.$main.'">'.strtoupper($label).'</text>
    </svg>';

    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

/* =========================
   6) SERVICES
========================= */
$services = [
    [
        "title"        => "Caregiver",
        "img"          => serviceSvg("caregiver"),
        "href"         => "caregiver_booking.php",
        "desc"         => "Personal daily support at home.",
        "badge"        => "Daily Support",
        "fallback"     => "🧑‍🦽",
        "btn"          => "Book Caregiver",
        "simple_line"  => "Support for safer daily routines.",
        "features"     => ["Personal care", "Daily help"]
    ],
    [
        "title"        => "Driver",
        "img"          => serviceSvg("driver"),
        "href"         => "request_driver.php",
        "desc"         => "Accessible rides for easier movement.",
        "badge"        => "Accessible Ride",
        "fallback"     => "🚗",
        "btn"          => "Request Ride",
        "simple_line"  => "Safe transportation when you need it.",
        "features"     => ["Accessible car", "Easy trip"]
    ],
    [
        "title"        => "Doctor",
        "img"          => serviceSvg("doctor"),
        "href"         => "doc_service.php",
        "desc"         => "Book trusted doctors by specialty.",
        "badge"        => "Medical Help",
        "fallback"     => "🩺",
        "btn"          => "Find Doctor",
        "simple_line"  => "Medical appointments made simple.",
        "features"     => ["Specialties", "Trusted care"]
    ],
    [
        "title"        => "Interpreter",
        "img"          => serviceSvg("interpreter"),
        "href"         => "int_service.php",
        "desc"         => "Language support.",
        "badge"        => "Communication",
        "fallback"     => "🗣️",
        "btn"          => "Book Interpreter",
        "simple_line"  => "Clear communication with confidence.",
        "features"     => ["Languages", "Clear talk"]
    ],
];

/* =========================
   7) PLACES LOAD FROM API
========================= */
/*
   Nearby places are loaded on the frontend the same way as accessibility_map.php:
   - Overpass API returns nearby map places.
   - ../general/accessibility_api.php?action=place_features overlays saved accessibility features.
*/
$places = [];


/* =========================
   8) CARTOON PLACE IMAGES
========================= */
function placeTypeImage(string $type, string $name = ''): string {
    $key = strtolower(trim($type . ' ' . $name));

    $label = 'Place';
    $main = '#353b69';
    $accent = '#6470d2';
    $soft = '#eef2ff';
    $shape = '';

    if (str_contains($key, 'hospital') || str_contains($key, 'clinic') || str_contains($key, 'medical') || str_contains($key, 'doctor')) {
        $label = 'Hospital';
        $main = '#2563eb';
        $accent = '#38bdf8';
        $soft = '#eff6ff';
        $shape = '
            <rect x="86" y="74" width="160" height="132" rx="26" fill="#ffffff" stroke="#dbeafe" stroke-width="5"/>
            <rect x="112" y="106" width="108" height="100" rx="20" fill="#dbeafe"/>
            <path d="M166 112v48M142 136h48" stroke="'.$main.'" stroke-width="14" stroke-linecap="round"/>
            <rect x="139" y="172" width="54" height="34" rx="12" fill="'.$main.'"/>
            <rect x="238" y="126" width="42" height="80" rx="18" fill="'.$accent.'" opacity=".42"/>
            <circle cx="105" cy="72" r="18" fill="'.$accent.'" opacity=".30"/>
        ';
    } elseif (str_contains($key, 'museum') || str_contains($key, 'gallery')) {
        $label = 'Museum';
        $main = '#353b69';
        $accent = '#6470d2';
        $soft = '#eef2ff';
        $shape = '
            <path d="M72 103h216L180 43z" fill="'.$main.'"/>
            <circle cx="180" cy="77" r="14" fill="#fff" opacity=".95"/>
            <rect x="84" y="113" width="192" height="18" rx="9" fill="'.$accent.'"/>
            <rect x="102" y="132" width="28" height="74" rx="10" fill="#fff" stroke="#dbe5f5" stroke-width="4"/>
            <rect x="148" y="132" width="28" height="74" rx="10" fill="#fff" stroke="#dbe5f5" stroke-width="4"/>
            <rect x="184" y="132" width="28" height="74" rx="10" fill="#fff" stroke="#dbe5f5" stroke-width="4"/>
            <rect x="230" y="132" width="28" height="74" rx="10" fill="#fff" stroke="#dbe5f5" stroke-width="4"/>
            <rect x="80" y="206" width="200" height="20" rx="10" fill="'.$main.'"/>
        ';
    } elseif (str_contains($key, 'mall') || str_contains($key, 'shopping') || str_contains($key, 'store') || str_contains($key, 'market')) {
        $label = 'Mall';
        $main = '#353b69';
        $accent = '#6470d2';
        $soft = '#eef2ff';
        $shape = '
            <rect x="80" y="83" width="200" height="128" rx="30" fill="#fff" stroke="#dbe5f5" stroke-width="5"/>
            <path d="M114 92c0-34 21-56 66-56s66 22 66 56" fill="none" stroke="'.$main.'" stroke-width="13" stroke-linecap="round"/>
            <rect x="104" y="119" width="48" height="44" rx="16" fill="'.$accent.'" opacity=".34"/>
            <rect x="156" y="119" width="48" height="44" rx="16" fill="'.$main.'" opacity=".16"/>
            <rect x="208" y="119" width="48" height="44" rx="16" fill="'.$accent.'" opacity=".34"/>
            <rect x="127" y="178" width="106" height="33" rx="16" fill="'.$main.'"/>
            <circle cx="112" cy="205" r="9" fill="'.$accent.'"/>
            <circle cx="248" cy="205" r="9" fill="'.$accent.'"/>
        ';
    } elseif (str_contains($key, 'zoo') || str_contains($key, 'park') || str_contains($key, 'garden')) {
        $label = 'Zoo';
        $main = '#2f7d5b';
        $accent = '#6ee7b7';
        $soft = '#ecfdf5';
        $shape = '
            <circle cx="129" cy="107" r="45" fill="'.$accent.'" opacity=".52"/>
            <circle cx="231" cy="105" r="42" fill="'.$accent.'" opacity=".38"/>
            <rect x="99" y="134" width="162" height="70" rx="33" fill="#fff" stroke="#dbe5f5" stroke-width="5"/>
            <circle cx="144" cy="160" r="9" fill="'.$main.'"/>
            <circle cx="216" cy="160" r="9" fill="'.$main.'"/>
            <path d="M168 181c9 9 24 9 33 0" stroke="'.$main.'" stroke-width="8" stroke-linecap="round" fill="none"/>
            <path d="M99 213h162" stroke="'.$main.'" stroke-width="13" stroke-linecap="round"/>
            <path d="M124 73l-19-21M236 72l19-21" stroke="'.$main.'" stroke-width="8" stroke-linecap="round"/>
        ';
    } elseif (str_contains($key, 'restaurant') || str_contains($key, 'cafe') || str_contains($key, 'food')) {
        $label = 'Food';
        $main = '#b53535';
        $accent = '#f59e0b';
        $soft = '#fff7ed';
        $shape = '
            <circle cx="180" cy="135" r="73" fill="#fff" stroke="#fed7aa" stroke-width="6"/>
            <circle cx="180" cy="135" r="45" fill="'.$accent.'" opacity=".34"/>
            <path d="M95 69v72M116 69v72M95 104h21" stroke="'.$main.'" stroke-width="10" stroke-linecap="round"/>
            <path d="M260 70v134" stroke="'.$main.'" stroke-width="10" stroke-linecap="round"/>
            <path d="M260 73c28 22 28 58 0 80" stroke="'.$main.'" stroke-width="10" stroke-linecap="round" fill="none"/>
        ';
    } else {
        $label = 'Place';
        $main = '#353b69';
        $accent = '#6470d2';
        $soft = '#eef2ff';
        $shape = '
            <path d="M180 48c-48 0-84 35-84 81 0 60 84 121 84 121s84-61 84-121c0-46-36-81-84-81z" fill="'.$main.'"/>
            <circle cx="180" cy="129" r="38" fill="#fff" opacity=".96"/>
            <path d="M160 130h40M180 110v40" stroke="'.$accent.'" stroke-width="11" stroke-linecap="round"/>
        ';
    }

    $svg = '
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 260">
      <defs>
        <linearGradient id="placeBg" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#ffffff"/>
          <stop offset="100%" stop-color="'.$soft.'"/>
        </linearGradient>
        <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
          <feDropShadow dx="0" dy="18" stdDeviation="14" flood-color="#1f2540" flood-opacity=".14"/>
        </filter>
      </defs>
      <rect width="360" height="260" rx="40" fill="url(#placeBg)"/>
      <circle cx="302" cy="54" r="78" fill="'.$accent.'" opacity=".16"/>
      <circle cx="58" cy="224" r="82" fill="'.$main.'" opacity=".09"/>
      <path d="M42 219C96 184 135 204 180 188s82-61 139-20" stroke="'.$accent.'" stroke-width="12" stroke-linecap="round" opacity=".18" fill="none"/>
      <g filter="url(#shadow)">'.$shape.'</g>
      <rect x="28" y="26" width="104" height="34" rx="17" fill="#fff" opacity=".92"/>
      <text x="80" y="48" text-anchor="middle" font-family="Arial, sans-serif" font-size="13" font-weight="800" fill="'.$main.'">'.strtoupper($label).'</text>
    </svg>';

    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

/* =========================
   9) PRODUCTS
========================= */
$products = [
    [
        "title" => "Smart Beeping Glasses",
        "img"   => $PIC . "glasses.jpeg",
        "desc"  => "Smart glasses that help users detect nearby obstacles with gentle sound alerts for safer movement.",
        "price" => "1499 EGP",
        "href"  => "product_payment.php?product=" . urlencode("Smart Beeping Glasses")
    ],
    [
        "title" => "Emergency Alert Bracelet",
        "img"   => $PIC . "watch.jpeg",
        "desc"  => "A smart bracelet that sends an emergency alert with important details quickly when needed.",
        "price" => "2999 EGP",
        "href"  => "product_payment.php?product=" . urlencode("Emergency Alert Bracelet")
    ],
];

/* =========================
   10) HELPER
========================= */
function accessibilityFeatures(array $place): array {
    $features = [];

    if (!empty($place['elevator'])) $features[] = 'Elevator';
    if (!empty($place['ramp']))     $features[] = 'Ramp';
    if (!empty($place['toilet']))   $features[] = 'Accessible Toilet';
    if (!empty($place['parking']))  $features[] = 'Parking';

    return $features;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RafiQ — Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root{
            --primary:#4b4f83;
            --primary-dark:#242742;
            --secondary:#6d73c8;
            --danger:#b53535;
            --bg:#f6f8fd;
            --text:#222335;
            --muted:#6b7188;
            --line:#e8ebf5;
            --soft:#f1f4fb;
            --card:#ffffff;
            --shadow:0 16px 34px rgba(36,39,66,.08);
            --shadow-lg:0 26px 54px rgba(36,39,66,.12);
            --container:1160px;
        }

        *{ box-sizing:border-box; }
        html{ scroll-behavior:smooth; }
        body{
            margin:0;
            font-family:"Manrope", system-ui, sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at top left, rgba(109,115,200,.10), transparent 26%),
                radial-gradient(circle at bottom right, rgba(181,53,53,.06), transparent 22%),
                var(--bg);
        }

        a{
            text-decoration:none;
            color:inherit;
        }

        button, input{ font-family:inherit; }

        .container{
            width:min(var(--container), calc(100% - 32px));
            margin:0 auto;
        }

        .intro-top{
            padding:28px 0 14px;
        }

        .intro-row{
            display:flex;
            justify-content:space-between;
            align-items:flex-end;
            gap:16px;
            flex-wrap:wrap;
        }

        .hello-title{
            margin:0;
            font-size:38px;
            line-height:1.04;
            font-weight:800;
            color:var(--primary-dark);
            letter-spacing:-1.2px;
        }

        .hello-sub{
            margin:10px 0 0;
            color:var(--muted);
            font-size:14px;
            line-height:1.9;
            max-width:620px;
        }

        .location-inline{
            margin-top:16px;
            display:inline-flex;
            align-items:center;
            gap:10px;
            padding:12px 16px;
            border-radius:16px;
            background:rgba(255,255,255,.82);
            border:1px solid rgba(75,79,131,.08);
            backdrop-filter:blur(10px);
            box-shadow:0 10px 24px rgba(36,39,66,.05);
            color:var(--primary-dark);
            font-size:14px;
            font-weight:700;
        }

        .hidden-form{ display:none; }

        .section{
            padding:18px 0 10px;
        }

        .section-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
            margin-bottom:16px;
        }

        .section-head h2{
            margin:0;
            font-size:24px;
            font-weight:800;
            color:var(--primary-dark);
            letter-spacing:-.5px;
        }

        .show-all{
            border:none;
            background:transparent;
            color:var(--primary);
            font-weight:800;
            font-size:14px;
            cursor:pointer;
            padding:0;
        }

        /* =========================
           PREMIUM SERVICES
        ========================= */
        .services-hero{
            position:relative;
            overflow:hidden;
            border-radius:34px;
            background:linear-gradient(135deg,#20233c 0%, #353b69 54%, #6470d2 100%);
            box-shadow:var(--shadow-lg);
            padding:30px;
            margin-bottom:18px;
            color:#fff;
        }

        .services-hero::before{
            content:"";
            position:absolute;
            width:360px;
            height:360px;
            border-radius:50%;
            right:-120px;
            top:-150px;
            background:rgba(255,255,255,.11);
        }

        .services-hero::after{
            content:"";
            position:absolute;
            width:210px;
            height:210px;
            border-radius:44px;
            left:-70px;
            bottom:-85px;
            background:rgba(255,255,255,.06);
            transform:rotate(18deg);
        }

        .services-hero-inner{
            position:relative;
            z-index:2;
            display:grid;
            grid-template-columns:1.05fr .95fr;
            gap:28px;
            align-items:center;
        }

        .eyebrow{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:9px 13px;
            border-radius:999px;
            background:rgba(255,255,255,.10);
            border:1px solid rgba(255,255,255,.12);
            font-size:12px;
            font-weight:800;
            letter-spacing:.08em;
            text-transform:uppercase;
            margin-bottom:14px;
        }

        .services-hero h2{
            margin:0;
            max-width:640px;
            font-size:38px;
            line-height:1.08;
            font-weight:800;
            letter-spacing:-1.4px;
        }

        .services-hero p{
            margin:14px 0 0;
            max-width:620px;
            color:rgba(255,255,255,.86);
            font-size:14px;
            line-height:1.9;
            font-weight:600;
        }

        .hero-service-preview{
            display:grid;
            grid-template-columns:repeat(2,1fr);
            gap:12px;
        }

        .preview-tile{
            min-height:112px;
            padding:16px;
            border-radius:22px;
            background:rgba(255,255,255,.10);
            border:1px solid rgba(255,255,255,.12);
            backdrop-filter:blur(12px);
        }

        .preview-icon{
            width:38px;
            height:38px;
            border-radius:14px;
            display:grid;
            place-items:center;
            background:rgba(255,255,255,.14);
            margin-bottom:10px;
            font-size:19px;
        }

        .preview-title{
            font-size:14px;
            font-weight:800;
            margin-bottom:5px;
        }

        .preview-text{
            color:rgba(255,255,255,.76);
            font-size:12px;
            line-height:1.7;
            font-weight:600;
        }

        .services-grid{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:16px;
            align-items:stretch;
        }

        .service-card{
            position:relative;
            overflow:hidden;
            height:100%;
            display:flex;
            flex-direction:column;
            background:rgba(255,255,255,.92);
            border:1px solid rgba(36,39,66,.07);
            min-width:0;
            border-radius:28px;
            box-shadow:var(--shadow);
            transition:.24s ease;
        }

        .service-card:hover{
            transform:translateY(-6px);
            box-shadow:0 24px 44px rgba(36,39,66,.13);
        }

        .service-card::before{
            content:"";
            position:absolute;
            inset:0 0 auto 0;
            height:6px;
            background:linear-gradient(90deg,#353b69,#6470d2);
        }

        .service-media{
            padding:22px 22px 0;
        }

        .service-media-box{
            height:158px;
            border-radius:22px;
            background:linear-gradient(180deg,#f9fbff,#eef2fb);
            border:1px solid var(--line);
            display:flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
        }

        .service-media-box img{
            width:100%;
            height:100%;
            object-fit:contain;
            padding:8px;
        }

        .service-body{
            padding:20px 22px 24px;
            display:flex;
            flex-direction:column;
            flex:1;
        }

        .service-card-top{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            margin-bottom:16px;
        }

        .service-badge{
            display:inline-flex;
            align-items:center;
            padding:8px 13px;
            border-radius:999px;
            background:#eef2ff;
            color:#353b69;
            border:1px solid rgba(100,112,210,.22);
            font-size:11px;
            font-weight:800;
            white-space:nowrap;
            letter-spacing:.02em;
        }

        .service-number{
            width:34px;
            height:34px;
            border-radius:13px;
            display:grid;
            place-items:center;
            background:var(--primary-dark);
            color:#fff;
            font-size:12px;
            font-weight:800;
        }

        .service-card h3{
            margin:0 0 10px;
            font-size:22px;
            line-height:1.2;
            white-space:nowrap;
            color:var(--primary-dark);
            font-weight:800;
            letter-spacing:-.4px;
        }

        .service-card p{
            margin:0;
            color:var(--muted);
            font-size:13px;
            line-height:1.85;
            font-weight:600;
        }

        .service-mini-features{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            margin:20px 0 22px;
        }

        .mini-feature{
            padding:8px 12px;
            border-radius:999px;
            background:#f4f6fb;
            border:1px solid var(--line);
            color:#4f556d;
            font-size:11px;
            font-weight:800;
        }

        .service-path{
            display:grid;
            gap:8px;
            padding-top:14px;
            border-top:1px solid var(--line);
        }

        .path-row{
            display:flex;
            align-items:center;
            gap:8px;
            color:#586078;
            font-size:12px;
            font-weight:800;
        }

        .path-dot{
            width:22px;
            height:22px;
            min-width:22px;
            border-radius:9px;
            display:grid;
            place-items:center;
            background:color-mix(in srgb, var(--service-color) 11%, white);
            color:var(--service-color);
            font-size:11px;
            font-weight:900;
        }

        .service-cta{
            margin-top:auto;
            display:flex;
            align-items:center;
            justify-content:center;
            height:50px;
            border-radius:16px;
            background:linear-gradient(135deg,#353b69,#6470d2);
            color:#fff;
            font-weight:800;
            font-size:13px;
            letter-spacing:.02em;
            white-space:nowrap;
            box-shadow:0 12px 28px rgba(53,59,105,.26);
            transition:.2s ease;
            text-decoration:none;
        }

        .service-cta:hover{
            transform:translateY(-2px);
            box-shadow:0 16px 34px rgba(53,59,105,.32);
        }

        /* =========================
           PLACES
        ========================= */
        .places-grid{
            display:grid;
            grid-template-columns:repeat(2, 1fr);
            gap:16px;
        }

        .place-card{
            background:rgba(255,255,255,.88);
            border:1px solid rgba(36,39,66,.06);
            border-radius:24px;
            padding:16px;
            box-shadow:var(--shadow);
            backdrop-filter:blur(10px);
            transition:.22s ease;
        }

        .place-card:hover{
            transform:translateY(-4px);
            box-shadow:0 22px 40px rgba(36,39,66,.10);
        }

        .place-card.hidden-place{
            display:none;
        }

        .place-top{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:12px;
            margin-bottom:12px;
        }

        .place-left{
            display:flex;
            align-items:flex-start;
            gap:12px;
            min-width:0;
        }

        .place-logo{
            width:92px;
            height:72px;
            border-radius:20px;
            background:#fff;
            border:1px solid var(--line);
            display:flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
            flex:0 0 auto;
        }

        .place-logo img{
            width:100%;
            height:100%;
            object-fit:cover;
        }

        .place-title-wrap{
            min-width:0;
        }

        .place-name{
            margin:0 0 4px;
            font-size:18px;
            font-weight:800;
            color:var(--primary-dark);
            line-height:1.25;
        }

        .place-type{
            display:inline-flex;
            align-items:center;
            padding:6px 10px;
            border-radius:999px;
            background:#eef2fb;
            color:var(--primary);
            font-size:11px;
            font-weight:800;
        }

        .distance{
            flex:0 0 auto;
            padding:8px 10px;
            border-radius:12px;
            background:#f3f5fb;
            color:var(--primary);
            font-size:12px;
            font-weight:800;
        }

        .place-address{
            margin:0 0 12px;
            color:var(--muted);
            font-size:13px;
            line-height:1.8;
        }

        .place-meta{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            margin-bottom:10px;
        }

        .meta-chip{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 10px;
            border-radius:12px;
            background:#f8f9fd;
            color:#4e556e;
            font-size:12px;
            font-weight:800;
        }

        .meta-dot{
            width:8px;
            height:8px;
            border-radius:50%;
            background:var(--primary);
        }

        .features{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }

        .feature-tag{
            padding:7px 10px;
            border-radius:999px;
            background:#f1f4fb;
            color:var(--primary-dark);
            font-size:11px;
            font-weight:800;
        }

        .place-comment{
            margin-top:10px;
            color:#5e647a;
            font-size:12px;
            line-height:1.7;
        }

        /* =========================
           PRODUCTS
        ========================= */
        .products-grid{
            display:grid;
            grid-template-columns:repeat(2, 1fr);
            gap:18px;
        }

        .product{
            background:linear-gradient(180deg, rgba(255,255,255,.95), rgba(247,249,253,.90));
            border:1px solid rgba(36,39,66,.06);
            border-radius:26px;
            padding:22px;
            box-shadow:var(--shadow);
            display:flex;
            flex-direction:column;
            gap:16px;
            transition:.22s ease;
        }

        .product:hover{
            transform:translateY(-4px);
        }

        .product-top{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
        }

        .product-text{ flex:1; }

        .product-badge{
            display:inline-block;
            padding:7px 10px;
            border-radius:999px;
            background:#eef2fb;
            color:var(--primary);
            font-size:12px;
            font-weight:800;
            margin-bottom:10px;
        }

        .product h3{
            margin:0 0 8px;
            font-size:22px;
            line-height:1.3;
            font-weight:800;
            color:var(--primary-dark);
        }

        .product p{
            margin:0;
            color:var(--muted);
            font-size:14px;
            line-height:1.8;
        }

        .product-image-wrap{
            width:118px;
            height:118px;
            border-radius:22px;
            background:#fff;
            border:1px solid var(--line);
            display:flex;
            align-items:center;
            justify-content:center;
            flex:0 0 auto;
        }

        .product-image-wrap img{
            width:88px;
            max-width:100%;
            object-fit:contain;
        }

        .product-bottom{
            margin-top:auto;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
        }

        .price{
            font-size:22px;
            font-weight:800;
            color:var(--primary-dark);
        }

        .buy{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:128px;
            height:44px;
            padding:0 18px;
            border-radius:14px;
            background:linear-gradient(135deg,#353b69,#6470d2);
            color:#fff;
            font-weight:800;
            box-shadow:0 10px 24px rgba(53,59,105,.24);
        }

        .empty{
            color:var(--muted);
            font-weight:700;
            background:#fff;
            border:1px solid var(--line);
            border-radius:22px;
            padding:18px;
            box-shadow:var(--shadow);
        }

        @media (max-width: 1180px){
            .services-grid{grid-template-columns:repeat(2,1fr);}
        }

        @media (max-width: 900px){
            .services-hero-inner{grid-template-columns:1fr;}
            .services-hero h2{font-size:30px;}
        }

        @media (max-width: 620px){
            .services-grid{grid-template-columns:1fr;}
            .hero-service-preview{grid-template-columns:1fr;}
            .services-hero{padding:22px;border-radius:26px;}
            .services-hero h2{font-size:27px;}
            .service-media-box{height:135px;}
        }

        @media (max-width: 1100px){
            .service-features,
            .service-steps{
                grid-template-columns:1fr 1fr 1fr;
            }
        }

        @media (max-width: 980px){
            .service-slide{
                grid-template-columns:1fr;
                padding:24px 22px 82px;
            }

            .service-title{
                font-size:32px;
            }

            .places-grid,
            .products-grid{
                grid-template-columns:1fr;
            }
        }

        @media (max-width: 700px){
            .service-features,
            .service-steps{
                grid-template-columns:1fr;
            }

            .service-step{
                min-height:auto;
            }
        }

        @media (max-width: 640px){
            .container{ width:min(var(--container), calc(100% - 20px)); }
            .hello-title{ font-size:29px; }
            .place-top{ flex-direction:column; }
            .distance{ align-self:flex-start; }
            .product-top{ flex-direction:column-reverse; align-items:flex-start; }
        }

        /* ── SCROLL ANIMATIONS ── */
        .fade-up {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.55s cubic-bezier(.22,.68,0,1.2), transform 0.55s cubic-bezier(.22,.68,0,1.2);
        }
        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .fade-up-delay-1 { transition-delay: 0.08s; }
        .fade-up-delay-2 { transition-delay: 0.16s; }
        .fade-up-delay-3 { transition-delay: 0.24s; }
        .fade-up-delay-4 { transition-delay: 0.32s; }

        /* ── PLACE FILTER TABS ── */
        .place-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .place-filter-btn {
            padding: 7px 16px;
            border-radius: 99px;
            border: 1.5px solid var(--line);
            background: #fff;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.18s;
        }
        .place-filter-btn:hover { border-color: var(--primary); color: var(--primary); }
        .place-filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        /* ── PRODUCT CARD POLISH ── */
        .product { transition: transform 0.22s ease, box-shadow 0.22s ease; }
        .product:hover { transform: translateY(-5px); box-shadow: 0 28px 54px rgba(36,39,66,.13); }
        .buy { transition: transform 0.18s, box-shadow 0.18s; }
        .buy:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(53,59,105,.32); }

        /* ── SERVICE CARD POLISH ── */
        .service-card { transition: transform 0.24s ease, box-shadow 0.24s ease; }
        .service-cta { transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s; }

        /* ── QUICK ACTIONS ── */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin: 18px 0 4px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 18px 12px;
            border-radius: 20px;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(36,39,66,0.07);
            box-shadow: var(--shadow);
            text-decoration: none;
            color: var(--primary-dark);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s;
            backdrop-filter: blur(8px);
        }
        .quick-action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 36px rgba(36,39,66,.12);
            background: #fff;
        }
        .quick-action-icon {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            background: linear-gradient(135deg, #353b69, #6470d2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .quick-action-label {
            font-size: 12px;
            font-weight: 800;
            color: var(--primary-dark);
            text-align: center;
        }
        @media (max-width: 600px) {
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
        }
        /* Final service cards alignment fix */
.services-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 22px;
    align-items: stretch;
}

.service-card {
    height: 100%;
    min-height: 540px;
    display: flex;
    flex-direction: column;
}

.service-media {
    padding: 22px 22px 0;
}

.service-media-box {
    height: 170px;
}

.service-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 22px 22px 24px;
}

.service-card-top {
    min-height: 42px;
    margin-bottom: 18px;
}

.service-badge {
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.service-card h3 {
    min-height: 34px;
    margin: 0 0 12px;
    display: flex;
    align-items: center;
}

.service-card p {
    min-height: 58px;
    margin: 0;
    line-height: 1.8;
}

.service-mini-features {
    min-height: 46px;
    margin: 22px 0 24px;
    display: flex;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 10px;
}

.mini-feature {
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.service-cta {
    margin-top: auto;
    height: 54px;
}

@media (max-width: 1180px) {
    .services-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .service-card {
        min-height: 520px;
    }
}

@media (max-width: 620px) {
    .services-grid {
        grid-template-columns: 1fr;
    }

    .service-card {
        min-height: auto;
    }

    .service-card p,
    .service-mini-features,
    .service-card h3,
    .service-card-top {
        min-height: unset;
    }
}
.floating-chatbot {
    position: fixed;
    right: 26px;
    bottom: 26px;
    width: 62px;
    height: 62px;
    border-radius: 22px;
    background: linear-gradient(135deg, #353b69, #6470d2);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    overflow: hidden;
    text-decoration: none;
    box-shadow: 0 18px 40px rgba(53, 59, 105, 0.32);
    z-index: 9999;
    transition: 0.2s ease;
}

.floating-chatbot:hover {
    transform: translateY(-4px);
    box-shadow: 0 22px 48px rgba(53, 59, 105, 0.40);
}

.floating-chatbot img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

@media (max-width: 600px) {
    .floating-chatbot {
        right: 18px;
        bottom: 18px;
        width: 56px;
        height: 56px;
        border-radius: 18px;
        font-size: 22px;
    }
}
    
/* Simple nearby places cards: no image/icon, no rating/review */
.simple-place-card{
    min-height:150px !important;
    padding:24px !important;
}

.simple-place-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    margin-bottom:18px;
}

.simple-place-card .place-name{
    margin:0 0 8px;
}

.simple-place-card .place-type{
    display:inline-flex;
    align-items:center;
    padding:7px 14px;
    border-radius:999px;
    background:#f0f2fb;
    color:#404066;
    font-weight:900;
    font-size:13px;
}

.simple-place-card .place-address{
    margin:0;
    color:#6b7080;
    font-weight:700;
    line-height:1.6;
}


.simple-features{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:18px;
}

.simple-features .feature-tag{
    display:inline-flex;
    align-items:center;
    padding:8px 13px;
    border-radius:999px;
    background:#f3f4fb;
    border:1px solid rgba(64,64,102,.08);
    color:#2B2C41;
    font-size:13px;
    font-weight:900;
}

</style>
</head>
<body>

<?php include '../general/nav_patient.php'; ?>

<main class="container">

    <section class="intro-top">
        <div class="intro-row fade-up">
            <div>
                <h1 class="hello-title">Hello, <?= htmlspecialchars($userName) ?> 👋</h1>
                <p class="hello-sub">Find nearby accessible places and explore support services designed to make daily life easier.</p>
            </div>
        </div>

        <div class="location-inline fade-up fade-up-delay-1">
            <span>📍</span>
            <span id="liveLocationText">
                <?= ($userLat !== null && $userLng !== null) ? 'Loading your current place...' : 'Detecting your location...' ?>
            </span>
        </div>

        <form method="get" action="" id="locationForm" class="hidden-form">
            <input type="hidden" name="lat" id="latInput" value="<?= htmlspecialchars((string)($userLat ?? '')) ?>">
            <input type="hidden" name="lng" id="lngInput" value="<?= htmlspecialchars((string)($userLng ?? '')) ?>">
        </form>
    </section>

    <section class="section">
        <div class="services-hero">
            <div class="services-hero-inner">
                <div>
                    <div class="eyebrow">Rafiq services</div>
                    <h2>Everything you need, organized in one clean place.</h2>
                    <p>
                        Choose the service you need quickly.
                    </p>
                </div>

                <div class="hero-service-preview">
                    <div class="preview-tile">
                        <div class="preview-icon">🧑‍🦽</div>
                        <div class="preview-title">Daily support</div>
                        <div class="preview-text">Caregiver help for safer routines.</div>
                    </div>
                    <div class="preview-tile">
                        <div class="preview-icon">🚗</div>
                        <div class="preview-title">Accessible rides</div>
                        <div class="preview-text">Move easier with driver support.</div>
                    </div>
                    <div class="preview-tile">
                        <div class="preview-icon">🩺</div>
                        <div class="preview-title">Medical booking</div>
                        <div class="preview-text">Find doctors and appointments.</div>
                    </div>
                    <div class="preview-tile">
                        <div class="preview-icon">🗣️</div>
                        <div class="preview-title">Communication</div>
                        <div class="preview-text">Interpreter support when needed.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-head">
            <h2>Our Services</h2>
        </div>

        <div class="services-grid">
            <?php foreach ($services as $index => $s): ?>
                <article class="service-card">
                    <div class="service-media">
                        <div class="service-media-box">
                            <img src="<?= htmlspecialchars($s["img"]) ?>" alt="<?= htmlspecialchars($s["title"]) ?>" loading="lazy">
                        </div>
                    </div>

                    <div class="service-body">
                        <div class="service-card-top">
                            <span class="service-badge"><?= htmlspecialchars($s["badge"]) ?></span>
                        </div>

                        <h3><?= htmlspecialchars($s["title"]) ?></h3>
                        <p><?= htmlspecialchars($s["desc"]) ?></p>

                        <div class="service-mini-features">
                            <?php foreach ($s["features"] as $feature): ?>
                                <span class="mini-feature"><?= htmlspecialchars($feature) ?></span>
                            <?php endforeach; ?>
                        </div>

                        <a class="service-cta" href="<?= htmlspecialchars($s["href"]) ?>">
                            <?= htmlspecialchars($s["btn"]) ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section fade-up" id="placesSection">
        <div class="section-head">
            <h2>Nearby Accessible Places</h2>
            <button type="button" class="show-all" id="togglePlacesBtn">View All</button>
        </div>

        <div class="places-grid" id="placesList">
            <div class="empty" id="placesLoading">Loading nearby accessible places...</div>
        </div>
    </section>

    <section class="section fade-up">
        <div class="section-head">
            <h2>Our Products</h2>
        </div>

        <div class="products-grid">
            <?php foreach ($products as $prod): ?>
                <div class="product">
                    <div class="product-top">
                        <div class="product-text">
                            <h3><?= htmlspecialchars($prod["title"]) ?></h3>
                            <p><?= htmlspecialchars($prod["desc"]) ?></p>
                        </div>

                        <div class="product-image-wrap">
                            <img src="<?= htmlspecialchars($prod["img"]) ?>" alt="<?= htmlspecialchars($prod["title"]) ?>" onerror="this.style.opacity='0';">
                        </div>
                    </div>

                    <div class="product-bottom">
                        <div class="price"><?= htmlspecialchars($prod["price"]) ?></div>
                        <a class="buy" href="<?= htmlspecialchars($prod["href"]) ?>">Buy Now</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<a href="../general/chatbot.php" class="floating-chatbot" aria-label="Open Rafiq Assistant">
    <img src="../pictures/helpy.png" alt="">
</a>
</main>

<script>
function resetScrollToTop() {
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
}

async function reverseGeocode(lat, lng) {
    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`
        );

        const data = await response.json();

        if (data && data.address) {
            const a = data.address;
            const place =
                a.suburb ||
                a.neighbourhood ||
                a.city ||
                a.town ||
                a.village ||
                a.county ||
                a.state ||
                "Your current location";

            document.getElementById("liveLocationText").textContent = place;
        } else {
            document.getElementById("liveLocationText").textContent = "Your current location";
        }
    } catch (error) {
        document.getElementById("liveLocationText").textContent = "Your current location";
    }
}

function autoGetLocation() {
    if (!navigator.geolocation) {
        document.getElementById("liveLocationText").textContent = "Location unavailable";
        loadNearbyPlacesFromApi();
        return;
    }

    const currentLat = document.getElementById("latInput").value;
    const currentLng = document.getElementById("lngInput").value;

    if (currentLat && currentLng) {
        reverseGeocode(currentLat, currentLng);
        loadNearbyPlacesFromApi(currentLat, currentLng);
        return;
    }

    navigator.geolocation.getCurrentPosition(
        async function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            document.getElementById("latInput").value = lat;
            document.getElementById("lngInput").value = lng;

            document.getElementById("liveLocationText").textContent = "Getting your current place...";
            await reverseGeocode(lat, lng);

            window.scrollTo(0, 0);
            loadNearbyPlacesFromApi(lat, lng);
        },
        function() {
            document.getElementById("liveLocationText").textContent = "Current location unavailable";
            loadNearbyPlacesFromApi();
        },
        {
            enableHighAccuracy: true,
            timeout: 12000,
            maximumAge: 0
        }
    );
}

function initServiceFallbacks() {
    const serviceImages = document.querySelectorAll(".service-image-top img");

    serviceImages.forEach(img => {
        img.addEventListener("error", function() {
            const wrapper = this.closest(".service-image-top");
            if (wrapper) {
                wrapper.classList.add("is-fallback");
            }
        });

        if (img.complete && img.naturalWidth === 0) {
            const wrapper = img.closest(".service-image-top");
            if (wrapper) {
                wrapper.classList.add("is-fallback");
            }
        }
    });
}

function initServicesSlider() {
    const track = document.getElementById("servicesTrack");
    const slides = document.querySelectorAll(".service-slide");
    const dots = document.querySelectorAll(".dot-btn");
    const prevBtn = document.getElementById("prevService");
    const nextBtn = document.getElementById("nextService");
    const showcase = document.getElementById("servicesShowcase");

    if (!track || !slides.length) return;

    let currentIndex = 0;
    let startX = 0;
    let endX = 0;
    let autoSlide = null;

    function updateSlider(index) {
        currentIndex = index;
        track.style.transform = `translateX(-${currentIndex * 100}%)`;

        dots.forEach((dot, i) => {
            dot.classList.toggle("active", i === currentIndex);
        });
    }

    function nextSlide() {
        updateSlider((currentIndex + 1) % slides.length);
    }

    function prevSlide() {
        updateSlider((currentIndex - 1 + slides.length) % slides.length);
    }

    function stopAutoSlide() {
        if (autoSlide) {
            clearInterval(autoSlide);
            autoSlide = null;
        }
    }

    function startAutoSlide() {
        stopAutoSlide();
        autoSlide = setInterval(() => {
            nextSlide();
        }, 3000);
    }

    if (nextBtn) {
        nextBtn.addEventListener("click", () => {
            nextSlide();
            startAutoSlide();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener("click", () => {
            prevSlide();
            startAutoSlide();
        });
    }

    dots.forEach(dot => {
        dot.addEventListener("click", function() {
            updateSlider(parseInt(this.dataset.slide, 10));
            startAutoSlide();
        });
    });

    track.addEventListener("touchstart", function(e) {
        startX = e.changedTouches[0].clientX;
        stopAutoSlide();
    });

    track.addEventListener("touchend", function(e) {
        endX = e.changedTouches[0].clientX;
        const diff = startX - endX;

        if (diff > 50) {
            nextSlide();
        } else if (diff < -50) {
            prevSlide();
        }

        startAutoSlide();
    });

    if (showcase) {
        showcase.addEventListener("mouseenter", stopAutoSlide);
        showcase.addEventListener("mouseleave", startAutoSlide);
    }

    updateSlider(0);
    startAutoSlide();
}


const ACCESSIBILITY_API_FILE = "../general/accessibility_api.php";

const EGYPT_CENTER = {
    lat: 30.0444,
    lng: 31.2357
};

const ACCESS_FEATURES = [
    ["wheelchair", "Wheelchair"],
    ["elevator", "Elevator"],
    ["ramp", "Ramp"],
    ["toilet", "Accessible Toilet"],
    ["parking", "Parking"]
];

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function haversine(lat1, lon1, lat2, lon2) {
    const r = 6371;
    const toRad = value => value * Math.PI / 180;

    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);

    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) *
        Math.cos(toRad(lat2)) *
        Math.sin(dLon / 2) ** 2;

    return r * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function resolvePlaceType(tags) {
    if (tags.amenity) return String(tags.amenity).replaceAll("_", " ");
    if (tags.shop) return String(tags.shop).replaceAll("_", " ");
    if (tags.tourism) return String(tags.tourism).replaceAll("_", " ");
    if (tags.leisure) return String(tags.leisure).replaceAll("_", " ");
    if (tags.public_transport) return "public transport";
    if (tags.railway) return String(tags.railway).replaceAll("_", " ");
    if (tags.historic) return String(tags.historic).replaceAll("_", " ");
    return "Place";
}

function buildOverpassQuery(lat, lng, radius = 5000) {
    const around = `around:${radius},${lat},${lng}`;

    return `
[out:json][timeout:45];
(
  node["amenity"~"^(hospital|clinic|pharmacy|dentist|doctors|restaurant|cafe|fast_food|food_court|ice_cream|bakery|university|school|bank|atm|place_of_worship|theatre|cinema|library|community_centre|bus_station|parking|toilets)$"](${around});
  way["amenity"~"^(hospital|clinic|pharmacy|restaurant|cafe|fast_food|food_court|university|school|bank|place_of_worship|theatre|cinema|library|bus_station|parking|toilets)$"](${around});
  relation["amenity"~"^(hospital|clinic|pharmacy|restaurant|cafe|fast_food|food_court|university|school|bank|place_of_worship|theatre|cinema|library|bus_station)$"](${around});

  node["tourism"~"^(museum|hotel|hostel|guest_house|attraction|gallery|apartment)$"](${around});
  way["tourism"~"^(museum|hotel|hostel|guest_house|attraction|gallery|apartment)$"](${around});
  relation["tourism"~"^(museum|hotel|hostel|guest_house|attraction|gallery|apartment)$"](${around});

  node["leisure"~"^(park|garden|playground|sports_centre|fitness_centre|swimming_pool|nature_reserve)$"](${around});
  way["leisure"~"^(park|garden|playground|sports_centre|fitness_centre|swimming_pool|nature_reserve)$"](${around});
  relation["leisure"~"^(park|garden|playground|sports_centre|fitness_centre|swimming_pool|nature_reserve)$"](${around});

  node["shop"~"^(mall|department_store|supermarket|convenience|bakery)$"](${around});
  way["shop"~"^(mall|department_store|supermarket)$"](${around});
  relation["shop"~"^(mall|department_store|supermarket)$"](${around});

  node["public_transport"="station"](${around});
  way["public_transport"="station"](${around});
  relation["public_transport"="station"](${around});

  node["railway"~"^(station|halt)$"](${around});
  way["railway"~"^(station|halt)$"](${around});
  relation["railway"~"^(station|halt)$"](${around});
);
out center tags 180;
`;
}

function parseOverpassElement(element, userLat, userLng) {
    const tags = element.tags || {};

    let lat = element.lat;
    let lng = element.lon;

    if ((element.type === "way" || element.type === "relation") && element.center) {
        lat = element.center.lat;
        lng = element.center.lon;
    }

    if (!lat || !lng) return null;

    const name = (
        tags["name:en"] ||
        tags.name ||
        tags["name:ar"] ||
        tags["brand:en"] ||
        tags.brand ||
        ""
    ).trim();

    if (!name) return null;

    const addressParts = [];

    if (tags["addr:housenumber"] && tags["addr:street"]) {
        addressParts.push(`${tags["addr:housenumber"]} ${tags["addr:street"]}`);
    } else if (tags["addr:street"]) {
        addressParts.push(tags["addr:street"]);
    }

    if (tags["addr:suburb"]) addressParts.push(tags["addr:suburb"]);
    if (tags["addr:city"]) addressParts.push(tags["addr:city"]);

    const wheelchair = ["yes", "limited", "designated"].includes(String(tags.wheelchair || "").toLowerCase());
    const elevator = boolValue(tags.elevator) || tags.highway === "elevator";
    const ramp =
        boolValue(tags.ramp) ||
        boolValue(tags["ramp:wheelchair"]) ||
        ["lowered", "flush", "yes"].includes(String(tags.kerb || "").toLowerCase());
    const toilet =
        boolValue(tags["toilets:wheelchair"]) ||
        boolValue(tags["wheelchair:toilet"]) ||
        (tags.amenity === "toilets" && wheelchair);
    const parking =
        tags["capacity:disabled"] !== undefined ||
        tags["parking:condition"] === "disabled" ||
        boolValue(tags["parking:disabled"]) ||
        (tags.amenity === "parking" && wheelchair);

    return {
        id: `${element.type}_${element.id}`,
        name,
        address: addressParts.length ? addressParts.join(", ") : "Egypt",
        type: resolvePlaceType(tags),
        latitude: parseFloat(lat),
        longitude: parseFloat(lng),
        wheelchair,
        elevator,
        ramp,
        toilet,
        parking,
        rating: 0,
        review_count: 0,
        distance_km: haversine(userLat, userLng, parseFloat(lat), parseFloat(lng))
    };
}

async function fetchOverpassPlaces(lat, lng, radius = 5000) {
    const query = buildOverpassQuery(lat, lng, radius);
    const endpoints = [
        "https://overpass-api.de/api/interpreter",
        "https://overpass.kumi.systems/api/interpreter"
    ];

    let lastError = "";

    for (const endpoint of endpoints) {
        try {
            const response = await fetch(endpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
                },
                body: `data=${encodeURIComponent(query)}`
            });

            if (response.ok) {
                const json = await response.json();
                return (json.elements || [])
                    .map(element => parseOverpassElement(element, lat, lng))
                    .filter(Boolean);
            }

            lastError = `HTTP ${response.status}`;
        } catch (error) {
            lastError = error.message;
        }
    }

    throw new Error(`Could not reach map data service. ${lastError}`);
}

async function getPlaceFeatures(osmIds) {
    if (!osmIds.length) return {};

    const response = await fetch(`${ACCESSIBILITY_API_FILE}?action=place_features`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            osm_ids: osmIds
        })
    });

    return await response.json();
}

function boolValue(value) {
    if (value === true || value === 1) return true;
    const v = String(value ?? "").toLowerCase().trim();
    return ["1", "true", "t", "yes", "y", "limited"].includes(v);
}

function mergeFeatures(place, overlay) {
    if (!overlay) {
        return {
            ...place,
            hasSavedAccessibilityOverlay: false
        };
    }

    return {
        ...place,
        hasSavedAccessibilityOverlay: true,
        wheelchair: place.wheelchair || boolValue(overlay.wheelchair),
        elevator: place.elevator || boolValue(overlay.elevator),
        ramp: place.ramp || boolValue(overlay.ramp),
        toilet: place.toilet || boolValue(overlay.toilet),
        parking: place.parking || boolValue(overlay.parking),
        rating: Number(overlay.rating || 0),
        review_count: Number(overlay.review_count || 0)
    };
}

function accessibilityFeatureList(place) {
    return ACCESS_FEATURES
        .filter(([key]) => place[key])
        .map(([, label]) => label);
}

function hasAnyAccessibilityFeature(place) {
    return accessibilityFeatureList(place).length > 0;
}


function renderHomePlaceCard(place, index) {
    const features = accessibilityFeatureList(place);

    // Remove cards that would say "".
    if (!features.length) {
        return "";
    }

    const hiddenClass = index >= 4 ? "hidden-place" : "";
    const distance = Number.isFinite(Number(place.distance_km))
        ? `<span class="distance">${Number(place.distance_km).toFixed(2)} km</span>`
        : "";

    const featuresHtml = `<div class="features simple-features">
        ${features.map(feature => `<span class="feature-tag">${escapeHtml(feature)}</span>`).join("")}
    </div>`;

    return `
        <a class="place-card simple-place-card ${hiddenClass} fade-up visible"
           href="../patient/accessibility_map.php">
            <div class="simple-place-head">
                <div>
                    <h3 class="place-name">${escapeHtml(place.name)}</h3>
                    <span class="place-type">${escapeHtml(place.type || "Place")}</span>
                </div>
                ${distance}
            </div>

            <p class="place-address">${escapeHtml(place.address || "Egypt")}</p>

            ${featuresHtml}
        </a>
    `;
}

async function loadNearbyPlacesFromApi(lat = "", lng = "") {
    const list = document.getElementById("placesList");
    if (!list) return;

    list.innerHTML = `<div class="empty">Loading nearby places from the accessibility map...</div>`;

    try {
        const centerLat = lat || EGYPT_CENTER.lat;
        const centerLng = lng || EGYPT_CENTER.lng;

        let overpassPlaces = await fetchOverpassPlaces(centerLat, centerLng, 5000);
        let overlays = await getPlaceFeatures(overpassPlaces.map(place => place.id));

        let places = overpassPlaces
            .map(place => mergeFeatures(place, overlays[place.id]))
            .sort((a, b) => (a.distance_km || 9999) - (b.distance_km || 9999));

        // If the nearby 5km search has too few map places, try a wider search.
        if (places.length < 12) {
            overpassPlaces = await fetchOverpassPlaces(centerLat, centerLng, 12000);
            overlays = await getPlaceFeatures(overpassPlaces.map(place => place.id));

            places = overpassPlaces
                .map(place => mergeFeatures(place, overlays[place.id]))
                .sort((a, b) => (a.distance_km || 9999) - (b.distance_km || 9999));
        }

        // Keep all real map places, but remove only places with no recorded accessibility features.
        const placesWithFeatures = places
            .filter(place => accessibilityFeatureList(place).length > 0)
            .slice(0, 12);

        if (!placesWithFeatures.length) {
            list.innerHTML = `<div class="empty">No nearby places with accessibility features found.</div>`;
            initPlacesToggle();
            return;
        }

        list.innerHTML = placesWithFeatures
            .map((place, index) => renderHomePlaceCard(place, index))
            .filter(card => card.trim() !== "")
            .join("");

        initPlacesToggle();
    } catch (error) {
        list.innerHTML = `<div class="empty">Could not load nearby places from the accessibility map.</div>`;
    }
}

function initPlacesToggle() {
    const toggleBtn = document.getElementById("togglePlacesBtn");
    const hiddenPlaces = document.querySelectorAll(".place-card.hidden-place");

    if (!toggleBtn) return;

    toggleBtn.replaceWith(toggleBtn.cloneNode(true));
    const freshToggleBtn = document.getElementById("togglePlacesBtn");

    if (hiddenPlaces.length === 0) {
        freshToggleBtn.style.display = "none";
        return;
    }

    freshToggleBtn.style.display = "";
    let expanded = false;

    freshToggleBtn.addEventListener("click", function() {
        expanded = !expanded;

        hiddenPlaces.forEach(place => {
            place.style.display = expanded ? "block" : "none";
        });

        freshToggleBtn.textContent = expanded ? "Show Less" : "View All";
    });
}

window.addEventListener("beforeunload", function() {
    window.scrollTo(0, 0);
});

// ── SCROLL ANIMATION OBSERVER ──────────────────────────────
function initScrollAnimations() {
    const els = document.querySelectorAll('.fade-up');
    if (!els.length) return;
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    els.forEach(el => observer.observe(el));
}

// ── PLACE TYPE FILTER ──────────────────────────────────────
function initPlaceFilters() {
    const btns  = document.querySelectorAll('.place-filter-btn');
    const cards = document.querySelectorAll('.place-card');
    if (!btns.length) return;
    btns.forEach(btn => {
        btn.addEventListener('click', function () {
            btns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const type = this.dataset.type;
            cards.forEach(card => {
                const cardType = (card.dataset.placeType || '').toLowerCase();
                const match = type === 'all' || cardType.includes(type);
                card.style.display = match ? '' : 'none';
            });
        });
    });
}

window.addEventListener("load", function () {
    resetScrollToTop();
    initServiceFallbacks();
    autoGetLocation();
    initScrollAnimations();
    initPlaceFilters();
});
</script>

<?php include '../general/footer.php'; ?>
</body>
</html>
