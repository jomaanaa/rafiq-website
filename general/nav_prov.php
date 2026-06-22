<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['provider_type']) && empty($_SESSION['user_id'])) {
    header("Location: ../../general/login.php");
    exit();
}

$providerType = strtolower(trim((string)($_SESSION['provider_type'] ?? '')));

/*
    This navbar is used inside:
    providers/doctor/
    providers/caregiver/
    providers/driver/
    providers/interpreter/

    So paths must start with ../../
*/

$home_link = '../../general/login.php';
$profile_link = '../../general/login.php';

if ($providerType === 'doctor') {
    $home_link = '../../providers/doctor/doctor_homepage.php';
    $profile_link = '../../providers/doctor/doctor_profile.php';
}

elseif ($providerType === 'caregiver') {
    $home_link = '../../providers/caregiver/caregiver_homepage.php';
    $profile_link = '../../providers/caregiver/caregiver_profile.php';
}

elseif ($providerType === 'driver') {
    $home_link = '../../providers/driver/driver_portal.php';
    $profile_link = '../../providers/driver/driver_profile.php';
}

elseif ($providerType === 'interpreter') {
    $home_link = '../../providers/interpreter/int_homepage.php';
    $profile_link = '../../providers/interpreter/int_profile.php';
}
?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
.provider-navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    width: 100%;
    height: 68px;
    background: rgba(255,255,255,0.94);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 36px;
    box-sizing: border-box;
    border-bottom: 1px solid rgba(36,39,66,0.08);
    box-shadow: 0 2px 16px rgba(36,39,66,0.06);
}

.provider-nav-left {
    display: flex;
    align-items: center;
    gap: 34px;
}

.provider-logo {
    display: flex;
    align-items: center;
    text-decoration: none;
}

.provider-logo img {
    height: 40px;
    display: block;
}

.provider-nav-links {
    display: flex;
    align-items: center;
    gap: 24px;
}

.provider-nav-links a,
.provider-nav-right a {
    text-decoration: none;
    font-family: 'Segoe UI', sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: #2B2C41;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 13px;
    border-radius: 12px;
    transition: background .18s ease, color .18s ease;
}

.provider-nav-links a:hover,
.provider-nav-right a:hover {
    background: #f0f2fb;
    color: #4b4f83;
}

.provider-nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.provider-logout {
    color: #b53535 !important;
}

.provider-logout:hover {
    background: #fff1f1 !important;
    color: #8b1a1a !important;
}

@media(max-width: 640px) {
    .provider-navbar {
        padding: 0 18px;
    }

    .provider-nav-left {
        gap: 16px;
    }

    .provider-logo img {
        height: 34px;
    }

    .provider-nav-links a,
    .provider-nav-right a {
        font-size: 13px;
        padding: 8px 9px;
    }

    .provider-nav-links a span,
    .provider-nav-right a span {
        display: none;
    }
}
</style>

<nav class="provider-navbar">
    <div class="provider-nav-left">
        <a class="provider-logo" href="<?= htmlspecialchars($home_link) ?>">
            <img src="../../pictures/rafiq_logo.png" alt="Rafiq Logo">
        </a>

        <div class="provider-nav-links">
            <a href="<?= htmlspecialchars($home_link) ?>">
                <i class="fa-solid fa-house"></i>
                <span>Home</span>
            </a>
        </div>
    </div>

    <div class="provider-nav-right">
        <a href="<?= htmlspecialchars($profile_link) ?>">
            <i class="fa-regular fa-user"></i>
            <span>Profile</span>
        </a>

        <a href="../../general/logout.php" class="provider-logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>