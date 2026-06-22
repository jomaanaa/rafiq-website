<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rafiq - Sign Up</title>

<style>

/* BODY */
body{
    margin:0;
    padding:0;
    font-family: 'Arial', sans-serif;
    background-color:#F2F2F6;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

/* MAIN CONTAINER */
.container{
    width:1100px;
    max-width:95%;
    text-align:center;
}

/* TITLE */
.container h1{
    color:#2B2C41;
    margin-bottom:60px;
    font-size:32px;
}

/* CARD WRAPPER */
.cards{
    display:flex;
    justify-content:center;
    gap:60px;
}

/* SINGLE CARD */
.card{
    background:white;
    width:350px;
    padding:40px 30px;
    border-radius:25px;
    box-shadow:0 15px 30px rgba(0,0,0,0.08);
    transition:0.3s;
}

.card:hover{
    transform:translateY(-8px);
}

/* IMAGE */
.card img{
    width:220px;
    margin-bottom:25px;
}

/* TITLE */
.card h3{
    color:#2B2C41;
    margin-bottom:15px;
}

/* TEXT */
.card p{
    color:#6B6C80;
    font-size:14px;
    line-height:1.6;
    margin-bottom:30px;
}

/* BUTTON */
.card a{
    display:inline-block;
    padding:14px 40px;
    background-color:#404066;
    color:white;
    border-radius:12px;
    text-decoration:none;
    font-weight:600;
    transition:0.3s;
}

.card a:hover{
    background-color:#2B2C41;
}

/* LOGIN LINK */
.login-link{
    margin-top:40px;
    font-size:14px;
}

.login-link a{
    text-decoration:none;
    color:#404066;
    font-weight:600;
}

</style>
</head>

<body>

<div class="container">

    <h1>How will you use Rafiq?</h1>

    <div class="cards">

        <!-- SERVICE PROVIDER -->
        <div class="card">
            <img src="../pictures/provider.jpeg" alt="Provider">
            <h3>Service Provider</h3>
            <p>
                Offer your services to patients, manage requests,
                and support people in their daily and medical needs.
            </p>
            <a href="../providers/provtype.php">Continue</a>
        </div>

        <!-- PATIENT -->
        <div class="card">
            <img src="../pictures/patient.jpeg" alt="Patient">
            <h3>Patient</h3>
            <p>
                Access support services, find trusted providers,
                and get help with daily needs and healthcare.
            </p>
            <a href="../patient/signup_patient.php">Continue</a>
        </div>

    </div>

    <div class="login-link">
        Already have an account?
        <a href="login.php">Sign in</a>
    </div>

</div>

</body>
</html>