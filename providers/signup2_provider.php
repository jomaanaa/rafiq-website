<?php
session_start();

if(!isset($_SESSION['step1']) || !isset($_SESSION['provider_type'])){
    header("Location: signupprov.php");
    exit();
}

$conn = pg_connect("host=localhost dbname=rafiq user=postgres password=123456789");
if(!$conn){
    die("Database connection failed.");
}

function is_at_least_18(string $dob): bool {
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y >= 18;
    } catch (Exception $e) {
        return false;
    }
}

if(isset($_POST['continue'])){

    $address = trim($_POST['address']);
    $gender  = strtolower($_POST['gender']);
    $phone   = trim($_POST['phone']);
    $national_id = trim($_POST['national_id']);
    $month = (int)($_POST['month'] ?? 0);
    $day   = (int)($_POST['day'] ?? 0);
    $year  = (int)($_POST['year'] ?? 0);
    $dob = sprintf('%04d-%02d-%02d', $year, $month, $day);

if(empty($address) || empty($gender) || empty($phone) || empty($national_id)){
        $error = "All fields required.";
    }
    elseif(!preg_match('/^01[0125][0-9]{8}$/', $phone)){
        $error = "Phone number must be 11 digits and start with 010, 011, 012, or 015.";
    }
    elseif(!preg_match('/^[0-9]{14}$/', $national_id)){
        $error = "National ID must be exactly 14 digits.";
    }
    elseif(!checkdate($month, $day, $year)){
        $error = "Invalid date of birth.";
    }
    elseif(!is_at_least_18($dob)){
        $error = "Provider must be at least 18 years old.";
    }
    elseif(!isset($_FILES['cv']) || $_FILES['cv']['error'] !== 0){
        $error = "Please upload your CV.";
    }
    else {

        // ✅ CREATE UPLOADS FOLDER IF NOT EXISTS
        if(!is_dir("../uploads")){
            mkdir("../uploads", 0777, true);
        }

        // ✅ MOVE CV FILE NOW (IMPORTANT)
        $cv_name = uniqid() . "_" . basename($_FILES['cv']['name']);
        $cv_disk_path = "../uploads/" . $cv_name;
        $cv_path = "uploads/" . $cv_name;

        if(!move_uploaded_file($_FILES['cv']['tmp_name'], $cv_disk_path)){
            die("Failed to upload CV.");
        }

        // ✅ SAVE ONLY PATH IN SESSION
        $_SESSION['step2'] = [
            'address'     => $address,
            'gender'      => $gender,
            'phone'       => $phone,
            'national_id' => $national_id,
            'dob'         => $dob,
            'cv'          => $cv_path
        ];

        if($_SESSION['provider_type'] === 'doctor'){
            header("Location: doctor/signup_doctor.php");
        } 
        elseif($_SESSION['provider_type'] === 'interpreter') {
            header("Location: interpreter/signup_interpreter.php");
        }
        elseif($_SESSION['provider_type'] === 'caregiver'){
           header("Location: caregiver/signup_caregiver.php"); 
        }
        elseif($_SESSION['provider_type'] === 'driver'){
           header("Location: driver/signup_driver.php"); 
        }
        else{
            header("Location: signup2_provider.php");
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Rafiq - Provider Details</title>
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
    margin-bottom:12px;
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

h3{
    color:#3E3D63;
    margin:0 0 10px;
    font-size:22px;
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

input.input-error,
select.input-error{
    border-color:#B00020;
    background:#FFF7F7;
}

input[type="file"]{
    padding:11px 16px 0 16px;
    font-size:13px;
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
    padding:10px 14px;
    border-radius:14px;
    margin-bottom:10px;
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

    .row{
        flex-direction:column;
        gap:13px;
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
        <img src="../pictures/rafiq_logo.png">
    </div>

    <div class="subtitle">
        Join Rafiq and provide support to users.
    </div>

    <!-- 3 STEPS -->
    <div class="steps">
        <div class="circle">1</div>
        <div class="line"></div>
        <div class="circle active">2</div>
        <div class="line"></div>
        <div class="circle">3</div>
    </div>

    <h3 style="color:#3E3D63;">Almost done!</h3>

    <div class="error" id="formMessage" style="<?php echo (isset($error) && $error !== '') ? '' : 'display:none;'; ?>">
        <?php echo isset($error) ? htmlspecialchars($error) : ''; ?>
    </div>

    <form method="POST" enctype="multipart/form-data" id="providerDetailsForm">

        <!-- Address + Gender -->
        <div class="row">
            <div class="field">
                <label>Address</label>
                <div class="input-group">
                    <i class="fa-regular fa-user"></i>
                    <input type="text" name="address" placeholder="Enter your address" required>
                </div>
            </div>

            <div class="field">
                <label>Gender</label>
                <div class="input-group">
                    <i class="fa-regular fa-user"></i>
                        <select name="gender" required>
                            <option value="">Select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Phone -->
            <div class="field">
                <label>Phone number</label>
                <div class="input-group">
                    <i class="fa-solid fa-phone"></i>
                    <input
                        type="tel"
                        name="phone"
                        placeholder="010 / 011 / 012 / 015"
                        maxlength="11"
                        minlength="11"
                        inputmode="numeric"
                        pattern="^01[0125][0-9]{8}$"
                        title="Phone number must be 11 digits and start with 010, 011, 012, or 015"
                        required
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);"
                    >
                </div>
            </div>

            <!-- National ID -->
            <div class="field">
                <label>National ID</label>
                <div class="input-group">
                    <i class="fa-regular fa-id-card"></i>
                    <input
                        type="text"
                        name="national_id"
                        placeholder="Enter 14 numbers"
                        maxlength="14"
                        minlength="14"
                        inputmode="numeric"
                        pattern="^[0-9]{14}$"
                        required
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 14);"
                    >
                </div>
            </div>
        </div>


        <!-- DOB -->
        <div class="field">
            <label>Date of birth</label>
            <div class="row">
                <select name="month" class="no-icon" required>
                    <option value="">MM</option>
                    <?php for($m=1;$m<=12;$m++) echo "<option>$m</option>"; ?>
                </select>
                <select name="day" class="no-icon" required>
                    <option value="">DD</option>
                    <?php for($d=1;$d<=31;$d++) echo "<option>$d</option>"; ?>
                </select>
                <select name="year" class="no-icon" required>
                    <option value="">YYYY</option>
                    <?php for($y=(int)date("Y") - 18;$y>=1950;$y--) echo "<option>$y</option>"; ?>
                </select>
            </div>
        </div>

        <!-- CV -->
        <div class="field">
            <label>Upload your CV</label>
            <input type="file" name="cv" class="no-icon" accept=".pdf,.doc,.docx" required>
        </div>

        <!-- Buttons -->
        <div class="buttons">
            <button type="button" onclick="window.location.href='signupprov.php'">Previous</button>
            <button type="submit" name="continue">Continue</button>
        </div>

    </form>
</div>

<script>
const providerDetailsForm = document.getElementById('providerDetailsForm');
const formMessage = document.getElementById('formMessage');

function showFormMessage(text){
    if(!formMessage) return;
    formMessage.textContent = text || '';
    formMessage.style.display = text ? 'block' : 'none';
}

function markError(field, hasError){
    if(!field) return;
    field.classList.toggle('input-error', !!hasError);
}

if (providerDetailsForm) {
    providerDetailsForm.addEventListener('input', function(e){
        if(e.target.classList.contains('input-error')){
            e.target.classList.remove('input-error');
        }
        showFormMessage('');
    });

    providerDetailsForm.addEventListener('submit', function(e) {
        const phone = providerDetailsForm.elements['phone'].value.trim();
        const nationalId = providerDetailsForm.elements['national_id'].value.trim();

        if (!/^01[0125][0-9]{8}$/.test(phone)) {
            e.preventDefault();
            showFormMessage('Phone number must be 11 digits and start with 010, 011, 012, or 015.');
            markError(providerDetailsForm.elements['phone'], true);
            providerDetailsForm.elements['phone'].focus();
            return;
        }

        if (!/^[0-9]{14}$/.test(nationalId)) {
            e.preventDefault();
            showFormMessage('National ID must be exactly 14 digits.');
            markError(providerDetailsForm.elements['national_id'], true);
            providerDetailsForm.elements['national_id'].focus();
            return;
        }

        const month = providerDetailsForm.elements['month'].value.trim();
        const day = providerDetailsForm.elements['day'].value.trim();
        const year = providerDetailsForm.elements['year'].value.trim();

        if (!month || !day || !year) {
            e.preventDefault();
            showFormMessage('Please enter your full date of birth.');
            return;
        }

        const birthDate = new Date(Number(year), Number(month) - 1, Number(day));
        if (birthDate.getFullYear() !== Number(year) || birthDate.getMonth() !== Number(month) - 1 || birthDate.getDate() !== Number(day)) {
            e.preventDefault();
            showFormMessage('Invalid date of birth.');
            return;
        }

        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }

        if (age < 18) {
            e.preventDefault();
            showFormMessage('Provider must be at least 18 years old.');
            markError(providerDetailsForm.elements['year'], true);
            providerDetailsForm.elements['year'].focus();
            return;
        }
    });
}
</script>

</body>
</html>
