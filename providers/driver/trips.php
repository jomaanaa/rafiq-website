<?php
session_start();
require __DIR__ . '/../../pgdb/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return number_format((float)$n, 2); }
function calc_driver_net(float $total): float { return round($total * 0.85, 2); }

function payment_method_safe($value): string {
    $v = strtolower(trim((string)$value));
    return in_array($v, ['cash', 'visa'], true) ? $v : 'cash';
}

function get_session_driver_id(): int {
    if (!empty($_SESSION['driver_id'])) return (int)$_SESSION['driver_id'];
    if (!empty($_SESSION['user_id']))   return (int)$_SESSION['user_id'];
    if (!empty($_SESSION['ID']))        return (int)$_SESSION['ID'];
    return 0;
}

function get_session_driver_name(): string {
    if (!empty($_SESSION['driver_name'])) return trim((string)$_SESSION['driver_name']);
    return '';
}

function fetch_driver_name(PDO $pdo, int $driver_id): string {
    $sessionName = get_session_driver_name();
    if ($sessionName !== '') return $sessionName;

    try {
        $stmt = $pdo->prepare('
            SELECT CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\')) AS full_name
            FROM "user"
            WHERE user_id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $driver_id]);
        $name = trim((string)$stmt->fetchColumn());
        if ($name !== '') return $name;
    } catch (Exception $e) {}

    return "Driver #{$driver_id}";
}

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

$driver_id = get_session_driver_id();

if ($driver_id <= 0) {
    header("Location: ../../general/login.php");
    exit;
}

$driverCol = null;
if (has_col($pdo, 'booking', 'driver_id')) $driverCol = 'driver_id';
elseif (has_col($pdo, 'booking', 'provider_id')) $driverCol = 'provider_id';

if (!$driverCol) {
    die("Booking table must contain driver_id or provider_id.");
}

$driver_name = fetch_driver_name($pdo, $driver_id);

$error = "";
$success = "";

