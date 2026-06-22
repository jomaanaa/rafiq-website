<?php
session_start();

$conn = pg_connect("host=localhost port=5432 dbname=rafiq user=postgres password=123456789");
if (!$conn) die("Database connection failed");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../general/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$userQuery = pg_query_params($conn,
    'SELECT user_id, first_name, last_name, email, photo
     FROM "user"
     WHERE user_id = $1',
    [$user_id]
);

$userData = pg_fetch_assoc($userQuery);
if (!$userData) die("User not found.");

$patientQuery = pg_query_params($conn,
    'SELECT phone, gender, dob, address, disability
     FROM patient
     WHERE user_id = $1',
    [$user_id]
);

$patientData = pg_fetch_assoc($patientQuery);
if (!$patientData) die("No patient record found.");

$data = array_merge($userData, $patientData);

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function photo_src($path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    // If it is already a URL or absolute web path, use it as it is.
    if (preg_match('/^(https?:\/\/|\/)/i', $path)) {
        return $path;
    }

    // DB should save photo like: pictures/users/patient_xxx.png
    // This page is inside /patient, so the browser needs ../ before it.
    if (strpos($path, '../') === 0) {
        return $path;
    }

    return '../' . ltrim($path, '/');
}


$fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
$initials = strtoupper(substr($data['first_name'] ?? 'P', 0, 1) . substr($data['last_name'] ?? '', 0, 1));
$photo = trim((string)($data['photo'] ?? ''));
$photoSrc = photo_src($photo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Profile</title>

<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">

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
    --danger:#B53535;
    --shadow:0 20px 50px rgba(43,44,65,.10);
}

*{box-sizing:border-box}

body{
    margin:0;
    font-family:"Manrope", Arial, sans-serif;
    background:
        radial-gradient(circle at top left, rgba(109,115,200,.13), transparent 28%),
        radial-gradient(circle at bottom right, rgba(64,64,102,.10), transparent 25%),
        var(--bg);
    color:var(--text);
}

.profile-page{
    width:min(1100px, calc(100% - 32px));
    margin:36px auto 50px;
}

.profile-card{
    overflow:hidden;
    border-radius:34px;
    background:var(--card);
    box-shadow:var(--shadow);
    border:1px solid rgba(64,64,102,.08);
}

.profile-hero{
    position:relative;
    padding:38px;
    background:linear-gradient(135deg,#2B2C41 0%, #404066 55%, #6d73c8 100%);
    color:#fff;
}

.profile-hero::after{
    content:"";
    position:absolute;
    width:260px;
    height:260px;
    border-radius:50%;
    right:-80px;
    top:-100px;
    background:rgba(255,255,255,.12);
}

.hero-content{
    position:relative;
    z-index:2;
    display:flex;
    align-items:center;
    gap:24px;
}

.avatar{
    width:140px;
    height:140px;
    border-radius:34px;
    display:grid;
    place-items:center;
    background:rgba(255,255,255,.14);
    border:2px solid rgba(255,255,255,.22);
    font-size:42px;
    font-weight:800;
    box-shadow:0 18px 38px rgba(0,0,0,.18);
    overflow:hidden;
}

.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.profile-title{
    margin:0;
    font-size:38px;
    line-height:1.1;
    font-weight:800;
}

.profile-sub{
    margin:10px 0 0;
    color:rgba(255,255,255,.80);
    font-weight:600;
}

.profile-body{
    padding:32px;
}

.info-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:18px;
}

.info-box{
    padding:18px;
    border-radius:22px;
    background:#f8f9fd;
    border:1px solid var(--line);
}

.label{
    margin:0 0 8px;
    font-size:12px;
    font-weight:800;
    letter-spacing:.4px;
    text-transform:uppercase;
    color:var(--muted);
}

.value{
    margin:0;
    font-size:17px;
    font-weight:800;
    color:var(--primary-dark);
    line-height:1.55;
    word-break:break-word;
}

