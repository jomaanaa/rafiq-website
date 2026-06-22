<?php
session_start();

if (
    !isset($_SESSION['step1']) ||
    !isset($_SESSION['step2']) ||
    !isset($_SESSION['provider_type']) ||
    $_SESSION['provider_type'] !== 'driver'
) {
    header("Location: ../signup2_provider.php");
    exit();
}

$conn = pg_connect("host=localhost dbname=rafiq user=postgres password=123456789");
if (!$conn) {
    die("Database connection failed.");
}

$error = "";

if (isset($_POST['finish'])) {

    if (!isset($_FILES['driving_license']) || $_FILES['driving_license']['error'] !== 0) {
        $error = "Please upload your driving license.";
    } else {

        $fname = trim($_SESSION['step1']['fname'] ?? '');
        $lname = trim($_SESSION['step1']['lname'] ?? '');
        $email = trim($_SESSION['step1']['email'] ?? '');
        $pass  = $_SESSION['step1']['pass'] ?? '';
        $photo = $_SESSION['step1']['photo'] ?? null;

        $address     = trim($_SESSION['step2']['address'] ?? '');
        $gender      = trim($_SESSION['step2']['gender'] ?? '');
        $phone       = trim($_SESSION['step2']['phone'] ?? '');
        $national_id = trim($_SESSION['step2']['national_id'] ?? '');
        $dob         = trim($_SESSION['step2']['dob'] ?? '');
        $cv_path     = trim($_SESSION['step2']['cv'] ?? '');

        if ($fname === '' || $lname === '' || $email === '' || $pass === '') {
            $error = "Missing account data.";
        } elseif ($address === '' || $gender === '' || $phone === '' || $national_id === '' || $dob === '') {
            $error = "Missing personal data.";
        } else {

            pg_query($conn, "BEGIN");

            if (empty($photo)) {
            $error = "Signup session is missing the provider photo. Please go back and upload your photo.";
        } else {
        $insert_user = pg_query_params(
                $conn,
                'INSERT INTO "user" (first_name, last_name, email, password, role, photo)
                 VALUES ($1, $2, $3, $4, $5, $6)
                 RETURNING user_id',
                array($fname, $lname, $email, $pass, 'provider', $photo)
            );

            if (!$insert_user) {
                pg_query($conn, "ROLLBACK");
                die("User insert failed: " . pg_last_error($conn));
            }

            $row = pg_fetch_assoc($insert_user);
            $user_id = (int)$row['user_id'];

            if (!is_dir("../../uploads")) {
                mkdir("../../uploads", 0777, true);
            }

            $license_name = uniqid("license_", true) . "_" . basename($_FILES['driving_license']['name']);
            $license_disk_path = "../../uploads/" . $license_name;
            $license_path = "uploads/" . $license_name;

            if (!move_uploaded_file($_FILES['driving_license']['tmp_name'], $license_disk_path)) {
                pg_query($conn, "ROLLBACK");
                die("Failed to upload driving license.");
            }

            $insert_provider = pg_query_params(
                $conn,
                'INSERT INTO provider (user_id, national_id, gender, dob, address, phone, cv, status)
                 OVERRIDING SYSTEM VALUE
                 VALUES ($1, $2, $3, $4, $5, $6, $7, \'pending\')',
                array($user_id, $national_id, $gender, $dob, $address, $phone, $cv_path)
            );

            if (!$insert_provider) {
                pg_query($conn, "ROLLBACK");
                die("Provider insert failed: " . pg_last_error($conn));
            }

            $insert_driver = pg_query_params(
                $conn,
                'INSERT INTO driver (user_id, driving_license)
                 OVERRIDING SYSTEM VALUE
                 VALUES ($1, $2)',
                array($user_id, $license_path)
            );

            if (!$insert_driver) {
                pg_query($conn, "ROLLBACK");
                die("Driver insert failed: " . pg_last_error($conn));
            }

            pg_query($conn, "COMMIT");

            $_SESSION['user_id'] = $user_id;
            $_SESSION['driver_id'] = $user_id;
            $_SESSION['driver_name'] = $fname . ' ' . $lname;
            $_SESSION['role'] = 'provider';

            unset($_SESSION['step1'], $_SESSION['step2'], $_SESSION['provider_type']);

            header("Location: ../../general/terms.php");
exit();
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rafiq - Driver Sign Up</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
html, body{
    margin:0;
    padding:0;
    min-height:100vh;
    font-family:'Segoe UI', sans-serif;
}

body{
    background:#484b78;
    min-height:100vh;
    overflow:hidden;
    display:flex;
    justify-content:center;
    align-items:center;
}

.card{
    background:#FFFFFF;
    width:690px;
    max-width:calc(100% - 48px);
    max-height:calc(100vh - 36px);
    padding:26px 56px 28px;
    border-radius:32px;
    box-sizing:border-box;
    overflow:hidden;
    box-shadow:0 24px 60px rgba(20,20,45,.18);
}

.logo,
.subtitle,
.steps{
    text-align:center;
}

.logo img{
    width:140px;
    max-width:100%;
    margin-bottom:4px;
}

.subtitle{
    color:#2B2C41;
    font-size:17px;
    line-height:1.4;
    margin-bottom:14px;
}

.steps{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:18px;
    margin-bottom:18px;
}

.circle{
    width:38px;
    height:38px;
    border-radius:50%;
    display:flex;
    justify-content:center;
    align-items:center;
    font-weight:600;
    border:1.5px solid #3E3D63;
    background:#FFFFFF;
    color:#3E3D63;
}

.circle.active{
    background:#CFE6FF;
}

.line{
    width:80px;
    height:2px;
    background:#3E3D63;
}

h3{
    color:#3E3D63;
    margin:0 0 10px;
    font-size:22px;
}

.question{
    font-size:16px;
    color:#3E3D63;
    margin-bottom:5px;
    font-weight:600;
}

.small{
    font-size:13px;
    color:#8C8FB1;
    margin-bottom:14px;
    font-weight:500;
}

select,
input[type="file"]{
    width:100%;
    height:46px;
    border-radius:18px;
    border:1.5px solid #3E3D63;
    padding:0 16px;
    font-size:15px;
    background:#FFFFFF;
    box-sizing:border-box;
    outline:none;
    color:#2B2C41;
}

input[type="file"]{
    padding:11px 16px 0 16px;
    font-size:13px;
}

select:focus,
input[type="file"]:focus{
    border-color:#4B4A73;
}

.file-box{
    width:100%;
    border:none;
    padding:0;
    margin:0 0 18px;
    background:transparent;
    box-sizing:border-box;
}

.buttons{
    display:flex;
    justify-content:space-between;
    gap:15px;
    margin-top:8px;
}

button{
    width:190px;
    height:52px;
    border:none;
    border-radius:30px;
    background:#4B4A73;
    color:white;
    font-weight:600;
    font-size:16px;
    cursor:pointer;
    transition:0.3s;
}

button:hover{
    background:#37365A;
}

.error{
    background:#FFE8E8;
    color:#B00020;
    border:1px solid #F3B5B5;
    padding:10px 14px;
    border-radius:14px;
    margin-bottom:12px;
    font-size:14px;
    font-weight:600;
    text-align:center;
}

@media (max-width:768px){
    body{
        align-items:flex-start;
        overflow:auto;
        padding:18px 14px;
    }

    .card{
        max-width:100%;
        max-height:none;
        padding:24px 20px;
        border-radius:22px;
        overflow:visible;
    }

    .buttons{
        flex-direction:column;
        align-items:center;
    }

    button{
        width:100%;
    }

    .line{
        width:40px;
    }
}
</style>
</head>
<body>

<div class="card">
    <div class="logo">
        <img src="../../pictures/rafiq_logo.png" alt="Rafiq Logo">
    </div>

    <div class="subtitle">
        Join Rafiq and provide transportation services.<br>
        Tell us about yourself.
    </div>

    <div class="steps">
        <div class="circle">1</div>
        <div class="line"></div>
        <div class="circle">2</div>
        <div class="line"></div>
        <div class="circle active">3</div>
    </div>

    <h3>Almost done!</h3>

    <div class="question">Driving License</div>
    <div class="small">Upload a clear copy of your license</div>

    <div class="error" id="formMessage" style="<?php echo (isset($error) && $error !== '') ? '' : 'display:none;'; ?>">
        <?php echo isset($error) ? htmlspecialchars($error) : ''; ?>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="file-box">
            <input type="file" name="driving_license" accept=".pdf,.jpg,.jpeg,.png" required>
        </div>

        <div class="buttons">
            <button type="button" onclick="history.back()">Previous</button>
            <button type="submit" name="finish">Finish</button>
        </div>
    </form>
</div>

</body>
</html>