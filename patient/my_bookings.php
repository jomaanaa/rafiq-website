<?php
session_start();
require __DIR__ . '/../pgdb/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return number_format((float)$n, 2); }

function get_session_patient_id(): int {
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    if (!empty($_SESSION['patient_id'])) return (int)$_SESSION['patient_id'];
    if (!empty($_SESSION['ID'])) return (int)$_SESSION['ID'];
    return 0;
}

function booking_status_label($status): string {
    $map = [
        'pending'    => 'Pending',
        'accepted'   => 'Accepted',
        'arrived'    => 'Arrived',
        'in_trip'    => 'In Progress',
        'in_session' => 'In Session',
        'completed'  => 'Completed',
        'declined'   => 'Declined',
        'cancelled'  => 'Cancelled',
    ];
    $s = strtolower(trim((string)$status));
    return $map[$s] ?? ucfirst($s);
}

function payment_method_label($method): string {
    $m = strtolower(trim((string)$method));
    if ($m === 'visa') return 'Visa';
    if ($m === 'cash') return 'Cash';
    return ucfirst($m);
}


function is_booking_expired(array $booking): bool {
    $status = strtolower(trim((string)($booking['status'] ?? 'pending')));

    if ($status !== 'pending') {
        return false;
    }

    $date = trim((string)($booking['date'] ?? ''));

    if ($date === '') {
        return false;
    }

    $time = trim((string)($booking['booking_time'] ?? ''));

    if ($time === '') {
        $time = trim((string)($booking['service_time'] ?? ''));
    }

    if ($time === '') {
        $time = '23:59:59';
    }

    try {
        $bookingDateTime = new DateTime($date . ' ' . $time);
        $now = new DateTime();

        return $bookingDateTime < $now;
    } catch (Exception $e) {
        return false;
    }
}

function display_booking_status(array $booking): string {
    if (is_booking_expired($booking)) {
        return 'Expired';
    }

    return booking_status_label($booking['status'] ?? 'pending');
}

function display_booking_status_class(array $booking): string {
    if (is_booking_expired($booking)) {
        return 'expired';
    }

    return strtolower(trim((string)($booking['status'] ?? 'pending')));
}

function provider_type_label($row): string {
    if (!empty($row['doctor_id'])) return 'Doctor';
    if (!empty($row['interpreter_id'])) return 'Interpreter';
    if (!empty($row['caregiver_id'])) return 'Caregiver';
    if (!empty($row['driver_id'])) return 'Driver';
    if (!empty($row['service_type'])) return (string)$row['service_type'];
    return 'Provider';
}

$patient_id = get_session_patient_id();

if ($patient_id <= 0) {
    header("Location: ../general/login.php");
    exit;
}

$bookings = [];
$error = "";
$success = "";

/* ---------------- HANDLE RATING SUBMIT ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

    if ($booking_id <= 0) {
        $error = "Invalid booking.";
    } elseif ($rating < 1 || $rating > 5) {
        $error = "Please select a valid rating from 1 to 5.";
    } else {
        try {
            $checkStmt = $pdo->prepare("
                SELECT booking_id, status, rating
                FROM booking
                WHERE booking_id = :booking_id
                  AND patient_id = :patient_id
                LIMIT 1
            ");
            $checkStmt->execute([
                ':booking_id' => $booking_id,
                ':patient_id' => $patient_id
            ]);
            $bookingRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$bookingRow) {
                $error = "Booking not found.";
            } else {
                $status = strtolower(trim((string)($bookingRow['status'] ?? '')));

                if ($status !== 'completed') {
                    $error = "You can only rate completed bookings.";
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE booking
                        SET rating = :rating
                        WHERE booking_id = :booking_id
                          AND patient_id = :patient_id
                          AND status = 'completed'
                    ");
                    $updateStmt->execute([
                        ':rating' => $rating,
                        ':booking_id' => $booking_id,
                        ':patient_id' => $patient_id
                    ]);

                    $success = "Your rating has been saved successfully.";
                }
            }
        } catch (Exception $e) {
            $error = "Could not save your rating: " . $e->getMessage();
        }
    }
}

/* ---------------- PAGINATION ---------------- */
$perPage = 4;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$totalBookings = 0;
$totalPages = 1;
$offset = 0;

