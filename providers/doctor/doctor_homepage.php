<?php
session_start();
require __DIR__ . '/../../pgdb/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return number_format((float)$n, 2); }
function normalize_phone_for_tel(?string $phone): string {
    $phone = trim((string)$phone);
    if ($phone === '') return '';
    return preg_replace('/[^0-9\+]/', '', $phone);
}
function payment_method_safe($value): string {
    $v = strtolower(trim((string)$value));
    return in_array($v, ['cash', 'visa'], true) ? $v : 'cash';
}
function has_col(PDO $pdo, string $table, string $col): bool {
    $q = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name=:t AND column_name=:c LIMIT 1");
    $q->execute([':t'=>$table, ':c'=>$col]);
    return (bool)$q->fetchColumn();
}
function booking_status_label($status): string {
    $map = [
        'pending'=>'Waiting', 'accepted'=>'Confirmed', 'arrived'=>'Arrived',
        'in_trip'=>'In Progress', 'in_session'=>'In Session', 'completed'=>'Completed',
        'declined'=>'Declined', 'rejected'=>'Rejected', 'cancelled'=>'Cancelled'
    ];
    $s = strtolower(trim((string)$status));
    return $map[$s] ?? ucfirst($s ?: 'Pending');
}
function is_booking_expired(array $b): bool {
    if (strtolower(trim((string)($b['status'] ?? 'pending'))) !== 'pending') return false;
    $date = trim((string)($b['date'] ?? ''));
    if ($date === '') return false;
    $time = trim((string)($b['booking_time'] ?? ''));
    if ($time === '') $time = '23:59:59';
    try { return (new DateTime($date . ' ' . $time)) < new DateTime(); } catch(Exception $e) { return false; }
}
function status_badge_class($status): string {
    $s = strtolower(trim((string)$status));
    if ($s === 'in_trip') return 'in_session';
    if ($s === 'rejected') return 'declined';
    return in_array($s, ['pending','accepted','in_session','completed','declined','cancelled','cash','visa'], true) ? $s : 'pending';
}
function get_provider_id(): int {
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    if (!empty($_SESSION['provider_id'])) return (int)$_SESSION['provider_id'];
    if (!empty($_SESSION['driver_id'])) return (int)$_SESSION['driver_id'];
    return 0;
}
function get_provider_name(PDO $pdo, int $id, string $fallback): string {
    try {
        $stmt = $pdo->prepare('SELECT CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\')) FROM "user" WHERE user_id=:id LIMIT 1');
        $stmt->execute([':id'=>$id]);
        $name = trim((string)$stmt->fetchColumn());
        return $name !== '' ? $name : $fallback;
    } catch(Exception $e) { return $fallback; }
}
function first_initial(string $name): string {
    $name = trim($name);
    return $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : 'P';
}
function time_range_html($b): string {
    $from = !empty($b['booking_time']) ? substr((string)$b['booking_time'], 0, 5) : '-';
    $to = !empty($b['service_time']) ? substr((string)$b['service_time'], 0, 5) : '-';
    return h($from).' - '.h($to);
}
function patient_name_from_booking($b): string {
    $name = trim((string)($b['fullname'] ?? ''));
    if ($name !== '') return $name;
    $name = trim((string)($b['patient_name'] ?? ''));
    return $name !== '' ? $name : 'Patient';
}
function render_booking_card(array $b, array $actions = [], array $extraDetails = []): void {
    $pm = payment_method_safe($b['payment_method'] ?? 'cash');
    $status = strtolower(trim((string)($b['status'] ?? 'pending')));
    $isExpired = is_booking_expired($b);
    $statusClass = $isExpired ? 'expired' : status_badge_class($status);
    $statusLabel = $isExpired ? 'Expired' : booking_status_label($status);
    $patientName = patient_name_from_booking($b);
    $initial = first_initial($patientName);
    $phoneRaw = trim((string)(($b['phone'] ?? '') ?: ($b['patient_phone'] ?? '')));
    $phoneTel = normalize_phone_for_tel($phoneRaw);
    $email = trim((string)($b['email'] ?? ''));
    ?>
    <article class="booking-card">
        <div class="booking-top">
            <div class="patient-avatar"><?= h($initial) ?></div>
            <div class="booking-main">
                <div class="booking-title-row">
                    <div class="patient-name"><?= h($patientName) ?></div>
                    <div class="badges">
                        <span class="badge <?= h($pm) ?>"><?= h(strtoupper($pm)) ?></span>
                        <span class="badge <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
                    </div>
                </div>
                <div class="details-grid">
                    <div class="detail"><div class="detail-label">Date</div><div class="detail-value"><?= h($b['date'] ?? '-') ?></div></div>
                    <div class="detail"><div class="detail-label">Time</div><div class="detail-value"><?= time_range_html($b) ?></div></div>
                    <?php foreach ($extraDetails as $label => $value): ?>
                        <div class="detail"><div class="detail-label"><?= h($label) ?></div><div class="detail-value"><?= h($value) ?></div></div>
                    <?php endforeach; ?>
                    <div class="detail"><div class="detail-label">Fee</div><div class="detail-value"><?= h(money($b['payment_total'] ?? 0)) ?> EGP</div></div>
                </div>
                <div class="contact-row">
                    <?php if ($phoneTel !== ''): ?><a class="contact-link" href="tel:<?= h($phoneTel) ?>"><i class="fa-solid fa-phone"></i><?= h($phoneRaw) ?></a><?php endif; ?>
                    <?php if ($email !== ''): ?><a class="contact-link" href="mailto:<?= h($email) ?>"><i class="fa-regular fa-envelope"></i><?= h($email) ?></a><?php endif; ?>
                </div>
                <?php if ($actions): ?>
                <div class="action-row">
                    <?php foreach ($actions as $a): ?>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="action" value="<?= h($a['action']) ?>">
                            <input type="hidden" name="booking_id" value="<?= h($b['booking_id']) ?>">
                            <button class="btn <?= h($a['class']) ?>" type="submit"><i class="fa-solid <?= h($a['icon']) ?>"></i><?= h($a['label']) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

$_SESSION['provider_type'] = 'doctor';
$provider_id = get_provider_id();
if ($provider_id <= 0) { header("Location: ../../general/login.php"); exit; }
$provider_name = get_provider_name($pdo, $provider_id, 'Doctor');
$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'logout') { session_unset(); session_destroy(); header("Location: ../../general/login.php"); exit; }
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    if ($booking_id <= 0) { $_SESSION['flash_error'] = "Invalid booking."; header("Location: " . $_SERVER['PHP_SELF']); exit; }
    try {
        if ($action === 'accept') {
            $stmt = $pdo->prepare("UPDATE booking SET status='accepted' WHERE booking_id=:booking_id AND provider_id=:provider_id AND COALESCE(status,'pending')='pending'");
            $stmt->execute([':booking_id'=>$booking_id, ':provider_id'=>$provider_id]);
            $_SESSION['flash_success'] = $stmt->rowCount() ? "The booking was accepted successfully." : "Could not accept the booking.";
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }
        if ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE booking SET status='rejected' WHERE booking_id=:booking_id AND provider_id=:provider_id AND COALESCE(status,'pending')='pending'");
            $stmt->execute([':booking_id'=>$booking_id, ':provider_id'=>$provider_id]);
            $_SESSION['flash_success'] = $stmt->rowCount() ? "The booking was declined." : "Could not decline the booking.";
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }
        
        if ($action === 'arrived') {
            $stmt = $pdo->prepare("UPDATE booking SET status='arrived' WHERE booking_id=:booking_id AND provider_id=:provider_id AND status='accepted'");
            $stmt->execute([':booking_id'=>$booking_id, ':provider_id'=>$provider_id]);
            $_SESSION['flash_success'] = $stmt->rowCount() ? "Status updated successfully." : "Could not update the booking.";
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }

        if ($action === 'start') {
            $stmt = $pdo->prepare("UPDATE booking SET status='in_trip' WHERE booking_id=:booking_id AND provider_id=:provider_id AND status IN ('accepted','arrived')");
            $stmt->execute([':booking_id'=>$booking_id, ':provider_id'=>$provider_id]);
            $_SESSION['flash_success'] = $stmt->rowCount() ? "Started successfully." : "Could not start the booking.";
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }
        if ($action === 'complete') {
            $stmt = $pdo->prepare("UPDATE booking SET status='completed', payment_status='paid', payment_state='paid', paid_at=COALESCE(paid_at,NOW()), wallet_processed=TRUE WHERE booking_id=:booking_id AND provider_id=:provider_id AND status IN ('accepted','arrived','in_trip','in_session')");
            $stmt->execute([':booking_id'=>$booking_id, ':provider_id'=>$provider_id]);
            $_SESSION['flash_success'] = $stmt->rowCount() ? "Completed successfully." : "Could not complete the booking.";
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }
    } catch(Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); header("Location: " . $_SERVER['PHP_SELF']); exit; }
}

