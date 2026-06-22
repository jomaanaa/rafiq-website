<?php
session_start();
require __DIR__ . '/../pgdb/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return number_format((float)$n, 2); }

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

function get_session_patient_id(): int {
    if (!empty($_SESSION['patient_id'])) return (int)$_SESSION['patient_id'];
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
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
    return ucfirst($m ?: 'Not selected');
}

function normalize_card_brand(string $number): string {
    $digits = preg_replace('/\D+/', '', $number);
    if (preg_match('/^4/', $digits)) return 'Visa';
    if (preg_match('/^(5[1-5]|2[2-7])/', $digits)) return 'Mastercard';
    if (preg_match('/^3[47]/', $digits)) return 'American Express';
    return 'Card';
}

$patient_id = get_session_patient_id();
if ($patient_id <= 0) {
    header("Location: ../general/login.php");
    exit;
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (int)($_POST['booking_id'] ?? 0);
if ($booking_id <= 0) {
    die("Invalid booking ID.");
}

$error = "";
$success = "";
$booking = null;

$providerJoinExpr = has_col($pdo, 'booking', 'driver_id')
    ? "COALESCE(b.provider_id, b.driver_id)"
    : "b.provider_id";

try {
    $stmt = $pdo->prepare("
        SELECT
            b.*,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS provider_name,
            u.photo AS provider_photo,
            CONCAT(COALESCE(pu.first_name, ''), ' ', COALESCE(pu.last_name, '')) AS patient_name,
            pp.phone AS patient_phone
        FROM booking b
        LEFT JOIN provider p ON p.user_id = $providerJoinExpr
        LEFT JOIN \"user\" u ON u.user_id = p.user_id
        LEFT JOIN patient pp ON pp.user_id = b.patient_id
        LEFT JOIN \"user\" pu ON pu.user_id = b.patient_id
        WHERE b.booking_id = :booking_id
          AND b.patient_id = :patient_id
        LIMIT 1
    ");
    $stmt->execute([
        ':booking_id' => $booking_id,
        ':patient_id' => $patient_id
    ]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die("Booking not found.");
    }
} catch (Exception $e) {
    die("Could not load booking: " . h($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_now') {
    $selectedMethod = strtolower(trim((string)($_POST['payment_method'] ?? '')));

    try {
        $refreshStmt = $pdo->prepare("
            SELECT *
            FROM booking
            WHERE booking_id = :booking_id
              AND patient_id = :patient_id
            LIMIT 1
        ");
        $refreshStmt->execute([
            ':booking_id' => $booking_id,
            ':patient_id' => $patient_id
        ]);
        $currentBooking = $refreshStmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentBooking) {
            $error = "Booking not found.";
        } elseif (strtolower((string)($currentBooking['payment_status'] ?? '')) === 'paid') {
            $success = "This booking has already been paid.";
        } else {
            if ($selectedMethod === 'cash') {
                $updateStmt = $pdo->prepare("
                    UPDATE booking
                    SET
                        payment_method = 'cash',
                        payment_status = CASE WHEN LOWER(COALESCE(service_type,'')) = 'driver' THEN 'pending' ELSE 'paid' END,
                        payment_state  = CASE WHEN LOWER(COALESCE(service_type,'')) = 'driver' THEN 'unpaid' ELSE 'paid' END,
                        paid_at = CASE WHEN LOWER(COALESCE(service_type,'')) = 'driver' THEN NULL ELSE NOW() END,
                        card_last4 = NULL,
                        card_brand = NULL,
                        card_holder = NULL
                    WHERE booking_id = :booking_id
                      AND patient_id = :patient_id
                ");
                $updateStmt->execute([
                    ':booking_id' => $booking_id,
                    ':patient_id' => $patient_id
                ]);

                $success = "Cash payment method saved successfully.";
            } elseif ($selectedMethod === 'visa') {
                $cardHolder = trim((string)($_POST['card_holder'] ?? ''));
                $cardNumber = trim((string)($_POST['card_number'] ?? ''));
                $expiry     = trim((string)($_POST['expiry'] ?? ''));
                $cvv        = trim((string)($_POST['cvv'] ?? ''));

                $digits = preg_replace('/\D+/', '', $cardNumber);
                $last4  = substr($digits, -4);

                if ($cardHolder === '') {
                    $error = "Please enter the card holder name.";
                } elseif (strlen($digits) < 12 || strlen($digits) > 16) {
                    $error = "Please enter a valid card number.";
                } elseif (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry)) {
                    $error = "Please enter a valid expiry date in MM/YY format.";
                } elseif (!preg_match('/^[0-9]{3}$/', $cvv)) {
                    $error = "CVV must be exactly 3 digits.";
                } else {
                    $brand = normalize_card_brand($digits);

                    $updateStmt = $pdo->prepare("
                        UPDATE booking
                        SET
                            payment_method = 'visa',
                            payment_status = 'paid',
                            payment_state = 'paid',
                            paid_at = NOW(),
                            card_last4 = :card_last4,
                            card_brand = :card_brand,
                            card_holder = :card_holder
                        WHERE booking_id = :booking_id
                          AND patient_id = :patient_id
                    ");
                    $updateStmt->execute([
                        ':card_last4'  => $last4,
                        ':card_brand'  => $brand,
                        ':card_holder' => $cardHolder,
                        ':booking_id'  => $booking_id,
                        ':patient_id'  => $patient_id
                    ]);

                    $success = "Visa payment completed successfully.";
                }
            } else {
                $error = "Please choose a payment method.";
            }

            if ($error === "") {
                // Redirect to booking status page after successful payment
                header("Location: booking_status.php?booking_id=$booking_id");
                exit;
            }
        }
    } catch (Exception $e) {
        $error = "Could not process payment: " . $e->getMessage();
    }
}

$serviceType = trim((string)($booking['service_type'] ?? 'Service'));
$isDriverBooking = strtolower($serviceType) === 'driver';

$providerName = trim((string)($booking['provider_name'] ?? ''));
if ($providerName === '') {
    $providerName = $isDriverBooking ? 'Driver Request' : 'Provider';
}

$providerPhoto = trim((string)($booking['provider_photo'] ?? ''));
if ($isDriverBooking && $providerName === 'Driver Request') {
    $providerPhoto = '';
}

$patientDisplayName = trim((string)($booking['fullname'] ?? ''));
if ($patientDisplayName === '') {
    $patientDisplayName = trim((string)($booking['patient_name'] ?? ''));
}
if ($patientDisplayName === '') {
    $patientDisplayName = 'Patient';
}

$avatarText = mb_strtoupper(mb_substr($providerName, 0, 1));
$isPaid = strtolower(trim((string)($booking['payment_status'] ?? ''))) === 'paid';
$bookingStatus = booking_status_label($booking['status'] ?? '');
$paymentMethodLabel = payment_method_label($booking['payment_method'] ?? '');
$paymentStatus = trim((string)($booking['payment_status'] ?? 'Unpaid'));
$cardBrand = trim((string)($booking['card_brand'] ?? ''));
$cardLast4 = trim((string)($booking['card_last4'] ?? ''));
$paidAt = trim((string)($booking['paid_at'] ?? ''));

$bookingTimeValue = h(!empty($booking['booking_time']) ? substr((string)$booking['booking_time'], 0, 5) : '-');
$serviceTimeValue = h(!empty($booking['service_time']) ? substr((string)$booking['service_time'], 0, 5) : '-');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Secure Payment - Rafiq</title>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

<style>
:root{
    --ink:#23243a;
    --soft:#6f748b;
    --muted:#8b91a6;
    --cream:#f7f8ff;
    --white:#ffffff;
    --line:#e4e6f5;
    --accent:#6470d2;
    --accent-2:#8b8cff;
    --navy:#292b4a;
    --navy-2:#353b69;
    --blue:#5267e8;
    --green:#168653;
    --green-bg:#eefbf4;
    --red:#b83a3a;
    --red-bg:#fff3f3;
    --shadow:0 30px 80px rgba(35,36,58,.13);
    --shadow-soft:0 18px 42px rgba(35,36,58,.08);
    --radius:34px;
    --radius-md:22px;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:"Nunito",system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    color:var(--ink);
    background:
        radial-gradient(circle at 10% 0%, rgba(100,112,210,.16), transparent 28%),
        radial-gradient(circle at 90% 8%, rgba(53,59,105,.12), transparent 26%),
        linear-gradient(180deg,#f5f6ff 0%, #f4f5fb 54%, #f2f4fa 100%);
    min-height:100vh;
}

.container{
    width:min(1220px, calc(100% - 32px));
    margin:0 auto;
}

.page{
    padding:32px 0 46px;
}

.lux-hero{
    position:relative;
    overflow:hidden;
    border:1px solid rgba(100,112,210,.2);
    border-radius:38px;
    padding:30px;
    margin-bottom:22px;
    background:
        radial-gradient(circle at 85% 8%, rgba(100,112,210,.18), transparent 28%),
        linear-gradient(135deg, rgba(255,255,255,.96) 0%, rgba(248,248,255,.92) 52%, rgba(245,246,255,.94) 100%);
    box-shadow:var(--shadow);
}

.lux-hero:before{
    content:"";
    position:absolute;
    width:280px;
    height:280px;
    border-radius:50%;
    right:-90px;
    bottom:-150px;
    background:radial-gradient(circle, rgba(100,112,210,.18), transparent 65%);
}

.lux-hero:after{
    content:"";
    position:absolute;
    width:160px;
    height:160px;
    border-radius:50%;
    left:-70px;
    top:-78px;
    background:radial-gradient(circle, rgba(53,59,105,.14), transparent 65%);
}

.hero-content{
    position:relative;
    z-index:2;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
    flex-wrap:wrap;
}

.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:9px 13px;
    border-radius:999px;
    background:#fff;
    border:1px solid rgba(100,112,210,.28);
    color:#4a56b0;
    font-weight:900;
    font-size:12px;
    box-shadow:var(--shadow-soft);
    margin-bottom:14px;
}

.hero-title{
    margin:0;
    font-size:42px;
    line-height:1.02;
    letter-spacing:-.8px;
    color:#20213b;
}

.hero-sub{
    margin:12px 0 0;
    max-width:640px;
    color:var(--soft);
    font-weight:800;
    font-size:15px;
    line-height:1.8;
}

.status-wrap{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    justify-content:flex-end;
}

.status-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:12px 15px;
    border-radius:999px;
    background:#fff;
    border:1px solid rgba(100,112,210,.18);
    box-shadow:var(--shadow-soft);
    font-size:13px;
    font-weight:900;
    color:#32344f;
}

.status-pill.green{
    color:#12643e;
    background:#f0fbf5;
    border-color:rgba(22,134,83,.18);
}

.status-pill.blue{
    color:#3147c7;
    background:#f1f4ff;
    border-color:rgba(82,103,232,.18);
}

.alert{
    position:relative;
    z-index:3;
    margin-top:18px;
    border-radius:20px;
    padding:15px 17px;
    font-weight:900;
    font-size:14px;
    box-shadow:var(--shadow-soft);
}

.alert.error{
    background:var(--red-bg);
    color:#8d2727;
    border:1px solid rgba(184,58,58,.16);
}

.alert.success{
    background:var(--green-bg);
    color:#12643e;
    border:1px solid rgba(22,134,83,.16);
}

.layout{
    display:grid;
    grid-template-columns:.96fr 1.04fr;
    gap:22px;
    align-items:start;
}

.panel{
    border-radius:var(--radius);
    background:rgba(255,255,255,.94);
    border:1px solid rgba(100,112,210,.14);
    box-shadow:var(--shadow);
    overflow:hidden;
    backdrop-filter:blur(16px);
}

.panel-inner{
    padding:24px;
}

.panel-title{
    margin:0;
    font-size:22px;
    line-height:1.15;
    color:#23243a;
    font-weight:900;
    letter-spacing:-.2px;
}

.panel-sub{
    margin:7px 0 0;
    color:var(--muted);
    font-weight:800;
    line-height:1.6;
    font-size:13px;
}

.provider-card{
    margin-top:18px;
    display:flex;
    align-items:center;
    gap:15px;
    padding:17px;
    border-radius:26px;
    border:1px solid rgba(100,112,210,.18);
    background:
        radial-gradient(circle at 95% 0%, rgba(100,112,210,.12), transparent 28%),
        linear-gradient(135deg,#fff 0%, #f8f8ff 100%);
}

.avatar{
    width:70px;
    height:70px;
    border-radius:24px;
    background:linear-gradient(135deg,var(--navy),var(--navy-2));
    color:#fff;
    display:grid;
    place-items:center;
    font-size:24px;
    font-weight:900;
    overflow:hidden;
    flex-shrink:0;
    box-shadow:0 18px 32px rgba(41,43,74,.22);
}

.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.provider-name{
    font-size:19px;
    font-weight:900;
    color:#25263e;
}

.provider-role{
    margin-top:5px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    color:#4a56b0;
    background:#eef0ff;
    border:1px solid rgba(100,112,210,.22);
    padding:8px 11px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
}

.summary-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
    margin-top:16px;
}

.meta{
    border:1px solid rgba(100,112,210,.14);
    background:linear-gradient(180deg,#fff,#f8f8ff);
    border-radius:20px;
    padding:14px;
}

.meta-label{
    color:var(--muted);
    font-size:11px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.7px;
    margin-bottom:7px;
}

.meta-value{
    color:#30324c;
    font-size:15px;
    font-weight:900;
    line-height:1.55;
    word-break:break-word;
}

.total-card{
    margin-top:16px;
    border-radius:30px;
    padding:22px;
    color:#fff;
    background:
        radial-gradient(circle at 82% 12%, rgba(139,140,255,.28), transparent 28%),
        linear-gradient(135deg,#20213b 0%, #30335f 52%, #5b58eb 100%);
    box-shadow:0 24px 48px rgba(32,33,59,.26);
    position:relative;
    overflow:hidden;
}

.total-card:after{
    content:"";
    position:absolute;
    width:170px;
    height:170px;
    border-radius:50%;
    right:-55px;
    bottom:-80px;
    background:radial-gradient(circle, rgba(255,255,255,.13), transparent 65%);
}

.total-top{
    position:relative;
    z-index:2;
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:flex-start;
    flex-wrap:wrap;
}

.total-label{
    font-size:12px;
    font-weight:900;
    opacity:.78;
    text-transform:uppercase;
    letter-spacing:.7px;
}

.total-amount{
    margin-top:8px;
    font-size:39px;
    line-height:1;
    font-weight:900;
    letter-spacing:-.8px;
}

.total-side{
    text-align:right;
    min-width:150px;
}

.secure-mini{
    margin-top:16px;
    position:relative;
    z-index:2;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.secure-chip{
    padding:9px 11px;
    border-radius:999px;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.16);
    font-size:12px;
    font-weight:900;
    color:#fff;
}

.method-list{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    margin-top:18px;
}

.method-option{
    position:relative;
}

.method-option input{
    position:absolute;
    opacity:0;
    pointer-events:none;
}

.method-label{
    min-height:166px;
    cursor:pointer;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    border-radius:26px;
    padding:18px;
    border:1px solid rgba(100,112,210,.14);
    background:linear-gradient(135deg,#fff 0%, #f9f9ff 100%);
    box-shadow:var(--shadow-soft);
    transition:.22s ease;
}

.method-label:hover{
    transform:translateY(-4px);
    box-shadow:0 24px 48px rgba(35,36,58,.12);
}

.method-option input:checked + .method-label{
    border-color:rgba(100,112,210,.5);
    background:
        radial-gradient(circle at 92% 0%, rgba(100,112,210,.14), transparent 32%),
        linear-gradient(135deg,#ffffff 0%, #f4f4ff 100%);
    box-shadow:0 24px 48px rgba(100,112,210,.14);
}

.method-icon{
    width:56px;
    height:56px;
    border-radius:20px;
    display:grid;
    place-items:center;
    font-size:25px;
    font-weight:900;
    box-shadow:var(--shadow-soft);
}

.method-icon.cash{
    color:#12643e;
    background:#effbf4;
}

.method-icon.visa{
    color:#3147c7;
    background:#f0f3ff;
}

.method-title{
    margin-top:14px;
    font-size:18px;
    font-weight:900;
    color:#25263e;
}

.method-sub{
    margin-top:5px;
    color:var(--muted);
    line-height:1.55;
    font-size:13px;
    font-weight:800;
}

.method-check{
    align-self:flex-start;
    margin-top:14px;
    padding:8px 11px;
    border-radius:999px;
    color:#4a56b0;
    background:#eef0ff;
    border:1px solid rgba(100,112,210,.2);
    font-size:12px;
    font-weight:900;
}

.form-block{
    display:none;
    margin-top:16px;
    animation:rise .25s ease both;
}

.form-block.show{
    display:block;
}

@keyframes rise{
    from{opacity:0; transform:translateY(8px)}
    to{opacity:1; transform:translateY(0)}
}

.cash-note{
    padding:17px;
    border-radius:22px;
    color:#12643e;
    background:#f0fbf5;
    border:1px solid rgba(22,134,83,.16);
    font-size:14px;
    line-height:1.7;
    font-weight:800;
}

.field-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:13px;
}

.field{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.field.full{
    grid-column:1 / -1;
}

.field label{
    font-size:13px;
    font-weight:900;
    color:#4b4e68;
}

.input{
    width:100%;
    border:1px solid rgba(100,112,210,.2);
    outline:none;
    background:#f8f8ff;
    border-radius:18px;
    padding:15px 15px;
    font:inherit;
    font-weight:900;
    color:#23243a;
    transition:.18s ease;
}

.input:focus{
    border-color:rgba(100,112,210,.56);
    background:#fff;
    box-shadow:0 0 0 5px rgba(100,112,210,.12);
}

.fake-card{
    margin-top:17px;
    min-height:218px;
    border-radius:30px;
    padding:20px;
    color:#fff;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    overflow:hidden;
    position:relative;
    background:
        radial-gradient(circle at 86% 10%, rgba(244,213,138,.45), transparent 28%),
        radial-gradient(circle at 16% 90%, rgba(82,103,232,.22), transparent 26%),
        linear-gradient(135deg,#17182d 0%, #2c315f 50%, #3f426f 100%);
    box-shadow:0 28px 54px rgba(23,24,45,.28);
}

.fake-card:before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(120deg, rgba(255,255,255,.10), transparent 45%);
    pointer-events:none;
}

.card-row{
    position:relative;
    z-index:2;
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:flex-start;
}

.card-chip{
    width:52px;
    height:38px;
    border-radius:12px;
    background:linear-gradient(135deg,#d4c8ff,#fff 50%,#9a8fd4);
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.45);
}

.card-brand{
    font-size:14px;
    font-weight:900;
    letter-spacing:1px;
}

.card-number{
    position:relative;
    z-index:2;
    font-size:25px;
    letter-spacing:2.2px;
    font-weight:900;
}

.card-bottom{
    position:relative;
    z-index:2;
    display:flex;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
}

.card-cap{
    font-size:10px;
    font-weight:900;
    opacity:.68;
    letter-spacing:.8px;
    text-transform:uppercase;
    margin-bottom:5px;
}

.card-val{
    font-size:15px;
    font-weight:900;
}

.pay-actions{
    display:flex;
    gap:12px;
    margin-top:18px;
    flex-wrap:wrap;
}

.pay-btn{
    border:none;
    outline:none;
    cursor:pointer;
    min-height:54px;
    border-radius:20px;
    padding:0 22px;
    background:
        radial-gradient(circle at 80% 0%, rgba(244,213,138,.32), transparent 30%),
        linear-gradient(135deg,#20213b 0%, #30335f 54%, #555991 100%);
    color:#fff;
    font-weight:900;
    font-size:15px;
    font-family:inherit;
    box-shadow:0 22px 42px rgba(32,33,59,.24);
    transition:.22s ease;
    flex:1;
}

.pay-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 28px 52px rgba(32,33,59,.30);
}

.pay-btn.secondary{
    background:#fff;
    color:#30335f;
    border:1px solid rgba(235,231,220,.95);
    box-shadow:var(--shadow-soft);
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    flex:.6;
}

.paid-box{
    margin-top:18px;
    border-radius:28px;
    padding:22px;
    background:
        radial-gradient(circle at 90% 0%, rgba(22,134,83,.17), transparent 30%),
        linear-gradient(135deg,#ffffff 0%, #f1fbf5 100%);
    border:1px solid rgba(22,134,83,.16);
    box-shadow:var(--shadow-soft);
}

.paid-head{
    display:flex;
    justify-content:space-between;
    gap:14px;
    align-items:flex-start;
    flex-wrap:wrap;
}

.paid-icon{
    width:64px;
    height:64px;
    border-radius:22px;
    display:grid;
    place-items:center;
    color:#fff;
    background:linear-gradient(135deg,#168653,#25b56f);
    font-size:30px;
    box-shadow:0 18px 32px rgba(22,134,83,.22);
}

.paid-title{
    font-size:24px;
    font-weight:900;
    color:#12643e;
}

.paid-sub{
    margin-top:5px;
    color:#4d8064;
    font-weight:800;
    line-height:1.6;
}

.paid-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
    margin-top:17px;
}

.back-link{
    margin-top:16px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    color:#30335f;
    text-decoration:none;
    font-weight:900;
    background:#fff;
    border:1px solid rgba(235,231,220,.95);
    padding:12px 15px;
    border-radius:999px;
    box-shadow:var(--shadow-soft);
}

@media(max-width:980px){
    .layout{
        grid-template-columns:1fr;
    }

    .hero-title{
        font-size:34px;
    }
}

@media(max-width:720px){
    .page{
        padding:20px 0 34px;
    }

    .lux-hero,
    .panel-inner{
        padding:20px;
    }

    .hero-title{
        font-size:30px;
    }

    .method-list,
    .summary-grid,
    .paid-grid,
    .field-grid{
        grid-template-columns:1fr;
    }

    .total-amount{
        font-size:32px;
    }

    .total-side{
        text-align:left;
    }

    .pay-actions{
        flex-direction:column;
    }

    .pay-btn,
    .pay-btn.secondary{
        width:100%;
        flex:1;
    }

    .card-number{
        font-size:20px;
        letter-spacing:1.3px;
    }
}
</style>
</head>

<body>

<?php include '../general/nav_patient.php'; ?>

<main class="container page">
    <section class="lux-hero">
        <div class="hero-content">
            <div>
                <h1 class="hero-title">Complete your payment</h1>
                <p class="hero-sub">Review your booking details and choose the payment method that suits you best.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?= h($success) ?></div>
        <?php endif; ?>
    </section>

    <section class="layout">
        <div class="panel">
            <div class="panel-inner">
                <h2 class="panel-title">Booking Summary</h2>
                <p class="panel-sub"><?= $isDriverBooking ? 'Your driver request and trip details are shown below.' : 'Your selected provider and booking details are shown below.' ?></p>

                <div class="provider-card">
                    <div class="avatar">
                        <?php if ($providerPhoto !== ''): ?>
                            <img src="<?= h($providerPhoto) ?>" alt="<?= h($providerName) ?>">
                        <?php else: ?>
                            <?= h($avatarText) ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="provider-name"><?= h($providerName) ?></div>
                        <div class="provider-role"><?= h($serviceType) ?></div>
                    </div>
                </div>

                <div class="summary-grid">
                    <div class="meta">
                        <div class="meta-label">Date</div>
                        <div class="meta-value"><?= h($booking['date'] ?? '-') ?></div>
                    </div>

                    <div class="meta">
                        <div class="meta-label"><?= $isDriverBooking ? 'Pickup Time' : 'Time' ?></div>
                        <div class="meta-value">
                            <?php if ($isDriverBooking): ?>
                                <?= $serviceTimeValue !== '-' ? $serviceTimeValue : $bookingTimeValue ?>
                            <?php else: ?>
                                <?= $bookingTimeValue ?> — <?= $serviceTimeValue ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="meta">
                        <div class="meta-label">Status</div>
                        <div class="meta-value"><?= h($bookingStatus) ?></div>
                    </div>

                    <?php if (!empty($booking['address'])): ?>
                        <div class="meta">
                            <div class="meta-label">Address</div>
                            <div class="meta-value"><?= h($booking['address']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['destination'])): ?>
                        <div class="meta">
                            <div class="meta-label">Destination</div>
                            <div class="meta-value"><?= h($booking['destination']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($isDriverBooking && !empty($booking['distance_km'])): ?>
                        <div class="meta">
                            <div class="meta-label">Distance</div>
                            <div class="meta-value"><?= h(number_format((float)$booking['distance_km'], 2)) ?> km</div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['location_address'])): ?>
                        <div class="meta">
                            <div class="meta-label">Location</div>
                            <div class="meta-value"><?= h($booking['location_address']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="meta">
                        <div class="meta-label">Patient</div>
                        <div class="meta-value"><?= h($patientDisplayName) ?></div>
                    </div>
                </div>

                <div class="total-card">
                    <div class="total-top">
                        <div>
                            <div class="total-label">Total Amount</div>
                            <div class="total-amount"><?= h(money($booking['payment_total'] ?? 0)) ?> EGP</div>
                        </div>

                        <div class="total-side">
                            <div class="total-label">Selected Service</div>
                            <div style="font-size:18px;font-weight:900;margin-top:7px;"><?= h($serviceType) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-inner">
                <?php if ($isPaid): ?>
                    <h2 class="panel-title">Payment Details</h2>
                    <p class="panel-sub">This booking has already been paid successfully.</p>

                    <div class="paid-box">
                        <div class="paid-head">
                            <div style="display:flex;gap:14px;align-items:center;">
                                <div class="paid-icon">✓</div>
                                <div>
                                    <div class="paid-title">Payment Completed</div>
                                    <div class="paid-sub">Your payment has been confirmed.</div>
                                </div>
                            </div>
                        </div>

                        <div class="paid-grid">
                            <div class="meta">
                                <div class="meta-label">Method</div>
                                <div class="meta-value"><?= h($paymentMethodLabel) ?></div>
                            </div>

                            <div class="meta">
                                <div class="meta-label">Payment Status</div>
                                <div class="meta-value"><?= h($paymentStatus) ?></div>
                            </div>

                            <div class="meta">
                                <div class="meta-label">Card Brand</div>
                                <div class="meta-value"><?= h($cardBrand ?: '—') ?></div>
                            </div>

                            <div class="meta">
                                <div class="meta-label">Card Last 4</div>
                                <div class="meta-value"><?= h($cardLast4 ?: '—') ?></div>
                            </div>

                            <div class="meta">
                                <div class="meta-label">Card Holder</div>
                                <div class="meta-value"><?= h($booking['card_holder'] ?? '—') ?></div>
                            </div>

                            <div class="meta">
                                <div class="meta-label">Paid At</div>
                                <div class="meta-value"><?= h($paidAt ?: '—') ?></div>
                            </div>
                        </div>
                    </div>


                    <a class="back-link" href="my_bookings.php">← Back to My Bookings</a>

                <?php else: ?>
                    <h2 class="panel-title">Choose Payment Method</h2>
                    <p class="panel-sub">Select how you would like to complete this booking.</p>

                    <form method="POST" action="" id="paymentForm" novalidate>
                        <input type="hidden" name="action" value="pay_now">
                        <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">

                        <div class="method-list">
                            <div class="method-option">
                                <input type="radio" name="payment_method" id="method-cash" value="cash">
                                <label class="method-label" for="method-cash">
                                    <div>
                                        <div class="method-icon cash"><i class="fa-solid fa-money-bill-wave"></i></div>
                                        <div class="method-title">Cash</div>
                                        <div class="method-sub">Pay directly when the service starts.</div>
                                    </div>
                                    <div class="method-check">Pay later</div>
                                </label>
                            </div>

                            <div class="method-option">
                                <input type="radio" name="payment_method" id="method-visa" value="visa">
                                <label class="method-label" for="method-visa">
                                    <div>
                                        <div class="method-icon visa"><i class="fa-solid fa-credit-card"></i></div>
                                        <div class="method-title">Card</div>
                                        <div class="method-sub">Pay now using your card details.</div>
                                    </div>
                                    <div class="method-check">Secure checkout</div>
                                </label>
                            </div>
                        </div>

                        <div class="form-block" id="cashBlock">
                            <div class="cash-note">
                                <?= $isDriverBooking ? 'Cash selected. You will pay the driver when the trip starts.' : 'Cash payment selected.' ?>
                            </div>
                        </div>

                        <div class="form-block" id="visaBlock">
                            <div class="field-grid">
                                <div class="field full">
                                    <label for="card_holder">Card Holder Name</label>
                                    <input class="input" type="text" id="card_holder" name="card_holder" placeholder="Enter card holder name" value="<?= h($_POST['card_holder'] ?? ($booking['card_holder'] ?? '')) ?>">
                                </div>

                                <div class="field full">
                                    <label for="card_number">Card Number</label>
                                    <input class="input" type="text" id="card_number" name="card_number" maxlength="19" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" value="<?= h($_POST['card_number'] ?? '') ?>">
                                </div>

                                <div class="field">
                                    <label for="expiry">Expiry</label>
                                    <input class="input" type="text" id="expiry" name="expiry" maxlength="5" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/YY" value="<?= h($_POST['expiry'] ?? '') ?>">
                                </div>

                                <div class="field">
                                    <label for="cvv">CVV</label>
                                    <input class="input" type="password" id="cvv" name="cvv" maxlength="3" minlength="3" pattern="[0-9]{3}" inputmode="numeric" autocomplete="cc-csc" placeholder="123" value="<?= h($_POST['cvv'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="fake-card">
                                <div class="card-row">
                                    <div class="card-chip"></div>
                                    <div class="card-brand" id="liveBrand">VISA</div>
                                </div>

                                <div class="card-number" id="liveCardNumber">•••• •••• •••• ••••</div>

                                <div class="card-bottom">
                                    <div>
                                        <div class="card-cap">Card Holder</div>
                                        <div class="card-val" id="liveHolder">YOUR NAME</div>
                                    </div>

                                    <div>
                                        <div class="card-cap">Expiry</div>
                                        <div class="card-val" id="liveExpiry">MM/YY</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pay-actions">
                            <button type="submit" class="pay-btn">Confirm Payment</button>
                            <a href="my_bookings.php" class="pay-btn secondary">Back</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include '../general/footer.php'; ?>

<script>
(function(){
    const cashRadio = document.getElementById('method-cash');
    const visaRadio = document.getElementById('method-visa');
    const cashBlock = document.getElementById('cashBlock');
    const visaBlock = document.getElementById('visaBlock');

    function togglePaymentBlocks(){
        if (!cashBlock || !visaBlock || !cashRadio || !visaRadio) return;
        cashBlock.classList.toggle('show', cashRadio.checked);
        visaBlock.classList.toggle('show', visaRadio.checked);
    }

    if (cashRadio && visaRadio) {
        cashRadio.addEventListener('change', togglePaymentBlocks);
        visaRadio.addEventListener('change', togglePaymentBlocks);

        <?php
            $postedMethod = strtolower(trim((string)($_POST['payment_method'] ?? '')));
            if ($postedMethod === 'visa') {
                echo "visaRadio.checked = true;";
            } elseif ($postedMethod === 'cash') {
                echo "cashRadio.checked = true;";
            }
        ?>

        togglePaymentBlocks();
    }

    const cardNumber = document.getElementById('card_number');
    const cardHolder = document.getElementById('card_holder');
    const expiry = document.getElementById('expiry');
    const cvv = document.getElementById('cvv');

    const liveCardNumber = document.getElementById('liveCardNumber');
    const liveHolder = document.getElementById('liveHolder');
    const liveExpiry = document.getElementById('liveExpiry');
    const liveBrand = document.getElementById('liveBrand');

    function detectBrand(num){
        const digits = (num || '').replace(/\D+/g, '');
        if (/^4/.test(digits)) return 'VISA';
        if (/^(5[1-5]|2[2-7])/.test(digits)) return 'MASTERCARD';
        if (/^3[47]/.test(digits)) return 'AMEX';
        return 'CARD';
    }

    function formatCardNumber(value){
        const digits = (value || '').replace(/\D+/g, '').substring(0, 16);
        return digits.replace(/(.{4})/g, '$1 ').trim();
    }

    function formatExpiry(value){
        const digits = (value || '').replace(/\D+/g, '').substring(0, 4);
        if (digits.length <= 2) return digits;
        return digits.substring(0, 2) + '/' + digits.substring(2);
    }

    if (cardNumber) {
        cardNumber.addEventListener('input', function(){
            this.value = formatCardNumber(this.value);
            if (liveCardNumber) liveCardNumber.textContent = this.value || '•••• •••• •••• ••••';
            if (liveBrand) liveBrand.textContent = detectBrand(this.value);
        });
        cardNumber.dispatchEvent(new Event('input'));
    }

    if (cardHolder) {
        cardHolder.addEventListener('input', function(){
            if (liveHolder) liveHolder.textContent = (this.value || 'YOUR NAME').toUpperCase();
        });
        cardHolder.dispatchEvent(new Event('input'));
    }

    if (expiry) {
        expiry.addEventListener('input', function(){
            this.value = formatExpiry(this.value);
            if (liveExpiry) liveExpiry.textContent = this.value || 'MM/YY';
        });
        expiry.dispatchEvent(new Event('input'));
    }

    if (cvv) {
        cvv.addEventListener('input', function(){
            this.value = (this.value || '').replace(/\D+/g, '').substring(0, 3);
        });
        cvv.dispatchEvent(new Event('input'));
    }
})();
</script>

</body>
</html>