try {
    $countSql = '
        SELECT COUNT(*) 
        FROM booking
        WHERE patient_id = :patient_id
    ';
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':patient_id' => $patient_id]);
    $totalBookings = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalBookings / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    $sql = '
        SELECT
            b.booking_id,
            b.date,
            b.booking_time,
            b.service_time,
            b.start_at,
            b.end_at,
            b.payment_total,
            b.payment_method,
            b.payment_status,
            b.payment_state,
            b.status,
            b.service_type,
            b.address,
            b.destination,
            b.location_address,
            b.fullname,
            b.phone,
            b.email,
            b.provider_id,
            b.rating,

            CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\')) AS provider_name,
            u.email AS provider_email,
            u.photo AS provider_photo,

            d.user_id  AS doctor_id,
            i.user_id  AS interpreter_id,
            c.user_id  AS caregiver_id,
            dr.user_id AS driver_id

        FROM booking b
        LEFT JOIN provider p ON p.user_id = b.provider_id
        LEFT JOIN "user" u ON u.user_id = p.user_id
        LEFT JOIN doctor d ON d.user_id = p.user_id
        LEFT JOIN interpreter i ON i.user_id = p.user_id
        LEFT JOIN caregiver c ON c.user_id = p.user_id
        LEFT JOIN driver dr ON dr.user_id = p.user_id
        WHERE b.patient_id = :patient_id
        ORDER BY b.date DESC, b.booking_time DESC, b.booking_id DESC
        LIMIT :limit OFFSET :offset
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Could not load your bookings: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Bookings - Rafiq</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#404066;
    --primary-2:#5a5a88;
    --dark:#2B2C41;
    --white:#FFFFFF;
    --red:#B53535;
    --green:#2d8a57;
    --gold:#f4b400;
    --line:#e7e8f0;
    --muted:#6f738a;
    --bg:#f5f6fb;
    --shadow:0 18px 40px rgba(43,44,65,0.08);
    --shadow-sm:0 10px 24px rgba(43,44,65,0.05);
}

*{box-sizing:border-box}

