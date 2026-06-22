<?php
session_start();
require __DIR__ . '/../pgdb/db.php';

$error = '';

$photoUploadDir = __DIR__ . '/../pictures/users/';
$photoUploadUrl = 'pictures/users/';

if (!is_dir($photoUploadDir)) {
    @mkdir($photoUploadDir, 0777, true);
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =============================
// AJAX EMAIL CHECK
// =============================
if (isset($_GET['check_email'])) {
    header('Content-Type: text/plain; charset=utf-8');

    $email = trim((string)$_GET['check_email']);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'invalid';
        exit();
    }

    $check = $pdo->prepare('SELECT user_id FROM public."user" WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $check->execute([':email' => $email]);

    echo $check->fetchColumn() ? 'taken' : 'available';
    exit();
}

// =============================
// STEP 1 FORM SUBMISSION
// Saves only to session, then goes to step 2
// =============================
if (isset($_POST['continue'])) {
    $fname   = trim((string)($_POST['fname'] ?? ''));
    $lname   = trim((string)($_POST['lname'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $pass    = (string)($_POST['pass'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    $photoPath = '';

    if ($fname === '' || $lname === '' || $email === '' || $pass === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE || empty($_FILES['photo']['name'])) {
        $error = 'Profile photo is required.';
    } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Could not upload the profile photo. Please try again.';
    } else {
        $check = $pdo->prepare('SELECT user_id FROM public."user" WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $check->execute([':email' => $email]);

        if ($check->fetchColumn()) {
            $error = 'Email already registered. Please use a different email.';
        } else {
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $mime = mime_content_type($_FILES['photo']['tmp_name']);

            if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
                $error = 'Photo must be JPG, PNG, or WEBP.';
            } elseif ((int)$_FILES['photo']['size'] > 3 * 1024 * 1024) {
                $error = 'Photo must be less than 3MB.';
            } else {
                $fileName = 'patient_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $target = $photoUploadDir . $fileName;

                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                    $error = 'Could not upload the profile photo.';
                } else {
                    $photoPath = $photoUploadUrl . $fileName;
                }
            }

            if ($error === '') {
                $_SESSION['signup'] = [
                    'fname'    => $fname,
                    'lname'    => $lname,
                    'email'    => $email,
                    'password' => password_hash($pass, PASSWORD_DEFAULT),
                    'role'     => 'patient',
                    'photo'    => $photoPath
                ];

                header('Location: signup2_patient.php');
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
<title>Rafiq - Patient Sign Up</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
html, body{
    margin:0;
    padding:0;
    height:100%;
    font-family:'Segoe UI', sans-serif;
}

body{
    background:#484b78;
    height:100vh;
    overflow:hidden;
    display:flex;
    justify-content:center;
    align-items:center;
}

.card{
    background:#FFFFFF;
    width:760px;
    max-width:calc(100% - 48px);
    max-height:calc(100vh - 48px);
    padding:28px 56px 30px;
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

form{
    display:flex;
    flex-direction:column;
    gap:13px;
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
    margin-bottom:5px;
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

input::placeholder{
    color:#A3A6C3;
}

input:focus{
    border-color:#4B4A73;
}

input[type="file"]{
    padding:11px 10px 0 45px;
    font-size:13px;
}

.file-note{
    margin-top:6px;
    color:#8C8FB1;
    font-size:12px;
    font-weight:500;
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

.email-status{
    display:none;
    margin-top:6px;
    font-size:13px;
    font-weight:600;
}

.email-status.taken{
    display:block;
    color:#B00020;
}

.email-status.available{
    display:none;
}

@media (max-width: 768px){
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
        Tell us about yourself.
    </div>

    <div class="steps">
        <div class="circle active">1</div>
        <div class="line"></div>
        <div class="circle">2</div>
    </div>

    <h3>Create your account</h3>

    <div class="error" id="formMessage" style="<?= $error !== '' ? '' : 'display:none;' ?>"><?= h($error) ?></div>

    <form method="POST" id="signupForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="continue" value="1">
        <div class="row">
            <div class="field">
                <label>First name</label>
                <div class="input-group">
                    <i class="fa-regular fa-user"></i>
                    <input
                        type="text"
                        name="fname"
                        placeholder="First name"
                        value="<?= h($_POST['fname'] ?? '') ?>"
                        required
                    >
                </div>
            </div>

            <div class="field">
                <label>Last name</label>
                <div class="input-group">
                    <i class="fa-regular fa-user"></i>
                    <input
                        type="text"
                        name="lname"
                        placeholder="Last name"
                        value="<?= h($_POST['lname'] ?? '') ?>"
                        required
                    >
                </div>
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label>Email</label>
                <div class="input-group">
                    <i class="fa-regular fa-envelope"></i>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        placeholder="example@gmail.com"
                        value="<?= h($_POST['email'] ?? '') ?>"
                        required
                    >
                </div>
                <div class="email-status" id="emailStatus"></div>
            </div>

            <div class="field">
                <label>Photo</label>
                <div class="input-group">
                    <i class="fa-regular fa-image"></i>
                    <input
                        type="file"
                        name="photo"
                        id="photo"
                        accept=".jpg,.jpeg,.png,.webp"
                        required
                    >
                </div>
            </div>
        </div>

        <div class="field">
            <label>Password</label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input
                    type="password"
                    name="pass"
                    id="pass"
                    placeholder="Must have at least 8 characters"
                    minlength="8"
                    required
                >
            </div>
        </div>

        <div class="field">
            <label>Confirm password</label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input
                    type="password"
                    name="confirm"
                    id="confirm"
                    placeholder="Re-enter your password"
                    minlength="8"
                    required
                >
            </div>
        </div>

        <button type="submit" name="continue" id="continueBtn">Continue</button>
    </form>
</div>

<script>
const form = document.getElementById('signupForm');
const emailField = document.getElementById('email');
const emailStatus = document.getElementById('emailStatus');
const formMessage = document.getElementById('formMessage');
const passField = document.getElementById('pass');
const confirmField = document.getElementById('confirm');
const photoField = document.getElementById('photo');
let emailTaken = false;

function showFormMessage(text) {
    formMessage.textContent = text;
    formMessage.style.display = text ? 'block' : 'none';
}

function markError(field, hasError) {
    if (!field) return;
    field.classList.toggle('input-error', !!hasError);
}

function clearFieldErrors() {
    form.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
}

function showEmailStatus(text, type) {
    emailStatus.textContent = text;
    emailStatus.className = 'email-status ' + (type || '');
}

async function checkEmail() {
    const email = emailField.value.trim();
    emailTaken = false;

    if (email === '') {
        showEmailStatus('', '');
        return 'empty';
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showEmailStatus('Enter a valid email address.', 'taken');
        return 'invalid';
    }

    try {
        const res = await fetch('signup_patient.php?check_email=' + encodeURIComponent(email));
        const status = (await res.text()).trim();

        if (status === 'taken') {
            emailTaken = true;
            showEmailStatus('Email already registered. Please use a different email.', 'taken');
            return 'taken';
        }

        if (status === 'available') {
            showEmailStatus('', '');
            return 'available';
        }

        showEmailStatus('', '');
        return status;
    } catch (e) {
        showEmailStatus('', '');
        return 'error';
    }
}

emailField.addEventListener('blur', checkEmail);

form.addEventListener('input', function(e) {
    if (e.target.classList.contains('input-error')) {
        e.target.classList.remove('input-error');
    }
    showFormMessage('');
});

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    clearFieldErrors();
    showFormMessage('');

    const firstName = form.elements['fname'];
    const lastName = form.elements['lname'];

    if (firstName.value.trim() === '') {
        markError(firstName, true);
        firstName.focus();
        showFormMessage('Please enter your first name.');
        return;
    }

    if (lastName.value.trim() === '') {
        markError(lastName, true);
        lastName.focus();
        showFormMessage('Please enter your last name.');
        return;
    }

    if (emailField.value.trim() === '') {
        markError(emailField, true);
        emailField.focus();
        showFormMessage('Please enter your email address.');
        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value.trim())) {
        markError(emailField, true);
        emailField.focus();
        showFormMessage('Please enter a valid email address.');
        return;
    }


    if (!photoField || photoField.files.length === 0) {
        markError(photoField, true);
        photoField.focus();
        showFormMessage('Profile photo is required.');
        return;
    }

    const file = photoField.files[0];
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!allowedTypes.includes(file.type)) {
        markError(photoField, true);
        photoField.focus();
        showFormMessage('Photo must be JPG, PNG, or WEBP.');
        return;
    }

    if (file.size > 3 * 1024 * 1024) {
        markError(photoField, true);
        photoField.focus();
        showFormMessage('Photo must be less than 3MB.');
        return;
    }

    if (passField.value.length < 8) {
        markError(passField, true);
        passField.focus();
        showFormMessage('Password must be at least 8 characters.');
        return;
    }

    if (confirmField.value.trim() === '') {
        markError(confirmField, true);
        confirmField.focus();
        showFormMessage('Please confirm your password.');
        return;
    }

    if (passField.value !== confirmField.value) {
        markError(confirmField, true);
        confirmField.focus();
        showFormMessage('Passwords do not match.');
        return;
    }

    const status = await checkEmail();

    if (status === 'taken') {
        markError(emailField, true);
        emailField.focus();
        showFormMessage('Email already registered. Please use a different email.');
        return;
    }

    if (status === 'invalid') {
        markError(emailField, true);
        emailField.focus();
        showFormMessage('Please enter a valid email address.');
        return;
    }

    form.submit();
});
</script>
</body>
</html>
