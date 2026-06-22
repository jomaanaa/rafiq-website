<?php
session_start();
require __DIR__ . '/../pgdb/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return number_format((float)$n, 2); }

function get_session_patient_id(): int {
    if (!empty($_SESSION['patient_id'])) return (int)$_SESSION['patient_id'];
    if (!empty($_SESSION['user_id']))    return (int)$_SESSION['user_id'];
    if (!empty($_SESSION['ID']))         return (int)$_SESSION['ID'];
    return 0;
}

$patient_id = get_session_patient_id();
if ($patient_id <= 0) {
    header("Location: ../general/login.php");
    exit;
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($booking_id <= 0) { die("Invalid booking ID."); }

/* ── AJAX polling endpoint ── */
if (isset($_GET['action']) && $_GET['action'] === 'poll') {
    header('Content-Type: application/json');
    try {
        $s = $pdo->prepare("
            SELECT b.status, b.payment_status, b.payment_method,
                   CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS provider_name
            FROM booking b
            LEFT JOIN provider p ON p.user_id = b.provider_id
            LEFT JOIN \"user\" u ON u.user_id = p.user_id
            WHERE b.booking_id = :bid AND b.patient_id = :pid
            LIMIT 1
        ");
        $s->execute([':bid' => $booking_id, ':pid' => $patient_id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ?: ['status' => 'unknown']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'unknown', 'error' => $e->getMessage()]);
    }
    exit;
}

/* ── Load booking ── */
try {
    $stmt = $pdo->prepare("
        SELECT b.*,
               CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS provider_name,
               u.photo AS provider_photo
        FROM booking b
        LEFT JOIN provider p ON p.user_id = b.provider_id
        LEFT JOIN \"user\" u ON u.user_id = p.user_id
        WHERE b.booking_id = :bid AND b.patient_id = :pid
        LIMIT 1
    ");
    $stmt->execute([':bid' => $booking_id, ':pid' => $patient_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) { die("Booking not found."); }
} catch (Exception $e) {
    die("Error: " . h($e->getMessage()));
}

/* ── Rating submission ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    $postRating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    if ($postRating >= 1 && $postRating <= 5 && strtolower(trim((string)($booking['status'] ?? ''))) === 'completed') {
        try {
            $rs = $pdo->prepare("UPDATE booking SET rating = :r WHERE booking_id = :bid AND patient_id = :pid AND status = 'completed'");
            $rs->execute([':r' => $postRating, ':bid' => $booking_id, ':pid' => $patient_id]);
        } catch (Exception $e) {}
    }
    header("Location: booking_status.php?booking_id=$booking_id");
    exit;
}

$ratingValue = (isset($booking['rating']) && $booking['rating'] !== null && $booking['rating'] !== '') ? (int)$booking['rating'] : 0;
$hasRating   = ($booking['rating'] !== null && $booking['rating'] !== '');

$serviceType  = trim((string)($booking['service_type'] ?? 'Service'));
$status       = strtolower(trim((string)($booking['status'] ?? 'pending')));
$payMethod    = strtolower(trim((string)($booking['payment_method'] ?? '')));
$providerName = trim((string)($booking['provider_name'] ?? ''));
if ($providerName === '') $providerName = 'Your Provider';
$avatarText   = mb_strtoupper(mb_substr($providerName, 0, 1));
$isDriver     = strtolower($serviceType) === 'driver';

$serviceLabels = [
    'doctor'      => 'DR',
    'caregiver'   => 'CG',
    'interpreter' => 'IN',
    'driver'      => 'DV',
];
$serviceIcon = $serviceLabels[strtolower($serviceType)] ?? 'SR';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking Status — Rafiq</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#404066;
    --primary-dark:#2B2C41;
    --primary-light:#6d73c8;
    --bg:#f6f8fd;
    --card:#ffffff;
    --text:#222335;
    --muted:#6e7388;
    --line:#e7e9f2;
    --success:#168653;
    --success-bg:#eefbf4;
    --danger:#B53535;
    --danger-bg:#fff1f1;
    --shadow:0 20px 50px rgba(43,44,65,.10);
    --shadow-soft:0 12px 28px rgba(64,64,102,.08);
}
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:"Nunito",system-ui,-apple-system,Segoe UI,Arial,sans-serif;
    background:radial-gradient(circle at top left,rgba(109,115,200,.13),transparent 28%),
               radial-gradient(circle at bottom right,rgba(64,64,102,.10),transparent 25%),
               var(--bg);
    min-height:100vh;
    color:var(--text);
}
.container{width:min(840px,calc(100% - 32px));margin:0 auto;padding:36px 0 56px}
.hero{
    border-radius:34px;
    padding:34px 32px;
    margin-bottom:20px;
    background:linear-gradient(135deg,#2B2C41 0%,#404066 55%,#6d73c8 100%);
    color:#fff;
    position:relative;
    overflow:hidden;
    box-shadow:var(--shadow);
}
.hero:after{
    content:"";
    position:absolute;
    width:260px;height:260px;border-radius:50%;
    right:-80px;top:-100px;background:rgba(255,255,255,.12);
    pointer-events:none;
}
.hero-top{position:relative;z-index:2;display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.hero-label{font-size:13px;font-weight:900;opacity:.76;letter-spacing:.6px;text-transform:uppercase}
.hero-title{font-size:31px;font-weight:900;line-height:1.1;margin-top:5px}
.hero-sub{margin-top:8px;font-size:15px;font-weight:700;opacity:.82}
.status-banner{
    margin-top:24px;border-radius:20px;padding:16px 18px;
    display:flex;align-items:center;gap:14px;font-weight:800;font-size:15px;
    position:relative;z-index:2;background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.22);
}
.status-banner.accepted,.status-banner.completed{background:rgba(22,134,83,.20);border-color:rgba(22,134,83,.34);color:#d8fce9}
.status-banner.declined{background:rgba(181,53,53,.20);border-color:rgba(181,53,53,.34);color:#ffe0e0}
.pulse-ring{width:12px;height:12px;border-radius:50%;background:#fff;animation:ping 1.4s ease-in-out infinite;flex-shrink:0}
@keyframes ping{0%{transform:scale(1);opacity:1}70%{transform:scale(1.7);opacity:0}100%{transform:scale(1.7);opacity:0}}
.card{
    background:#fff;border-radius:28px;border:1px solid rgba(64,64,102,.08);
    box-shadow:var(--shadow);overflow:hidden;margin-bottom:18px;
    animation:fadeUp .45s cubic-bezier(.22,.68,0,1.2) both;
}
.card-inner{padding:23px 24px}
.card-title{font-size:18px;font-weight:900;color:#20213b;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.card-title-icon{
    width:38px;height:38px;border-radius:12px;background:#eef2ff;color:var(--primary);
    display:grid;place-items:center;font-size:14px;font-weight:900;flex-shrink:0;
}
.provider-row{display:flex;align-items:center;gap:14px}
.avatar{
    width:62px;height:62px;border-radius:20px;background:linear-gradient(135deg,#292b4a,#353b69);
    color:#fff;display:grid;place-items:center;font-size:22px;font-weight:900;overflow:hidden;flex-shrink:0;
}
.avatar img{width:100%;height:100%;object-fit:cover}
.provider-name{font-size:18px;font-weight:900;color:#25263e}
.provider-type-pill{
    margin-top:6px;display:inline-flex;align-items:center;background:#eef2ff;color:var(--primary);
    border:1px solid rgba(100,112,210,.2);padding:6px 11px;border-radius:999px;font-size:12px;font-weight:900;
}
.details-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.detail-cell{
    background:#f8f9fd;border:1px solid var(--line);border-radius:18px;padding:14px;
    transition:transform .2s ease,box-shadow .2s ease;
}
.detail-cell:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(64,64,102,.07)}
.detail-label{font-size:11px;font-weight:900;color:#8b91a6;text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px}
.detail-value{font-size:15px;font-weight:900;color:#30324c;line-height:1.5}
.payment-row{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.amount-big{font-size:36px;font-weight:900;color:#20213b;letter-spacing:-.6px}
.method-pill{
    display:inline-flex;align-items:center;padding:10px 14px;border-radius:999px;background:#eef2ff;
    border:1px solid rgba(100,112,210,.2);color:var(--primary);font-weight:900;font-size:13px;
}
.timeline{display:flex;flex-direction:column;gap:0}
.tl-step{display:flex;gap:14px;align-items:flex-start;padding-bottom:18px;position:relative}
.tl-step:not(:last-child):before{content:"";position:absolute;left:17px;top:34px;width:2px;bottom:0;background:rgba(100,112,210,.18)}
.tl-dot{
    width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-size:13px;
    font-weight:900;flex-shrink:0;border:2px solid transparent;
}
.tl-dot.done{background:var(--success-bg);border-color:rgba(22,134,83,.3);color:var(--success)}
.tl-dot.active{background:#eef2ff;border-color:var(--primary-light);color:var(--primary);animation:pulse-dot 1.8s ease-in-out infinite}
.tl-dot.waiting{background:#f1f5f9;border-color:#e2e8f0;color:#94a3b8}
@keyframes pulse-dot{0%,100%{box-shadow:0 0 0 0 rgba(100,112,210,.32)}50%{box-shadow:0 0 0 8px rgba(100,112,210,0)}}
.tl-content{padding-top:6px}
.tl-title{font-size:15px;font-weight:900;color:#30324c}
.tl-sub{font-size:13px;font-weight:700;color:#8b91a6;margin-top:2px}
.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
.btn-primary,.btn-secondary{
    display:inline-flex;align-items:center;justify-content:center;padding:14px 22px;border-radius:18px;
    font-weight:900;font-size:15px;text-decoration:none;border:none;cursor:pointer;transition:.2s ease;flex:1;min-width:140px;
}
.btn-primary{background:linear-gradient(135deg,#404066,#6d73c8);color:#fff;box-shadow:0 16px 36px rgba(53,59,105,.22)}
.btn-secondary{background:#fff;color:#353b69;border:1px solid rgba(100,112,210,.22);box-shadow:var(--shadow-soft)}
.btn-primary:hover,.btn-secondary:hover{transform:translateY(-2px)}
.poll-bar{display:flex;align-items:center;gap:8px;font-size:12px;color:#94a3b8;font-weight:800;margin-top:14px;justify-content:center}
.poll-dot{width:6px;height:6px;border-radius:50%;background:var(--primary-light);animation:blink 1.2s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
.celebrate{
    background:#fff;border:1px solid rgba(64,64,102,.08);border-radius:28px;box-shadow:var(--shadow);
    padding:24px;text-align:center;margin-bottom:18px;display:none;
}
.celebrate.show{display:block}
.celebrate-title{font-size:22px;font-weight:900;color:#12643e}
.celebrate-sub{color:#4d8064;font-size:14px;font-weight:700;margin-top:6px}
.rate-card{margin-bottom:18px;display:none}
.rate-card.show{display:block}
.rating-wrap{background:#fff;border:1px solid rgba(64,64,102,.08);border-radius:28px;box-shadow:var(--shadow);padding:22px 24px}
.rating-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.rating-title{font-size:18px;font-weight:900;color:#20213b}
.rating-subtitle{color:#8b91a6;font-size:13px;font-weight:700;margin-top:3px}
.rating-chip{padding:8px 12px;border-radius:999px;background:#eefbf4;color:#12643e;border:1px solid rgba(22,134,83,.18);font-weight:900;font-size:12px}
.star-rating{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:8px;margin:12px 0 6px}
.star-rating input{display:none}
.star-rating label{font-size:34px;line-height:1;cursor:pointer;color:#dddfea;transition:transform .16s ease,color .16s ease;user-select:none}
.star-rating label:hover,.star-rating label:hover ~ label{color:#f4b400;transform:translateY(-2px) scale(1.06)}
.star-rating input:checked ~ label{color:#f4b400}
.rating-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:10px}
.rating-note{color:#8b91a6;font-size:13px;font-weight:800}
.rate-btn{
    border:none;outline:none;cursor:pointer;border-radius:14px;padding:12px 20px;
    background:linear-gradient(135deg,#404066,#6d73c8);color:#fff;font-weight:900;font-size:14px;
    font-family:inherit;box-shadow:0 10px 24px rgba(53,59,105,.20);transition:.2s ease;
}
.rate-btn:hover{transform:translateY(-1px);box-shadow:0 14px 28px rgba(53,59,105,.26)}
.rating-stars-display{font-size:30px;letter-spacing:3px;color:#f4b400;margin-top:10px}
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:600px){
    .details-grid{grid-template-columns:1fr}
    .hero-title{font-size:24px}
    .actions{flex-direction:column}
    .btn-primary,.btn-secondary{flex:none;width:100%}
    .star-rating label{font-size:28px}
    .rating-actions{flex-direction:column;align-items:stretch}
    .rate-btn{width:100%}
}
</style>
</head>
<body>

<?php include '../general/nav_patient.php'; ?>

<main class="container">

    <!-- hero -->
    <div class="hero">
        <div class="hero-top">
            <div>
                <div class="hero-title"><?= h($serviceType) ?> Request Submitted</div>
            </div>
        </div>

        <div class="status-banner pending" id="statusBanner">
            <div class="pulse-ring" id="pulseRing"></div>
            <div>
                <div id="bannerTitle" style="font-size:16px;font-weight:900;">Waiting for a provider to accept</div>
                <div id="bannerSub" style="font-size:13px;opacity:.82;margin-top:3px;">Your request is live and visible to providers</div>
            </div>
        </div>
    </div>

    <!-- celebration (shown when completed) -->
    <div class="celebrate" id="celebrateCard">
        <div class="celebrate-title">Service Completed!</div>
        <div class="celebrate-sub">We hope everything went smoothly.</div>
    </div>

    <!-- rating (shown when completed) -->
    <div class="rate-card<?= ($status === 'completed') ? ' show' : '' ?>" id="ratingCard">
        <div class="rating-wrap">
            <div class="rating-head">
                <div>
                    <?php if (!$hasRating): ?>
                    <div class="rating-title">Rate your experience</div>
                    <div class="rating-subtitle">Your feedback helps us improve the quality of service.</div>
                    <?php else: ?>
                    <div class="rating-title">Your rating</div>
                    <div class="rating-subtitle">Thank you for your feedback!</div>
                    <?php endif; ?>
                </div>
                <div class="rating-chip">Completed</div>
            </div>

            <?php if (!$hasRating): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="submit_rating">
                <input type="hidden" name="booking_id" value="<?= (int)$booking_id ?>">
                <div class="star-rating">
                    <input type="radio" id="bstar5" name="rating" value="5" required>
                    <label for="bstar5" title="5 stars">★</label>
                    <input type="radio" id="bstar4" name="rating" value="4">
                    <label for="bstar4" title="4 stars">★</label>
                    <input type="radio" id="bstar3" name="rating" value="3">
                    <label for="bstar3" title="3 stars">★</label>
                    <input type="radio" id="bstar2" name="rating" value="2">
                    <label for="bstar2" title="2 stars">★</label>
                    <input type="radio" id="bstar1" name="rating" value="1">
                    <label for="bstar1" title="1 star">★</label>
                </div>
                <div class="rating-actions">
                    <div class="rating-note">Choose a star rating, then submit.</div>
                    <button type="submit" class="rate-btn">Submit Rating</button>
                </div>
            </form>
            <?php else: ?>
            <div class="rating-stars-display">
                <?= str_repeat('★', $ratingValue) . str_repeat('☆', max(0, 5 - $ratingValue)) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- provider -->
    <div class="card">
        <div class="card-inner">
            <div class="card-title">
                <div class="card-title-icon">P</div>
                Provider
            </div>
            <div class="provider-row">
                <div class="avatar" id="providerAvatar">
                    <?php if (!empty($booking['provider_photo'])): ?>
                        <img src="<?= h($booking['provider_photo']) ?>" alt="<?= h($providerName) ?>">
                    <?php else: ?>
                        <?= h($avatarText) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="provider-name" id="providerName">
                        <?= $booking['provider_id'] ? h($providerName) : 'Awaiting assignment...' ?>
                    </div>
                    <div class="provider-type-pill"><?= h($serviceType) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- booking details -->
    <div class="card">
        <div class="card-inner">
            <div class="card-title">
                <div class="card-title-icon">B</div>
                Booking Details
            </div>
            <div class="details-grid">
                <div class="detail-cell">
                    <div class="detail-label">Service</div>
                    <div class="detail-value"><?= h($serviceType) ?></div>
                </div>
                <?php if (!empty($booking['date'])): ?>
                <div class="detail-cell">
                    <div class="detail-label">Date</div>
                    <div class="detail-value"><?= h($booking['date']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['booking_time'])): ?>
                <div class="detail-cell">
                    <div class="detail-label">Time</div>
                    <div class="detail-value">
                        <?= h(substr((string)$booking['booking_time'], 0, 5)) ?>
                        <?= !empty($booking['service_time']) ? '— ' . h(substr((string)$booking['service_time'], 0, 5)) : '' ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['address'])): ?>
                <div class="detail-cell">
                    <div class="detail-label">Address</div>
                    <div class="detail-value"><?= h($booking['address']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['destination'])): ?>
                <div class="detail-cell">
                    <div class="detail-label">Destination</div>
                    <div class="detail-value"><?= h($booking['destination']) ?></div>
                </div>
                <?php endif; ?>
                <div class="detail-cell">
                    <div class="detail-label">Status</div>
                    <div class="detail-value" id="statusCell"><?= h(ucfirst($status)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- payment -->
    <div class="card">
        <div class="card-inner">
            <div class="card-title">
                <div class="card-title-icon">P</div>
                Payment
            </div>
            <div class="payment-row">
                <div>
                    <div style="font-size:12px;font-weight:900;color:#8b91a6;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Total Amount</div>
                    <div class="amount-big"><?= h(money($booking['payment_total'] ?? 0)) ?> EGP</div>
                </div>
                <div class="method-pill">
                    <?= $payMethod === 'cash' ? 'Cash' : ucfirst($payMethod ?: 'Pending') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- timeline -->
    <div class="card">
        <div class="card-inner">
            <div class="card-title">
                <div class="card-title-icon">N</div>
                What happens next
            </div>
            <div class="timeline" id="timeline">
                <?php
                $steps = [
                    ['icon' => '1', 'title' => 'Booking confirmed',    'sub' => 'Your request has been sent',              'key' => 'confirmed'],
                    ['icon' => '2', 'title' => 'Provider reviewing',   'sub' => 'A provider is looking at your request',   'key' => 'reviewing'],
                    ['icon' => '3', 'title' => 'Provider accepted',    'sub' => 'Your provider is on the way',             'key' => 'accepted'],
                    ['icon' => '4', 'title' => 'Service completed',    'sub' => 'You can rate your experience',            'key' => 'completed'],
                ];
                $activeKey = 'confirmed';
                if ($status === 'accepted' || $status === 'arrived' || $status === 'in_trip' || $status === 'in_session') $activeKey = 'accepted';
                if ($status === 'completed') $activeKey = 'completed';
                $order = ['confirmed','reviewing','accepted','completed'];
                $activeIdx = array_search($activeKey, $order);
                foreach ($steps as $i => $step):
                    $idx = array_search($step['key'], $order);
                    $cls = $idx < $activeIdx ? 'done' : ($idx === $activeIdx ? 'active' : 'waiting');
                ?>
                <div class="tl-step">
                    <div class="tl-dot <?= $cls ?>"><?= $step['icon'] ?></div>
                    <div class="tl-content">
                        <div class="tl-title"><?= h($step['title']) ?></div>
                        <div class="tl-sub"><?= h($step['sub']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- actions -->
    <div class="actions">
        <a class="btn-secondary" href="my_bookings.php">My Bookings</a>
    </div>

    <div class="poll-bar" id="pollBar">
        <div class="poll-dot"></div>
        <span id="pollText">Checking for updates every 5 seconds...</span>
    </div>

</main>

<?php include '../general/footer.php'; ?>

<script>
(function(){
    const bookingId  = <?= (int)$booking_id ?>;
    const isDriver   = <?= $isDriver ? 'true' : 'false' ?>;
    let pollInterval = 5000;
    let attempts     = 0;
    let lastStatus   = <?= json_encode($status) ?>;

    const statusBanner  = document.getElementById('statusBanner');
    const bannerTitle   = document.getElementById('bannerTitle');
    const bannerSub     = document.getElementById('bannerSub');
    const pulseRing     = document.getElementById('pulseRing');
    const statusCell    = document.getElementById('statusCell');
    const pollText      = document.getElementById('pollText');
    const pollBar       = document.getElementById('pollBar');
    const celebrateCard = document.getElementById('celebrateCard');
    const ratingCard    = document.getElementById('ratingCard');
    const providerName  = document.getElementById('providerName');

    const bannerConfig = {
        pending   : { cls:'pending',   title:'Waiting for a provider to accept',      sub:'Your request is live and visible to providers' },
        accepted  : { cls:'accepted',  title:'Provider accepted your request',        sub:'They are preparing to come to you' },
        arrived   : { cls:'accepted',  title:'Your provider has arrived',             sub:'They are waiting for you' },
        in_trip   : { cls:'accepted',  title:'You are on your way',                   sub:'Enjoy your trip' },
        in_session: { cls:'accepted',  title:'Session in progress',                   sub:'Your provider is with you' },
        completed : { cls:'completed', title:'Service completed',                    sub:'We hope everything went smoothly' },
        declined  : { cls:'declined',  title:'Request declined',                      sub:'The provider was unable to accept. Please try again.' },
        cancelled : { cls:'declined',  title:'Booking cancelled',                     sub:'This booking has been cancelled' },
    };

    function applyStatus(s) {
        const cfg = bannerConfig[s] || bannerConfig['pending'];
        statusBanner.className = 'status-banner ' + cfg.cls;
        bannerTitle.textContent = cfg.title;
        bannerSub.textContent   = cfg.sub;
        pulseRing.style.display = (s === 'pending') ? 'block' : 'none';
        statusCell.textContent  = s.charAt(0).toUpperCase() + s.slice(1).replace('_',' ');

        if (s === 'completed') {
            celebrateCard.classList.add('show');
            if (ratingCard) ratingCard.classList.add('show');
        }

    }

    function poll() {
        attempts++;
        fetch(`booking_status.php?booking_id=${bookingId}&action=poll`, { cache:'no-store' })
            .then(r => r.json())
            .then(data => {
                const s = (data.status || 'pending').toLowerCase().trim();

                if (s !== lastStatus) {
                    lastStatus = s;
                    applyStatus(s);
                }

                if (data.provider_name && data.provider_name.trim() !== '' && providerName) {
                    if (!providerName.textContent.includes(data.provider_name.trim())) {
                        providerName.textContent = data.provider_name.trim();
                    }
                }

                if (s === 'completed' || s === 'declined' || s === 'cancelled') {
                    pollBar.style.display = 'none';
                    return;
                }

                if (attempts > 24) pollInterval = 10000;
                pollText.textContent = 'Checking for updates…';
                setTimeout(poll, pollInterval);
            })
            .catch(() => {
                setTimeout(poll, pollInterval);
            });
    }

    applyStatus(lastStatus);
    setTimeout(poll, pollInterval);
})();
</script>

</body>
</html>
