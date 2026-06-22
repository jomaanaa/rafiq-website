<?php
session_start();
require __DIR__ . '/../../pgdb/db.php';

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function provider_photo_src($path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('/^(https?:\/\/|\/)/i', $path)) return $path;
    if (strpos($path, '../../') === 0 || strpos($path, '../') === 0) return $path;
    return '../../' . ltrim($path, '/');
}

function provider_photo_db_path($path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    $path = preg_replace('#^(\.\./)+#', '', $path);
    return ltrim($path, '/');
}


function get_session_provider_id(): int {
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    if (!empty($_SESSION['provider_id'])) return (int)$_SESSION['provider_id'];
    return 0;
}

$provider_id = get_session_provider_id();

if ($provider_id <= 0) {
    header("Location: ../../general/login.php");
    exit;
}

$_SESSION['provider_type'] = 'caregiver';
$_SESSION['provider_id'] = $provider_id;

try {
    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.photo,
            p.phone,
            p.gender,
            p.dob,
            p.address,
            p.national_id,
            p.cv,
            COALESCE(p.status, 'pending') AS provider_status
            , c.shift_preference
        FROM provider p
        INNER JOIN \"user\" u ON u.user_id = p.user_id
        INNER JOIN caregiver c ON c.user_id = p.user_id
        WHERE p.user_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $provider_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Caregiver profile not found.");
    }
} catch (Exception $e) {
    die("Could not load profile: " . h($e->getMessage()));
}

$fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
$initials = strtoupper(substr($data['first_name'] ?? 'C', 0, 1) . substr($data['last_name'] ?? '', 0, 1));
if ($initials === '') $initials = 'C';

$photo = provider_photo_db_path($data['photo'] ?? '');
$photoSrc = provider_photo_src($photo);
$cvFile = trim((string)($data['cv'] ?? ''));
$cvLink = '';

if ($cvFile !== '') {
    $cvLink = '../../uploads/' . basename($cvFile);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Caregiver Profile</title>
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
    animation:fadeUp 0.5s cubic-bezier(.22,.68,0,1.2) both;
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
.avatar img{width:100%;height:100%;object-fit:cover;display:block}
.profile-title{margin:0;font-size:38px;line-height:1.1;font-weight:800}
.profile-sub{margin:10px 0 0;color:rgba(255,255,255,.80);font-weight:600}
.status-pill{
    display:inline-flex;
    align-items:center;
    margin-top:14px;
    padding:8px 14px;
    border-radius:999px;
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.18);
    color:rgba(255,255,255,.92);
    font-size:13px;
    font-weight:800;
    text-transform:capitalize;
}
.profile-body{padding:32px}
.info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
.info-box{
    padding:18px;
    border-radius:22px;
    background:#f8f9fd;
    border:1px solid var(--line);
    transition:transform 0.2s ease, box-shadow 0.2s ease;
}
.info-box:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(64,64,102,.09)}
.info-box.wide{grid-column:auto}
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
.value a{color:var(--primary);text-decoration:none}
.value a:hover{text-decoration:underline}
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
    text-decoration:none;
}
.btn:hover{transform:translateY(-2px)}
.edit{background:linear-gradient(135deg,#404066,#6d73c8);color:#fff;box-shadow:0 14px 28px rgba(64,64,102,.20)}
.logout{background:#fff1f1;color:var(--danger);border:1px solid rgba(181,53,53,.18)}
.btn.edit:hover{box-shadow:0 18px 36px rgba(64,64,102,.26)}
.btn.logout:hover{background:#ffe8e8}
@keyframes fadeUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:760px){
    .hero-content{flex-direction:column;text-align:center}
    .profile-title{font-size:28px}
    .info-grid{grid-template-columns:1fr}
    .info-box.wide{grid-column:auto}
    .actions{flex-direction:column}
    .btn{width:100%;text-align:center}
}
</style>

</head>
<body>
<?php include '../../general/nav_prov.php'; ?>

<main class="profile-page">
    <section class="profile-card">
        <div class="profile-hero">
            <div class="hero-content">
                <div class="avatar">
                    <?php if ($photo !== ''): ?>
                        <img src="<?= h($photoSrc) ?>" alt="<?= h($fullName ?: 'Caregiver photo') ?>">
                    <?php else: ?>
                        <?= h($initials) ?>
                    <?php endif; ?>
                </div>

                <div>
                    <h1 class="profile-title"><?= h($fullName ?: 'Caregiver Profile') ?></h1>
                    <p class="profile-sub"><?= h($data['email'] ?? '') ?></p>
                    <div class="status-pill"><?= h($data['provider_status'] ?? 'pending') ?></div>
                </div>
            </div>
        </div>

        <div class="profile-body">
            <div class="info-grid">
                <div class="info-box"><p class="label">First Name</p><p class="value"><?= h($data['first_name'] ?? '-') ?></p></div>
                <div class="info-box"><p class="label">Last Name</p><p class="value"><?= h($data['last_name'] ?? '-') ?></p></div>
                <div class="info-box"><p class="label">Email</p><p class="value"><?= h($data['email'] ?? '-') ?></p></div>
                <div class="info-box"><p class="label">Phone Number</p><p class="value"><?= h($data['phone'] ?? '-') ?></p></div>
                <div class="info-box"><p class="label">Date of Birth</p><p class="value"><?= h($data['dob'] ?? '-') ?></p></div>
                <div class="info-box"><p class="label">Gender</p><p class="value"><?= h($data['gender'] ?? '-') ?></p></div>
                <div class="info-box"><p class="label">Address</p><p class="value"><?= h($data['address'] ?? '-') ?></p></div>
                <div class="info-box"><p class="label">Shift Preference</p><p class="value"><?= h($data['shift_preference'] ?? '-') ?></p></div>
                <div class="info-box"><p class="label">National ID</p><p class="value"><?= h($data['national_id'] ?? '-') ?></p></div>
                <div class="info-box">
                    <p class="label">CV</p>
                    <p class="value">
                        <?php if ($cvLink !== ''): ?>
                            <a href="<?= h($cvLink) ?>" target="_blank" rel="noopener">View uploaded CV</a>
                        <?php else: ?>
                            No CV uploaded
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="actions">
                <a class="btn edit" href="caregiver_profile_edit.php">Edit Profile</a>
                <a class="btn logout" href="../../general/logout.php">Logout</a>
            </div>
        </div>
    </section>
</main>

<?php include '../../general/footer.php'; ?>
</body>
</html>