body{
    margin:0;
    font-family:"Nunito",system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    background:
      radial-gradient(circle at top left, rgba(64,64,102,.08), transparent 24%),
      linear-gradient(180deg,#fafbff 0%, #f4f6fb 100%);
    color:var(--dark);
}

.container{
    width:min(1180px, calc(100% - 32px));
    margin:0 auto;
}

.page-head{
    padding:28px 0 18px;
}

.head-box{
    background:linear-gradient(135deg,#ffffff 0%, #fafaff 65%, #f4f4fb 100%);
    border:1px solid var(--line);
    border-radius:30px;
    box-shadow:var(--shadow);
    padding:28px;
}

.head-box h1{
    margin:0;
    font-size:34px;
    color:var(--dark);
    line-height:1.1;
}

.head-box p{
    margin:10px 0 0;
    color:var(--muted);
    font-weight:800;
}

.alert{
    margin-top:16px;
    border-radius:16px;
    padding:14px 16px;
    font-weight:800;
}

.alert.error{
    background:#fff5f5;
    color:#8c2626;
    border:1px solid rgba(181,53,53,.18);
}

.alert.success{
    background:#eefaf3;
    color:#16663d;
    border:1px solid rgba(45,138,87,.18);
}

.list{
    display:flex;
    flex-direction:column;
    gap:18px;
    padding-bottom:28px;
}

.booking-card{
    background:var(--white);
    border:1px solid var(--line);
    border-radius:26px;
    box-shadow:var(--shadow);
    padding:20px;
    overflow:hidden;
}

.top-row{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
}

.provider-block{
    display:flex;
    align-items:center;
    gap:14px;
}

.avatar{
    width:62px;
    height:62px;
    border-radius:18px;
    background:linear-gradient(135deg,var(--primary),#5a5a88);
    color:#fff;
    display:grid;
    place-items:center;
    font-weight:900;
    font-size:20px;
    overflow:hidden;
    flex-shrink:0;
    box-shadow:0 10px 24px rgba(64,64,102,.22);
}

.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.provider-name{
    font-size:19px;
    font-weight:900;
    color:var(--dark);
}

.provider-role{
    margin-top:4px;
    color:var(--muted);
    font-weight:800;
    font-size:14px;
}

.badges{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.badge{
    font-size:12px;
    font-weight:900;
    padding:8px 11px;
    border-radius:999px;
    border:1px solid var(--line);
    background:#fff;
    color:var(--dark);
}

.badge.pending{
    border-color:rgba(212,155,0,0.28);
    color:#8a6700;
    background:#fff9e9;
}
.badge.accepted{
    border-color:rgba(45,138,87,0.28);
    color:#16663d;
    background:#eef9f2;
}
.badge.arrived{
    border-color:rgba(79,99,216,0.24);
    color:#2f4bc0;
    background:#eef3ff;
}
.badge.in_trip,
.badge.in_session{
    border-color:rgba(79,99,216,0.24);
    color:#2f4bc0;
    background:#eef3ff;
}
.badge.completed{
    border-color:rgba(45,138,87,0.28);
    color:#16663d;
    background:#eef9f2;
}
.badge.declined,
.badge.cancelled{
    border-color:rgba(181,53,53,0.22);
    color:#8c2626;
    background:#fff4f4;
}

.badge.expired{
    border-color:rgba(107,114,128,0.24);
    color:#4b5563;
    background:#f3f4f6;
}
.badge.cash{
    border-color:rgba(25,135,84,0.22);
    color:#146c43;
    background:#eefaf3;
}
.badge.visa{
    border-color:rgba(79,99,216,0.20);
    color:#3550c7;
    background:#eef3ff;
}

.meta-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px 18px;
    margin-top:18px;
}

.meta-box{
    background:#f8f9fd;
    border:1px solid #eceef5;
    border-radius:18px;
    padding:14px;
    transition:.25s ease;
}

.meta-box:hover{
    transform:translateY(-2px);
    box-shadow:var(--shadow-sm);
}

.meta-label{
    font-size:12px;
    font-weight:900;
    color:var(--muted);
    text-transform:uppercase;
    margin-bottom:6px;
}

.meta-value{
    font-size:15px;
    font-weight:800;
    color:var(--dark);
    line-height:1.5;
}

/* ---------- SIMPLE PREMIUM RATING UI ---------- */
.rating-wrap{
    margin-top:16px;
    border-radius:18px;
    border:1px solid rgba(64,64,102,.08);
    background:linear-gradient(135deg,#fffefb 0%, #ffffff 100%);
    padding:14px 16px;
    box-shadow:var(--shadow-sm);
}

.rating-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:10px;
}

.rating-title{
    font-size:16px;
    font-weight:900;
    color:var(--dark);
}

.rating-subtitle{
    margin-top:4px;
    color:var(--muted);
    font-weight:800;
    font-size:13px;
}

.rating-chip{
    padding:8px 12px;
    border-radius:999px;
    background:#fff7db;
    color:#946200;
    border:1px solid rgba(244,180,0,.18);
    font-weight:900;
    font-size:12px;
}

.star-rating{
    display:flex;
    flex-direction:row-reverse;
    justify-content:flex-end;
    gap:8px;
    margin:12px 0 6px;
}

.star-rating input{
    display:none;
}

.star-rating label{
    font-size:32px;
    line-height:1;
    cursor:pointer;
    color:#dddfea;
    transition:transform .16s ease, color .16s ease;
    user-select:none;
}

.star-rating label:hover,
.star-rating label:hover ~ label{
    color:#f4b400;
    transform:translateY(-2px) scale(1.04);
}

.star-rating input:checked ~ label{
    color:#f4b400;
}

.rating-actions{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-top:8px;
}

.rating-note{
    color:var(--muted);
    font-size:13px;
    font-weight:800;
}

.rate-btn{
    border:none;
    outline:none;
    cursor:pointer;
    border-radius:12px;
    padding:10px 16px;
    background:linear-gradient(135deg,var(--primary) 0%, var(--primary-2) 100%);
    color:#fff;
    font-weight:900;
    font-size:13px;
    box-shadow:0 10px 20px rgba(64,64,102,.16);
    transition:.2s ease;
}

.rate-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 14px 24px rgba(64,64,102,.20);
}

.rating-display{
    margin-top:14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    padding-top:12px;
    border-top:1px solid #eef0f6;
}

.rating-stars{
    font-size:20px;
    letter-spacing:2px;
    color:#f4b400;
    font-weight:900;
}

.rating-inline-actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.edit-rating-btn{
    border:none;
    outline:none;
    cursor:pointer;
    border-radius:10px;
    padding:8px 12px;
    background:#f3f5fb;
    color:var(--primary);
    font-weight:900;
    font-size:12px;
    border:1px solid #e4e8f3;
    transition:.2s ease;
}

.edit-rating-btn:hover{
    background:#e9edf8;
    transform:translateY(-1px);
}

.rating-edit-box{
    display:none;
    margin-top:12px;
    padding-top:12px;
    border-top:1px dashed #e7e8f0;
}

.rating-edit-box.show{
    display:block;
}

.empty{
    background:#fff;
    border:1px dashed var(--line);
    border-radius:22px;
    padding:20px;
    color:var(--muted);
    font-weight:800;
    box-shadow:var(--shadow-sm);
}

.pagination{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:10px;
    padding:0 0 40px;
    flex-wrap:wrap;
}

.pagination a,
.pagination span{
    min-width:42px;
    height:42px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:0 14px;
    border-radius:12px;
    text-decoration:none;
    font-weight:900;
    border:1px solid var(--line);
    background:#fff;
    color:var(--dark);
    box-shadow:var(--shadow-sm);
}

.pagination a:hover{
    background:#f4f6fb;
}

.pagination .active{
    background:var(--primary);
    color:#fff;
    border-color:var(--primary);
}

@media (max-width: 860px){
    .meta-grid{ grid-template-columns:1fr; }
    .star-rating label{ font-size:30px; }
    .rating-actions{ flex-direction:column; align-items:stretch; }
    .rate-btn{ width:100%; }
}

/* ── ANIMATIONS ── */
.booking-card {
    transition: transform 0.22s ease, box-shadow 0.22s ease;
    animation: slideUp 0.45s cubic-bezier(.22,.68,0,1.2) both;
}
.booking-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 28px 54px rgba(43,44,65,0.11);
}
@keyframes slideUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
}
.booking-card:nth-child(1){ animation-delay:.04s }
.booking-card:nth-child(2){ animation-delay:.10s }
.booking-card:nth-child(3){ animation-delay:.16s }
.booking-card:nth-child(4){ animation-delay:.22s }