$serviceWhere = "";
if (True) { $serviceWhere = " AND (LOWER(COALESCE(service_type,'')) = 'doctor' OR service_type IS NULL) "; }
$pendingBookings = $activeBookings = $history = [];
$stats = ['pending_count'=>0,'active_count'=>0,'completed_count'=>0,'total_earned'=>0];
try {
    $stmtStats = $pdo->prepare("SELECT COUNT(*) FILTER (WHERE COALESCE(status,'pending')='pending') AS pending_count, COUNT(*) FILTER (WHERE status IN ('accepted','arrived','in_trip','in_session')) AS active_count, COUNT(*) FILTER (WHERE status='completed') AS completed_count, COALESCE(SUM(payment_total * 0.85) FILTER (WHERE status='completed'),0) AS total_earned FROM booking WHERE provider_id=:provider_id $serviceWhere");
    $stmtStats->execute([':provider_id'=>$provider_id]);
    $row = $stmtStats->fetch(PDO::FETCH_ASSOC); if($row) $stats = $row;
    $select = "booking_id, fullname, phone, email, address, destination, date, booking_time, service_time, service_type, payment_total, payment_method, payment_status, status";
    $stmtPending = $pdo->prepare("SELECT $select FROM booking WHERE provider_id=:provider_id AND COALESCE(status,'pending')='pending' $serviceWhere ORDER BY date ASC, booking_time ASC, booking_id DESC LIMIT 50");
    $stmtPending->execute([':provider_id'=>$provider_id]); $pendingBookings = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
    $stmtActive = $pdo->prepare("SELECT $select FROM booking WHERE provider_id=:provider_id AND status IN ('accepted','arrived','in_trip','in_session') $serviceWhere ORDER BY date ASC, booking_time ASC, booking_id DESC LIMIT 50");
    $stmtActive->execute([':provider_id'=>$provider_id]); $activeBookings = $stmtActive->fetchAll(PDO::FETCH_ASSOC);
    $stmtHistory = $pdo->prepare("SELECT $select FROM booking WHERE provider_id=:provider_id AND status='completed' $serviceWhere ORDER BY date DESC, booking_time DESC, booking_id DESC LIMIT 10");
    $stmtHistory->execute([':provider_id'=>$provider_id]); $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $error = "Error loading your bookings: " . $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Doctor Dashboard | Rafiq</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
    --primary:#404066;
    --primary-dark:#2B2C41;
    --primary-soft:#eef0ff;
    --bg:#f5f6fb;
    --card:#ffffff;
    --muted:#727793;
    --text:#23243a;
    --line:#e7e8f0;
    --success:#2d8a57;
    --success-bg:#eefaf3;
    --danger:#B53535;
    --danger-bg:#fff4f4;
    --warning:#8a6700;
    --warning-bg:#fff8e7;
    --blue:#3550c7;
    --blue-bg:#eef2ff;
    --shadow:0 18px 46px rgba(43,44,65,.08);
    --shadow-soft:0 10px 24px rgba(43,44,65,.055);
}

