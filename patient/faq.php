<?php
session_start();
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>FAQs — Rafiq</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Manrope',sans-serif;background:linear-gradient(135deg,#f6f8fd 0%,#eef2ff 100%);min-height:100vh;color:#222335}

.faq-page{width:min(860px,calc(100% - 32px));margin:48px auto 72px}

/* Hero */
.faq-hero{
    text-align:center;
    padding:0 0 48px;
    animation:fadeUp .5s cubic-bezier(.22,.68,0,1.2) both;
}
.faq-hero-chip{
    display:inline-flex;align-items:center;gap:8px;
    padding:9px 16px;border-radius:99px;
    background:#eef2ff;color:#4b4f83;
    font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;
    margin-bottom:18px;border:1px solid rgba(75,79,131,.14);
}
.faq-hero h1{font-size:46px;font-weight:800;letter-spacing:-1.6px;color:#2B2C41;line-height:1.1;margin-bottom:14px}
.faq-hero p{color:#6b7188;font-size:16px;line-height:1.9;max-width:540px;margin:0 auto}

@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}

/* Category tabs */
.faq-tabs{
    display:flex;gap:8px;flex-wrap:wrap;
    justify-content:center;margin-bottom:36px;
}
.faq-tab{
    padding:9px 20px;border-radius:99px;
    border:1.5px solid #e2e5f1;background:#fff;
    color:#6b7188;font-size:13px;font-weight:700;
    cursor:pointer;font-family:inherit;
    transition:all .18s;
}
.faq-tab:hover{border-color:#4b4f83;color:#4b4f83}
.faq-tab.active{background:#4b4f83;border-color:#4b4f83;color:#fff}

/* Accordion */
.faq-section{margin-bottom:12px;display:none}
.faq-section.active{display:block}

.faq-item{
    background:#fff;border-radius:20px;
    border:1px solid rgba(36,39,66,.07);
    box-shadow:0 8px 24px rgba(36,39,66,.06);
    margin-bottom:10px;overflow:hidden;
    transition:box-shadow .2s;
}
.faq-item:hover{box-shadow:0 14px 36px rgba(36,39,66,.10)}

.faq-question{
    width:100%;padding:22px 24px;
    display:flex;align-items:center;justify-content:space-between;gap:16px;
    background:none;border:none;cursor:pointer;
    font-family:inherit;font-size:16px;font-weight:700;
    color:#2B2C41;text-align:left;
}
.faq-question:hover{color:#4b4f83}
.faq-chevron{
    width:34px;height:34px;min-width:34px;border-radius:12px;
    display:grid;place-items:center;
    background:#f0f2fb;color:#4b4f83;font-size:14px;
    transition:transform .25s,background .18s;
}
.faq-item.open .faq-chevron{transform:rotate(180deg);background:#4b4f83;color:#fff}

.faq-answer{
    max-height:0;overflow:hidden;
    transition:max-height .35s cubic-bezier(.22,.68,0,1.2),padding .25s;
    padding:0 24px;
}
.faq-item.open .faq-answer{
    max-height:500px;
    padding:0 24px 22px;
}
.faq-answer p{
    color:#6b7188;font-size:14px;line-height:1.9;
    border-top:1px solid #f1f3fa;padding-top:16px;
}

/* CTA */
.faq-cta{
    margin-top:48px;
    background:linear-gradient(135deg,#2B2C41,#404066 55%,#6d73c8);
    border-radius:28px;padding:40px 32px;text-align:center;color:#fff;
    animation:fadeUp .6s .1s cubic-bezier(.22,.68,0,1.2) both;
}
.faq-cta h2{font-size:28px;font-weight:800;margin-bottom:10px;letter-spacing:-.5px}
.faq-cta p{color:rgba(255,255,255,.80);font-size:15px;line-height:1.8;margin-bottom:24px}
.faq-cta-btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:13px 28px;border-radius:14px;
    background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);
    color:#fff;font-size:15px;font-weight:800;
    text-decoration:none;transition:background .18s;
}
.faq-cta-btn:hover{background:rgba(255,255,255,.22)}
</style>
</head>
<body>

<?php include '../general/nav_patient.php'; ?>

<div class="faq-page">

    <div class="faq-hero">
        <div class="faq-hero-chip"><i class="fa-solid fa-circle-question"></i> Help Center</div>
        <h1>Frequently Asked<br>Questions</h1>
        <p>Find quick answers about our services, payments, bookings, and more.</p>
    </div>

    <div class="faq-tabs">
        <button class="faq-tab active" data-target="general">General</button>
        <button class="faq-tab" data-target="booking">Booking</button>
        <button class="faq-tab" data-target="payment">Payment</button>
        <button class="faq-tab" data-target="transport">Transport</button>
        <button class="faq-tab" data-target="account">Account</button>
    </div>

    <!-- General -->
    <div class="faq-section active" id="faq-general">
        <div class="faq-item">
            <button class="faq-question">
                What is Rafiq?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Rafiq is an accessibility platform that connects people with disabilities to caregivers, doctors, drivers, and interpreters. We make it easy to find trusted support whenever you need it.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                Who can use Rafiq?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Rafiq is designed for people with physical, sensory, or communication disabilities who need support in their daily lives. Family members can also create an account to book services on behalf of their loved ones.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                Is Rafiq available in my city?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>We are currently operating in Egypt and expanding to more cities. Use the map feature in the app to see providers and accessible places available near your location.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                How do I contact support?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>You can reach our support team through the Contact page or via our Instagram page @rafiq_eg. We aim to respond within 24 hours on business days.</p>
            </div>
        </div>
    </div>

    <!-- Booking -->
    <div class="faq-section" id="faq-booking">
        <div class="faq-item">
            <button class="faq-question">
                How do I book a service?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>From the homepage, choose the service you need (caregiver, driver, doctor, or interpreter), select a provider, pick your preferred date and time, then confirm your booking. You'll receive a confirmation right away.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                Can I cancel or reschedule a booking?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Yes. Go to "My Bookings" in your profile, find the booking, and choose Cancel or Reschedule. Please note that cancellations within 2 hours of the appointment may incur a small fee depending on the provider's policy.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                How are providers selected?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>All providers go through a review and approval process by our admin team before they appear on the platform. We verify their credentials, experience, and background to ensure safe and reliable service.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                Can I rate my provider after a session?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Yes. After your session is marked complete, you'll be able to leave a star rating and a comment. Your feedback helps other users find the best providers and helps us maintain quality on the platform.</p>
            </div>
        </div>
    </div>

    <!-- Payment -->
    <div class="faq-section" id="faq-payment">
        <div class="faq-item">
            <button class="faq-question">
                What payment methods are accepted?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>We accept cash on delivery and Visa / credit card payments. You can choose your preferred method when placing a booking or purchasing a product.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                Is my payment information secure?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Yes. All payment data is encrypted and handled securely. We do not store your full card number on our servers. Card payments are processed through a certified payment gateway.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                How much does Rafiq charge?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Rafiq charges a 15% platform fee on each booking. The remaining 85% goes directly to your provider. Pricing details are shown clearly on each provider's profile before you confirm.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                Can I get a refund?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Refunds are reviewed on a case-by-case basis. If a provider cancels or fails to show up, you will receive a full refund. Please contact our support team within 48 hours of the issue to submit a refund request.</p>
            </div>
        </div>
    </div>

    <!-- Transport -->
    <div class="faq-section" id="faq-transport">
        <div class="faq-item">
            <button class="faq-question">
                How do I book an emergency transport?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>From the homepage, go to the Driver section and select "Emergency Ride." Fill in your destination and any special requirements. Our system will match you with the nearest available driver as quickly as possible.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                Are the vehicles accessible for wheelchairs?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Many of our drivers have accessible vehicles equipped for wheelchairs and mobility aids. You can filter by accessibility features when searching for a driver, or mention your requirements in the booking notes.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                Can I track my driver in real time?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Once your booking is confirmed, you can use the Map feature on Rafiq to see nearby places and navigate. Driver real-time tracking is a feature we are actively developing and will be available soon.</p>
            </div>
        </div>
    </div>

    <!-- Account -->
    <div class="faq-section" id="faq-account">
        <div class="faq-item">
            <button class="faq-question">
                How do I create an account?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Click "Sign Up" on the login page, fill in your details including your disability type, and submit. Your account will be created immediately and you can start booking services right away.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                How do I edit my profile?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>Go to your Profile page (click the user icon in the top navigation bar), then click "Edit Profile." You can update your name, phone number, address, and disability information at any time.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                I forgot my password. What should I do?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>On the login page, click "Forgot Password" and enter your registered email address. You'll receive a password reset link within a few minutes. Check your spam folder if you don't see it in your inbox.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">
                How do I delete my account?
                <span class="faq-chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="faq-answer">
                <p>To delete your account, contact our support team. We'll verify your identity and process the deletion within 7 business days. Please note that all your booking history will be permanently removed.</p>
            </div>
        </div>
    </div>

    <div class="faq-cta">
        <h2>Still have questions?</h2>
        <p>Our team is happy to help. Reach out through our Instagram page or contact us directly.</p>
        <a class="faq-cta-btn" href="https://www.instagram.com/rafiq_eg?igsh=OGF4OXJ6eHJ1bHpo&utm_source=qr" target="_blank" rel="noopener">
            <i class="fa-brands fa-instagram"></i> Contact us on Instagram
        </a>
    </div>

</div>

<script>
// Tabs
document.querySelectorAll('.faq-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.faq-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.faq-section').forEach(s => s.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('faq-' + this.dataset.target).classList.add('active');
    });
});

// Accordion
document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', function() {
        const item = this.closest('.faq-item');
        const isOpen = item.classList.contains('open');
        // close siblings
        item.closest('.faq-section').querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
    });
});
</script>

<?php include '../general/footer.php'; ?>
</body>
</html>
