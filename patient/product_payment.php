<?php
session_start();
require __DIR__ . '/../pgdb/db.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['user_id'])) {
    header("Location: ../general/login.php");
    exit;
}

$products = [
    'Smart Beeping Glasses'    => ['price' => 1499, 'img' => '../pictures/glasses.jpeg', 'desc' => 'Smart glasses that help detect nearby obstacles with gentle sound alerts for safer movement.'],
    'Emergency Alert Bracelet' => ['price' => 2999,  'img' => '../pictures/watch.jpeg',   'desc' => 'A smart bracelet that sends an emergency alert with important details quickly when needed.'],
];

$productName = $_GET['product'] ?? '';
if (!isset($products[$productName])) {
    header("Location: patient_homepage.php");
    exit;
}
$product = $products[$productName];

/* Load patient profile for auto-fill */
$patientData = ['full_name' => '', 'phone' => '', 'address' => ''];
try {
    $stmt = $pdo->prepare("
        SELECT CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS full_name,
               pt.phone, pt.address
        FROM \"user\" u
        LEFT JOIN patient pt ON pt.user_id = u.user_id
        WHERE u.user_id = :uid LIMIT 1
    ");
    $stmt->execute([':uid' => (int)$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $patientData = $row;
} catch (Exception $e) {}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $fullName  = trim((string)($_POST['full_name'] ?? ''));
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $address   = trim((string)($_POST['address'] ?? ''));
    $payMethod = trim((string)($_POST['payment'] ?? 'cash'));
    $cardHolder = trim((string)($_POST['card_holder'] ?? ''));
    $cardNumber = trim((string)($_POST['card_number'] ?? ''));
    $expiry     = trim((string)($_POST['expiry'] ?? ''));
    $cvv        = trim((string)($_POST['cvv'] ?? ''));

    if ($fullName === '') { $error = 'Please enter your full name.'; }
    elseif ($phone === '') { $error = 'Please enter your phone number.'; }
    elseif ($address === '') { $error = 'Please enter your delivery address.'; }
    elseif ($payMethod === 'visa') {
        $digits = preg_replace('/\D+/', '', $cardNumber);
        if ($cardHolder === '') { $error = 'Please enter the card holder name.'; }
        elseif (strlen($digits) < 12) { $error = 'Please enter a valid card number.'; }
        elseif (!preg_match('/^(0[1-9]|1[0-2])\/[0-9]{2}$/', $expiry)) { $error = 'Please enter a valid expiry (MM/YY).'; }
        elseif (!preg_match('/^[0-9]{3}$/', $cvv)) { $error = 'CVV must be exactly 3 digits.'; }
    }

    if ($error === '') {
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Buy <?= h($productName) ?> — Rafiq</title>

<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
:root{
    --primary:#404066;
    --primary-dark:#2B2C41;
    --primary-2:#6470d2;
    --bg:#f6f7fb;
    --card:#ffffff;
    --text:#23243a;
    --muted:#6b7188;
    --line:#e7eaf4;
    --soft:#f1f3fb;
    --green:#168653;
    --green-soft:#eefbf4;
    --red:#b53535;
    --red-soft:#fff3f3;
    --shadow:0 18px 42px rgba(36,39,66,.10);
    --shadow-lg:0 28px 70px rgba(36,39,66,.15);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0;
    font-family:'Nunito',system-ui,sans-serif;
    color:var(--text);
    background:
        radial-gradient(circle at top left, rgba(100,112,210,.16), transparent 26%),
        radial-gradient(circle at bottom right, rgba(64,64,102,.10), transparent 24%),
        linear-gradient(180deg,#fbfcff 0%, #f4f6fc 100%);
    min-height:100vh;
}
a{text-decoration:none;color:inherit}
button,input{font-family:inherit}

.page-shell{
    width:min(1120px, calc(100% - 32px));
    margin:0 auto;
    padding:28px 0 40px;
}

.checkout-header{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:18px;
    margin-bottom:20px;
}
.header-kicker{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 13px;
    border-radius:999px;
    background:#eef0ff;
    color:var(--primary);
    border:1px solid rgba(64,64,102,.10);
    font-size:12px;
    font-weight:900;
    letter-spacing:.04em;
    text-transform:uppercase;
    margin-bottom:10px;
}
.checkout-header h1{
    margin:0;
    font-size:34px;
    line-height:1.1;
    color:var(--primary-dark);
    font-weight:900;
    letter-spacing:-.7px;
}
.checkout-header p{
    margin:8px 0 0;
    color:var(--muted);
    font-size:15px;
    line-height:1.7;
    font-weight:700;
}

.checkout-layout{
    display:grid;
    grid-template-columns:minmax(0, 1fr) 380px;
    gap:22px;
    align-items:start;
}

.checkout-main,
.order-summary{
    background:rgba(255,255,255,.94);
    border:1px solid rgba(64,64,102,.08);
    border-radius:30px;
    box-shadow:var(--shadow);
}
.checkout-main{padding:24px}
.order-summary{
    position:sticky;
    top:88px;
    overflow:hidden;
}

.summary-hero{
    padding:24px;
    background:linear-gradient(135deg,#353b69 0%,#6470d2 100%);
    color:#fff;
    position:relative;
    overflow:hidden;
}
.summary-hero::before{
    content:"";
    position:absolute;
    width:210px;
    height:210px;
    border-radius:50%;
    right:-90px;
    top:-90px;
    background:rgba(255,255,255,.12);
}
.summary-product{
    position:relative;
    z-index:1;
    display:flex;
    gap:16px;
    align-items:center;
}
.product-image{
    width:112px;
    height:112px;
    border-radius:24px;
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.24);
    display:grid;
    place-items:center;
    padding:12px;
    flex:0 0 auto;
}
.product-image img{
    width:100%;
    height:100%;
    object-fit:contain;
    display:block;
}
.summary-title{
    font-size:20px;
    font-weight:900;
    line-height:1.25;
    margin-bottom:8px;
}
.summary-desc{
    font-size:13px;
    line-height:1.7;
    color:rgba(255,255,255,.82);
    font-weight:700;
}
.summary-body{padding:22px 24px 24px}
.summary-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    padding:12px 0;
    border-bottom:1px solid var(--line);
    color:#565d75;
    font-size:14px;
    font-weight:800;
}
.summary-row:last-child{border-bottom:0}
.summary-total{
    margin-top:12px;
    padding:17px;
    border-radius:20px;
    background:#f4f6fb;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
}
.summary-total span{
    color:#565d75;
    font-size:14px;
    font-weight:900;
}
.summary-total strong{
    color:var(--primary-dark);
    font-size:24px;
    font-weight:900;
}

.step-strip{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px;
    margin-bottom:20px;
}
.step-pill{
    display:flex;
    align-items:center;
    gap:10px;
    padding:13px;
    border-radius:18px;
    background:#f7f8fd;
    border:1px solid var(--line);
}
.step-num{
    width:32px;
    height:32px;
    border-radius:12px;
    display:grid;
    place-items:center;
    background:linear-gradient(135deg,#353b69,#6470d2);
    color:#fff;
    font-size:13px;
    font-weight:900;
    flex:0 0 auto;
}
.step-pill span{
    font-size:13px;
    color:var(--primary-dark);
    font-weight:900;
}

.section-card{
    border:1px solid var(--line);
    border-radius:24px;
    background:#fff;
    padding:22px;
    margin-bottom:18px;
    box-shadow:0 10px 26px rgba(36,39,66,.05);
}
.section-title{
    display:flex;
    align-items:center;
    gap:12px;
    margin:0 0 18px;
    color:var(--primary-dark);
    font-size:18px;
    font-weight:900;
}
.title-icon{
    width:40px;
    height:40px;
    border-radius:15px;
    display:grid;
    place-items:center;
    background:#eef0ff;
    color:var(--primary);
    flex:0 0 auto;
}
.field{margin-bottom:14px}
.field:last-child{margin-bottom:0}
.field label{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    color:#4b4e68;
    font-size:12px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.06em;
    margin-bottom:7px;
}
.input{
    width:100%;
    height:50px;
    border:1.5px solid rgba(64,64,102,.13);
    border-radius:16px;
    background:#f8f9fd;
    color:#23243a;
    font-size:15px;
    font-weight:800;
    outline:none;
    padding:0 15px;
    transition:.18s ease;
}
.input:focus{
    border-color:#6470d2;
    background:#fff;
    box-shadow:0 0 0 4px rgba(100,112,210,.10);
}
.row2{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}

.method-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    margin-bottom:18px;
}
.method-opt{position:relative}
.method-opt input{
    position:absolute;
    opacity:0;
    pointer-events:none;
}
.method-label{
    min-height:110px;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    gap:10px;
    padding:18px 14px;
    border-radius:20px;
    border:2px solid rgba(64,64,102,.10);
    background:#f8f9fd;
    cursor:pointer;
    color:#4a4e6a;
    font-size:14px;
    font-weight:900;
    transition:.18s ease;
    text-align:center;
}
.method-label i{font-size:28px}
.method-label:hover{
    border-color:rgba(100,112,210,.35);
    background:#f4f6ff;
    transform:translateY(-2px);
}
.method-opt input:checked + .method-label{
    border-color:#6470d2;
    background:#eef0ff;
    color:#353b69;
    box-shadow:0 10px 24px rgba(100,112,210,.15);
}

.card-block{
    display:none;
    animation:rise .22s ease both;
}
.card-block.show{display:block}
@keyframes rise{
    from{opacity:0; transform:translateY(8px)}
    to{opacity:1; transform:translateY(0)}
}
.fake-card{
    margin-bottom:18px;
    border-radius:24px;
    padding:20px;
    color:#fff;
    min-height:190px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    background:
        radial-gradient(circle at 85% 20%, rgba(255,255,255,.18), transparent 26%),
        linear-gradient(135deg,#17182d 0%,#2c315f 50%,#454a86 100%);
    box-shadow:0 18px 44px rgba(23,24,45,.24);
    overflow:hidden;
}
.fc-row,
.fc-bottom{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
}
.fc-chip{
    width:48px;
    height:34px;
    border-radius:10px;
    background:linear-gradient(135deg,#d4c8ff,#fff 50%,#9a8fd4);
}
.fc-brand{
    font-size:13px;
    font-weight:900;
    letter-spacing:1px;
}
.fc-number{
    font-size:21px;
    letter-spacing:2px;
    font-weight:900;
}
.fc-cap{
    font-size:9px;
    font-weight:900;
    opacity:.65;
    letter-spacing:.08em;
    text-transform:uppercase;
    margin-bottom:4px;
}
.fc-val{
    font-size:13px;
    font-weight:900;
}
.alert{
    display:flex;
    align-items:center;
    gap:10px;
    border-radius:18px;
    padding:14px 16px;
    font-size:14px;
    font-weight:900;
    margin-bottom:16px;
}
.alert.err{
    background:var(--red-soft);
    color:var(--red);
    border:1px solid rgba(181,53,53,.18);
}

.submit-row{
    display:grid;
    gap:12px;
}
.submit-btn{
    width:100%;
    height:58px;
    border:none;
    border-radius:20px;
    background:linear-gradient(135deg,#353b69,#6470d2);
    color:#fff;
    font-size:16px;
    font-weight:900;
    cursor:pointer;
    box-shadow:0 16px 36px rgba(53,59,105,.26);
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    transition:.2s ease;
}
.submit-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 22px 44px rgba(53,59,105,.32);
}
.secure-note{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    color:#727692;
    font-size:13px;
    font-weight:800;
}

.success-wrap{
    width:min(740px, calc(100% - 32px));
    margin:28px auto 42px;
}
.success-card{
    background:#ffffff;
    border-radius:34px;
    padding:48px 42px;
    text-align:center;
    box-shadow:var(--shadow-lg);
    border:1px solid #eef1f8;
}
.success-mark{
    width:78px;
    height:78px;
    margin:0 auto 24px;
    border-radius:26px;
    background:linear-gradient(135deg,#353b69,#6470d2);
    color:#fff;
    display:grid;
    place-items:center;
    font-size:32px;
    box-shadow:0 16px 34px rgba(53,59,105,.24);
}
.success-card h1{
    margin:0 0 14px;
    color:#242742;
    font-size:32px;
    font-weight:900;
    letter-spacing:-.5px;
}
.success-card p{
    margin:0 auto 8px;
    max-width:560px;
    color:#6b7188;
    font-size:17px;
    line-height:1.8;
    font-weight:700;
}
.success-card strong{
    color:#353b69;
    font-weight:900;
}
.back-home-btn{
    margin-top:28px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    min-width:190px;
    height:54px;
    border-radius:17px;
    background:linear-gradient(135deg,#353b69,#6470d2);
    color:#fff;
    font-size:15px;
    font-weight:900;
    box-shadow:0 14px 30px rgba(53,59,105,.24);
    transition:.2s ease;
}

@media(max-width:980px){
    .checkout-layout{grid-template-columns:1fr}
    .order-summary{position:relative;top:auto;order:-1}
}
@media(max-width:620px){
    .page-shell{width:min(100% - 20px,1120px);padding-top:18px}
    .checkout-header{align-items:flex-start;flex-direction:column}
    .checkout-header h1{font-size:28px}
    .checkout-main{padding:18px;border-radius:24px}
    .step-strip{grid-template-columns:1fr}
    .method-grid,.row2{grid-template-columns:1fr}
    .summary-product{flex-direction:column;text-align:center}
    .summary-title{font-size:19px}
    .success-card{padding:34px 22px;border-radius:26px}
    .success-card h1{font-size:26px}
}
</style>
</head>

<body>
<?php include '../general/nav_patient.php'; ?>

<?php if ($success): ?>
<div class="success-wrap">
    <div class="success-card">
        <div class="success-mark">
            <i class="fa-solid fa-check"></i>
        </div>

        <h1>Order Placed Successfully</h1>

        <p>
            Thank you for your order. Your <strong><?= h($productName) ?></strong>
            will be delivered soon.
        </p>

        <p>
            Our team will contact you to confirm the delivery details.
        </p>
    </div>
</div>

<?php else: ?>

<main class="page-shell">
    <div class="checkout-header">
        <div>
            <h1>Complete your order</h1>
            <p>Review your product, confirm delivery details, and choose your preferred payment method.</p>
        </div>
    </div>

    <form method="POST" novalidate>
        <div class="checkout-layout">

            <section class="checkout-main">

                <?php if ($error): ?>
                    <div class="alert err">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>

                <div class="section-card">
                    <h2 class="section-title">
                        <span class="title-icon"><i class="fa-solid fa-truck"></i></span>
                        Delivery Information
                    </h2>

                    <div class="field">
                        <label>Full Name</label>
                        <input class="input" type="text" name="full_name"
                               value="<?= h($_POST['full_name'] ?? trim((string)$patientData['full_name'])) ?>"
                               placeholder="Your full name" required>
                    </div>

                    <div class="field">
                        <label>Phone Number</label>
                        <input class="input" type="tel" name="phone"
                               value="<?= h($_POST['phone'] ?? trim((string)$patientData['phone'])) ?>"
                               placeholder="01XXXXXXXXX" required>
                    </div>

                    <div class="field">
                        <label>Delivery Address</label>
                        <input class="input" type="text" name="address"
                               value="<?= h($_POST['address'] ?? trim((string)$patientData['address'])) ?>"
                               placeholder="Your full delivery address" required>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="section-title">
                        <span class="title-icon"><i class="fa-solid fa-credit-card"></i></span>
                        Payment Method
                    </h2>

                    <div class="method-grid">
                        <div class="method-opt">
                            <input type="radio" name="payment" id="pay-cash" value="cash"
                                   <?= (($_POST['payment'] ?? 'cash') === 'cash') ? 'checked' : '' ?>>
                            <label class="method-label" for="pay-cash">
                                <i class="fa-solid fa-money-bill-wave" style="color:#168653"></i>
                                Cash on Delivery
                            </label>
                        </div>

                        <div class="method-opt">
                            <input type="radio" name="payment" id="pay-visa" value="visa"
                                   <?= (($_POST['payment'] ?? '') === 'visa') ? 'checked' : '' ?>>
                            <label class="method-label" for="pay-visa">
                                <i class="fa-brands fa-cc-visa" style="color:#3147c7"></i>
                                Visa / Card
                            </label>
                        </div>
                    </div>

                    <div class="card-block" id="cardBlock">
                        <div class="fake-card">
                            <div class="fc-row">
                                <div class="fc-chip"></div>
                                <div class="fc-brand" id="fcBrand">CARD</div>
                            </div>

                            <div class="fc-number" id="fcNumber">•••• •••• •••• ••••</div>

                            <div class="fc-bottom">
                                <div>
                                    <div class="fc-cap">Card Holder</div>
                                    <div class="fc-val" id="fcHolder">YOUR NAME</div>
                                </div>
                                <div>
                                    <div class="fc-cap">Expiry</div>
                                    <div class="fc-val" id="fcExpiry">MM/YY</div>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label>Card Holder Name</label>
                            <input class="input" type="text" name="card_holder" id="card_holder"
                                   value="<?= h($_POST['card_holder'] ?? '') ?>" placeholder="Name on card">
                        </div>

                        <div class="field">
                            <label>Card Number</label>
                            <input class="input" type="text" name="card_number" id="card_number"
                                   value="<?= h($_POST['card_number'] ?? '') ?>"
                                   placeholder="1234 5678 9012 3456" maxlength="23" inputmode="numeric">
                        </div>

                        <div class="row2">
                            <div class="field">
                                <label>Expiry</label>
                                <input class="input" type="text" name="expiry" id="expiry"
                                       value="<?= h($_POST['expiry'] ?? '') ?>" placeholder="MM/YY" maxlength="5">
                            </div>

                            <div class="field">
                                <label>CVV</label>
                                <input class="input" type="password" name="cvv" id="cvv"
                                       value="<?= h($_POST['cvv'] ?? '') ?>" placeholder="123"
                                       maxlength="3" minlength="3" pattern="[0-9]{3}" inputmode="numeric">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="submit-row">
                    <button class="submit-btn" type="submit" name="confirm">
                        <i class="fa-solid fa-bag-shopping"></i>
                        Confirm Order — <?= number_format($product['price']) ?> EGP
                    </button>

                    <div class="secure-note">
                        <i class="fa-solid fa-lock"></i>
                        Your order details are handled securely.
                    </div>
                </div>
            </section>

            <aside class="order-summary">
                <div class="summary-hero">
                    <div class="summary-product">
                        <div class="product-image">
                            <img src="<?= h($product['img']) ?>" alt="<?= h($productName) ?>">
                        </div>

                        <div>
                            <div class="summary-title"><?= h($productName) ?></div>
                            <div class="summary-desc"><?= h($product['desc']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="summary-body">
                    <div class="summary-row">
                        <span>Product price</span>
                        <strong><?= number_format($product['price']) ?> EGP</strong>
                    </div>

                    <div class="summary-row">
                        <span>Delivery</span>
                        <strong>To be confirmed</strong>
                    </div>

                    <div class="summary-row">
                        <span>Payment</span>
                        <strong id="summaryPayment">Cash</strong>
                    </div>

                    <div class="summary-total">
                        <span>Total</span>
                        <strong><?= number_format($product['price']) ?> EGP</strong>
                    </div>
                </div>
            </aside>

        </div>
    </form>
</main>
<?php endif; ?>

<?php include '../general/footer.php'; ?>

<script>
(function(){
    const cashRadio = document.getElementById('pay-cash');
    const visaRadio = document.getElementById('pay-visa');
    const cardBlock = document.getElementById('cardBlock');
    const cardNumber= document.getElementById('card_number');
    const cardHolder= document.getElementById('card_holder');
    const expiry    = document.getElementById('expiry');
    const cvv       = document.getElementById('cvv');
    const fcNumber  = document.getElementById('fcNumber');
    const fcHolder  = document.getElementById('fcHolder');
    const fcExpiry  = document.getElementById('fcExpiry');
    const fcBrand   = document.getElementById('fcBrand');
    const summaryPayment = document.getElementById('summaryPayment');

    function toggleCard(){
        const isVisa = visaRadio && visaRadio.checked;
        if(cardBlock) cardBlock.classList.toggle('show', isVisa);
        if(summaryPayment) summaryPayment.textContent = isVisa ? 'Visa / Card' : 'Cash';
    }

    cashRadio && cashRadio.addEventListener('change', toggleCard);
    visaRadio && visaRadio.addEventListener('change', toggleCard);
    toggleCard();

    function detectBrand(num){
        const d = (num||'').replace(/\D+/g,'');
        if(/^4/.test(d)) return 'VISA';
        if(/^(5[1-5]|2[2-7])/.test(d)) return 'MASTERCARD';
        if(/^3[47]/.test(d)) return 'AMEX';
        return 'CARD';
    }

    function fmtCard(v){
        return (v||'').replace(/\D+/g,'').substring(0,19).replace(/(.{4})/g,'$1 ').trim();
    }

    function fmtExpiry(v){
        const d=(v||'').replace(/\D+/g,'').substring(0,4);
        return d.length<=2 ? d : d.slice(0,2)+'/'+d.slice(2);
    }

    cardNumber && cardNumber.addEventListener('input', function(){
        this.value = fmtCard(this.value);
        if(fcNumber) fcNumber.textContent = this.value || '•••• •••• •••• ••••';
        if(fcBrand) fcBrand.textContent = detectBrand(this.value);
    });

    cardHolder && cardHolder.addEventListener('input', function(){
        if(fcHolder) fcHolder.textContent = (this.value||'YOUR NAME').toUpperCase();
    });

    expiry && expiry.addEventListener('input', function(){
        this.value = fmtExpiry(this.value);
        if(fcExpiry) fcExpiry.textContent = this.value || 'MM/YY';
    });

    cvv && cvv.addEventListener('input', function(){
        this.value = (this.value || '').replace(/\D+/g, '').substring(0, 3);
    });

    if(cardNumber) cardNumber.dispatchEvent(new Event('input'));
    if(cardHolder) cardHolder.dispatchEvent(new Event('input'));
    if(expiry) expiry.dispatchEvent(new Event('input'));
    if(cvv) cvv.dispatchEvent(new Event('input'));
})();
</script>
</body>
</html>