*{box-sizing:border-box}

body{
    margin:0;
    font-family:'Nunito',system-ui,-apple-system,Segoe UI,Arial,sans-serif;
    background:
        radial-gradient(circle at top left, rgba(64,64,102,.10), transparent 28%),
        linear-gradient(180deg,#fafbff 0%, var(--bg) 100%);
    color:var(--text);
}

.container{
    width:min(1180px, calc(100% - 32px));
    margin:0 auto;
}

.page-shell{
    padding:26px 0 46px;
}

.hero-card{
    background:
        radial-gradient(circle at 92% 0%, rgba(100,112,210,.14), transparent 30%),
        linear-gradient(135deg,#ffffff 0%, #f8f9ff 100%);
    border:1px solid var(--line);
    border-radius:32px;
    box-shadow:var(--shadow);
    padding:30px;
    overflow:hidden;
}

.hero-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:20px;
    flex-wrap:wrap;
}

.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 13px;
    border-radius:999px;
    background:var(--primary-soft);
    color:var(--primary);
    font-size:12px;
    font-weight:900;
    border:1px solid rgba(64,64,102,.12);
    margin-bottom:14px;
}

.hero-title{
    margin:0;
    font-size:38px;
    line-height:1.08;
    letter-spacing:-.8px;
    color:var(--primary-dark);
    font-weight:900;
}

