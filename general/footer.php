<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ── Dynamic base path (works regardless of server config) ── */
$_footerDoc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_footerDir = str_replace('\\', '/', dirname(__DIR__)); // rafiq root
$_rel       = ltrim(str_replace($_footerDoc, '', $_footerDir), '/');
$_base      = '/' . $_rel;   // e.g. /rafiiq/rafiq

$home_link      = "$_base/general/login.php";
$faq_link       = "$_base/patient/faq.php";
$our_story_link = "$_base/patient/our_story.php";
$chatbot_link   = "$_base/general/chatbot.php";

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == "patient") {
        $home_link = "$_base/patient/patient_homepage.php";
    } elseif ($_SESSION['role'] == "provider") {
        if ($_SESSION['provider_type'] == "doctor") {
            $home_link = "$_base/providers/doctor/doctor_homepage.php";
        } elseif ($_SESSION['provider_type'] == "interpreter") {
            $home_link = "$_base/providers/interpreter/int_homepage.php";
        } elseif ($_SESSION['provider_type'] == "driver") {
            $home_link = "$_base/providers/driver/driver_portal.php";
        } elseif ($_SESSION['provider_type'] == "caregiver") {
            $home_link = "$_base/providers/caregiver/caregiver_home.php";
        }
    }
}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
.rafiq-footer {
    background: white;
    border-top: 1px solid #e5e5e5;
    text-align: center;
    padding: 40px 20px;
}
.rafiq-footer .footer-logo img {
    height: 55px;
    margin-bottom: 20px;
}
.rafiq-footer .footer-links {
    display: flex;
    justify-content: center;
    gap: 60px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}
.rafiq-footer .footer-links a {
    text-decoration: none;
    color: #2B2C41;
    font-size: 16px;
    font-weight: 500;
    transition: color .18s;
}
.rafiq-footer .footer-links a:hover { color: #6470d2; }
.rafiq-footer .footer-social { margin-bottom: 20px; }
.rafiq-footer .footer-ig-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    background: linear-gradient(135deg, #353b69, #6470d2);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: opacity .18s, transform .18s;
}
.rafiq-footer .footer-ig-btn:hover { opacity: .88; transform: translateY(-2px); }
.rafiq-footer .footer-ig-btn i { font-size: 18px; }
.rafiq-footer .footer-copy { font-size: 14px; color: #2B2C41; }
.rafiq-footer .footer-copy i { margin: 0 4px; }
</style>

<footer class="rafiq-footer">

    <div class="footer-logo">
        <img src="<?= $_base ?>/pictures/rafiq_logo.png" alt="Rafiq Logo">
    </div>

    <nav class="footer-links" aria-label="Footer navigation">
        <a href="<?= $home_link ?>">Home</a>
        <a href="<?= $faq_link ?>">FAQs</a>
        <a href="<?= $our_story_link ?>">Our Story</a>
    </nav>

    <div class="footer-social">
        <a href="https://www.instagram.com/rafiq_eg?igsh=OGF4OXJ6eHJ1bHpo&utm_source=qr"
           target="_blank" rel="noopener" aria-label="Follow Rafiq on Instagram"
           class="footer-ig-btn">
            <i class="fa-brands fa-instagram"></i> @rafiq_eg
        </a>
    </div>

    <div class="footer-copy">
        <i class="fa-regular fa-copyright"></i>
        Copyright <?= date("Y") ?> All rights reserved |
        Made with <i class="fa-solid fa-heart"></i> by RafiQ
    </div>

</footer>
