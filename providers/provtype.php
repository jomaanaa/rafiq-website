<?php
session_start();

if(isset($_POST['role'])){
    $_SESSION['provider_type'] = $_POST['role']; // Doctor, Caregiver, etc.
    $_SESSION['role'] = 'provider';              // fixed role
    header("Location: signupprov.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Select Provider Role</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
body{
    margin:0;
    font-family:'Inter', sans-serif;
    background:#F6F7FB;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:flex-start; /* move content to top */
    padding-top:60px;
}

.container{
    max-width:1200px;
    text-align:center;
}

h1{
    font-size:26px;
    font-weight:600;
    margin-bottom:8px;
}

h3{
    font-weight:400;
    color:#666;
    margin-bottom:60px;
}

.cards{
    display:flex;
    gap:25px;
    justify-content:center;
    flex-wrap:nowrap;
}

.card{
    background:#fff;
    width:240px;
    padding:30px 20px;
    border-radius:20px;
    box-shadow:0 10px 25px rgba(0,0,0,0.08);
    display:flex;
    flex-direction:column;
    align-items:center;
    transition:.3s;
}

.card:hover{
    transform:translateY(-6px);
    box-shadow:0 15px 35px rgba(0,0,0,0.12);
}

.card img{
    width:150px; /* bigger images */
    margin-bottom:25px;
}

.card h4{
    font-size:18px;
    font-weight:600;
    margin-bottom:12px;
}

.card p{
    font-size:14px;
    color:#6b7280;
    line-height:1.5;
    min-height:80px;
}

button{
    margin-top:auto;
    width:100%;
    padding:12px;
    border:none;
    border-radius:30px;
    background:#4B4C7C;
    color:#fff;
    font-size:14px;
    font-weight:500;
    cursor:pointer;
    transition:.3s;
}

button:hover{
    background:#383A65;
}
</style>
</head>

<body>

<div class="container">
    <h1>Support users by offering care, guidance, and assistance.</h1>
    <h3>Which describes you best?</h3>

    <form method="POST">
        <div class="cards">

            <div class="card">
                <img src="../pictures/doctor.jpeg" alt="Doctor">
                <h4>Doctor</h4>
                <p>Provide physical therapy services to help patients improve movement, reduce pain, and recover from injuries.</p>
                <button name="role" value="doctor">Continue</button>
            </div>

            <div class="card">
                <img src="../pictures/caregiver.jpeg" alt="Caregiver">
                <h4>Caregiver</h4>
                <p>Help individuals communicate effectively using sign language or other supported communication methods.</p>
                <button name="role" value="caregiver">Continue</button>
            </div>

            <div class="card">
                <img src="../pictures/driver.jpeg" alt="Driver">
                <h4>Driver</h4>
                <p>Offer safe and reliable transportation for people with disabilities and older adults.</p>
                <button name="role" value="driver">Continue</button>
            </div>

            <div class="card">
                <img src="../pictures/interpreter.jpeg" alt="Interpreter">
                <h4>Interpreter</h4>
                <p>Assist individuals with daily activities such as personal care, medication reminders, and mobility support.</p>
                <button name="role" value="interpreter">Continue</button>
            </div>

        </div>
    </form>
</div>

</body>
</html>
