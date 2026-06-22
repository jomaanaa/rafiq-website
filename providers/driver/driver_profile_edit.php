<?php
session_start();
require __DIR__ . '/../../pgdb/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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

$_SESSION['provider_type'] = 'driver';
$_SESSION['provider_id'] = $provider_id;

$uploadDir = __DIR__ . '/../../pictures/providers/';
$uploadUrl = 'pictures/providers/';

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

$cvUploadDir = __DIR__ . '/../../uploads/';
$cvUploadUrl = 'uploads/';

if (!is_dir($cvUploadDir)) {
    @mkdir($cvUploadDir, 0777, true);
}

function fetchProfile(PDO $pdo, int $id): ?array {
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
            , d.driving_license
        FROM provider p
        INNER JOIN \"user\" u ON u.user_id = p.user_id
        INNER JOIN driver d ON d.user_id = p.user_id
        WHERE p.user_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$error = "";
$data = fetchProfile($pdo, $provider_id);

if (!$data) {
    die("Driver profile not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name  = trim((string)($_POST['last_name'] ?? ''));
    $email      = trim((string)($_POST['email'] ?? ''));
    $phone      = trim((string)($_POST['phone'] ?? ''));
    $dob        = trim((string)($_POST['dob'] ?? ''));
    $address    = trim((string)($_POST['address'] ?? ''));
    $gender     = trim((string)($_POST['gender'] ?? ''));
    $drivingLicensePath = trim((string)($data['driving_license'] ?? ''));

    $photoPath = trim((string)($data['photo'] ?? ''));
    $cvPath = trim((string)($data['cv'] ?? ''));

    if ($first_name === '' || $last_name === '' || $email === '' || $phone === '' || $address === '' || $gender === '') {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^01[0125][0-9]{8}$/', $phone)) {
        $error = "Phone number must be 11 digits and start with 010, 011, 012, or 015.";
    }  else {
        try {
            $emailCheck = $pdo->prepare('
                SELECT user_id
                FROM "user"
                WHERE LOWER(email) = LOWER(:email)
                  AND user_id <> :id
                LIMIT 1
            ');
            $emailCheck->execute([
                ':email' => $email,
                ':id' => $provider_id
            ]);

            if ($emailCheck->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("This email is already used by another account.");
            }

            if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                $allowed = ['jpg','jpeg','png','webp'];
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed, true)) {
                    throw new Exception("Profile photo must be JPG, PNG, or WEBP.");
                }

                if ((int)$_FILES['photo']['size'] > 3 * 1024 * 1024) {
                    throw new Exception("Profile photo must be less than 3MB.");
                }

                $fileName = 'driver_' . $provider_id . '_' . time() . '.' . $ext;
                $target = $uploadDir . $fileName;

                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                    throw new Exception("Could not upload profile photo.");
                }

                $photoPath = $uploadUrl . $fileName;
            }

            if (!empty($_FILES['cv']['name']) && is_uploaded_file($_FILES['cv']['tmp_name'])) {
                $allowedCv = ['pdf', 'doc', 'docx'];
                $cvExt = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));

                if (!in_array($cvExt, $allowedCv, true)) {
                    throw new Exception("CV must be PDF, DOC, or DOCX.");
                }

                if ((int)$_FILES['cv']['size'] > 5 * 1024 * 1024) {
                    throw new Exception("CV file must be less than 5MB.");
                }

                $cvFileName = 'cv_driver_' . $provider_id . '_' . time() . '.' . $cvExt;
                $cvTarget = $cvUploadDir . $cvFileName;

                if (!move_uploaded_file($_FILES['cv']['tmp_name'], $cvTarget)) {
                    throw new Exception("Could not upload CV file.");
                }

                $cvPath = $cvUploadUrl . $cvFileName;
            }

            
            if (!empty($_FILES['driving_license']['name']) && is_uploaded_file($_FILES['driving_license']['tmp_name'])) {
                $allowedLicense = ['pdf', 'jpg', 'jpeg', 'png'];
                $licenseExt = strtolower(pathinfo($_FILES['driving_license']['name'], PATHINFO_EXTENSION));

                if (!in_array($licenseExt, $allowedLicense, true)) {
                    throw new Exception("Driving license must be PDF, JPG, JPEG, or PNG.");
                }

                if ((int)$_FILES['driving_license']['size'] > 5 * 1024 * 1024) {
                    throw new Exception("Driving license must be less than 5MB.");
                }

                $licenseName = 'driving_license_driver_' . $provider_id . '_' . time() . '.' . $licenseExt;
                $licenseTarget = $cvUploadDir . $licenseName;

                if (!move_uploaded_file($_FILES['driving_license']['tmp_name'], $licenseTarget)) {
                    throw new Exception("Could not upload driving license.");
                }

                $drivingLicensePath = $cvUploadUrl . $licenseName;
            }


            $pdo->beginTransaction();

            $updateUser = $pdo->prepare('
                UPDATE "user"
                SET first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    photo = :photo
                WHERE user_id = :id
            ');
            $updateUser->execute([
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':photo' => $photoPath,
                ':id' => $provider_id
            ]);

            $updateProvider = $pdo->prepare("
                UPDATE provider
                SET phone = :phone,
                    gender = :gender,
                    dob = :dob,
                    address = :address,
                    cv = :cv
                WHERE user_id = :id
            ");
            $updateProvider->execute([
                ':phone' => $phone,
                ':gender' => $gender,
                ':dob' => $dob !== '' ? $dob : null,
                ':address' => $address,
                ':cv' => $cvPath,
                ':id' => $provider_id
            ]);

            $updateDriver = $pdo->prepare("
                UPDATE driver
                SET driving_license = :driving_license
                WHERE user_id = :id
            ");
            $updateDriver->execute([
                ':driving_license' => $drivingLicensePath,
                ':id' => $provider_id
            ]);

            $pdo->commit();

            $_SESSION['Name'] = trim($first_name . ' ' . $last_name);
            $_SESSION['email'] = $email;

            header("Location: driver_profile.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    $data = array_merge($data, [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'dob' => $dob,
        'address' => $address,
        'gender' => $gender,
        'photo' => $photoPath,
        'cv' => $cvPath,
    ]);
}

$fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
$initials = strtoupper(substr($data['first_name'] ?? 'D', 0, 1) . substr($data['last_name'] ?? '', 0, 1));
if ($initials === '') $initials = 'D';
$photo = provider_photo_db_path($data['photo'] ?? '');
$photoSrc = provider_photo_src($photo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Driver Profile</title>
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
.profile-page{width:min(1100px, calc(100% - 32px));margin:36px auto 50px}
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
.hero-content{position:relative;z-index:2;display:flex;align-items:center;gap:24px}
.avatar{
    width:140px;height:140px;border-radius:34px;display:grid;place-items:center;
    background:rgba(255,255,255,.14);border:2px solid rgba(255,255,255,.22);
    font-size:42px;font-weight:800;box-shadow:0 18px 38px rgba(0,0,0,.18);overflow:hidden;
}
.avatar img{width:100%;height:100%;object-fit:cover;display:block}
.profile-title{margin:0;font-size:38px;line-height:1.1;font-weight:800}
.profile-sub{margin:10px 0 0;color:rgba(255,255,255,.80);font-weight:600}
.profile-body{padding:32px}
.error-box{
    padding:15px 17px;border-radius:18px;background:#fff1f1;color:var(--danger);
    border:1px solid rgba(181,53,53,.18);font-size:14px;font-weight:800;margin-bottom:20px;
}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
.form-box{padding:18px;border-radius:22px;background:#f8f9fd;border:1px solid var(--line)}
.form-box.wide{grid-column:auto}
label{
    display:block;margin:0 0 8px;font-size:12px;font-weight:800;
    letter-spacing:.4px;text-transform:uppercase;color:var(--muted);
}
input,select,textarea{
    width:100%;min-height:48px;border:none;outline:none;background:transparent;color:var(--primary-dark);
    font-family:inherit;font-size:16px;font-weight:800;line-height:1.55;
}
textarea{min-height:92px;resize:vertical}
input[type="file"]{padding-top:10px;font-size:13px}
.locked{color:#8a8fa5}
.note{margin-top:8px;color:var(--muted);font-size:12px;line-height:1.6;font-weight:700}
.actions{display:flex;justify-content:center;align-items:center;gap:18px;margin-top:28px;width:100%}
.btn{
    width:150px;height:52px;border:none;cursor:pointer;padding:0;border-radius:16px;font-weight:800;
    font-family:inherit;font-size:14px;transition:.2s ease;display:inline-flex;align-items:center;
    justify-content:center;text-decoration:none;
}
.btn:hover{transform:translateY(-2px)}
.save{background:linear-gradient(135deg,#404066,#6d73c8);color:#fff;box-shadow:0 14px 28px rgba(64,64,102,.20)}
.cancel{background:#fff;color:var(--primary-dark);border:1px solid var(--line)}
.btn.save:hover{box-shadow:0 18px 36px rgba(64,64,102,.26)}
@keyframes fadeUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:760px){
    .info-grid{grid-template-columns:1fr}
    .form-grid{grid-template-columns:1fr}
    .hero-content{flex-direction:column;text-align:center}
    .profile-title{font-size:28px}
    .form-grid{grid-template-columns:1fr}
    .form-box.wide{grid-column:auto}
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
                        <img src="<?= h($photoSrc) ?>" alt="<?= h($fullName ?: 'Driver photo') ?>">
                    <?php else: ?>
                        <?= h($initials) ?>
                    <?php endif; ?>
                </div>

                <div>
                    <h1 class="profile-title">Edit Profile</h1>
                    <p class="profile-sub"><?= h($fullName ?: 'Driver Profile') ?></p>
                </div>
            </div>
        </div>

        <div class="profile-body">
            <?php if ($error): ?>
                <div class="error-box"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-box"><label for="first_name">First Name</label><input type="text" id="first_name" name="first_name" required value="<?= h($data['first_name'] ?? '') ?>"></div>
                    <div class="form-box"><label for="last_name">Last Name</label><input type="text" id="last_name" name="last_name" required value="<?= h($data['last_name'] ?? '') ?>"></div>
                    <div class="form-box"><label for="email">Email</label><input type="email" id="email" name="email" required value="<?= h($data['email'] ?? '') ?>"></div>
                    <div class="form-box"><label for="phone">Phone Number</label><input type="text" id="phone" name="phone" required value="<?= h($data['phone'] ?? '') ?>" maxlength="11" inputmode="numeric"></div>
                    <div class="form-box"><label for="dob">Date of Birth</label><input type="date" id="dob" name="dob" value="<?= h($data['dob'] ?? '') ?>"></div>
                    <div class="form-box">
                        <label for="gender">Gender</label>
                        <?php $g = strtolower((string)($data['gender'] ?? '')); ?>
                        <select id="gender" name="gender" required>
                            <option value="">Select gender</option>
                            <option value="male" <?= $g === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $g === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-box"><label for="address">Address</label><textarea id="address" name="address" required><?= h($data['address'] ?? '') ?></textarea></div>
                    <div class="form-box">
                        <label for="driving_license">Driving License</label>
                        <input type="file" id="driving_license" name="driving_license" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if (!empty($data['driving_license'])): ?>
                            <div class="note">Current: <a href="../../uploads/<?= h(basename($data['driving_license'])) ?>" target="_blank" rel="noopener">View driving license</a></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-box">
                        <label>National ID</label>
                        <input class="locked" type="text" value="<?= h($data['national_id'] ?? '') ?>" disabled>
                        <div class="note">This field cannot be edited from this page.</div>
                    </div>

                    <div class="form-box">
                        <label for="photo">Profile Photo</label>
                        <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp">
                        <div class="note">Accepted formats: JPG, PNG, WEBP. Maximum size: 3MB.</div>
                    </div>

                    <div class="form-box">
                        <label for="cv">CV Document</label>
                        <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx">
                        <div class="note">Accepted formats: PDF, DOC, DOCX. Maximum size: 5MB.</div>
                        <?php if (!empty($data['cv'])): ?>
                            <div class="note">Current CV: <a href="../../uploads/<?= h(basename($data['cv'])) ?>" target="_blank" rel="noopener">View uploaded CV</a></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="actions">
                    <a class="btn cancel" href="driver_profile.php">Cancel</a>
                    <button class="btn save" type="submit">Save</button>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include '../../general/footer.php'; ?>
</body>
</html>
