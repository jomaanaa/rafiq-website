<?php
session_start();
$_doc  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_dir  = str_replace('\\', '/', dirname(__DIR__));
$_rel  = ltrim(str_replace($_doc, '', $_dir), '/');
$_base = '/' . $_rel;

$back_link = "$_base/general/login.php";
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'patient') $back_link = "$_base/patient/patient_homepage.php";
    elseif ($_SESSION['role'] === 'provider') {
        $map = ['doctor'=>"$_base/providers/doctor/doctor_homepage.php",'interpreter'=>"$_base/providers/interpreter/int_homepage.php",'driver'=>"$_base/providers/driver/driver_portal.php",'caregiver'=>"$_base/providers/caregiver/caregiver_home.php"];
        $back_link = $map[$_SESSION['provider_type'] ?? ''] ?? $back_link;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rafiq | Smart OCR Reader</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#1e2040;--purple:#353b69;--accent:#6470d2;--a2:#494788;
  --light:#eef0ff;--green:#16a34a;--red:#dc2626;--amber:#d97706;
  --bg:#f4f5fb;--card:#fff;--text:#1e2040;--muted:#6b7080;
  --border:rgba(100,112,210,.13);--sh:0 4px 20px rgba(30,32,64,.08);
  --sh-lg:0 16px 48px rgba(30,32,64,.13);--mono:'JetBrains Mono',monospace;
}
html{scroll-behavior:smooth}
body{font-family:"Nunito",system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
:focus-visible{outline:2.5px solid var(--accent);outline-offset:3px;border-radius:6px}

/* ── Init overlay ── */
#ocrInit{position:fixed;inset:0;z-index:9000;background:rgba(20,22,50,.93);backdrop-filter:blur(10px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;color:#fff;transition:opacity .5s}
#ocrInit.hidden{opacity:0;pointer-events:none}
.init-icon{width:72px;height:72px;border-radius:22px;background:linear-gradient(135deg,var(--accent),var(--a2));display:flex;align-items:center;justify-content:center;font-size:30px;box-shadow:0 12px 40px rgba(100,112,210,.45);animation:initFloat 2s ease-in-out infinite}
@keyframes initFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.init-title{font-size:20px;font-weight:900}
.init-sub{font-size:13px;font-weight:700;opacity:.6;margin-top:-6px}
.init-bar{width:220px;height:5px;background:rgba(255,255,255,.12);border-radius:99px;overflow:hidden}
.init-fill{height:100%;width:0;background:linear-gradient(90deg,var(--accent),var(--a2));border-radius:99px;transition:width .3s ease}
.init-step{font-size:12px;font-weight:700;opacity:.55;min-height:18px;text-align:center}

/* ── Layout ── */
.wrap{max-width:1220px;margin:0 auto;padding:0 24px 24px}

/* ── Hero ── */
.hero{background:linear-gradient(135deg,var(--navy) 0%,#2d1b69 50%,var(--accent) 100%);margin:0 -24px;padding:38px 52px 46px;color:#fff;position:relative;overflow:hidden;border-radius:0 0 40px 40px}
.hero-orb{position:absolute;border-radius:50%;pointer-events:none}
.hero-orb-1{width:380px;height:380px;top:-150px;right:-100px;background:rgba(255,255,255,.04)}
.hero-orb-2{width:200px;height:200px;bottom:-80px;left:3%;background:rgba(255,255,255,.03)}
.hero-inner{position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:28px;flex-wrap:wrap}
.hero-text{flex:1;min-width:240px}
.hero-back{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:12px;border:1.5px solid rgba(255,255,255,.3);background:rgba(255,255,255,.1);color:#fff;font-size:13px;font-weight:800;text-decoration:none;margin-bottom:18px;transition:background .15s,transform .12s;backdrop-filter:blur(8px)}
.hero-back:hover{background:rgba(255,255,255,.2);transform:translateX(-2px)}
.hero-badge{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);font-size:10.5px;font-weight:900;letter-spacing:.07em;text-transform:uppercase;margin-bottom:14px}
.hero h1{font-size:clamp(24px,3.8vw,42px);font-weight:900;letter-spacing:-.8px;line-height:1.06;margin-bottom:12px}
.hero h1 span{background:linear-gradient(90deg,#c4caff,#fff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hero p{font-size:14px;font-weight:600;color:rgba(255,255,255,.78);line-height:1.75;max-width:520px}
.hero-tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
.hero-tag{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:8px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);font-size:11.5px;font-weight:800;color:rgba(255,255,255,.88)}
.hero-widget{background:rgba(255,255,255,.1);backdrop-filter:blur(16px);border:1.5px solid rgba(255,255,255,.2);border-radius:24px;padding:26px 30px;text-align:center;min-width:160px;flex-shrink:0}
.hw-icon{font-size:34px;margin-bottom:8px}
.hw-num{font-size:34px;font-weight:900;line-height:1}
.hw-label{font-size:11px;font-weight:800;opacity:.6;text-transform:uppercase;letter-spacing:.06em;margin-top:4px}

/* ── Cards ── */
.card{background:var(--card);border:1px solid var(--border);border-radius:24px;box-shadow:var(--sh);overflow:hidden}
.card-head{padding:18px 22px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.card-head-icon{width:40px;height:40px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:17px;background:var(--light);flex-shrink:0;color:var(--accent)}
.card-head-title{font-size:15px;font-weight:900;color:var(--navy)}
.card-head-sub{font-size:11.5px;font-weight:700;color:var(--muted);margin-top:1px}
.card-body{padding:20px 22px}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:7px;padding:11px 20px;border-radius:13px;font-size:13.5px;font-weight:800;font-family:inherit;cursor:pointer;border:none;transition:all .18s;text-decoration:none;user-select:none}
.btn-primary{background:linear-gradient(135deg,var(--accent),var(--a2));color:#fff;box-shadow:0 4px 16px rgba(100,112,210,.25)}
.btn-primary:hover{box-shadow:0 6px 24px rgba(100,112,210,.4);transform:translateY(-1px)}
.btn-primary:disabled{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none}
.btn-secondary{background:var(--light);color:var(--accent);border:1.5px solid var(--border)}
.btn-secondary:hover:not(:disabled){background:#e4e7ff;border-color:rgba(100,112,210,.3)}
.btn-secondary:disabled{opacity:.45;cursor:not-allowed}
.btn-danger{background:#fef2f2;color:var(--red);border:1.5px solid rgba(220,38,38,.16)}
.btn-danger:hover:not(:disabled){background:#fee2e2}
.btn-danger:disabled{opacity:.45;cursor:not-allowed}
.btn-amber{background:rgba(217,119,6,.1);color:var(--amber);border:1.5px solid rgba(217,119,6,.2)}
.btn-amber:hover:not(:disabled){background:rgba(217,119,6,.18)}
.btn-amber:disabled{opacity:.45;cursor:not-allowed}
.btn-green{background:linear-gradient(135deg,#15803d,#16a34a);color:#fff;box-shadow:0 4px 14px rgba(22,163,74,.25)}
.btn-green:hover{box-shadow:0 6px 20px rgba(22,163,74,.38);transform:translateY(-1px)}
.btn-green:disabled{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none}
.btn-sm{padding:8px 14px;font-size:12px;border-radius:10px}

/* ── Grid ── */
.input-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:28px}
@media(max-width:820px){.input-grid{grid-template-columns:1fr}}

/* ── Settings bar ── */
.settings-bar{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:14px 20px;margin-top:16px;display:flex;flex-wrap:wrap;gap:16px;align-items:center}
.setting-group{display:flex;align-items:center;gap:8px}
.setting-label{font-size:12px;font-weight:800;color:var(--navy)}
.toggle-wrap{display:flex;align-items:center;gap:6px}
.toggle-cb{width:36px;height:20px;border-radius:99px;border:none;cursor:pointer;position:relative;background:#d1d5db;transition:background .2s;flex-shrink:0}
.toggle-cb.on{background:var(--accent)}
.toggle-cb::after{content:'';position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.toggle-cb.on::after{transform:translateX(16px)}
.toggle-cb-label{font-size:12px;font-weight:700;color:var(--muted)}
.lang-select{padding:6px 10px;border:1.5px solid var(--border);border-radius:10px;font-size:12px;font-weight:700;font-family:inherit;color:var(--navy);background:var(--card);cursor:pointer;outline:none}
.lang-select:focus{border-color:var(--accent)}
.settings-divider{width:1px;height:20px;background:var(--border)}

/* ── Camera ── */
.cam-viewport{position:relative;width:100%;aspect-ratio:4/3;background:#06070f;border-radius:18px;overflow:hidden;margin-bottom:14px;box-shadow:inset 0 0 0 1.5px rgba(100,112,210,.2)}
#camVideo{width:100%;height:100%;object-fit:cover;display:block}
.cam-off{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:rgba(255,255,255,.35);font-weight:800}
.cam-off i{font-size:44px;opacity:.2}
.cam-corner{position:absolute;z-index:3;width:18px;height:18px;border-color:rgba(100,112,210,.38);border-style:solid;pointer-events:none}
.cam-corner.tl{top:10px;left:10px;border-width:2px 0 0 2px;border-radius:4px 0 0 0}
.cam-corner.tr{top:10px;right:10px;border-width:2px 2px 0 0;border-radius:0 4px 0 0}
.cam-corner.bl{bottom:10px;left:10px;border-width:0 0 2px 2px;border-radius:0 0 0 4px}
.cam-corner.br{bottom:10px;right:10px;border-width:0 2px 2px 0;border-radius:0 0 4px 0}
.cam-corner.live{border-color:rgba(52,211,153,.7)!important}
.cam-flash{position:absolute;inset:0;background:#fff;opacity:0;pointer-events:none;z-index:9;transition:opacity .08s}
.cam-flash.pop{opacity:.65}
.scan-line{position:absolute;left:0;right:0;height:3px;z-index:6;background:linear-gradient(90deg,transparent,var(--accent),rgba(100,112,210,.9),var(--accent),transparent);box-shadow:0 0 18px rgba(100,112,210,.7);display:none;animation:scanMove 1.6s ease-in-out infinite}
.scan-line.active{display:block}
@keyframes scanMove{0%{top:0;opacity:.8}50%{opacity:1}100%{top:calc(100% - 3px);opacity:.8}}
.cam-btns{display:flex;gap:8px;flex-wrap:wrap}

/* ── Upload zone ── */
.upload-zone{border:2px dashed rgba(100,112,210,.25);border-radius:16px;padding:32px 20px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;position:relative;min-height:170px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px}
.upload-zone:hover,.upload-zone.over{border-color:var(--accent);background:rgba(100,112,210,.04)}
.upload-zone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-zone-icon{font-size:36px;color:var(--accent);opacity:.55}
.upload-zone-title{font-size:14px;font-weight:800;color:var(--navy)}
.upload-zone-sub{font-size:11.5px;font-weight:700;color:var(--muted)}
.upload-zone-paste{font-size:11px;font-weight:700;color:var(--accent);opacity:.7}
.upload-preview{width:100%;max-height:200px;object-fit:contain;border-radius:10px;margin-top:10px;display:none}
.upload-preview.show{display:block}
.upload-name{font-size:11px;font-weight:700;color:var(--muted);margin-top:6px;display:none}
.upload-name.show{display:block}
.paste-highlight{border-color:var(--accent)!important;background:rgba(100,112,210,.07)!important;animation:pastePop .4s ease}
@keyframes pastePop{50%{transform:scale(1.01)}}

/* ── Progress ── */
.ocr-progress{background:var(--card);border:1px solid var(--border);border-radius:20px;box-shadow:var(--sh);padding:22px 24px;margin-top:20px;display:none}
.ocr-progress.show{display:block}
.prog-title{font-size:13px;font-weight:900;color:var(--navy);display:flex;align-items:center;gap:8px;margin-bottom:14px;text-transform:uppercase;letter-spacing:.04em}
.prog-bar{height:7px;background:var(--light);border-radius:99px;overflow:hidden;margin-bottom:14px}
.prog-fill{height:100%;width:0;background:linear-gradient(90deg,var(--accent),var(--a2));border-radius:99px;transition:width .35s ease}
.steps{display:flex;flex-direction:column;gap:7px}
.step{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:700;color:var(--muted);opacity:.4;transition:opacity .3s,color .3s}
.step.active{opacity:1;color:var(--navy)}
.step.done{opacity:.65;color:var(--green)}
.step-ico{width:24px;height:24px;border-radius:7px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px;background:var(--light);color:var(--muted);transition:background .3s,color .3s}
.step.active .step-ico{background:rgba(100,112,210,.15);color:var(--accent)}
.step.done .step-ico{background:rgba(22,163,74,.12);color:var(--green)}
.spin{animation:spinA .7s linear infinite}
@keyframes spinA{to{transform:rotate(360deg)}}

/* ── Result ── */
.result-panel{background:var(--card);border:1px solid var(--border);border-radius:24px;box-shadow:var(--sh);margin-top:20px;overflow:hidden}
.result-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.rh-left{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.rh-left i{color:var(--accent)}
.rh-title{font-size:15px;font-weight:900;color:var(--navy)}
.rh-stats{display:flex;gap:6px;flex-wrap:wrap}
.rh-stat{font-size:11px;font-weight:800;background:var(--light);color:var(--accent);border-radius:7px;padding:3px 10px}
.rh-stat.rtl{background:rgba(217,119,6,.1);color:var(--amber)}
.rh-stat.conf{background:rgba(22,163,74,.1);color:var(--green)}
.result-actions{display:flex;gap:5px;flex-wrap:wrap}
.result-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:56px 24px;color:var(--muted)}
.result-empty-icon{width:72px;height:72px;border-radius:22px;background:var(--light);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:28px;opacity:.6}
.result-empty p{font-size:14px;font-weight:700;opacity:.6;text-align:center}
#resultBox{padding:22px 26px;font-family:var(--mono);font-size:14px;line-height:1.9;color:var(--text);min-height:160px;max-height:440px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;display:none;border:none;outline:none;resize:none;width:100%;background:transparent}
#resultBox[dir="rtl"]{text-align:right;font-size:15px;font-family:"Nunito",sans-serif}

/* ── TTS ── */
.tts-panel{background:var(--card);border:1px solid var(--border);border-radius:24px;box-shadow:var(--sh);padding:22px 24px;margin-top:20px}
.tts-title{font-size:13px;font-weight:900;color:var(--navy);display:flex;align-items:center;gap:8px;margin-bottom:14px;text-transform:uppercase;letter-spacing:.04em}
.tts-title i{color:var(--accent)}
.tts-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.tts-speed-row{display:flex;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap}
.tts-speed-lbl{font-size:13px;font-weight:700;color:var(--muted);display:flex;align-items:center;gap:6px}
.speed-range{-webkit-appearance:none;appearance:none;width:130px;height:5px;background:var(--light);border-radius:99px;outline:none;cursor:pointer}
.speed-range::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;background:var(--accent);border-radius:50%;cursor:pointer;box-shadow:0 2px 8px rgba(100,112,210,.3)}
.tts-indicator{display:none;align-items:center;gap:8px;font-size:13px;font-weight:800;color:var(--accent);background:var(--light);border-radius:10px;padding:7px 14px}
.tts-indicator.show{display:flex}
.tts-waves{display:flex;align-items:center;gap:2px;height:16px}
.tts-wave{width:3px;border-radius:2px;background:var(--accent);animation:waveAnim .7s ease-in-out infinite}
.tts-wave:nth-child(1){height:6px;animation-delay:0s}.tts-wave:nth-child(2){height:13px;animation-delay:.12s}.tts-wave:nth-child(3){height:8px;animation-delay:.24s}.tts-wave:nth-child(4){height:14px;animation-delay:.36s}.tts-wave:nth-child(5){height:5px;animation-delay:.48s}
@keyframes waveAnim{0%,100%{transform:scaleY(.5);opacity:.6}50%{transform:scaleY(1.2);opacity:1}}
.lang-chip{font-size:11px;font-weight:800;padding:3px 10px;border-radius:7px;background:rgba(100,112,210,.12);color:var(--accent)}

/* ── Debug panel ── */
.debug-wrap{margin-top:20px}
.debug-toggle{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:800;color:var(--muted);cursor:pointer;padding:8px 12px;border-radius:10px;background:var(--light);border:none;font-family:inherit;transition:background .15s}
.debug-toggle:hover{background:#e4e7ff}
.debug-panel{background:#06070f;border-radius:14px;padding:16px 18px;margin-top:8px;display:none;font-family:var(--mono);font-size:12px;color:#a0e0c0;line-height:1.7}
.debug-panel.show{display:block}
.debug-row{display:flex;gap:6px}
.debug-key{color:#7090a0;min-width:160px}
.debug-val{color:#c8ffc8}
.debug-val.warn{color:#ffd080}

/* ── Tips ── */
.tips-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;margin-top:28px}
.tip-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px 18px;display:flex;gap:13px}
.tip-icon{width:36px;height:36px;border-radius:11px;flex-shrink:0;background:var(--light);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:15px}
.tip-title{font-size:13px;font-weight:800;color:var(--navy);margin-bottom:3px}
.tip-desc{font-size:11.5px;font-weight:600;color:var(--muted);line-height:1.55}
.section-title{font-size:17px;font-weight:900;color:var(--navy);margin:36px 0 16px;display:flex;align-items:center;gap:10px}
.section-title i{color:var(--accent);font-size:15px}

/* Reduce empty bottom space on OCR page */
html,
body {
    min-height: auto !important;
    padding-bottom: 0 !important;
}

.ocr-page,
.ocr-wrapper,
.page-wrapper,
.main-wrapper,
.container,
main {
    padding-bottom: 24px !important;
    margin-bottom: 0 !important;
}

footer {
    margin-top: 0 !important;
}


/* Final OCR spacing cleanup */
.wrap {
  padding-bottom: 24px !important;
}

.section-title {
  margin: 28px 0 14px !important;
}

.tips-grid {
  margin-top: 14px !important;
  margin-bottom: 8px !important;
}

.tip-card {
  margin-bottom: 0 !important;
}

.debug-wrap {
  margin-bottom: 0 !important;
}

.tts-panel,
.result-panel,
.ocr-progress {
  margin-bottom: 0 !important;
}

footer {
  margin-top: 0 !important;
}

/* ── Toast ── */
#toast{position:fixed;bottom:28px;right:28px;z-index:8000;background:var(--navy);color:#fff;font-size:13px;font-weight:800;padding:12px 20px;border-radius:13px;box-shadow:0 8px 30px rgba(0,0,0,.2);display:flex;align-items:center;gap:8px;opacity:0;transform:translateY(8px);transition:opacity .25s,transform .25s;pointer-events:none}
#toast.show{opacity:1;transform:translateY(0)}
@media(max-width:600px){.hero{padding:28px 22px 36px}.result-actions{gap:4px}.btn-sm{padding:7px 10px;font-size:11px}}
</style>
</head>
<body>
<?php include 'nav_patient.php'; ?>
<!-- Init overlay -->
<div id="ocrInit">
  <div class="init-icon"><i class="fa-solid fa-eye"></i></div>
  <div class="init-title">Smart OCR Reader</div>
  <div class="init-sub">Loading AI language engine...</div>
  <div class="init-bar"><div class="init-fill" id="initFill"></div></div>
  <div class="init-step" id="initStep">Starting Tesseract.js...</div>
</div>
<div id="toast"><i class="fa-solid fa-check" id="toastIcon"></i> <span id="toastMsg">Done</span></div>

<div class="wrap">

<!-- Hero -->
<div class="hero">
  <div class="hero-orb hero-orb-1"></div><div class="hero-orb hero-orb-2"></div>
  <div class="hero-inner">
    <div class="hero-text">
      <div class="hero-badge"><i class="fa-solid fa-eye"></i> Accessibility Feature</div>
      <h1>Smart <span>OCR Reader</span></h1>
      <p>Point your camera at any text — medicine labels, menus, signs, books — and Rafiq reads it aloud. Supports Arabic and English.</p>
      <div class="hero-tags">
        <span class="hero-tag"><i class="fa-solid fa-brain"></i> Tesseract AI</span>
        <span class="hero-tag"><i class="fa-solid fa-language"></i> Arabic + English</span>
        <span class="hero-tag"><i class="fa-solid fa-volume-high"></i> Text-to-Speech</span>
        <span class="hero-tag"><i class="fa-solid fa-lock"></i> 100% Private</span>
      </div>
    </div>
    <div class="hero-widget">
      <div class="hw-icon"><i class="fa-solid fa-file-lines"></i></div>
      <div class="hw-num" id="heroWords">0</div>
      <div class="hw-label">Words Read</div>
    </div>
  </div>
</div>

<!-- Settings bar -->
<div class="settings-bar">
  <div class="setting-group">
    <span class="setting-label"><i class="fa-solid fa-language"></i> OCR Language:</span>
    <select class="lang-select" id="ocrLang">
      <option value="ara+eng" selected>Auto (Arabic + English)</option>
      <option value="ara">Arabic only</option>
      <option value="eng">English only</option>
    </select>
  </div>
  <div class="settings-divider"></div>
  <div class="setting-group">
    <span class="setting-label"><i class="fa-solid fa-left-right"></i> Mirror fix:</span>
    <div class="toggle-wrap">
      <button class="toggle-cb on" id="mirrorToggle" aria-label="Mirror correction toggle"></button>
      <span class="toggle-cb-label" id="mirrorLabel">ON</span>
    </div>
  </div>
  <div class="settings-divider"></div>
  <div class="setting-group">
    <span class="setting-label"><i class="fa-solid fa-wand-magic-sparkles"></i> Enhance:</span>
    <div class="toggle-wrap">
      <button class="toggle-cb on" id="enhanceToggle" aria-label="Image enhancement toggle"></button>
      <span class="toggle-cb-label" id="enhanceLabel">ON</span>
    </div>
  </div>
</div>

<!-- Input grid -->
<div class="input-grid">

  <!-- Camera -->
  <div class="card">
    <div class="card-head">
      <div class="card-head-icon"><i class="fa-solid fa-camera"></i></div>
      <div><div class="card-head-title">Camera Capture</div><div class="card-head-sub">Point at text and capture</div></div>
    </div>
    <div class="card-body">
      <div class="cam-viewport" id="camViewport">
        <video id="camVideo" autoplay muted playsinline></video>
        <div class="cam-corner tl" id="ccTL"></div><div class="cam-corner tr" id="ccTR"></div>
        <div class="cam-corner bl" id="ccBL"></div><div class="cam-corner br" id="ccBR"></div>
        <div class="scan-line" id="scanLine"></div>
        <div class="cam-flash" id="camFlash"></div>
        <div class="cam-off" id="camOff"><i class="fa-solid fa-camera-slash"></i><span>Camera is off</span></div>
      </div>
      <canvas id="camCanvas" style="display:none"></canvas>
      <div class="cam-btns">
        <button class="btn btn-green btn-sm" id="startCamBtn"><i class="fa-solid fa-video"></i> Start Camera</button>
        <button class="btn btn-danger btn-sm" id="stopCamBtn" style="display:none"><i class="fa-solid fa-video-slash"></i> Stop</button>
        <button class="btn btn-primary btn-sm" id="captureScanBtn" style="display:none" disabled><i class="fa-solid fa-scan"></i> Capture &amp; Scan</button>
      </div>
    </div>
  </div>

  <!-- Upload -->
  <div class="card">
    <div class="card-head">
      <div class="card-head-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
      <div><div class="card-head-title">Upload Image</div><div class="card-head-sub">JPG, PNG, WEBP, BMP</div></div>
    </div>
    <div class="card-body">
      <label class="upload-zone" id="uploadZone">
        <input type="file" id="fileInput" accept="image/*">
        <div class="upload-zone-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <div class="upload-zone-title">Drop image here or click to browse</div>
        <div class="upload-zone-sub">Medicine labels, menus, signs, documents</div>
        <div class="upload-zone-paste"><i class="fa-solid fa-clipboard"></i> Ctrl+V to paste from clipboard</div>
        <img class="upload-preview" id="uploadPreview" src="" alt="Preview">
        <div class="upload-name" id="uploadName"></div>
      </label>
      <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-primary btn-sm" id="scanUploadBtn" disabled><i class="fa-solid fa-magnifying-glass"></i> Scan Image</button>
        <button class="btn btn-secondary btn-sm" id="clearUploadBtn" style="display:none"><i class="fa-solid fa-xmark"></i> Clear</button>
      </div>
    </div>
  </div>
</div>

<!-- Progress -->
<div class="ocr-progress" id="ocrProgress">
  <div class="prog-title"><i class="fa-solid fa-microchip"></i> Processing</div>
  <div class="prog-bar"><div class="prog-fill" id="progFill"></div></div>
  <div class="steps">
    <div class="step" id="s1"><div class="step-ico" id="s1i"><i class="fa-solid fa-image"></i></div> Correcting camera mirror...</div>
    <div class="step" id="s2"><div class="step-ico" id="s2i"><i class="fa-solid fa-sliders"></i></div> Enhancing image...</div>
    <div class="step" id="s3"><div class="step-ico" id="s3i"><i class="fa-solid fa-language"></i></div> Scanning Arabic + English...</div>
    <div class="step" id="s4"><div class="step-ico" id="s4i"><i class="fa-solid fa-align-left"></i></div> Extracting text...</div>
    <div class="step" id="s5"><div class="step-ico" id="s5i"><i class="fa-solid fa-check"></i></div> Done!</div>
  </div>
</div>

<!-- Result -->
<div class="result-panel">
  <div class="result-head">
    <div class="rh-left">
      <i class="fa-solid fa-file-lines"></i>
      <span class="rh-title">Extracted Text</span>
      <div class="rh-stats">
        <span class="rh-stat" id="wordStat">0 words</span>
        <span class="rh-stat" id="charStat">0 chars</span>
        <span class="rh-stat conf" id="confStat" style="display:none"></span>
        <span class="rh-stat rtl" id="langStat" style="display:none"></span>
      </div>
    </div>
    <div class="result-actions">
      <button class="btn btn-secondary btn-sm" id="copyBtn" disabled><i class="fa-regular fa-copy"></i> Copy</button>
      <button class="btn btn-secondary btn-sm" id="downloadBtn" disabled><i class="fa-solid fa-download"></i> Download</button>
      <button class="btn btn-amber btn-sm" id="reverseBtn" disabled title="Reverse each line (use if text is still backwards)"><i class="fa-solid fa-rotate-left"></i> Reverse fix</button>
      <button class="btn btn-danger btn-sm" id="clearBtn" disabled><i class="fa-solid fa-trash-can"></i> Clear</button>
    </div>
  </div>
  <div class="result-empty" id="resultEmpty">
    <div class="result-empty-icon"><i class="fa-solid fa-file-magnifying-glass"></i></div>
    <p>Upload an image or capture from camera<br>to extract text here</p>
  </div>
  <textarea id="resultBox" spellcheck="false" aria-label="Extracted OCR text" dir="ltr"></textarea>
</div>

<!-- TTS -->
<div class="tts-panel">
  <div class="tts-title"><i class="fa-solid fa-volume-high"></i> Text-to-Speech</div>
  <div class="tts-row">
    <button class="btn btn-primary" id="readBtn" disabled><i class="fa-solid fa-play" id="readIcon"></i> Read Aloud</button>
    <button class="btn btn-secondary" id="stopReadBtn" disabled><i class="fa-solid fa-stop"></i> Stop</button>
    <div class="tts-indicator" id="ttsIndicator">
      <div class="tts-waves"><div class="tts-wave"></div><div class="tts-wave"></div><div class="tts-wave"></div><div class="tts-wave"></div><div class="tts-wave"></div></div>
      Reading <span class="lang-chip" id="ttsLangChip"></span>
    </div>
  </div>
  <div class="tts-speed-row">
    <span class="tts-speed-lbl"><i class="fa-solid fa-gauge-simple"></i> Speed:</span>
    <input type="range" class="speed-range" id="speedRange" min="0.5" max="2" step="0.1" value="0.9">
    <span class="tts-speed-lbl" id="speedLabel">0.9×</span>
  </div>
</div>

<!-- Debug -->
<div class="debug-wrap">
  <button class="debug-toggle" id="debugToggle"><i class="fa-solid fa-bug"></i> Debug Info</button>
  <div class="debug-panel" id="debugPanel">
    <div class="debug-row"><span class="debug-key">OCR Language:</span><span class="debug-val" id="dbLang">ara+eng</span></div>
    <div class="debug-row"><span class="debug-key">Mirror Correction:</span><span class="debug-val" id="dbMirror">ON</span></div>
    <div class="debug-row"><span class="debug-key">Enhancement:</span><span class="debug-val" id="dbEnhance">ON</span></div>
    <div class="debug-row"><span class="debug-key">Detected Direction:</span><span class="debug-val" id="dbDir">—</span></div>
    <div class="debug-row"><span class="debug-key">OCR Confidence:</span><span class="debug-val" id="dbConf">—</span></div>
    <div class="debug-row"><span class="debug-key">Image size (preprocessed):</span><span class="debug-val" id="dbSize">—</span></div>
    <div class="debug-row"><span class="debug-key">Last source:</span><span class="debug-val" id="dbSource">—</span></div>
  </div>
</div>

<!-- Tips -->
<div class="section-title" style="margin-top:40px"><i class="fa-solid fa-lightbulb"></i> Tips for Best Results</div>
<div class="tips-grid">
  <div class="tip-card"><div class="tip-icon"><i class="fa-solid fa-sun"></i></div><div><div class="tip-title">Good Lighting</div><div class="tip-desc">Ensure text is well-lit without glare or shadows crossing the text.</div></div></div>
  <div class="tip-card"><div class="tip-icon"><i class="fa-solid fa-left-right"></i></div><div><div class="tip-title">Mirror Fix Toggle</div><div class="tip-desc">If text comes out backwards, toggle "Mirror fix" in the settings bar above.</div></div></div>
  <div class="tip-card"><div class="tip-icon"><i class="fa-solid fa-rotate-left"></i></div><div><div class="tip-title">Reverse Fix Button</div><div class="tip-desc">If text is still mirrored after OCR, click "Reverse fix" to flip each line.</div></div></div>
  <div class="tip-card"><div class="tip-icon"><i class="fa-solid fa-language"></i></div><div><div class="tip-title">Arabic + English</div><div class="tip-desc">Mixed Arabic and English text is supported. Language direction auto-detects.</div></div></div>
  <div class="tip-card"><div class="tip-icon"><i class="fa-solid fa-clipboard"></i></div><div><div class="tip-title">Paste from Clipboard</div><div class="tip-desc">Screenshot any text, then press Ctrl+V anywhere on this page to scan it.</div></div></div>
  <div class="tip-card"><div class="tip-icon"><i class="fa-solid fa-crop-simple"></i></div><div><div class="tip-title">Crop Tightly</div><div class="tip-desc">Crop images to contain only the text area for significantly higher accuracy.</div></div></div>
</div>

</div><!-- /wrap -->

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.4/dist/tesseract.min.js"></script>
<script>
'use strict';

/* ─── STATE ─── */
let worker       = null;
let workerReady  = false;
let loadedLang   = '';
let cameraStream = null;
let pendingBlob  = null;
let sessionWords = 0;
let isSpeaking   = false;
let mirrorOn     = true;
let enhanceOn    = true;
let lastConf     = 0;

/* ─── DOM ─── */
const $ = id => document.getElementById(id);
const D = {
  initFill:$('initFill'), initStep:$('initStep'), ocrInit:$('ocrInit'),
  ocrLang:$('ocrLang'),
  mirrorToggle:$('mirrorToggle'), mirrorLabel:$('mirrorLabel'),
  enhanceToggle:$('enhanceToggle'), enhanceLabel:$('enhanceLabel'),
  camVideo:$('camVideo'), camCanvas:$('camCanvas'), camOff:$('camOff'),
  scanLine:$('scanLine'), camFlash:$('camFlash'),
  startCamBtn:$('startCamBtn'), stopCamBtn:$('stopCamBtn'), captureScanBtn:$('captureScanBtn'),
  ccTL:$('ccTL'),ccTR:$('ccTR'),ccBL:$('ccBL'),ccBR:$('ccBR'),
  fileInput:$('fileInput'), uploadZone:$('uploadZone'),
  uploadPreview:$('uploadPreview'), uploadName:$('uploadName'),
  scanUploadBtn:$('scanUploadBtn'), clearUploadBtn:$('clearUploadBtn'),
  ocrProgress:$('ocrProgress'), progFill:$('progFill'),
  s1:$('s1'),s2:$('s2'),s3:$('s3'),s4:$('s4'),s5:$('s5'),
  s1i:$('s1i'),s2i:$('s2i'),s3i:$('s3i'),s4i:$('s4i'),s5i:$('s5i'),
  resultEmpty:$('resultEmpty'), resultBox:$('resultBox'),
  wordStat:$('wordStat'), charStat:$('charStat'), confStat:$('confStat'), langStat:$('langStat'),
  copyBtn:$('copyBtn'), downloadBtn:$('downloadBtn'), reverseBtn:$('reverseBtn'), clearBtn:$('clearBtn'),
  heroWords:$('heroWords'),
  readBtn:$('readBtn'), readIcon:$('readIcon'), stopReadBtn:$('stopReadBtn'),
  ttsIndicator:$('ttsIndicator'), ttsLangChip:$('ttsLangChip'),
  speedRange:$('speedRange'), speedLabel:$('speedLabel'),
  toast:$('toast'), toastIcon:$('toastIcon'), toastMsg:$('toastMsg'),
  debugToggle:$('debugToggle'), debugPanel:$('debugPanel'),
  dbLang:$('dbLang'),dbMirror:$('dbMirror'),dbEnhance:$('dbEnhance'),
  dbDir:$('dbDir'),dbConf:$('dbConf'),dbSize:$('dbSize'),dbSource:$('dbSource'),
};

/* ─── TESSERACT INIT ─── */
async function initWorker(lang) {
  lang = lang || 'ara+eng';
  try {
    let pct = 0;
    const tick = setInterval(() => { pct = Math.min(pct + Math.random()*12, 78); D.initFill.style.width = pct+'%'; }, 250);
    const msgs = ['Loading Tesseract core...','Downloading Arabic language data...','Downloading English language data...','Initialising OCR engine...'];
    let mi = 0;
    const mtick = setInterval(() => { if(mi<msgs.length) D.initStep.textContent = msgs[mi++]; }, 950);

    // Parse lang for Tesseract (ara+eng → ['ara','eng'])
    const langs = lang.includes('+') ? lang.split('+') : [lang];

    worker = await Tesseract.createWorker(langs, 1, {
      logger: m => {
        if(m.status === 'loading tesseract core')       D.initFill.style.width = '20%';
        if(m.status === 'loading language traineddata') D.initFill.style.width = '55%';
        if(m.status === 'initializing tesseract')       D.initFill.style.width = '82%';
      }
    });

    clearInterval(tick); clearInterval(mtick);
    D.initFill.style.width = '100%';
    D.initStep.textContent = 'Ready!';
    loadedLang = lang;
    await delay(400);
    workerReady = true;
    D.ocrInit.classList.add('hidden');
    dbUpdate();
  } catch(e) {
    console.error('Tesseract init failed:', e);
    D.initStep.textContent = 'Failed to load — please refresh the page.';
  }
}

/* ─── LANGUAGE SWITCH ─── */
D.ocrLang.addEventListener('change', async () => {
  const newLang = D.ocrLang.value;
  if(newLang === loadedLang || !workerReady) return;
  workerReady = false;
  if(worker) { try { await worker.terminate(); } catch(e){} worker = null; }
  D.ocrInit.classList.remove('hidden');
  D.initFill.style.width = '0';
  await initWorker(newLang);
});

/* ─── TOGGLE CONTROLS ─── */
D.mirrorToggle.addEventListener('click', () => {
  mirrorOn = !mirrorOn;
  D.mirrorToggle.classList.toggle('on', mirrorOn);
  D.mirrorLabel.textContent = mirrorOn ? 'ON' : 'OFF';
  dbUpdate();
});
D.enhanceToggle.addEventListener('click', () => {
  enhanceOn = !enhanceOn;
  D.enhanceToggle.classList.toggle('on', enhanceOn);
  D.enhanceLabel.textContent = enhanceOn ? 'ON' : 'OFF';
  dbUpdate();
});

/* ─── CAMERA ─── */
D.startCamBtn.addEventListener('click', startCamera);
D.stopCamBtn.addEventListener('click',  stopCamera);
D.captureScanBtn.addEventListener('click', captureAndScan);

async function startCamera() {
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal:'environment' }, width:{ideal:1920}, height:{ideal:1080} }
    }).catch(() => navigator.mediaDevices.getUserMedia({ video:true }));

    D.camVideo.srcObject = cameraStream;
    // Show video un-mirrored (raw stream) — mirror correction handled at capture time
    D.camVideo.style.transform = 'none';
    D.camVideo.style.display = 'block';
    D.camOff.style.display = 'none';
    D.startCamBtn.style.display = 'none';
    D.stopCamBtn.style.display  = 'inline-flex';
    D.captureScanBtn.style.display = 'inline-flex';
    D.captureScanBtn.disabled = false;
    [D.ccTL,D.ccTR,D.ccBL,D.ccBR].forEach(c => c.classList.add('live'));
  } catch(e) {
    showToast('Camera access denied or unavailable', true);
  }
}

function stopCamera() {
  if(cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
  D.camVideo.srcObject = null;
  D.camVideo.style.display = 'none';
  D.camOff.style.display = 'flex';
  D.startCamBtn.style.display = 'inline-flex';
  D.stopCamBtn.style.display  = 'none';
  D.captureScanBtn.style.display = 'none';
  [D.ccTL,D.ccTR,D.ccBL,D.ccBR].forEach(c => c.classList.remove('live'));
}

async function captureAndScan() {
  if(!workerReady) { showToast('OCR engine loading, please wait...', true); return; }
  // Flash
  D.camFlash.classList.add('pop');
  setTimeout(() => D.camFlash.classList.remove('pop'), 200);

  const v  = D.camVideo;
  const w  = v.videoWidth  || 640;
  const h  = v.videoHeight || 480;
  const cv = D.camCanvas;
  cv.width = w; cv.height = h;
  const ctx = cv.getContext('2d');

  if(mirrorOn) {
    // Mirror correction ON: the captured frame needs horizontal flip
    // (handles front-facing camera which is naturally mirrored)
    ctx.translate(w, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(v, 0, 0, w, h);
    ctx.setTransform(1,0,0,1,0,0);
  } else {
    // Mirror correction OFF: use raw stream as-is (rear camera)
    ctx.drawImage(v, 0, 0, w, h);
  }

  cv.toBlob(async blob => {
    D.dbSource && (D.dbSource.textContent = 'camera capture');
    await runOCR(blob);
  }, 'image/jpeg', 0.95);
}

/* ─── UPLOAD / PASTE ─── */
D.fileInput.addEventListener('change', e => { if(e.target.files[0]) handleFile(e.target.files[0]); });

D.uploadZone.addEventListener('dragover',  e => { e.preventDefault(); D.uploadZone.classList.add('over'); });
D.uploadZone.addEventListener('dragleave', ()  => D.uploadZone.classList.remove('over'));
D.uploadZone.addEventListener('drop', e => {
  e.preventDefault(); D.uploadZone.classList.remove('over');
  const f = e.dataTransfer.files[0];
  if(f && f.type.startsWith('image/')) handleFile(f);
});

document.addEventListener('paste', e => {
  const items = e.clipboardData?.items;
  if(!items) return;
  for(const item of items) {
    if(item.type.startsWith('image/')) {
      const f = item.getAsFile();
      if(f) {
        D.uploadZone.classList.add('paste-highlight');
        setTimeout(() => D.uploadZone.classList.remove('paste-highlight'), 700);
        handleFile(f);
        showToast('Image pasted from clipboard!');
      }
      break;
    }
  }
});

function handleFile(file) {
  if(!file.type.startsWith('image/')) { showToast('Please select an image file', true); return; }
  const url = URL.createObjectURL(file);
  D.uploadPreview.src = url;
  D.uploadPreview.classList.add('show');
  D.uploadName.textContent = file.name.length > 26 ? file.name.slice(0,24)+'…' : file.name;
  D.uploadName.classList.add('show');
  D.scanUploadBtn.disabled = false;
  D.clearUploadBtn.style.display = 'inline-flex';
  pendingBlob = file;
  D.dbSource.textContent = 'upload: ' + file.name;
}

D.scanUploadBtn.addEventListener('click', () => {
  if(pendingBlob && workerReady) runOCR(pendingBlob);
  else if(!workerReady) showToast('OCR engine loading...', true);
});

D.clearUploadBtn.addEventListener('click', () => {
  D.uploadPreview.src = ''; D.uploadPreview.classList.remove('show');
  D.uploadName.classList.remove('show');
  D.scanUploadBtn.disabled = true;
  D.clearUploadBtn.style.display = 'none';
  D.fileInput.value = '';
  pendingBlob = null;
});

/* ─── IMAGE PREPROCESSING ─── */
async function preprocessImage(source) {
  // Load into image element if needed
  let img;
  if(source instanceof HTMLCanvasElement) {
    img = source;
  } else {
    img = await loadImgFromSource(source);
  }

  const sw = img.naturalWidth  || img.width  || 640;
  const sh = img.naturalHeight || img.height || 480;

  // Upscale: Tesseract performs much better at higher resolution
  const TARGET = 1800;
  const scale  = sw < TARGET ? Math.min(4, TARGET / Math.max(sw, sh)) : 1;
  const ow = Math.round(sw * scale);
  const oh = Math.round(sh * scale);

  const cv  = document.createElement('canvas');
  cv.width  = ow; cv.height = oh;
  const ctx = cv.getContext('2d', { willReadFrequently: true });
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(img, 0, 0, ow, oh);

  // --- Pixel operations ---
  const id = ctx.getImageData(0, 0, ow, oh);
  const d  = id.data;

  // 1. Grayscale
  for(let i = 0; i < d.length; i += 4) {
    const g = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
    d[i] = d[i+1] = d[i+2] = g;
  }

  // 2. Auto-levels (histogram stretch → full 0-255 range)
  let mn = 255, mx = 0;
  for(let i = 0; i < d.length; i += 4) { if(d[i]<mn) mn=d[i]; if(d[i]>mx) mx=d[i]; }
  const rng = Math.max(1, mx - mn);
  for(let i = 0; i < d.length; i += 4) {
    const v = Math.round(((d[i]-mn)/rng)*255);
    d[i] = d[i+1] = d[i+2] = v < 0 ? 0 : v > 255 ? 255 : v;
  }

  // 3. Mild brightness boost (helps dim captures)
  for(let i = 0; i < d.length; i += 4) {
    const v = d[i] + 15;
    d[i] = d[i+1] = d[i+2] = v > 255 ? 255 : v;
  }

  ctx.putImageData(id, 0, 0);

  // 4. Sharpening convolution (unsharp 3×3)
  const sharp = sharpen(cv);
  D.dbSize.textContent = sharp.width + '×' + sharp.height + ' (scale ×' + scale.toFixed(2) + ')';
  return sharp;
}

function sharpen(src) {
  const out = document.createElement('canvas');
  out.width = src.width; out.height = src.height;
  const ctx = out.getContext('2d', { willReadFrequently: true });
  ctx.drawImage(src, 0, 0);
  const sd = ctx.getImageData(0, 0, out.width, out.height);
  const dd = ctx.createImageData(out.width, out.height);
  const K  = [0,-1,0,-1,5,-1,0,-1,0]; // sharpen kernel
  const W = out.width, H = out.height, s = sd.data, d = dd.data;
  for(let y = 0; y < H; y++) {
    for(let x = 0; x < W; x++) {
      let v = 0;
      for(let ky=-1; ky<=1; ky++) {
        for(let kx=-1; kx<=1; kx++) {
          const nx=x+kx, ny=y+ky;
          if(nx>=0&&nx<W&&ny>=0&&ny<H) v += s[(ny*W+nx)*4] * K[(ky+1)*3+(kx+1)];
        }
      }
      const idx=(y*W+x)*4;
      const cv2 = v<0?0:v>255?255:v;
      d[idx]=d[idx+1]=d[idx+2]=cv2; d[idx+3]=255;
    }
  }
  ctx.putImageData(dd, 0, 0);
  return out;
}

function loadImgFromSource(src) {
  return new Promise((res, rej) => {
    const img = new Image();
    img.onload = () => res(img);
    img.onerror = rej;
    img.src = (src instanceof Blob || src instanceof File)
      ? URL.createObjectURL(src) : src;
  });
}

/* ─── MAIN OCR PIPELINE ─── */
async function runOCR(source) {
  if(!workerReady) { showToast('OCR engine not ready yet', true); return; }

  D.scanLine.classList.add('active');
  showProgress(true);
  activateStep(1);

  try {
    await delay(300);
    activateStep(2);

    // Preprocess
    let imgSource = source;
    if(enhanceOn) {
      imgSource = await preprocessImage(source);
    } else {
      // Just draw raw to canvas for Tesseract
      const img = await loadImgFromSource(source);
      const cv  = document.createElement('canvas');
      cv.width  = img.naturalWidth  || img.width;
      cv.height = img.naturalHeight || img.height;
      cv.getContext('2d').drawImage(img, 0, 0);
      imgSource = cv;
      D.dbSize.textContent = cv.width + '×' + cv.height + ' (no enhance)';
    }

    await delay(250);
    activateStep(3);

    // OCR
    setBar(50);
    let pInterval = setInterval(() => {
      const cur = parseFloat(D.progFill.style.width)||50;
      if(cur < 88) setBar(cur + Math.random()*7);
    }, 500);

    const result = await worker.recognize(imgSource);
    clearInterval(pInterval);

    activateStep(4);
    setBar(96);
    await delay(280);
    activateStep(5);
    setBar(100);

    // Extract results
    const text = (result.data.text || '').trim();
    lastConf    = Math.round(result.data.confidence || 0);

    await delay(380);
    displayResult(text);

  } catch(e) {
    console.error('OCR error:', e);
    showToast('OCR failed — please try again', true);
  } finally {
    D.scanLine.classList.remove('active');
    setTimeout(() => showProgress(false), 700);
  }
}

/* ─── STEP HELPERS ─── */
const STEP_S  = [null,D.s1,D.s2,D.s3,D.s4,D.s5];
const STEP_I  = [null,D.s1i,D.s2i,D.s3i,D.s4i,D.s5i];
const SPIN_IC = '<i class="fa-solid fa-circle-notch spin"></i>';
const DONE_IC = '<i class="fa-solid fa-check"></i>';

function activateStep(n) {
  for(let i=1;i<=5;i++) {
    const s=STEP_S[i], ic=STEP_I[i];
    s.classList.remove('active','done');
    if(i<n)     { s.classList.add('done'); ic.innerHTML=DONE_IC; }
    else if(i===n){ s.classList.add('active'); ic.innerHTML=SPIN_IC; }
  }
  setBar(n===1?10:n===2?28:n===3?50:n===4?82:100);
}

function setBar(pct) { D.progFill.style.width = pct+'%'; }
function showProgress(v) { D.ocrProgress.classList.toggle('show', v); }

/* ─── DISPLAY RESULT ─── */
function displayResult(text) {
  D.resultEmpty.style.display = 'none';
  D.resultBox.style.display   = 'block';
  D.resultBox.value = text;

  const words = text ? text.trim().split(/\s+/).filter(Boolean).length : 0;
  const chars = text.length;
  D.wordStat.textContent = words + ' words';
  D.charStat.textContent = chars + ' chars';

  // Confidence
  if(lastConf > 0) {
    D.confStat.textContent = lastConf + '% confidence';
    D.confStat.style.display = 'inline-flex';
  } else {
    D.confStat.style.display = 'none';
  }

  // Direction
  const arabic = hasArabic(text);
  if(arabic) {
    D.resultBox.dir = 'rtl';
    D.langStat.textContent = 'Arabic / RTL';
    D.langStat.style.display = 'inline-flex';
    D.dbDir.textContent = 'rtl (Arabic detected)';
  } else {
    D.resultBox.dir = 'ltr';
    D.langStat.style.display = 'none';
    D.dbDir.textContent = 'ltr (no Arabic detected)';
  }
  D.dbConf.textContent = lastConf + '%';

  sessionWords += words;
  D.heroWords.textContent = sessionWords;

  const has = text.length > 0;
  D.copyBtn.disabled = D.downloadBtn.disabled = D.clearBtn.disabled = D.reverseBtn.disabled = D.readBtn.disabled = !has;
  dbUpdate();
}

/* ─── REVERSE FIX ─── */
D.reverseBtn.addEventListener('click', () => {
  const lines   = D.resultBox.value.split('\n');
  const reversed = lines.map(l => l.split('').reverse().join(''));
  D.resultBox.value = reversed.join('\n');
  showToast('Text reversed line-by-line');
});

/* ─── COPY / DOWNLOAD / CLEAR ─── */
D.copyBtn.addEventListener('click', () => {
  navigator.clipboard.writeText(D.resultBox.value)
    .then(() => showToast('Copied to clipboard!'))
    .catch(() => { D.resultBox.select(); document.execCommand('copy'); showToast('Copied!'); });
});

D.downloadBtn.addEventListener('click', () => {
  const blob = new Blob([D.resultBox.value], { type:'text/plain;charset=utf-8' });
  const url  = URL.createObjectURL(blob);
  const a    = Object.assign(document.createElement('a'), { href:url, download:'rafiq-ocr-'+Date.now()+'.txt' });
  a.click(); URL.revokeObjectURL(url);
  showToast('Downloaded!');
});

D.clearBtn.addEventListener('click', () => {
  D.resultBox.value = ''; D.resultBox.style.display = 'none';
  D.resultEmpty.style.display = 'flex';
  D.wordStat.textContent = '0 words'; D.charStat.textContent = '0 chars';
  D.confStat.style.display = D.langStat.style.display = 'none';
  [D.copyBtn,D.downloadBtn,D.reverseBtn,D.clearBtn,D.readBtn].forEach(b => b.disabled = true);
  stopSpeech();
  D.dbDir.textContent = '—'; D.dbConf.textContent = '—';
});

/* ─── TTS ─── */
D.speedRange.addEventListener('input', () => { D.speedLabel.textContent = parseFloat(D.speedRange.value).toFixed(1)+'×'; });

D.readBtn.addEventListener('click', () => {
  const text = D.resultBox.value.trim();
  if(!text) return;
  stopSpeech();
  const arabic = hasArabic(text);
  const utter  = new SpeechSynthesisUtterance(text);
  utter.rate   = parseFloat(D.speedRange.value);
  utter.lang   = arabic ? 'ar-SA' : 'en-US';
  const voices = window.speechSynthesis.getVoices();
  const voice  = voices.find(v => arabic ? v.lang.startsWith('ar') : v.lang.startsWith('en-US'))
              || voices.find(v => arabic ? v.lang.startsWith('ar') : v.lang.startsWith('en'));
  if(voice) utter.voice = voice;
  utter.onstart = () => {
    isSpeaking = true;
    D.ttsIndicator.classList.add('show');
    D.ttsLangChip.textContent = arabic ? 'Arabic' : 'English';
    D.stopReadBtn.disabled = false;
    D.readIcon.className = 'fa-solid fa-volume-high';
  };
  utter.onend = utter.onerror = () => {
    isSpeaking = false;
    D.ttsIndicator.classList.remove('show');
    D.stopReadBtn.disabled = true;
    D.readIcon.className = 'fa-solid fa-play';
  };
  window.speechSynthesis.speak(utter);
});

D.stopReadBtn.addEventListener('click', stopSpeech);
function stopSpeech() {
  window.speechSynthesis.cancel(); isSpeaking = false;
  D.ttsIndicator.classList.remove('show'); D.stopReadBtn.disabled = true;
  D.readIcon.className = 'fa-solid fa-play';
}

/* ─── ARABIC DETECTION ─── */
function hasArabic(t) {
  return /[؀-ۿݐ-ݿࢠ-ࣿﭐ-﷿ﹰ-﻿]/.test(t);
}

/* ─── DEBUG ─── */
D.debugToggle.addEventListener('click', () => D.debugPanel.classList.toggle('show'));
function dbUpdate() {
  D.dbLang.textContent    = D.ocrLang.value;
  D.dbMirror.textContent  = mirrorOn  ? 'ON' : 'OFF';
  D.dbEnhance.textContent = enhanceOn ? 'ON' : 'OFF';
}

/* ─── TOAST ─── */
let toastT = null;
function showToast(msg, err=false) {
  D.toastMsg.textContent = msg;
  D.toast.style.background = err ? '#dc2626' : 'var(--navy)';
  D.toastIcon.className = err ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-check';
  D.toast.classList.add('show');
  clearTimeout(toastT);
  toastT = setTimeout(() => D.toast.classList.remove('show'), 2500);
}

function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

/* ─── CLEANUP ─── */
window.addEventListener('beforeunload', () => {
  stopSpeech(); stopCamera();
  if(worker) worker.terminate();
});

/* ─── BOOT ─── */
initWorker('ara+eng');
dbUpdate();
if(window.speechSynthesis) {
  window.speechSynthesis.getVoices();
  window.speechSynthesis.addEventListener('voiceschanged', () => window.speechSynthesis.getVoices());
}
</script>
<?php include 'footer.php'; ?>
</body>
</html>