.actions{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:18px;
    margin-top:28px;
    width:100%;
}

.btn{
    width:150px;
    height:52px;
    border:none;
    cursor:pointer;
    padding:0;
    border-radius:16px;
    font-weight:800;
    font-family:inherit;
    font-size:14px;
    transition:.2s ease;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}

.btn:hover{
    transform:translateY(-2px);
}

.edit{
    background:linear-gradient(135deg,#404066,#6d73c8);
    color:#fff;
    box-shadow:0 14px 28px rgba(64,64,102,.20);
}

.logout{
    background:#fff1f1;
    color:var(--danger);
    border:1px solid rgba(181,53,53,.18);
}

@media(max-width:760px){
    .hero-content{ flex-direction:column; text-align:center; }
    .profile-title{ font-size:28px; }
    .info-grid{ grid-template-columns:1fr; }
    .actions{ flex-direction:column; }
    .btn{ width:100%; text-align:center; }
}

/* ── ANIMATIONS ── */
.profile-card {
    animation: fadeUp 0.5s cubic-bezier(.22,.68,0,1.2) both;
}
@keyframes fadeUp {
    from { opacity:0; transform:translateY(30px); }
    to   { opacity:1; transform:translateY(0); }
}
.info-box {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.info-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(64,64,102,.09);
}
.btn { font-size: 14px; }
.btn.edit:hover  { box-shadow: 0 18px 36px rgba(64,64,102,.26); }
.btn.logout:hover { background: #ffe8e8; }

/* ── QUICK LINKS ── */
.quick-links {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 6px;
}
.quick-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 10px;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.18);
    color: rgba(255,255,255,.90);
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.18s;
}
.quick-link:hover { background: rgba(255,255,255,.22); }
</style>
</head>

<body>

<?php include '../general/nav_patient.php'; ?>

<main class="profile-page">
    <section class="profile-card">
        <div class="profile-hero">
            <div class="hero-content">
                <div class="avatar">
                    <?php if ($photoSrc !== ''): ?>
                        <img src="<?= h($photoSrc) ?>" alt="<?= h($fullName ?: 'Patient photo') ?>">
                    <?php else: ?>
                        <?= h($initials) ?>
                    <?php endif; ?>
                </div>

                <div>
                    <h1 class="profile-title"><?= h($fullName ?: 'Patient Profile') ?></h1>
                </div>
            </div>
        </div>

        <div class="profile-body">
            <div class="info-grid">
                <div class="info-box">
                    <p class="label">First Name</p>
                    <p class="value"><?= h($data['first_name']) ?></p>
                </div>

                <div class="info-box">
                    <p class="label">Last Name</p>
                    <p class="value"><?= h($data['last_name']) ?></p>
                </div>

                <div class="info-box">
                    <p class="label">Email</p>
                    <p class="value"><?= h($data['email']) ?></p>
                </div>

                <div class="info-box">
                    <p class="label">Phone Number</p>
                    <p class="value"><?= h($data['phone']) ?></p>
                </div>

                <div class="info-box">
                    <p class="label">Date of Birth</p>
                    <p class="value"><?= h($data['dob']) ?></p>
                </div>

                <div class="info-box">
                    <p class="label">Address</p>
                    <p class="value"><?= h($data['address']) ?></p>
                </div>

                <div class="info-box">
                    <p class="label">Gender</p>
                    <p class="value"><?= h($data['gender']) ?></p>
                </div>

                <div class="info-box">
                    <p class="label">Disability</p>
                    <p class="value"><?= h($data['disability']) ?></p>
                </div>
            </div>

            <div class="actions">
                <button class="btn edit" onclick="window.location.href='patient_edit.php'">Edit Profile</button>
                <button class="btn logout" onclick="window.location.href='../general/logout.php'">Logout</button>
            </div>
        </div>
    </section>
</main>

<?php include '../general/footer.php'; ?>

</body>
</html>