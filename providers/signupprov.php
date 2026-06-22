<?php
session_start();

if(!isset($_SESSION['provider_type'])){
    header("Location: provtype.php");
    exit();
}

// Database connection at top
$conn = pg_connect("host=localhost dbname=rafiq user=postgres password=123456789");
if(!$conn){
    die("Database connection failed.");
}

$photoUploadDir = __DIR__ . '/../pictures/providers/';
$photoUploadUrl = 'pictures/providers/';

if (!is_dir($photoUploadDir)) {
    @mkdir($photoUploadDir, 0777, true);
}

if(isset($_POST['continue'])){

    $fname   = trim($_POST['fname']);
    $lname   = trim($_POST['lname']);
    $email   = trim($_POST['email']);
    $pass    = $_POST['pass'];
    $confirm = $_POST['confirm'];
    $photoPath = '';

    if(empty($fname) || empty($lname) || empty($email) || empty($pass) || empty($confirm)){
        $error = "All fields required.";
    }
    elseif (empty($_FILES['photo']['name']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $error = "Please upload your profile photo.";
    }
    elseif(strlen($pass) < 8){
        $error = "Password must be at least 8 characters.";
    }
    elseif($pass !== $confirm){
        $error = "Passwords do not match.";
    }
    else {

        // Check if email already exists
        $check = pg_query_params(
            $conn,
            'SELECT user_id FROM "user" WHERE email=$1',
            array($email)
        );

        if(pg_num_rows($check) > 0){
            $error = "Email already registered.";
        } else {

            if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed, true)) {
                    $error = "Photo must be JPG, PNG, or WEBP.";
                } elseif ((int)$_FILES['photo']['size'] > 3 * 1024 * 1024) {
                    $error = "Photo must be less than 3MB.";
                } else {
                    $photoName = 'provider_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $target = $photoUploadDir . $photoName;

                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                        $error = "Could not upload the photo.";
                    } else {
                        $photoPath = $photoUploadUrl . $photoName;
                    }
                }
            }

            if (!isset($error) || $error === '') {
                // Save step1 data in session
                $_SESSION['step1'] = [
                    'fname' => $fname,
                    'lname' => $lname,
                    'email' => $email,
                    'pass'  => password_hash($pass, PASSWORD_DEFAULT),
                    'photo' => $photoPath
                ];

                header("Location: signup2_provider.php");
                exit();
            }
        }
    }
}

// AJAX EMAIL CHECK
if(isset($_GET['check_email'])){
    $email = trim($_GET['check_email']);
    $check = pg_query_params(
        $conn,
        "SELECT user_id FROM \"user\" WHERE email = $1 LIMIT 1",
        array($email)
    );

    echo (pg_num_rows($check) > 0) ? "taken" : "available";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Rafiq - Provider Sign Up</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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
    width:760px;
    max-width:calc(100% - 48px);
    max-height:calc(100vh - 36px);
    padding:24px 56px 26px;
    border-radius:32px;
    box-sizing:border-box;
    overflow:hidden;
    box-shadow:0 24px 60px rgba(20,20,45,.18);
}

.logo{
    text-align:center;
    margin-bottom:2px;
}

.logo img{
    width:140px;
    max-width:100%;
}

.subtitle{
    text-align:center;
    color:#2B2C41;
    font-size:17px;
    line-height:1.4;
    margin-bottom:12px;
}

.subtitle span{
    display:block;
    margin-top:2px;
    font-weight:500;
}

