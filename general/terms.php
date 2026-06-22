<?php
session_start();
require __DIR__ . '/../pgdb/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('
    SELECT role
    FROM "user"
    WHERE user_id = :user_id
    LIMIT 1
');
$stmt->execute(['user_id' => $uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$role = strtolower($user['role'] ?? '');

function redirectAfterTerms(PDO $pdo, int $uid, string $role): void {
    if ($role === 'patient') {
        header("Location: ../patient/patient_homepage.php");
        exit();
    }

    if ($role === 'provider') {
    header("Location: ../providers/pending.php");
    exit();
}

    // Admin or any other role should not use Terms page
    if ($role === 'admin') {
        header("Location: ../admin/admin_dashboard.php");
        exit();
    }

    header("Location: login.php");
    exit();
}

if ($role === 'admin') {
    header("Location: ../admin/admin_dashboard.php");
    exit();
}

if ($role !== 'patient' && $role !== 'provider') {
    header("Location: login.php");
    exit();
}

if (isset($_POST['accept_terms']) && isset($_POST['signature_data'])) {
    $sigData = trim($_POST['signature_data'] ?? '');

    if ($sigData && str_starts_with($sigData, 'data:image/png;base64,')) {
        $_SESSION['terms_accepted'] = true;
        $_SESSION['terms_accepted_at'] = date('Y-m-d H:i:s');

        redirectAfterTerms($pdo, $uid, $role);
    }
}

if (!empty($_SESSION['terms_accepted'])) {
    redirectAfterTerms($pdo, $uid, $role);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rafiq — Terms & Conditions</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --bg:#f7f8fc;
  --card:#fff;
  --text:#1f2340;
  --muted:#7b7f98;
  --primary:#5b59a6;
  --primary-2:#494788;
  --line:#e9eaf5;
  --shadow:0 14px 34px rgba(35,39,92,.09);
}
*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:"Nunito",system-ui,-apple-system,sans-serif;
  background:radial-gradient(circle at top right,rgba(91,89,166,.08),transparent 22%),var(--bg);
  color:var(--text);
  min-height:100vh;
}

/* ── Page wrapper ── */
.page{
  width:min(820px,calc(100% - 32px));
  margin:0 auto;
  padding:36px 0 60px;
}

/* ── Header ── */
.tc-header{
  text-align:center;
  margin-bottom:28px;
}
.tc-logo{
  display:inline-flex;
  align-items:center;
  gap:10px;
  text-decoration:none;
  margin-bottom:18px;
}
.tc-logo-mark{
  width:48px;height:48px;border-radius:14px;
  background:linear-gradient(135deg,var(--primary),var(--primary-2));
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:22px;font-weight:900;
  box-shadow:0 8px 20px rgba(91,89,166,.28);
}
.tc-logo-text{font-size:24px;font-weight:900;color:var(--text)}
.tc-title{font-size:28px;font-weight:900;color:var(--text);line-height:1.2}
.tc-subtitle{margin-top:6px;color:var(--muted);font-weight:700;font-size:14px}
.tc-updated{
  margin-top:10px;
  display:inline-block;
  background:#eef0ff;
  color:var(--primary);
  padding:4px 14px;
  border-radius:20px;
  font-size:12px;
  font-weight:900;
}

/* ── Progress bar ── */
.scroll-progress-wrap{
  position:sticky;
  top:0;
  z-index:50;
  background:rgba(247,248,252,.92);
  backdrop-filter:blur(8px);
  padding:10px 0;
  margin-bottom:18px;
  border-bottom:1px solid var(--line);
}
.scroll-progress-inner{
  display:flex;
  align-items:center;
  gap:12px;
  width:min(820px,calc(100% - 32px));
  margin:0 auto;
}
.scroll-bar-bg{
  flex:1;
  height:6px;
  background:#e0e2f0;
  border-radius:99px;
  overflow:hidden;
}
.scroll-bar-fill{
  height:100%;
  width:0%;
  background:linear-gradient(90deg,var(--primary),#9b8cee);
  border-radius:99px;
  transition:width .15s;
}
.scroll-pct{
  font-size:12px;
  font-weight:900;
  color:var(--primary);
  min-width:36px;
  text-align:right;
}

/* ── T&C scroll box ── */
.tc-box{
  background:var(--card);
  border:1px solid var(--line);
  border-radius:24px;
  box-shadow:var(--shadow);
  overflow:hidden;
  margin-bottom:24px;
}
.tc-scroll{
  height:480px;
  overflow-y:auto;
  padding:30px 32px;
  scroll-behavior:smooth;
}
.tc-scroll::-webkit-scrollbar{width:5px}
.tc-scroll::-webkit-scrollbar-track{background:#f0f1f8}
.tc-scroll::-webkit-scrollbar-thumb{background:#c4c7e8;border-radius:99px}

.tc-intro{
  background:linear-gradient(135deg,#f4f5ff,#eef0ff);
  border:1px solid rgba(91,89,166,.12);
  border-radius:16px;
  padding:18px 20px;
  margin-bottom:28px;
  font-size:14px;
  font-weight:700;
  color:#363a6a;
  line-height:1.7;
}

.tc-section{margin-bottom:28px}
.tc-section-title{
  font-size:15px;
  font-weight:900;
  color:var(--primary);
  margin-bottom:10px;
  display:flex;
  align-items:center;
  gap:10px;
}
.tc-section-num{
  width:28px;height:28px;
  border-radius:8px;
  background:linear-gradient(135deg,var(--primary),var(--primary-2));
  color:#fff;
  font-size:12px;
  font-weight:900;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.tc-section p{
  font-size:13.5px;
  font-weight:700;
  color:#404060;
  line-height:1.75;
  margin-bottom:10px;
}
.tc-section ul{
  list-style:none;
  padding:0;
  margin:8px 0 10px 0;
}
.tc-section ul li{
  font-size:13.5px;
  font-weight:700;
  color:#404060;
  line-height:1.7;
  padding:4px 0 4px 22px;
  position:relative;
}
.tc-section ul li::before{
  content:'•';
  position:absolute;
  left:4px;
  color:var(--primary);
  font-size:16px;
  line-height:1.4;
}
.tc-section .sub-heading{
  font-size:13px;
  font-weight:900;
  color:#2a2d50;
  margin:12px 0 4px;
}

.tc-scroll-hint{
  text-align:center;
  padding:14px 0 0;
  font-size:12px;
  font-weight:800;
  color:var(--muted);
  border-top:1px solid var(--line);
  background:var(--card);
}
.tc-scroll-hint.done{color:#2f8f4e}

/* ── Signature area ── */
.sig-card{
  background:var(--card);
  border:1px solid var(--line);
  border-radius:24px;
  box-shadow:var(--shadow);
  padding:24px 28px;
  margin-bottom:20px;
}
.sig-title{font-size:17px;font-weight:900;margin-bottom:4px}
.sig-sub{font-size:13px;font-weight:700;color:var(--muted);margin-bottom:18px}
.sig-canvas-wrap{
  position:relative;
  border:2px dashed #c8cbea;
  border-radius:16px;
  background:#f9f9fd;
  overflow:hidden;
  height:160px;
  cursor:crosshair;
  transition:border-color .2s;
}
.sig-canvas-wrap.has-sig{border-color:var(--primary);border-style:solid}
#sigCanvas{display:block;width:100%;height:100%}
.sig-placeholder{
  position:absolute;
  inset:0;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  gap:6px;
  pointer-events:none;
  color:#b0b3cc;
  font-weight:800;
  font-size:13px;
  transition:opacity .2s;
}
.sig-placeholder i{font-size:28px}
.sig-actions{
  display:flex;
  justify-content:flex-end;
  margin-top:10px;
}
.sig-clear{
  background:none;
  border:1px solid #e0e2f0;
  border-radius:10px;
  padding:6px 14px;
  font-family:"Nunito",sans-serif;
  font-size:12px;
  font-weight:800;
  color:var(--muted);
  cursor:pointer;
  transition:background .15s;
}
.sig-clear:hover{background:#f0f1f8}

/* ── Agreement checkbox ── */
.agree-row{
  display:flex;
  align-items:flex-start;
  gap:14px;
  background:var(--card);
  border:1.5px solid var(--line);
  border-radius:18px;
  padding:18px 20px;
  margin-bottom:20px;
  cursor:pointer;
  transition:border-color .2s,box-shadow .2s;
}
.agree-row:hover{border-color:var(--primary)}
.agree-row input[type=checkbox]{
  width:22px;height:22px;
  flex-shrink:0;
  margin-top:1px;
  accent-color:var(--primary);
  cursor:pointer;
}
.agree-text{font-size:14px;font-weight:800;color:#333660;line-height:1.55}
.agree-text span{color:var(--primary)}

/* ── Submit button ── */
.submit-btn{
  width:100%;
  height:60px;
  border:0;
  border-radius:18px;
  background:linear-gradient(135deg,var(--primary),var(--primary-2));
  color:#fff;
  font-family:"Nunito",sans-serif;
  font-size:16px;
  font-weight:900;
  cursor:pointer;
  box-shadow:0 14px 30px rgba(91,89,166,.26);
  transition:transform .2s,box-shadow .2s,opacity .2s;
  position:relative;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
}
.submit-btn:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 18px 36px rgba(91,89,166,.32)}
.submit-btn:disabled{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none}
.submit-btn-hint{
  margin-top:10px;
  text-align:center;
  font-size:12px;
  font-weight:800;
  color:var(--muted);
  min-height:18px;
}

@media(max-width:600px){
  .page{padding:20px 0 40px}
  .tc-scroll{height:360px;padding:20px 18px}
  .sig-card{padding:18px 16px}
}
</style>
</head>
<body>

<div class="scroll-progress-wrap">
  <div class="scroll-progress-inner">
    <i class="fa-solid fa-scroll" style="color:var(--primary);font-size:16px"></i>
    <div class="scroll-bar-bg"><div class="scroll-bar-fill" id="scrollFill"></div></div>
    <span class="scroll-pct" id="scrollPct">0%</span>
  </div>
</div>

<div class="page">

  <!-- Header -->
  <div class="tc-header">
    <a href="login.php" class="tc-logo">
      <div class="tc-logo-mark">R</div>
      <span class="tc-logo-text">Rafiq</span>
    </a>
    <h1 class="tc-title">Terms &amp; Conditions</h1>
    <p class="tc-subtitle">Please read carefully before using the platform</p>
    <span class="tc-updated">Last Updated: 11 May 2026</span>
  </div>

  <!-- T&C Scroll Box -->
  <div class="tc-box">
    <div class="tc-scroll" id="tcScroll">

      <div class="tc-intro">
        Welcome to Rafiq. By creating an account, accessing, or using the Rafiq platform, website, or mobile application, you agree to comply with and be legally bound by the following Terms and Conditions. If you do not agree with any part of these terms, you must not use the platform.
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">1</div>About Rafiq</div>
        <p>Rafiq is a digital platform designed to connect individuals with disabilities and elderly users with independent caregivers, doctors, interpreters, transportation providers, and accessibility-related services.</p>
        <p>Rafiq acts solely as an intermediary platform and is not a direct healthcare provider, transportation company, emergency service, or medical institution.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">2</div>Eligibility</div>
        <p>To use the platform, users must:</p>
        <ul>
          <li>Be at least 18 years old or use the platform under the supervision of a legal guardian.</li>
          <li>Provide accurate and truthful registration information.</li>
          <li>Comply with all applicable laws and platform policies.</li>
        </ul>
        <p>Rafiq reserves the right to suspend or terminate accounts that contain false information or violate platform rules.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">3</div>Account Registration &amp; Security</div>
        <p>Users are responsible for:</p>
        <ul>
          <li>Maintaining the confidentiality of their account credentials.</li>
          <li>All activity occurring under their account.</li>
          <li>Immediately notifying the platform of unauthorized access or suspicious activity.</li>
        </ul>
        <p>Rafiq is not responsible for losses caused by unauthorized account access resulting from user negligence.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">4</div>Provider Verification</div>
        <p>All caregivers, doctors, interpreters, and drivers on the platform undergo:</p>
        <ul>
          <li>Identity verification</li>
          <li>Professional license verification</li>
          <li>Background checks</li>
          <li>Reference checks</li>
          <li>Periodic reviews and monitoring</li>
        </ul>
        <p>However, users acknowledge that while Rafiq performs reasonable verification procedures, the platform cannot always guarantee provider behavior.</p>
        <p>Each provider remains individually and legally responsible for their own conduct, services, medical decisions, transportation operations, and professional actions.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">5</div>Independent Contractor Relationship</div>
        <p>All service providers available on Rafiq are independent contractors and are not employees, agents, or representatives of Rafiq.</p>
        <p>Rafiq does not directly supervise or control the specific services provided by independent providers.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">6</div>Medical &amp; Emergency Disclaimer</div>
        <p>Rafiq does not provide emergency medical services. The platform must <strong>NOT</strong> be used during:</p>
        <ul>
          <li>Medical emergencies</li>
          <li>Life-threatening situations</li>
          <li>Criminal emergencies</li>
          <li>Fire or accident emergencies</li>
        </ul>
        <p>Users must contact official emergency services, hospitals, ambulance services, or police authorities directly when urgent assistance is required.</p>
        <p>Any healthcare-related information provided through the platform is for support and booking purposes only and does not replace professional medical diagnosis or emergency treatment.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">7</div>User Responsibilities</div>
        <p>Users agree to:</p>
        <ul>
          <li>Treat providers respectfully and professionally.</li>
          <li>Provide accurate booking and contact information.</li>
          <li>Avoid harassment, threats, discrimination, or abusive behavior.</li>
          <li>Avoid fake reviews, false reports, or fraudulent activity.</li>
          <li>Use the platform only for lawful purposes.</li>
        </ul>
        <p>Rafiq reserves the right to suspend or permanently terminate any account involved in misconduct, abuse, fraud, or policy violations.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">8</div>Provider Responsibilities</div>
        <p>Providers agree to:</p>
        <ul>
          <li>Deliver services professionally, ethically, and safely.</li>
          <li>Maintain valid licenses and certifications.</li>
          <li>Respect user privacy and confidentiality.</li>
          <li>Avoid discrimination or abusive behavior.</li>
          <li>Arrive on time for confirmed appointments whenever reasonably possible.</li>
          <li>Follow platform standards and code of conduct policies.</li>
        </ul>
        <p>Providers who violate platform rules may face:</p>
        <ul>
          <li>Warnings</li>
          <li>Temporary suspension</li>
          <li>Financial penalties</li>
          <li>Permanent removal from the platform</li>
        </ul>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">9</div>Payments &amp; Transactions</div>
        <p>All payments are processed securely through approved payment gateways. By booking a service, users agree to pay:</p>
        <ul>
          <li>Applicable service fees</li>
          <li>Platform commissions</li>
          <li>Taxes (if applicable)</li>
        </ul>
        <p>Prices displayed on the platform may change at any time without prior notice. Rafiq does not store full payment card details on its servers.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">10</div>Cancellation &amp; Refund Policy</div>
        <div class="sub-heading">User Cancellations</div>
        <ul>
          <li>Cancellations made more than 24 hours before the scheduled appointment are eligible for a full refund.</li>
          <li>Cancellations made within 24 hours may receive only a partial refund.</li>
          <li>Repeated no-shows or abusive cancellations may result in account restrictions or suspension.</li>
        </ul>
        <div class="sub-heading">Provider Cancellations</div>
        <ul>
          <li>Providers who repeatedly cancel appointments without valid reasons may receive penalties or account suspension.</li>
          <li>Emergency situations will be reviewed individually by platform administration.</li>
        </ul>
        <div class="sub-heading">Refund Processing</div>
        <p>Approved refunds may require several business days to process depending on the payment provider or bank.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">11</div>Disputes Between Users and Providers</div>
        <p>In the event of disputes between users and providers:</p>
        <ul>
          <li>Rafiq may investigate complaints and review evidence.</li>
          <li>Payments may be temporarily withheld during investigations.</li>
          <li>The platform may mediate disputes where possible.</li>
        </ul>
        <p>However, Rafiq is not legally liable for:</p>
        <ul>
          <li>Personal disagreements</li>
          <li>Medical malpractice</li>
          <li>Injuries caused by providers</li>
          <li>Theft or misconduct</li>
          <li>Transportation accidents</li>
          <li>Service dissatisfaction beyond platform control</li>
        </ul>
        <p>Each provider remains personally responsible for their own actions and services.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">12</div>Liability Limitation</div>
        <p>To the maximum extent permitted by law, Rafiq shall not be liable for:</p>
        <ul>
          <li>Direct or indirect damages</li>
          <li>Personal injury</li>
          <li>Emotional distress</li>
          <li>Financial losses</li>
          <li>Service interruptions</li>
          <li>Provider negligence</li>
          <li>Delays caused by traffic, weather, technical issues, or emergencies</li>
        </ul>
        <p>Users use the platform and services at their own discretion and risk.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">13</div>Accessibility Map &amp; Community Contributions</div>
        <p>Users may upload:</p>
        <ul>
          <li>Accessibility reviews</li>
          <li>Ratings</li>
          <li>Photos</li>
          <li>Comments</li>
          <li>Location reports</li>
        </ul>
        <p>By uploading content, users grant Rafiq a non-exclusive right to display and use this content within the platform. Users are prohibited from posting:</p>
        <ul>
          <li>Offensive content</li>
          <li>False information</li>
          <li>Hate speech</li>
          <li>Copyrighted material without permission</li>
          <li>Misleading accessibility reports</li>
        </ul>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">14</div>Privacy &amp; Data Protection</div>
        <p>Rafiq processes personal information according to:</p>
        <ul>
          <li>GDPR</li>
          <li>Egypt's Personal Data Protection Law No. 151 of 2020</li>
        </ul>
        <p>User data is encrypted, securely stored, and protected against unauthorized access. Users have the right to:</p>
        <ul>
          <li>Access their data</li>
          <li>Correct inaccurate information</li>
          <li>Request deletion of their account and personal data</li>
          <li>Withdraw consent where legally applicable</li>
        </ul>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">15</div>Suspension &amp; Termination</div>
        <p>Rafiq reserves the right to suspend or permanently terminate accounts involved in:</p>
        <ul>
          <li>Fraud</li>
          <li>Harassment</li>
          <li>Violence or criminal activity</li>
          <li>Abuse or fake reviews</li>
          <li>Impersonation</li>
          <li>Violation of platform policies</li>
        </ul>
        <p>Serious violations may also be reported to legal authorities when required.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">16</div>Intellectual Property</div>
        <p>All platform content including the Rafiq name, logo, designs, software, source code, branding, graphics, and interface elements are protected under intellectual property and copyright laws.</p>
        <p>No content may be copied, distributed, modified, or reused without written permission from Rafiq.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">17</div>Data Security &amp; Cybersecurity</div>
        <p>Rafiq implements security measures including:</p>
        <ul>
          <li>Encryption technologies</li>
          <li>Secure databases</li>
          <li>Authentication systems</li>
          <li>Audit logs</li>
          <li>Monitoring systems</li>
        </ul>
        <p>Despite these measures, no digital platform can guarantee absolute cybersecurity protection.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">18</div>Modifications to Terms</div>
        <p>Rafiq reserves the right to update or modify these Terms and Conditions at any time. Users will be notified of significant changes through the application or email notifications.</p>
        <p>Continued use of the platform after updates constitutes acceptance of the revised terms.</p>
      </div>

      <div class="tc-section">
        <div class="tc-section-title"><div class="tc-section-num">19</div>Governing Law</div>
        <p>These Terms and Conditions shall be governed and interpreted in accordance with the laws and regulations of the Arab Republic of Egypt.</p>
        <p>Any legal disputes shall fall under the jurisdiction of the Egyptian courts.</p>
      </div>

      <div class="tc-section" style="margin-bottom:0">
        <div class="tc-section-title"><div class="tc-section-num">20</div>Acceptance of Terms</div>
        <p>By selecting "I Agree and Continue," the user confirms that they:</p>
        <ul>
          <li>Have read and understood these Terms and Conditions</li>
          <li>Agree to comply with all platform policies</li>
          <li>Accept legal responsibility for their use of the platform</li>
        </ul>
      </div>

    </div><!-- end tc-scroll -->
    <div class="tc-scroll-hint" id="scrollHint">
      <i class="fa-solid fa-angles-down"></i>&nbsp; Scroll to read all terms
    </div>
  </div>

  <!-- Digital Signature -->
  <div class="sig-card">
    <div class="sig-title">Digital Signature</div>
    <div class="sig-sub">Please sign below to confirm your identity and acceptance.</div>
    <div class="sig-canvas-wrap" id="sigWrap">
      <canvas id="sigCanvas"></canvas>
      <div class="sig-placeholder" id="sigPlaceholder">
        <i class="fa-solid fa-pen-nib"></i>
        <span>Sign here with your mouse or finger</span>
      </div>
    </div>
    <div class="sig-actions">
      <button type="button" class="sig-clear" id="sigClearBtn">
        <i class="fa-solid fa-rotate-left"></i>&nbsp; Clear signature
      </button>
    </div>
  </div>

  <!-- Agree checkbox -->
  <label class="agree-row" for="agreeCheck">
    <input type="checkbox" id="agreeCheck">
    <span class="agree-text">I have read, understood, and agree to the <span>Rafiq Terms &amp; Conditions</span>. I accept all platform policies and take legal responsibility for my use of the service.</span>
  </label>

  <!-- Submit form -->
  <form method="post" id="acceptForm">
    <input type="hidden" name="accept_terms" value="1">
    <input type="hidden" name="signature_data" id="sigDataInput">
    <button type="submit" class="submit-btn" id="submitBtn" disabled>
      <i class="fa-solid fa-check-circle"></i> I Agree and Continue
    </button>
    <p class="submit-btn-hint" id="submitHint">Scroll through all terms, sign, and check the box to continue.</p>
  </form>

</div><!-- end .page -->

<script>
/* ── Scroll progress ── */
const tcScroll   = document.getElementById('tcScroll');
const scrollFill = document.getElementById('scrollFill');
const scrollPct  = document.getElementById('scrollPct');
const scrollHint = document.getElementById('scrollHint');

let scrolledFull = false;

tcScroll.addEventListener('scroll', function(){
  const max  = this.scrollHeight - this.clientHeight;
  const pct  = max > 0 ? Math.min(100, Math.round((this.scrollTop / max) * 100)) : 100;
  scrollFill.style.width = pct + '%';
  scrollPct.textContent  = pct + '%';

  if (pct >= 98 && !scrolledFull) {
    scrolledFull = true;
    scrollHint.innerHTML = '<i class="fa-solid fa-check" style="color:#2f8f4e"></i>&nbsp; You have read all the terms';
    scrollHint.classList.add('done');
  }
  checkReady();
});

/* ── Signature canvas ── */
const canvas      = document.getElementById('sigCanvas');
const ctx         = canvas.getContext('2d');
const sigWrap     = document.getElementById('sigWrap');
const sigPlaceholder = document.getElementById('sigPlaceholder');
const sigClearBtn = document.getElementById('sigClearBtn');

let drawing   = false;
let hasSig    = false;
let lastX = 0, lastY = 0;

function resizeCanvas(){
  const rect = sigWrap.getBoundingClientRect();
  const dpr  = window.devicePixelRatio || 1;
  canvas.width  = rect.width  * dpr;
  canvas.height = rect.height * dpr;
  ctx.scale(dpr, dpr);
  ctx.strokeStyle = '#2a2d50';
  ctx.lineWidth   = 2.2;
  ctx.lineCap     = 'round';
  ctx.lineJoin    = 'round';
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

function getPos(e){
  const rect = canvas.getBoundingClientRect();
  const src  = e.touches ? e.touches[0] : e;
  return { x: src.clientX - rect.left, y: src.clientY - rect.top };
}

function startDraw(e){
  e.preventDefault();
  drawing = true;
  const p = getPos(e);
  lastX = p.x; lastY = p.y;
  ctx.beginPath();
  ctx.moveTo(lastX, lastY);
}
function draw(e){
  if (!drawing) return;
  e.preventDefault();
  const p = getPos(e);
  ctx.lineTo(p.x, p.y);
  ctx.stroke();
  lastX = p.x; lastY = p.y;

  if (!hasSig) {
    hasSig = true;
    sigWrap.classList.add('has-sig');
    sigPlaceholder.style.opacity = '0';
  }
  checkReady();
}
function stopDraw(){ drawing = false; }

canvas.addEventListener('mousedown',  startDraw);
canvas.addEventListener('mousemove',  draw);
canvas.addEventListener('mouseup',    stopDraw);
canvas.addEventListener('mouseleave', stopDraw);
canvas.addEventListener('touchstart', startDraw, { passive:false });
canvas.addEventListener('touchmove',  draw,      { passive:false });
canvas.addEventListener('touchend',   stopDraw);

sigClearBtn.addEventListener('click', function(){
  resizeCanvas();
  hasSig = false;
  sigWrap.classList.remove('has-sig');
  sigPlaceholder.style.opacity = '1';
  checkReady();
});

/* ── Checkbox ── */
const agreeCheck = document.getElementById('agreeCheck');
agreeCheck.addEventListener('change', checkReady);

/* ── Enable / disable submit ── */
const submitBtn  = document.getElementById('submitBtn');
const submitHint = document.getElementById('submitHint');
const sigDataInput = document.getElementById('sigDataInput');

function checkReady(){
  const ready = scrolledFull && hasSig && agreeCheck.checked;
  submitBtn.disabled = !ready;

  if (ready) {
    submitHint.textContent = 'Everything is ready. Click to continue.';
  } else {
    const missing = [];
    if (!scrolledFull) missing.push('read all terms');
    if (!hasSig)       missing.push('add your signature');
    if (!agreeCheck.checked) missing.push('check the agreement box');
    submitHint.textContent = 'Please ' + missing.join(', then ') + '.';
  }
}

/* ── On submit: capture signature data ── */
document.getElementById('acceptForm').addEventListener('submit', function(e){
  if (!scrolledFull || !hasSig || !agreeCheck.checked){
    e.preventDefault();
    return;
  }
  sigDataInput.value = canvas.toDataURL('image/png');
});
</script>
</body>
</html>
