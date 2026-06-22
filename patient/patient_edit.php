<?php
session_start();

$conn = pg_connect("host=localhost port=5432 dbname=rafiq user=postgres password=123456789");
if(!$conn){
    die("Database connection failed");
}

if(!isset($_SESSION['user_id'])){
    header("Location: ../general/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = "";

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


if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $dob         = trim($_POST['dob'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $disability  = trim($_POST['disability'] ?? '');

    $photoPath = null;

    if(isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE){
        if($_FILES['photo']['error'] !== UPLOAD_ERR_OK){
            $error = "Photo upload failed. Please try again.";
        } else {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $tmpPath = $_FILES['photo']['tmp_name'];
            $mime = mime_content_type($tmpPath);

            if(!isset($allowedTypes[$mime])){
                $error = "Please upload a JPG, PNG, or WEBP image.";
            } elseif($_FILES['photo']['size'] > 2 * 1024 * 1024){
                $error = "Photo must be less than 2MB.";
            } else {
                $uploadDir = __DIR__ . '/../pictures/users';

                if(!is_dir($uploadDir)){
                    mkdir($uploadDir, 0777, true);
                }

                $ext = $allowedTypes[$mime];
                $fileName = 'patient_' . $user_id . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . '/' . $fileName;

                if(move_uploaded_file($tmpPath, $targetPath)){
                    $photoPath = 'pictures/users/' . $fileName;
                } else {
                    $error = "Could not save the uploaded photo.";
                }
            }
        }
    }

    if($error === ""){
        if($photoPath !== null){
            $updateUser = pg_query_params($conn,
                "UPDATE \"user\"
                 SET first_name=$1, last_name=$2, email=$3, photo=$4
                 WHERE user_id=$5",
                [$first_name, $last_name, $email, $photoPath, $user_id]
            );
        } else {
            $updateUser = pg_query_params($conn,
                "UPDATE \"user\"
                 SET first_name=$1, last_name=$2, email=$3
                 WHERE user_id=$4",
                [$first_name, $last_name, $email, $user_id]
            );
        }

        $updatePatient = pg_query_params($conn,
            "UPDATE patient
             SET phone=$1, gender=$2, dob=$3, address=$4, disability=$5
             WHERE user_id=$6",
            [$phone, $gender, $dob, $address, $disability, $user_id]
        );

        if($updateUser && $updatePatient){
            header("Location: patient_profile.php");
            exit;
        } else {
            $error = "Update failed. Please try again.";
        }
    }
}

$query = pg_query_params($conn,
"SELECT
    u.first_name,
    u.last_name,
    u.email,
    u.photo,
    p.phone,
    p.gender,
    p.dob,
    p.address,
    p.disability
FROM \"user\" u
JOIN patient p ON u.user_id = p.user_id
WHERE u.user_id = $1",
[$user_id]);

$data = pg_fetch_assoc($query);
if(!$data){
    die("No patient data found.");
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
<title>Edit Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

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
    --shadow:0 18px 42px rgba(43,44,65,.10);
}

*{
    box-sizing:border-box;
}

html,
body{
    margin:0;
    min-height:100%;
}

body{
    font-family:"Manrope", Arial, sans-serif;
    background:
        radial-gradient(circle at top left, rgba(109,115,200,.13), transparent 28%),
        radial-gradient(circle at bottom right, rgba(64,64,102,.10), transparent 25%),
        var(--bg);
    color:var(--text);
}

.edit-page{
    width:min(1050px, calc(100% - 32px));
    margin:24px auto 36px;
}

.edit-card{
    overflow:hidden;
    border-radius:30px;
    background:var(--card);
    box-shadow:var(--shadow);
    border:1px solid rgba(64,64,102,.08);
}

.edit-hero{
    position:relative;
    padding:26px 32px;
    background:linear-gradient(135deg,#2B2C41 0%, #404066 55%, #6d73c8 100%);
    color:#fff;
}

.edit-hero::after{
    content:"";
    position:absolute;
    width:220px;
    height:220px;
    border-radius:50%;
    right:-70px;
    top:-100px;
    background:rgba(255,255,255,.12);
}

.hero-content{
    position:relative;
    z-index:2;
    display:flex;
    align-items:center;
    gap:18px;
}

.avatar{
    width:88px;
    height:88px;
    border-radius:24px;
    display:grid;
    place-items:center;
    background:rgba(255,255,255,.14);
    border:2px solid rgba(255,255,255,.22);
    font-size:28px;
    font-weight:800;
    box-shadow:0 14px 28px rgba(0,0,0,.16);
    flex:0 0 auto;
    overflow:hidden;
}

.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.title{
    margin:0;
    font-size:30px;
    line-height:1.1;
    font-weight:800;
}

.subtitle{
    margin:6px 0 0;
    color:rgba(255,255,255,.80);
    font-weight:600;
    font-size:14px;
}

.form-body{
    padding:28px 32px 30px;
}

.err{
    margin-bottom:18px;
    padding:14px 16px;
    border-radius:16px;
    background:#fff1f1;
    color:var(--danger);
    border:1px solid rgba(181,53,53,.18);
    font-weight:800;
}

.row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.field{
    display:flex;
    flex-direction:column;
    gap:8px;
}

label{
    font-weight:800;
    font-size:13px;
    color:#2B2C41;
}

input,
select{
    width:100%;
    min-height:58px;
    padding:16px 18px;
    border:1px solid #e7e9f2;
    border-radius:22px;
    background:#f8f9fd;
    color:#2B2C41;
    font-family:inherit;
    font-size:17px;
    font-weight:800;
    outline:none;
    transition:.2s ease;
}

input:focus,
select:focus{
    background:#ffffff;
    border-color:rgba(109,115,200,.55);
    box-shadow:0 0 0 5px rgba(109,115,200,.12);
}

.photo-field{
    margin-bottom:22px;
}

.photo-upload{
    display:flex;
    align-items:center;
    gap:18px;
    padding:16px;
    border:1px solid var(--line);
    border-radius:22px;
    background:#f8f9fd;
}

.photo-preview{
    width:82px;
    height:82px;
    border-radius:22px;
    display:grid;
    place-items:center;
    overflow:hidden;
    background:linear-gradient(135deg,#404066,#6d73c8);
    color:#fff;
    font-size:24px;
    font-weight:800;
    flex:0 0 auto;
}

.photo-preview img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.photo-upload-text{
    flex:1;
}

.photo-upload-text strong{
    display:block;
    color:var(--primary-dark);
    font-size:15px;
    margin-bottom:6px;
}

.photo-upload-text span{
    display:block;
    color:var(--muted);
    font-size:13px;
    font-weight:700;
    margin-bottom:10px;
}

.photo-upload input{
    width:100%;
    min-height:auto;
    padding:0;
    border:none;
    background:transparent;
    border-radius:0;
    font-size:13px;
    font-weight:700;
}

.actions{
    margin-top:28px;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:16px;
    flex-wrap:wrap;
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

.save{
    background:linear-gradient(135deg,#404066,#6d73c8);
    color:#fff;
    box-shadow:0 12px 24px rgba(64,64,102,.20);
}

.cancel{
    background:#f1f4fb;
    color:var(--primary-dark);
    border:1px solid var(--line);
}

@media(max-width:760px){
    .edit-page{
        margin:18px auto 28px;
    }

    .hero-content{
        flex-direction:column;
        text-align:center;
    }

    .title{
        font-size:28px;
    }

    .row{
        grid-template-columns:1fr;
    }

    .photo-upload{
        flex-direction:column;
        text-align:center;
    }

    .actions{
        flex-direction:column;
    }

    .btn{
        width:100%;
    }
}
</style>
</head>

<body>

<?php include '../general/nav_patient.php'; ?>

<main class="edit-page">
    <section class="edit-card">
        <div class="edit-hero">
            <div class="hero-content">
                <div class="avatar">
                    <?php if($photoSrc !== ''): ?>
                        <img src="<?= h($photoSrc) ?>" alt="<?= h($fullName ?: 'Profile photo') ?>">
                    <?php else: ?>
                        <?= h($initials) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="title">Edit Profile</h1>
                    <p class="subtitle"><?= h($fullName ?: 'Update your personal information') ?></p>
                </div>
            </div>
        </div>

        <div class="form-body">
            <?php if(!empty($error)): ?>
                <div class="err"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="photo-field">
                    <label>Profile Photo</label>
                    <div class="photo-upload">
                        <div class="photo-preview">
                            <?php if($photoSrc !== ''): ?>
                                <img src="<?= h($photoSrc) ?>" alt="<?= h($fullName ?: 'Profile photo') ?>">
                            <?php else: ?>
                                <?= h($initials) ?>
                            <?php endif; ?>
                        </div>
                        <div class="photo-upload-text">
                            <strong>Upload a new photo</strong>
                            <span>JPG, PNG, or WEBP. Maximum size 2MB.</span>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="field">
                        <label>First Name</label>
                        <input name="first_name" value="<?= h($data['first_name']) ?>" required>
                    </div>

                    <div class="field">
                        <label>Last Name</label>
                        <input name="last_name" value="<?= h($data['last_name']) ?>" required>
                    </div>

                    <div class="field">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= h($data['email']) ?>" required>
                    </div>

                    <div class="field">
                        <label>Phone</label>
                        <input name="phone" value="<?= h($data['phone']) ?>">
                    </div>

                    <div class="field">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">-- Select --</option>
                            <option value="male" <?= strtolower($data['gender']) === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= strtolower($data['gender']) === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" value="<?= h($data['dob']) ?>">
                    </div>

                    <div class="field">
                        <label>Address</label>
                        <input name="address" value="<?= h($data['address']) ?>">
                    </div>

                    <div class="field">
                        <label>Disability</label>
                        <input name="disability" value="<?= h($data['disability']) ?>">
                    </div>
                </div>

                <div class="actions">
                    <button class="btn save" type="submit">Save Changes</button>
                    <button class="btn cancel" type="button" onclick="window.location.href='patient_profile.php'">Cancel</button>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include '../general/footer.php'; ?>

</body>
</html>