if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rate_patient') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $rating = (int)($_POST['driver_patient_rating'] ?? 0);
    $comment = trim((string)($_POST['driver_patient_comment'] ?? ''));

    try {
        if ($booking_id <= 0) throw new Exception("Invalid booking id.");
        if ($rating < 1 || $rating > 5) throw new Exception("Patient rating must be between 1 and 5.");

        $stmt = $pdo->prepare("
            UPDATE booking
            SET driver_patient_rating = :rating,
                driver_patient_comment = :comment
            WHERE booking_id = :booking_id
              AND {$driverCol} = :driver_id
              AND status = 'completed'
              AND payment_status = 'completed'
              AND driver_patient_rating IS NULL
        ");
        $stmt->execute([
            ':rating'     => $rating,
            ':comment'    => ($comment !== '' ? $comment : null),
            ':booking_id' => $booking_id,
            ':driver_id'  => $driver_id
        ]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['flash_success'] = "Patient rated successfully for trip #{$booking_id}.";
        } else {
            $_SESSION['flash_error'] = "This trip was already rated or could not be updated.";
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$wallet = [
    'available_balance' => 0,
    'total_earned' => 0,
    'total_trips' => 0,
];

$trips = [];
$driver_rating = null;
$driver_rating_count = 0;

try {
    $stmtWallet = $pdo->prepare("
        SELECT available_balance, total_earned, total_trips
        FROM driver
        WHERE user_id = :driver_id
        LIMIT 1
    ");
    $stmtWallet->execute([':driver_id' => $driver_id]);
    $walletRow = $stmtWallet->fetch(PDO::FETCH_ASSOC);
    if ($walletRow) $wallet = $walletRow;

    $stmtDriverRating = $pdo->prepare("
        SELECT
            ROUND(AVG(rating)::numeric, 1) AS avg_rating,
            COUNT(rating) AS rating_count
        FROM booking
        WHERE {$driverCol} = :driver_id
          AND rating IS NOT NULL
          AND status = 'completed'
          AND payment_status = 'completed'
    ");
    $stmtDriverRating->execute([':driver_id' => $driver_id]);
    $driverRatingRow = $stmtDriverRating->fetch(PDO::FETCH_ASSOC);
    if ($driverRatingRow) {
        $driver_rating = $driverRatingRow['avg_rating'];
        $driver_rating_count = (int)($driverRatingRow['rating_count'] ?? 0);
    }

    $stmtTrips = $pdo->prepare("
        SELECT
            b.booking_id,
            b.address,
            b.destination,
            b.payment_total,
            b.payment_method,
            b.date,
            b.service_time,
            b.rating,
            b.driver_patient_rating,
            b.driver_patient_comment
        FROM booking b
        WHERE b.{$driverCol} = :driver_id
          AND b.status = 'completed'
          AND b.payment_status = 'completed'
        ORDER BY COALESCE(b.end_at, b.start_at) DESC NULLS LAST, b.booking_id DESC
    ");
    $stmtTrips->execute([':driver_id' => $driver_id]);
    $trips = $stmtTrips->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trips</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#f4f7fb;
    --text:#1d2440;
    --muted:#7a84a3;
    --primary:#5b58eb;
    --primary-2:#4744cf;
    --card:#ffffff;
    --line:#e8edf6;
    --shadow:0 18px 40px rgba(31, 41, 86, 0.09);
    --shadow-sm:0 10px 24px rgba(31, 41, 86, 0.06);
    --ok:#1f9d5a;
    --bad:#c0392b;
    --gold:#d5a72c;
    --container:1180px;
    --radius:26px;
  }

  *{box-sizing:border-box}
  body{
    margin:0;
    font-family:"Nunito",system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    background:
      radial-gradient(circle at top left, rgba(91,88,235,.08), transparent 28%),
      linear-gradient(180deg,#f7f9fd 0%, #f2f5fb 100%);
    color:var(--text);
  }

  .container{width:min(var(--container), calc(100% - 32px)); margin:0 auto;}

  .topbar{
    position:sticky; top:0; z-index:40;
    background:rgba(255,255,255,0.84);
    backdrop-filter:blur(16px);
    border-bottom:1px solid rgba(232,237,246,.95);
  }
  .topbar-inner{
    display:flex; justify-content:space-between; align-items:center; gap:16px; padding:16px 0; flex-wrap:wrap;
  }
  .brand{display:flex; align-items:center; gap:14px; flex-wrap:wrap;}
  .logo{
    width:48px; height:48px; border-radius:16px; display:grid; place-items:center;
    background:linear-gradient(135deg,#5b58eb,#7d6eff); color:#fff; font-weight:900;
    box-shadow:0 14px 30px rgba(91,88,235,.28);
  }
  .brand-name{font-size:25px; font-weight:900; color:#2c2d55;}
  .brand-name span{color:var(--gold)}
  .pill, .back-btn{
    display:inline-flex; align-items:center; gap:8px; padding:10px 14px;
    background:#fff; border:1px solid var(--line); border-radius:999px; font-weight:900;
    color:#2f3558; box-shadow:var(--shadow-sm); text-decoration:none;
  }

  .hero{padding:28px 0 12px;}
  .hero-box{
    background:linear-gradient(135deg,#ffffff 0%, #f8faff 65%, #f4f4ff 100%);
    border:1px solid var(--line); border-radius:32px; box-shadow:var(--shadow); padding:26px;
  }
  .hero-head{
    display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap;
  }
  .hero h1{margin:0; font-size:34px; line-height:1.1; color:#25284b;}
  .hero p{margin:10px 0 0; color:var(--muted); font-weight:800; max-width:760px;}

  .alert{
    margin-top:14px; border-radius:16px; padding:13px 15px; font-weight:800; border:1px solid var(--line); background:#fff;
  }
  .alert.ok{border-color: rgba(31,157,90,0.20); background:#f4fcf7; color:#16663d;}
  .alert.bad{border-color: rgba(192,57,43,0.20); background:#fff5f4; color:#8d2b21;}

  .kpi-grid{
    display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin:20px 0 6px;
  }
  .kpi{
    padding:20px; border-radius:24px; border:1px solid var(--line);
    background:linear-gradient(180deg,#fff,#fafcff); box-shadow:var(--shadow);
  }
  .kpi .k{font-size:12px; color:var(--muted); font-weight:900; text-transform:uppercase; letter-spacing:.5px;}
  .kpi .v{margin-top:8px; font-size:30px; font-weight:900; color:#26264b;}
  .kpi .s{margin-top:6px; font-size:12px; color:#6e7694; font-weight:800;}

  .card{
    background:var(--card); border:1px solid rgba(232,237,246,.95); border-radius:var(--radius);
    box-shadow:var(--shadow); padding:18px; margin-bottom:34px;
  }
  .card h2{margin:0; font-size:20px; font-weight:900; color:#2d315d;}
  .sub{margin-top:8px; color:var(--muted); font-weight:800; font-size:13px;}

  .list{margin-top:14px; display:flex; flex-direction:column; gap:14px;}
  .item{
    border:1px solid rgba(232,237,246,.95); border-radius:22px; padding:15px; display:flex; gap:14px;
    align-items:flex-start; background:linear-gradient(180deg,#fff,#fcfcff); transition:.18s ease;
  }
  .item:hover{transform:translateY(-2px); box-shadow:0 14px 26px rgba(38,45,90,.06);}
  .item-left{
    width:60px; min-width:60px; height:60px; border-radius:18px; background:#f2f5ff; display:grid; place-items:center;
    font-weight:900; color:#3c3b59; border:1px solid rgba(236,236,245,.9);
  }
  .item-main{flex:1; min-width:0;}
  .title{display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;}
  .title b{font-size:15px; font-weight:900; color:#2a2a46;}
  .badge{
    font-size:12px; font-weight:900; padding:7px 10px; border-radius:999px; border:1px solid var(--line);
    background:#fff; color:#2a2a46;
  }
  .badge.completed{border-color: rgba(31,157,90,0.35); color:#137043; background:#eefaf3;}
  .badge.cash{border-color: rgba(25,135,84,0.28); color:#146c43; background:#eefaf3;}
  .badge.visa{border-color: rgba(13,110,253,0.25); color:#0b5ed7; background:#eef4ff;}
  .badge.done{border-color: rgba(91,88,235,.24); color:#4f46e5; background:#f4f3ff;}

  .meta{
    margin-top:8px; display:flex; flex-wrap:wrap; gap:10px 14px; color:#5a5a7a; font-weight:800; font-size:13px;
  }
  .tiny{
    width:6px; height:6px; border-radius:99px; background:#bdbdd6; display:inline-block; margin-right:6px;
  }

  .trip-price{margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;}
  .price-chip{
    padding:8px 10px; border-radius:999px; background:#f6f7fd; border:1px solid var(--line);
    font-size:12px; font-weight:900; color:#3a3a5e;
  }

  .star-rating-box{
    margin-top:14px;
    padding:14px;
    border-radius:18px;
    border:1px solid #ebe7ff;
    background:#f8f7ff;
  }
  .star-rating-box h4{
    margin:0 0 10px;
    font-size:14px;
    color:#4338ca;
  }
  .star-rating{
    display:flex;
    flex-direction:row-reverse;
    justify-content:flex-end;
    gap:8px;
    margin-bottom:12px;
  }
  .star-rating input{
    display:none;
  }
  .star-rating label{
    cursor:pointer;
    font-size:28px;
    color:#d8dceb;
    transition:.15s ease;
    line-height:1;
  }
  .star-rating label:hover,
  .star-rating label:hover ~ label,
  .star-rating input:checked ~ label{
    color:#f5b301;
    transform:scale(1.06);
  }

  .rate-input{
    width:100%;
    min-height:82px;
    border-radius:14px;
    border:1px solid var(--line);
    background:#fff;
    padding:12px 14px;
    font:inherit;
    resize:vertical;
    margin-bottom:10px;
  }
  .rated-note{
    margin-top:12px; font-weight:900; color:#4f46e5; background:#f5f3ff; border:1px solid #e9ddff;
    border-radius:14px; padding:10px 12px;
  }

  .btn{
    display:inline-flex; align-items:center; justify-content:center; height:42px; padding:0 15px;
    border-radius:14px; border:1px solid var(--line); background:#fff; font-weight:900; color:#2a2a46;
    cursor:pointer; transition:.15s; text-decoration:none;
  }
  .btn:hover{transform:translateY(-1px); box-shadow:0 8px 16px rgba(38,45,90,.06);}
  .btn.primary{
    background:linear-gradient(135deg,var(--primary),#716dff); border-color:transparent; color:#fff;
    box-shadow:0 12px 22px rgba(91,88,235,.22);
  }

  .empty{
    padding:14px; color:var(--muted); font-weight:800; border:1px dashed rgba(232,237,246,.95);
    border-radius:18px; background:#fff;
  }

  @media (max-width: 1100px){
    .kpi-grid{grid-template-columns:repeat(2,1fr);}
  }
  @media (max-width: 900px){
    .kpi-grid{grid-template-columns:1fr;}
  }
</style>
</head>
<body>

<header class="topbar">
  <div class="container">
    <div class="topbar-inner">
      <div class="brand">
        <div class="logo">R</div>
        <div class="brand-name">Rafi<span>Q</span></div>
        <div class="pill">Trips</div>
      </div>

      <a class="back-btn" href="driver_portal.php">← Back to Driver Portal</a>
    </div>
  </div>
</header>

<main class="container">
  <section class="hero">
    <div class="hero-box">
      <div class="hero-head">
        <div>
          <h1><?= h($driver_name) ?>'s Trips</h1>
          <p>Completed trips only, with the details that matter most.</p>
        </div>
      </div>

      <?php if ($success): ?>
        <div class="alert ok">✅ <?= h($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert bad">⛔ <?= h($error) ?></div>
      <?php endif; ?>

      <div class="kpi-grid">
        <div class="kpi">
          <div class="k">Wallet Balance</div>
          <div class="v"><?= h(money($wallet['available_balance'] ?? 0)) ?></div>
          <div class="s">Net after 15% commission</div>
        </div>

        <div class="kpi">
          <div class="k">Total Earned</div>
          <div class="v"><?= h(money($wallet['total_earned'] ?? 0)) ?></div>
          <div class="s">Gross completed-trip total</div>
        </div>

        <div class="kpi">
          <div class="k">Completed Trips</div>
          <div class="v"><?= h((int)($wallet['total_trips'] ?? 0)) ?></div>
          <div class="s">All finished trips</div>
        </div>

        <div class="kpi">
          <div class="k">Driver Rating</div>
          <div class="v"><?= $driver_rating !== null ? h($driver_rating) : '—' ?></div>
          <div class="s"><?= h($driver_rating_count) ?> rating<?= $driver_rating_count == 1 ? '' : 's' ?></div>
        </div>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Completed Trips</h2>
    <div class="sub">Trip price, your net, your rating, and patient rating from your side.</div>

    <div class="list">
      <?php if (!$trips): ?>
        <div class="empty">No completed trips yet.</div>
      <?php else: ?>
        <?php foreach ($trips as $t): ?>
          <?php
            $gross = (float)($t['payment_total'] ?? 0);
            $net = calc_driver_net($gross);
            $pm = payment_method_safe($t['payment_method'] ?? 'cash');
          ?>
          <div class="item">
            <div class="item-left">#<?= h($t['booking_id']) ?></div>
            <div class="item-main">
              <div class="title">
                <b>Completed Trip</b>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                  <span class="badge <?= h($pm) ?>"><?= h(strtoupper($pm)) ?></span>
                  <span class="badge completed">Completed</span>
                  <?php if ($t['rating'] !== null): ?>
                    <span class="badge done">Your Rating: <?= h($t['rating']) ?>/5</span>
                  <?php endif; ?>
                  <?php if ($t['driver_patient_rating'] !== null): ?>
                    <span class="badge done">You rated patient: <?= h($t['driver_patient_rating']) ?>/5</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="meta">
                <span><span class="tiny"></span><?= h($t['address'] ?? '—') ?></span>
                <span><span class="tiny"></span><?= h($t['destination'] ?? '—') ?></span>
                <?php if (!empty($t['date'])): ?>
                  <span><span class="tiny"></span>Date: <?= h($t['date']) ?></span>
                <?php endif; ?>
                <?php if (!empty($t['service_time'])): ?>
                  <span><span class="tiny"></span>Time: <?= h($t['service_time']) ?></span>
                <?php endif; ?>
              </div>

              <div class="trip-price">
                <span class="price-chip">Trip Price: <?= h(money($gross)) ?></span>
                <span class="price-chip">Your Net: <?= h(money($net)) ?></span>
              </div>

              <?php if ($t['driver_patient_rating'] === null): ?>
                <div class="star-rating-box">
                  <h4>Rate the patient</h4>
                  <form method="post">
                    <input type="hidden" name="action" value="rate_patient">
                    <input type="hidden" name="booking_id" value="<?= h($t['booking_id']) ?>">

                    <div class="star-rating">
                      <input type="radio" id="tripstar5_<?= h($t['booking_id']) ?>" name="driver_patient_rating" value="5" required>
                      <label for="tripstar5_<?= h($t['booking_id']) ?>">★</label>

                      <input type="radio" id="tripstar4_<?= h($t['booking_id']) ?>" name="driver_patient_rating" value="4">
                      <label for="tripstar4_<?= h($t['booking_id']) ?>">★</label>

                      <input type="radio" id="tripstar3_<?= h($t['booking_id']) ?>" name="driver_patient_rating" value="3">
                      <label for="tripstar3_<?= h($t['booking_id']) ?>">★</label>

                      <input type="radio" id="tripstar2_<?= h($t['booking_id']) ?>" name="driver_patient_rating" value="2">
                      <label for="tripstar2_<?= h($t['booking_id']) ?>">★</label>

                      <input type="radio" id="tripstar1_<?= h($t['booking_id']) ?>" name="driver_patient_rating" value="1">
                      <label for="tripstar1_<?= h($t['booking_id']) ?>">★</label>
                    </div>

                    <textarea class="rate-input" name="driver_patient_comment" placeholder="Optional note about the patient"></textarea>
                    <button class="btn primary" type="submit">Submit Rating</button>
                  </form>
                </div>
              <?php elseif (!empty(trim((string)$t['driver_patient_comment']))): ?>
                <div class="rated-note">Your note: <?= h($t['driver_patient_comment']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</main>

</body>
</html>