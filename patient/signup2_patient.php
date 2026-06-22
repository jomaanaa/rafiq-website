<?php
session_start();
require __DIR__ . '/../pgdb/db.php';

// للتطوير فقط — شيله في الإنتاج
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['signup']) || !is_array($_SESSION['signup'])) {
    header("Location: signup_patient.php");
    exit();
}

$error = "";

if (isset($_POST['finish'])) {

    $address    = trim($_POST['address'] ?? '');
    $gender     = trim($_POST['gender'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $disability = trim($_POST['disability'] ?? '');

    $month = (int)($_POST['month'] ?? 0);
    $day   = (int)($_POST['day'] ?? 0);
    $year  = (int)($_POST['year'] ?? 0);

    $signup = $_SESSION['signup'];

    // تأكد إن بيانات التسجيل الأساسية موجودة
    if (
        empty($signup['fname']) ||
        empty($signup['lname']) ||
        empty($signup['email']) ||
        empty($signup['password']) ||
        empty($signup['role']) ||
        empty($signup['photo'])
    ) {
        $error = "Signup session is incomplete. Please sign up again.";
    }

    // Address validation
    elseif (empty($address)) {
        $error = "Address is required.";
    }

    // Gender validation
    elseif (!in_array($gender, ['male', 'female'], true)) {
        $error = "Please select a valid gender.";
    }

    // Phone validation: exactly 11 digits and must start with 010, 011, 012, or 015
    elseif (!preg_match('/^01[0125][0-9]{8}$/', $phone)) {
        $error = "Phone number must be 11 digits and start with 010, 011, 012, or 015.";
    }

    // Disability validation
    elseif (
        !in_array($disability, [
            'Physical disability',
            'Visual impairment',
            'Hearing impairment',
            'Intellectual disability'
        ], true)
    ) {
        $error = "Please select a valid disability type.";
    }

    // Date validation
    elseif (!checkdate($month, $day, $year)) {
        $error = "Invalid date of birth.";
    }

    else {
        $dob = sprintf('%04d-%02d-%02d', $year, $month, $day);

        try {
            $pdo->beginTransaction();

            // Insert into user table
            $stmt1 = $pdo->prepare('
                INSERT INTO public."user" (first_name, last_name, email, password, role, photo)
                VALUES (:first_name, :last_name, :email, :password, :role, :photo)
                RETURNING user_id
            ');

            $stmt1->execute([
                ':first_name' => $signup['fname'],
                ':last_name'  => $signup['lname'],
                ':email'      => $signup['email'],
                ':password'   => $signup['password'], // hashed password
                ':role'       => $signup['role'],
                ':photo'      => $signup['photo']
            ]);

            $user_id = $stmt1->fetchColumn();

            if (!$user_id) {
                throw new Exception("Failed to create user account.");
            }

            // Insert into patient table
            $stmt2 = $pdo->prepare('
                INSERT INTO public.patient (user_id, disability, phone, address, gender, dob)
                VALUES (:user_id, :disability, :phone, :address, :gender, :dob)
            ');

            $stmt2->execute([
                ':user_id'    => $user_id,
                ':disability' => $disability,
                ':phone'      => $phone,
                ':address'    => $address,
                ':gender'     => $gender,
                ':dob'        => $dob
            ]);

            $pdo->commit();

            unset($_SESSION['signup']);
            $_SESSION['user_id'] = $user_id;

            header("Location: ../general/terms.php");
            exit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // للتطوير: اعرض الخطأ الحقيقي
            $error = $e->getMessage();

            // للإنتاج استخدم السطر ده بدل اللي فوق:
            // $error = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rafiq - Patient Details</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
html, body{
    margin:0;
    padding:0;
    min-height:100vh;
    font-family:'Segoe UI', sans-serif;
}

body{
    margin:0;
    font-family:'Segoe UI', sans-serif;
    background:#484b78;
    height:100vh;
    overflow:hidden;
    display:flex;
    justify-content:center;
    align-items:center;
}
.card{
    background:#FFFFFF;
    width:690px;
    max-width:100%;
    padding:28px 56px 24px;
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
    margin-bottom:12px;
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

form{
    display:flex;
    flex-direction:column;
    gap:13px;
}

.row{
    display:flex;
    gap:20px;
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

input, select{
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

select{
    appearance:none;
    background:#FFFFFF;
}

.no-icon{
    padding-left:16px;
}

input::placeholder{
    color:#A3A6C3;
}

input:focus, select:focus{
    border-color:#4B4A73;
}

.buttons{
    display:flex;
    justify-content:space-between;
    gap:15px;
    margin-top:6px;
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
    padding:12px 15px;
    border-radius:14px;
    margin-bottom:15px;
    font-size:14px;
}

/* Keep page fitted without breaking inputs */
h3{
    color:#3E3D63;
    margin:0 0 6px;
    font-size:21px;
}

.input-group input,
.input-group select{
    display:block;
}

.field.dob-field{
    margin-top:0;
}

.dob-row{
    gap:20px;
}

.dob-row select{
    flex:1;
}


@media (max-width: 768px){
    body{
        align-items:flex-start;
        padding:18px 14px;
    }

    .card{
        padding:24px 20px;
        border-radius:22px;
    }

    .row{
        flex-direction:column;
        gap:15px;
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
        <img src="../pictures/rafiq_logo.png" alt="Rafiq Logo">
    </div>

    <div class="subtitle">
        Tell us about yourself.
    </div>

    <div class="steps">
        <div class="circle">1</div>
        <div class="line"></div>
        <div class="circle active">2</div>
    </div>

    <h3>Almost done!</h3>

    <div class="error" id="formMessage" style="<?php echo !empty($error) ? '' : 'display:none;'; ?>"><?php echo htmlspecialchars($error); ?></div>

    <form method="POST" id="detailsForm" novalidate>

        <div class="row">
            <div class="field">
                <label>Address</label>
                <div class="input-group">
                    <i class="fa-solid fa-location-dot"></i>
                    <input
                        type="text"
                        name="address"
                        placeholder="Enter your address"
                        value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                        required
                    >
                </div>
            </div>

            <div class="field">
                <label>Gender</label>
                <div class="input-group">
                    <i class="fa-regular fa-user"></i>
                    <select name="gender" required>
                        <option value="">Select gender</option>
                        <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>male</option>
                        <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>female</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="field">
            <label>Phone number</label>
            <div class="input-group">
                <i class="fa-solid fa-phone"></i>
                <input
                    type="tel"
                    name="phone"
                    placeholder="010 / 011 / 012 / 015"
                    minlength="11"
                    maxlength="11"
                    inputmode="numeric"
                    pattern="^01[0125][0-9]{8}$"
                    title="Phone number must be 11 digits and start with 010, 011, 012, or 015"
                    value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                    required
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);"
                >
            </div>
        </div>

        <div class="field dob-field">
            <label>Date of birth</label>
            <div class="row dob-row">
                <select name="month" class="no-icon" required>
                    <option value="">MM</option>
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $value = str_pad($m, 2, '0', STR_PAD_LEFT);
                    ?>
                        <option value="<?php echo $value; ?>" <?php echo (($_POST['month'] ?? '') === $value) ? 'selected' : ''; ?>>
                            <?php echo $m; ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <select name="day" class="no-icon" required>
                    <option value="">DD</option>
                    <?php for ($d = 1; $d <= 31; $d++): 
                        $value = str_pad($d, 2, '0', STR_PAD_LEFT);
                    ?>
                        <option value="<?php echo $value; ?>" <?php echo (($_POST['day'] ?? '') === $value) ? 'selected' : ''; ?>>
                            <?php echo $d; ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <select name="year" class="no-icon" required>
                    <option value="">YYYY</option>
                    <?php for ($y = date("Y"); $y >= 1950; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo (($_POST['year'] ?? '') == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="field">
            <label>Disability type</label>
            <select name="disability" class="no-icon" required>
                <option value="">Select disability type</option>
                <option value="Physical disability" <?php echo (($_POST['disability'] ?? '') === 'Physical disability') ? 'selected' : ''; ?>>Physical disability</option>
                <option value="Visual impairment" <?php echo (($_POST['disability'] ?? '') === 'Visual impairment') ? 'selected' : ''; ?>>Visual impairment</option>
                <option value="Hearing impairment" <?php echo (($_POST['disability'] ?? '') === 'Hearing impairment') ? 'selected' : ''; ?>>Hearing impairment</option>
                <option value="Intellectual disability" <?php echo (($_POST['disability'] ?? '') === 'Intellectual disability') ? 'selected' : ''; ?>>Intellectual disability</option>
            </select>
        </div>

        <div class="buttons">
            <button type="button" onclick="window.location.href='signup_patient.php'">Previous</button>
            <button type="submit" name="finish">Finish</button>
        </div>

    </form>
</div>

<script>
const detailsForm = document.getElementById('detailsForm');
const formMessage = document.getElementById('formMessage');

function showFormMessage(text) {
    formMessage.textContent = text;
    formMessage.style.display = text ? 'block' : 'none';
}

if (detailsForm) {
    detailsForm.addEventListener('input', function () {
        showFormMessage('');
    });

    detailsForm.addEventListener('submit', function (e) {
        showFormMessage('');

        const address = detailsForm.elements['address'].value.trim();
        const gender = detailsForm.elements['gender'].value.trim();
        const phone = detailsForm.elements['phone'].value.trim();
        const month = detailsForm.elements['month'].value.trim();
        const day = detailsForm.elements['day'].value.trim();
        const year = detailsForm.elements['year'].value.trim();
        const disability = detailsForm.elements['disability'].value.trim();

        if (!address) {
            e.preventDefault();
            showFormMessage('Address is required.');
            detailsForm.elements['address'].focus();
            return;
        }

        if (!gender) {
            e.preventDefault();
            showFormMessage('Please select your gender.');
            detailsForm.elements['gender'].focus();
            return;
        }

        if (!/^01[0125][0-9]{8}$/.test(phone)) {
            e.preventDefault();
            showFormMessage('Phone number must be 11 digits and start with 010, 011, 012, or 015.');
            detailsForm.elements['phone'].focus();
            return;
        }

        if (!month || !day || !year) {
            e.preventDefault();
            showFormMessage('Please enter your full date of birth.');
            return;
        }

        const date = new Date(Number(year), Number(month) - 1, Number(day));
        if (date.getFullYear() !== Number(year) || date.getMonth() !== Number(month) - 1 || date.getDate() !== Number(day)) {
            e.preventDefault();
            showFormMessage('Invalid date of birth.');
            return;
        }

        if (!disability) {
            e.preventDefault();
            showFormMessage('Please select your disability type.');
            detailsForm.elements['disability'].focus();
        }
    });
}
</script>

</body>
</html>