.hero-title span{
    color:var(--primary);
}

.hero-sub{
    margin:12px 0 0;
    max-width:720px;
    color:var(--muted);
    font-size:15px;
    line-height:1.8;
    font-weight:700;
}

.hero-actions{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

.hero-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 13px;
    border-radius:999px;
    background:#fff;
    border:1px solid var(--line);
    color:var(--primary-dark);
    font-size:13px;
    font-weight:900;
    box-shadow:var(--shadow-soft);
}

.alerts{
    margin-top:18px;
    display:flex;
    flex-direction:column;
    gap:10px;
}

.alert{
    display:flex;
    gap:10px;
    align-items:flex-start;
    border-radius:16px;
    padding:13px 15px;
    font-size:14px;
    font-weight:800;
    border:1px solid var(--line);
}

.alert.ok{
    background:var(--success-bg);
    border-color:rgba(45,138,87,.18);
    color:#17643c;
}

.alert.bad{
    background:var(--danger-bg);
    border-color:rgba(181,53,53,.18);
    color:#8c2626;
}

.kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-top:20px;
}

.kpi{
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:var(--shadow-soft);
    padding:20px;
    min-height:132px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
}

.kpi-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}

.kpi-label{
    color:var(--muted);
    font-size:12px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.5px;
}

.kpi-icon{
    width:38px;
    height:38px;
    border-radius:14px;
    display:grid;
    place-items:center;
    background:var(--primary-soft);
    color:var(--primary);
}

.kpi-value{
    margin-top:12px;
    font-size:31px;
    line-height:1;
    color:var(--primary-dark);
    font-weight:900;
}

.kpi-note{
    margin-top:8px;
    color:var(--muted);
    font-size:12px;
    font-weight:700;
}

.dashboard-grid{
    display:grid;
    grid-template-columns:minmax(0,1.18fr) minmax(360px,.82fr);
    gap:20px;
    margin-top:20px;
    align-items:start;
}

.stack{
    display:flex;
    flex-direction:column;
    gap:20px;
}

.panel{
    background:#fff;
    border:1px solid var(--line);
    border-radius:28px;
    box-shadow:var(--shadow);
    overflow:hidden;
}

.panel-head{
    padding:22px 22px 16px;
    border-bottom:1px solid rgba(231,232,240,.75);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
}

.panel-title{
    margin:0;
    font-size:23px;
    color:var(--primary-dark);
    font-weight:900;
    letter-spacing:-.3px;
}

.panel-sub{
    margin:6px 0 0;
    color:var(--muted);
    font-size:14px;
    line-height:1.6;
    font-weight:700;
}

.count-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:38px;
    height:34px;
    padding:0 12px;
    border-radius:999px;
    background:var(--primary-soft);
    color:var(--primary);
    font-weight:900;
    font-size:13px;
}

.list{
    padding:16px;
    display:flex;
    flex-direction:column;
    gap:14px;
}