.head-box {
    animation: slideUp 0.4s cubic-bezier(.22,.68,0,1.2) both;
}
.empty-state {
    text-align: center;
    padding: 56px 24px;
    background: #fff;
    border-radius: 26px;
    border: 1px dashed var(--line);
}
.empty-state .empty-icon { font-size: 52px; margin-bottom: 14px; }
.empty-state h3 { margin: 0 0 8px; font-size: 20px; color: var(--dark); }
.empty-state p  { margin: 0; color: var(--muted); font-size: 14px; }
</style>
</head>
<body>

<?php include '../general/nav_patient.php'; ?>

<main class="container">
    <section class="page-head">
        <div class="head-box">
            <h1>My Bookings</h1>
            <p>View all the services you booked, including completed and past bookings.</p>

            <?php if ($error): ?>
                <div class="alert error"><?= h($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success"><?= h($success) ?></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="list">
        <?php if (!$bookings): ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <h3>No bookings yet</h3>
                <p>You haven't made any bookings. Explore services from the homepage.</p>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $b): ?>
                <?php
                    $providerName = trim((string)($b['provider_name'] ?? ''));
                    if ($providerName === '') $providerName = 'Provider';

                    $providerType = provider_type_label($b);
                    $statusClass = display_booking_status_class($b);
                    $paymentClass = strtolower(trim((string)($b['payment_method'] ?? 'cash')));
                    $avatarText = mb_strtoupper(mb_substr($providerName, 0, 1));
                    $providerPhoto = trim((string)($b['provider_photo'] ?? ''));
                    $ratingValue = isset($b['rating']) && $b['rating'] !== null && $b['rating'] !== '' ? (int)$b['rating'] : 0;
                    $isCompleted = strtolower(trim((string)($b['status'] ?? ''))) === 'completed';
                    $hasRating = ($b['rating'] !== null && $b['rating'] !== '');
                ?>
                <div class="booking-card">
                    <div class="top-row">
                        <div class="provider-block">
                            <div class="avatar">
                                <?php if ($providerPhoto !== ''): ?>
                                    <img src="<?= h($providerPhoto) ?>" alt="<?= h($providerName) ?>">
                                <?php else: ?>
                                    <?= h($avatarText) ?>
                                <?php endif; ?>
                            </div>

                            <div>
                                <div class="provider-name"><?= h($providerName) ?></div>
                                <div class="provider-role"><?= h($providerType) ?></div>
                            </div>
                        </div>

                        <div class="badges">
                            <span class="badge <?= h($statusClass) ?>">
                                <?= h(display_booking_status($b)) ?>
                            </span>
                            <span class="badge <?= h($paymentClass) ?>">
                                <?= h(payment_method_label($b['payment_method'] ?? 'cash')) ?>
                            </span>
                        </div>
                    </div>

                    <div class="meta-grid">
                        <div class="meta-box">
                            <div class="meta-label">Date</div>
                            <div class="meta-value"><?= h($b['date'] ?? '-') ?></div>
                        </div>

                        <div class="meta-box">
                            <div class="meta-label">Time</div>
                            <div class="meta-value">
                                <?= h(!empty($b['booking_time']) ? substr((string)$b['booking_time'], 0, 5) : '-') ?>
                                —
                                <?= h(!empty($b['service_time']) ? substr((string)$b['service_time'], 0, 5) : '-') ?>
                            </div>
                        </div>

                        <div class="meta-box">
                            <div class="meta-label">Service</div>
                            <div class="meta-value"><?= h($b['service_type'] ?: $providerType) ?></div>
                        </div>

                        <div class="meta-box">
                            <div class="meta-label">Session Fee</div>
                            <div class="meta-value"><?= h(money($b['payment_total'] ?? 0)) ?> EGP</div>
                        </div>

                        <?php if (!empty($b['address'])): ?>
                            <div class="meta-box">
                                <div class="meta-label">Address</div>
                                <div class="meta-value"><?= h($b['address']) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($b['destination'])): ?>
                            <div class="meta-box">
                                <div class="meta-label">Destination</div>
                                <div class="meta-value"><?= h($b['destination']) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($b['location_address'])): ?>
                            <div class="meta-box">
                                <div class="meta-label">Location</div>
                                <div class="meta-value"><?= h($b['location_address']) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($b['payment_status'])): ?>
                            <div class="meta-box">
                                <div class="meta-label">Payment Status</div>
                                <div class="meta-value"><?= h($b['payment_status']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php
                        $isDriverBooking = strtolower(trim((string)($b['service_type'] ?? ''))) === 'driver';
                        $isExpired = is_booking_expired($b);
                        $isActive = !$isExpired && in_array(strtolower(trim((string)($b['status'] ?? ''))), ['pending','accepted','arrived','in_trip']);
                    ?>
                    <?php if ($isDriverBooking && ($isActive || $isCompleted)): ?>
                        <a href="ride_tracking.php?booking_id=<?= (int)$b['booking_id'] ?>"
                           style="display:flex;align-items:center;justify-content:center;gap:10px;
                                  width:100%;padding:14px;border-radius:16px;
                                  background:linear-gradient(135deg,#353b69,#6470d2);color:#fff;
                                  font-weight:800;font-size:14px;text-decoration:none;margin-bottom:12px;
                                  box-shadow:0 10px 24px rgba(53,59,105,.20);">
                            <?= $isActive ? '📍 Track Ride Live' : '📍 View Ride Map' ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($isCompleted && !$hasRating): ?>
                        <div class="rating-wrap">
                            <div class="rating-head">
                                <div>
                                    <div class="rating-title">Rate your experience</div>
                                    <div class="rating-subtitle">Your feedback helps us improve the quality of service.</div>
                                </div>
                                <div class="rating-chip">Completed Booking</div>
                            </div>

                            <form method="POST" action="">
                                <input type="hidden" name="action" value="submit_rating">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">

                                <div class="star-rating">
                                    <input type="radio" id="star5-<?= (int)$b['booking_id'] ?>" name="rating" value="5" required>
                                    <label for="star5-<?= (int)$b['booking_id'] ?>" title="5 stars">★</label>

                                    <input type="radio" id="star4-<?= (int)$b['booking_id'] ?>" name="rating" value="4">
                                    <label for="star4-<?= (int)$b['booking_id'] ?>" title="4 stars">★</label>

                                    <input type="radio" id="star3-<?= (int)$b['booking_id'] ?>" name="rating" value="3">
                                    <label for="star3-<?= (int)$b['booking_id'] ?>" title="3 stars">★</label>

                                    <input type="radio" id="star2-<?= (int)$b['booking_id'] ?>" name="rating" value="2">
                                    <label for="star2-<?= (int)$b['booking_id'] ?>" title="2 stars">★</label>

                                    <input type="radio" id="star1-<?= (int)$b['booking_id'] ?>" name="rating" value="1">
                                    <label for="star1-<?= (int)$b['booking_id'] ?>" title="1 star">★</label>
                                </div>

                                <div class="rating-actions">
                                    <div class="rating-note">Choose a star rating, then submit it.</div>
                                    <button type="submit" class="rate-btn">Submit Rating</button>
                                </div>
                            </form>
                        </div>

                    <?php elseif ($isCompleted && $hasRating): ?>
                        <div class="rating-wrap">
                            <div class="rating-head">
                                <div>
                                    <div class="rating-title">Your rating</div>
                                    <div class="rating-subtitle">You can edit your rating anytime.</div>
                                </div>
                                <div class="rating-chip">Completed Booking</div>
                            </div>

                            <div class="rating-display">
                                <div class="rating-stars" aria-label="<?= (int)$ratingValue ?> out of 5 stars">
                                    <?= str_repeat('★', (int)$ratingValue) . str_repeat('☆', max(0, 5 - (int)$ratingValue)) ?>
                                </div>

                                <div class="rating-inline-actions">
                                    <button
                                        type="button"
                                        class="edit-rating-btn"
                                        onclick="toggleRatingEdit('edit-box-<?= (int)$b['booking_id'] ?>', this)"
                                    >
                                        Edit
                                    </button>
                                </div>
                            </div>

                            <div class="rating-edit-box" id="edit-box-<?= (int)$b['booking_id'] ?>">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="submit_rating">
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">

                                    <div class="star-rating">
                                        <input type="radio" id="edit-star5-<?= (int)$b['booking_id'] ?>" name="rating" value="5" <?= $ratingValue === 5 ? 'checked' : '' ?> required>
                                        <label for="edit-star5-<?= (int)$b['booking_id'] ?>" title="5 stars">★</label>

                                        <input type="radio" id="edit-star4-<?= (int)$b['booking_id'] ?>" name="rating" value="4" <?= $ratingValue === 4 ? 'checked' : '' ?>>
                                        <label for="edit-star4-<?= (int)$b['booking_id'] ?>" title="4 stars">★</label>

                                        <input type="radio" id="edit-star3-<?= (int)$b['booking_id'] ?>" name="rating" value="3" <?= $ratingValue === 3 ? 'checked' : '' ?>>
                                        <label for="edit-star3-<?= (int)$b['booking_id'] ?>" title="3 stars">★</label>

                                        <input type="radio" id="edit-star2-<?= (int)$b['booking_id'] ?>" name="rating" value="2" <?= $ratingValue === 2 ? 'checked' : '' ?>>
                                        <label for="edit-star2-<?= (int)$b['booking_id'] ?>" title="2 stars">★</label>

                                        <input type="radio" id="edit-star1-<?= (int)$b['booking_id'] ?>" name="rating" value="1" <?= $ratingValue === 1 ? 'checked' : '' ?>>
                                        <label for="edit-star1-<?= (int)$b['booking_id'] ?>" title="1 star">★</label>
                                    </div>

                                    <div class="rating-actions">
                                        <div class="rating-note">Change your rating, then save it.</div>
                                        <button type="submit" class="rate-btn">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">&laquo;</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">&raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<?php include '../general/footer.php'; ?>

<script>
function toggleRatingEdit(id, btn){
    const box = document.getElementById(id);
    if (!box) return;

    box.classList.toggle('show');
    btn.textContent = box.classList.contains('show') ? 'Cancel' : 'Edit';
}
</script>

</body>
</html>