.steps{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:18px;
    margin-bottom:16px;
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

form{
    display:flex;
    flex-direction:column;
    gap:10px;
}

.row{
    display:flex;
    gap:16px;
}

.field{
    flex:1;
    display:flex;
    flex-direction:column;
}

label{
    font-size:16px;
    color:#3E3D63;
    margin-bottom:4px;
    font-weight:500;
}

.input-group{
    position:relative;
}

.input-group i{
    position:absolute;
    left:16px;
    top:50%;
    transform:translateY(-50%);
    color:#8C8FB1;
    font-size:15px;
    z-index:2;
}

input{
    width:100%;
    height:46px;
    padding:0 16px 0 45px;
    border-radius:18px;
    border:1.5px solid #3E3D63;
    font-size:15px;
    background:#FFFFFF;
    box-sizing:border-box;
    outline:none;
    color:#2B2C41;
}

input[type="file"]{
    padding:11px 10px 0 45px;
    font-size:13px;
}

input::placeholder{
    color:#A3A6C3;
}

input:focus{
    border-color:#4B4A73;
}

input.input-error{
    border-color:#B00020;
    background:#FFF7F7;
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
    align-self:center;
    margin-top:4px;
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

    .row{
        flex-direction:column;
        gap:13px;
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
        <img src="../pictures/rafiq_logo.png" alt="Rafiq Logo">
    </div>

    <div class="subtitle">
        Let us know how we can assist you on your journey.
        <span class="bold">Tell us about yourself.</span>
    </div>

    <!-- ONLY CHANGE: 3 STEPS -->
    <div class="steps">
        <div class="circle active">1</div>
        <div class="line"></div>
        <div class="circle">2</div>
        <div class="line"></div>
        <div class="circle">3</div>
    </div>

    <div class="error" id="formMessage" style="<?php echo (isset($error) && $error !== '') ? '' : 'display:none;'; ?>">
        <?php echo isset($error) ? htmlspecialchars($error) : ''; ?>
    </div>

    <form method="POST" enctype="multipart/form-data" id="signupForm" novalidate>

        <div class="row">
            <div class="field">
                <label>First name</label>
                <div class="input-group">
                    <i class="fa-regular fa-user"></i>
                    <input type="text" name="fname" placeholder="First name" required>
                </div>
            </div>

            <div class="field">
                <label>Last name</label>
                <div class="input-group">
                    <i class="fa-regular fa-user"></i>
                    <input type="text" name="lname" placeholder="Last name" required>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label>Email</label>
                <div class="input-group">
                    <i class="fa-regular fa-envelope"></i>
                    <input type="email" name="email" id="email" placeholder="example@gmail.com" required>
                </div>
            </div>

            <div class="field">
                <label>Photo</label>
                <div class="input-group">
                    <i class="fa-regular fa-image"></i>
                    <input type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png,.webp">
                </div>
            </div>
        </div>

        <div>
            <label>Password</label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="pass" placeholder="Must have at least 8 characters" minlength="8" required>
            </div>
        </div>

        <div>
            <label>Confirm password</label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="confirm" placeholder="Re-enter your password" minlength="8" required>
            </div>
        </div>

        <button type="submit" name="continue">Continue</button>
    </form>
</div>

<script>
const signupForm = document.getElementById('signupForm');
const emailField = document.getElementById('email');
const formMessage = document.getElementById('formMessage');
const photoField = document.getElementById('photo');

function showFormMessage(text){
    if(!formMessage) return;
    formMessage.textContent = text || '';
    formMessage.style.display = text ? 'block' : 'none';
}

function markError(field, hasError){
    if(!field) return;
    field.classList.toggle('input-error', !!hasError);
}

async function checkEmail(){
    const email = emailField.value.trim();

    if(email === ''){
        return 'empty';
    }

    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
        showFormMessage('Please enter a valid email address.');
        markError(emailField, true);
        return 'invalid';
    }

    try{
        const res = await fetch(`signupprov.php?check_email=${encodeURIComponent(email)}`);
        const status = (await res.text()).trim();

        if(status === 'taken'){
            showFormMessage('Email already registered. Please use a different email.');
            markError(emailField, true);
            return 'taken';
        }

        return status;
    }catch(e){
        return 'error';
    }
}

if(emailField){
    emailField.addEventListener('blur', checkEmail);
}

if(signupForm){
    signupForm.addEventListener('input', function(e){
        if(e.target.classList.contains('input-error')){
            e.target.classList.remove('input-error');
        }
        showFormMessage('');
    });

    signupForm.addEventListener('submit', async function(e){
        showFormMessage('');

        if (!photoField || photoField.files.length === 0) {
            e.preventDefault();
            showFormMessage('Please upload your profile photo.');
            markError(photoField, true);
            if (photoField) photoField.focus();
            return;
        }

        const photo = photoField.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!allowedTypes.includes(photo.type)) {
            e.preventDefault();
            showFormMessage('Photo must be JPG, PNG, or WEBP.');
            markError(photoField, true);
            photoField.focus();
            return;
        }

        if (photo.size > 3 * 1024 * 1024) {
            e.preventDefault();
            showFormMessage('Photo must be less than 3MB.');
            markError(photoField, true);
            photoField.focus();
            return;
        }

        const emailStatus = await checkEmail();
        if(emailStatus === 'taken' || emailStatus === 'invalid'){
            e.preventDefault();
            emailField.focus();
        }
    });
}
</script>

</body>
</html>