.booking-card{
    border:1px solid var(--line);
    border-radius:22px;
    background:
        linear-gradient(180deg,#ffffff 0%, #fbfcff 100%);
    padding:17px;
    transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}

.booking-card:hover{
    transform:translateY(-2px);
    box-shadow:var(--shadow-soft);
    border-color:rgba(64,64,102,.18);
}

.booking-top{
    display:flex;
    align-items:flex-start;
    gap:14px;
}

.patient-avatar{
    width:48px;
    height:48px;
    border-radius:16px;
    display:grid;
    place-items:center;
    flex-shrink:0;
    background:linear-gradient(135deg,#404066,#6470d2);
    color:#fff;
    font-size:16px;
    font-weight:900;
    box-shadow:0 12px 22px rgba(64,64,102,.18);
}

.booking-main{
    flex:1;
    min-width:0;
}

.booking-title-row{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
}

.patient-name{
    font-size:17px;
    font-weight:900;
    color:var(--primary-dark);
    word-break:break-word;
}

.badges{
    display:flex;
    gap:7px;
    flex-wrap:wrap;
}

.badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    justify-content:center;
    padding:7px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    border:1px solid var(--line);
    background:#fff;
    color:var(--primary-dark);
    white-space:nowrap;
}

.badge.pending{background:var(--warning-bg);color:var(--warning);border-color:#f0ddb1}
.badge.accepted{background:var(--success-bg);color:#16663d;border-color:#cfe9d9}
.badge.in_session{background:var(--blue-bg);color:var(--blue);border-color:#d9e0ff}
.badge.completed{background:var(--success-bg);color:#16663d;border-color:#cfe9d9}
.badge.declined{background:var(--danger-bg);color:#8c2626;border-color:#efcaca}
.badge.expired{background:#f3f4f6;color:#4b5563;border-color:rgba(107,114,128,.24)}
.badge.cash{background:#eefaf3;color:#146c43;border-color:#d2ecdb}
.badge.visa{background:#eef3ff;color:#3550c7;border-color:#d9e2ff}

.details-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
    margin-top:14px;
}

.detail{
    border:1px solid rgba(231,232,240,.95);
    background:#f9faff;
    border-radius:16px;
    padding:11px 12px;
    min-width:0;
}

.detail-label{
    color:#8b90aa;
    font-size:11px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.45px;
    margin-bottom:4px;
}

.detail-value{
    color:#30324c;
    font-size:13px;
    font-weight:900;
    line-height:1.45;
    word-break:break-word;
}

.contact-row,
.action-row{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
    margin-top:14px;
}

.contact-link{
    display:inline-flex;
    align-items:center;
    gap:7px;
    min-height:38px;
    padding:0 12px;
    border-radius:999px;
    background:#f3f5fb;
    border:1px solid #dfe4f2;
    color:#3e4a74;
    text-decoration:none;
    font-size:12px;
    font-weight:900;
}

.contact-link:hover{
    background:#edf0f8;
}

.btn{
    min-height:42px;
    padding:0 16px;
    border:none;
    border-radius:14px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    text-decoration:none;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
    transition:transform .18s ease, background .18s ease, box-shadow .18s ease;
    font-family:inherit;
}

.btn:hover{
    transform:translateY(-1px);
}

.btn.primary{
    background:var(--primary);
    color:#fff;
    box-shadow:0 12px 22px rgba(64,64,102,.16);
}

.btn.primary:hover{
    background:var(--primary-dark);
}

.btn.danger{
    background:var(--danger-bg);
    color:var(--danger);
    border:1px solid #efcdcd;
}

.btn.danger:hover{
    background:#ffeaea;
}

.btn.neutral{
    background:#f4f5fa;
    color:var(--primary-dark);
    border:1px solid #e1e4ef;
}

.btn.neutral:hover{
    background:#eceff7;
}

.empty{
    padding:22px;
    border:1px dashed #d9dce8;
    border-radius:20px;
    background:#fbfcff;
    color:var(--muted);
    font-size:14px;
    font-weight:800;
    line-height:1.6;
    text-align:center;
}

.compact-card .list{
    padding:14px;
}

.compact-card .booking-card{
    padding:15px;
}

.compact-card .details-grid{
    grid-template-columns:1fr;
}

.section-divider{
    height:1px;
    background:var(--line);
    margin:14px 0 0;
}

@media (max-width:1080px){
    .dashboard-grid{grid-template-columns:1fr}
}

@media (max-width:860px){
    .kpi-grid{grid-template-columns:repeat(2,1fr)}
    .hero-title{font-size:32px}
    .hero-card{padding:24px}
}

@media (max-width:620px){
    .container{width:min(100% - 20px, 1180px)}
    .page-shell{padding-top:18px}
    .kpi-grid{grid-template-columns:1fr}
    .details-grid{grid-template-columns:1fr}
    .panel-head{padding:18px 18px 14px}
    .list{padding:12px}
    .booking-top{align-items:flex-start}
    .patient-avatar{width:42px;height:42px;border-radius:14px}
    .hero-title{font-size:28px}
    .btn{width:100%}
    .action-row form{width:100%}
    .action-row form .btn{width:100%}
}
</style>
</head>
<body>
<?php include '../../general/nav_prov.php'; ?>
<main class="container page-shell">
    <section class="hero-card">
        <div class="hero-top">
            <div>
                <h1 class="hero-title">Welcome back, <span><?= h($provider_name) ?></span></h1>
                <p class="hero-sub">Review new requests, manage confirmed visits, and keep track of completed work.</p>
            </div>
            <div class="hero-actions"><div class="hero-chip"><i class="fa-solid fa-clock"></i> New requests: <?= h((int)($stats['pending_count'] ?? 0)) ?></div></div>
        </div>
        <div class="alerts">
            <?php if ($success): ?><div class="alert ok"><i class="fa-solid fa-circle-check"></i><span><?= h($success) ?></span></div><?php endif; ?>
            <?php if ($error): ?><div class="alert bad"><i class="fa-solid fa-circle-exclamation"></i><span><?= h($error) ?></span></div><?php endif; ?>
        </div>
    </section>
    <section class="kpi-grid" aria-label="Dashboard summary">
        <div class="kpi"><div class="kpi-top"><div class="kpi-label">New Requests</div><div class="kpi-icon"><i class="fa-regular fa-bell"></i></div></div><div class="kpi-value"><?= h((int)($stats['pending_count'] ?? 0)) ?></div><div class="kpi-note">Waiting for your response</div></div>
        <div class="kpi"><div class="kpi-top"><div class="kpi-label">Upcoming</div><div class="kpi-icon"><i class="fa-regular fa-calendar-check"></i></div></div><div class="kpi-value"><?= h((int)($stats['active_count'] ?? 0)) ?></div><div class="kpi-note">Confirmed or in progress</div></div>
        <div class="kpi"><div class="kpi-top"><div class="kpi-label">Completed</div><div class="kpi-icon"><i class="fa-solid fa-check"></i></div></div><div class="kpi-value"><?= h((int)($stats['completed_count'] ?? 0)) ?></div><div class="kpi-note">Finished bookings</div></div>
        <div class="kpi"><div class="kpi-top"><div class="kpi-label">Total Earnings</div><div class="kpi-icon"><i class="fa-solid fa-wallet"></i></div></div><div class="kpi-value"><?= h(money($stats['total_earned'] ?? 0)) ?></div><div class="kpi-note">EGP from completed bookings</div></div>
    </section>
    <section class="dashboard-grid">
        <div class="panel">
            <div class="panel-head"><div><h2 class="panel-title">New Booking Requests</h2><p class="panel-sub">Patients waiting for your confirmation.</p></div><div class="count-pill"><?= h(count($pendingBookings)) ?></div></div>
            <div class="list">
                <?php if (!$pendingBookings): ?><div class="empty">There are no new booking requests at the moment.</div><?php else: ?>
                    <?php foreach ($pendingBookings as $b): ?>
                        <?php render_booking_card($b, [ ['action'=>'accept','class'=>'primary','icon'=>'fa-check','label'=>'Accept'], ['action'=>'reject','class'=>'danger','icon'=>'fa-xmark','label'=>'Decline'] ], ['Service'=>($b['service_type'] ?? 'Doctor')]); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="stack">
            <div class="panel compact-card">
                <div class="panel-head"><div><h2 class="panel-title">Current Visits</h2><p class="panel-sub">Confirmed bookings and work in progress.</p></div><div class="count-pill"><?= h(count($activeBookings)) ?></div></div>
                <div class="list">
                    <?php if (!$activeBookings): ?><div class="empty">You do not have any active bookings right now.</div><?php else: ?>
                        <?php foreach ($activeBookings as $b): ?>
                            <?php
                                $actions = [];
                                if (in_array(($b['status'] ?? ''), ['accepted'], true) && true) $actions[] = ['action'=>'arrived','class'=>'neutral','icon'=>'fa-location-dot','label'=>'Arrived'];
                                if (in_array(($b['status'] ?? ''), ['accepted','arrived'], true)) $actions[] = ['action'=>'start','class'=>'primary','icon'=>'fa-play','label'=>'Start Visit'];
                                if (in_array(($b['status'] ?? ''), ['accepted','arrived','in_trip','in_session'], true)) $actions[] = ['action'=>'complete','class'=>'neutral','icon'=>'fa-check-double','label'=>'Mark as Completed'];
                                render_booking_card($b, $actions);
                            ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel compact-card">
                <div class="panel-head"><div><h2 class="panel-title">Completed Visits</h2><p class="panel-sub">Most recent finished bookings.</p></div><div class="count-pill"><?= h(count($history)) ?></div></div>
                <div class="list">
                    <?php if (!$history): ?><div class="empty">No completed bookings yet.</div><?php else: ?>
                        <?php foreach ($history as $b): ?><?php render_booking_card($b, []); ?><?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include '../../general/footer.php'; ?>
</body>
</html>
