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
<title>Rafiq | AI Sign Language Assistant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* ── Reset & Tokens ── */
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

/* ── Loading overlay ── */
#mpLoadOverlay{position:fixed;inset:0;z-index:9000;background:rgba(20,22,50,.88);backdrop-filter:blur(8px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;color:#fff;transition:opacity .4s}
#mpLoadOverlay.hidden{opacity:0;pointer-events:none}
.mp-spinner{width:52px;height:52px;border:3px solid rgba(255,255,255,.15);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.mp-load-title{font-size:18px;font-weight:900}
.mp-load-sub{font-size:13px;opacity:.65;font-weight:600}

/* ── Layout ── */
.wrap{max-width:1220px;margin:0 auto;padding:0 24px 72px}

/* ── Hero ── */
.hero{background:linear-gradient(135deg,var(--navy) 0%,#2d1b69 50%,var(--accent) 100%);margin:0 -24px;padding:38px 52px 46px;color:#fff;position:relative;overflow:hidden;border-radius:0 0 40px 40px}
.hero-orb{position:absolute;border-radius:50%;pointer-events:none}
.hero-orb-1{width:380px;height:380px;top:-150px;right:-100px;background:rgba(255,255,255,.04)}
.hero-orb-2{width:200px;height:200px;bottom:-80px;left:3%;background:rgba(255,255,255,.03)}
.hero-orb-3{width:120px;height:120px;top:20%;right:28%;background:rgba(255,255,255,.025)}
.hero-inner{position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:28px;flex-wrap:wrap}
.hero-text{flex:1;min-width:240px}
.hero-badge{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);font-size:10.5px;font-weight:900;letter-spacing:.07em;text-transform:uppercase;margin-bottom:14px}
.hero h1{font-size:clamp(24px,3.8vw,42px);font-weight:900;letter-spacing:-.8px;line-height:1.06;margin-bottom:12px}
.hero h1 span{background:linear-gradient(90deg,#c4caff,#fff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hero p{font-size:14px;font-weight:600;color:rgba(255,255,255,.78);line-height:1.75;max-width:520px}
.hero-tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.hero-tag{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:999px;font-size:11px;font-weight:800;color:rgba(255,255,255,.85)}
.hero-icon{font-size:80px;filter:drop-shadow(0 10px 28px rgba(0,0,0,.25));flex-shrink:0;animation:float 3.5s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}

/* ── Main grid ── */
.main-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:28px}

/* ── Card ── */
.card{background:var(--card);border:1px solid var(--border);border-radius:24px;box-shadow:var(--sh);overflow:hidden}
.card-head{padding:18px 22px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.card-head-icon{width:40px;height:40px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:17px;background:var(--light);flex-shrink:0;color:var(--accent)}
.card-head-title{font-size:15px;font-weight:900;color:var(--navy)}
.card-head-sub{font-size:11.5px;font-weight:700;color:var(--muted);margin-top:1px}
.card-body{padding:20px 22px}

/* ── Camera viewport ── */
.camera-viewport{position:relative;width:100%;aspect-ratio:4/3;background:#06070f;border-radius:18px;overflow:hidden;margin-bottom:14px;box-shadow:inset 0 0 0 1.5px rgba(100,112,210,.2)}
.camera-viewport video,.camera-viewport canvas{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;border-radius:18px}
.camera-viewport video{transform:scaleX(-1)}
.camera-viewport canvas{z-index:2;transform:scaleX(-1)}

/* Hand guide ring */
.hand-guide{position:absolute;z-index:3;top:50%;left:50%;transform:translate(-50%,-56%);width:46%;aspect-ratio:1;border:2px dashed rgba(100,112,210,.3);border-radius:50%;pointer-events:none;animation:guidePulse 3s ease-in-out infinite;transition:border-color .4s,opacity .4s}
.hand-guide.active{border-color:rgba(52,211,153,.55);animation:none}
.hand-guide.hidden{opacity:0}
.hand-guide-label{position:absolute;bottom:-26px;left:50%;transform:translateX(-50%);font-size:9.5px;font-weight:800;color:rgba(255,255,255,.45);white-space:nowrap;letter-spacing:.05em}
@keyframes guidePulse{0%,100%{border-color:rgba(100,112,210,.2);transform:translate(-50%,-56%) scale(1)}50%{border-color:rgba(100,112,210,.45);transform:translate(-50%,-56%) scale(1.04)}}

/* Camera corners */
.cam-corner{position:absolute;z-index:3;width:18px;height:18px;border-color:rgba(100,112,210,.38);border-style:solid;pointer-events:none;transition:border-color .4s}
.cam-corner.active{border-color:rgba(52,211,153,.65)}
.cam-corner.tl{top:10px;left:10px;border-width:2px 0 0 2px;border-radius:4px 0 0 0}
.cam-corner.tr{top:10px;right:10px;border-width:2px 2px 0 0;border-radius:0 4px 0 0}
.cam-corner.bl{bottom:10px;left:10px;border-width:0 0 2px 2px;border-radius:0 0 0 4px}
.cam-corner.br{bottom:10px;right:10px;border-width:0 2px 2px 0;border-radius:0 0 4px 0}

/* Camera off state */
.camera-off-state{position:absolute;inset:0;z-index:4;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:rgba(255,255,255,.4);font-weight:800;background:#06070f}
.camera-off-state i{font-size:48px;opacity:.25}
.camera-off-state .off-title{font-size:14px}
.camera-off-state .off-hint{font-size:11.5px;opacity:.55}

/* Lighting warn */
#lightingWarn{position:absolute;bottom:10px;left:10px;right:10px;z-index:5;background:rgba(217,119,6,.88);color:#fff;font-size:10.5px;font-weight:800;padding:7px 12px;border-radius:9px;display:none;align-items:center;gap:7px;backdrop-filter:blur(4px)}
#lightingWarn.show{display:flex}

/* ── Phase / Status bar ── */
.phase-bar{display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--bg);border-radius:14px;border:1px solid var(--border);margin-bottom:12px;min-height:48px}
.phase-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;transition:background .35s}
.phase-dot.off     {background:#94a3b8}
.phase-dot.scanning{background:#60a5fa;animation:blink .9s ease-in-out infinite}
.phase-dot.found   {background:#34d399;animation:blink .6s ease-in-out infinite}
.phase-dot.locked  {background:var(--green)}
.phase-dot.warn    {background:var(--amber)}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
.phase-text{font-size:12.5px;font-weight:800;color:var(--text);flex:1;line-height:1.4}
.phase-conf{font-size:10.5px;font-weight:900;padding:3px 9px;border-radius:999px;background:var(--light);color:var(--accent);display:none;white-space:nowrap}
.phase-conf.show{display:block}
.phase-conf.hi{background:#dcfce7;color:#166534}
.phase-conf.mid{background:#fef9c3;color:#854d0e}
.phase-conf.lo{background:#fee2e2;color:#b91c1c}

/* Progress bar */
.prog-wrap{height:7px;background:var(--bg);border-radius:99px;overflow:hidden;margin-bottom:14px;border:1px solid var(--border)}
.prog-bar{height:100%;width:0%;border-radius:99px;background:linear-gradient(90deg,var(--accent),#34d399);transition:width .09s linear,background .3s}
.prog-bar.full{background:linear-gradient(90deg,var(--green),#34d399)}

/* Camera buttons */
.cam-btns{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px}
.cam-btn{display:flex;align-items:center;justify-content:center;gap:8px;height:48px;border-radius:14px;border:none;font-family:"Nunito",sans-serif;font-size:13.5px;font-weight:900;cursor:pointer;transition:transform .14s,opacity .14s}
.cam-btn:hover:not(:disabled){transform:translateY(-1px)}
.cam-btn:disabled{opacity:.35;cursor:not-allowed;transform:none}
.btn-start{background:linear-gradient(135deg,var(--a2),var(--accent));color:#fff;box-shadow:0 6px 18px rgba(100,112,210,.28)}
.btn-stop{background:#fef2f2;color:var(--red);border:1.5px solid rgba(220,38,38,.18)}
.btn-stop:hover:not(:disabled){background:#fee2e2}

/* Alert banners */
.alert-banner{display:none;margin-top:10px;padding:12px 16px;border-radius:13px;font-size:12.5px;font-weight:700;line-height:1.65}
.alert-banner.show{display:block}
.alert-error{background:#fef2f2;border:1px solid rgba(220,38,38,.22);color:#991b1b}
.alert-warn{background:#fffbeb;border:1px solid rgba(217,119,6,.25);color:#78350f}

/* ── AI Analysis Panel ── */
.ai-phase-card{padding:22px 20px;border-radius:18px;border:1.5px solid var(--border);margin-bottom:16px;background:var(--bg);transition:border-color .3s,background .3s}
.ai-phase-card.phase-thinking  {border-color:rgba(167,139,250,.35);background:linear-gradient(135deg,#faf5ff,#ede9fe)}
.ai-phase-card.phase-analyzing {border-color:rgba(52,211,153,.35);background:linear-gradient(135deg,#f0fdf4,#dcfce7)}
.ai-phase-card.phase-confirming{border-color:rgba(251,191,36,.35);background:linear-gradient(135deg,#fffbeb,#fef3c7)}
.ai-phase-card.phase-confirmed {border-color:rgba(22,163,74,.45);background:linear-gradient(135deg,#f0fdf4,#dcfce7)}
.ai-phase-card.phase-unknown   {border-color:rgba(249,115,22,.35);background:linear-gradient(135deg,#fff7ed,#ffedd5)}
.ai-phase-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.ai-phase-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;background:rgba(100,112,210,.1);color:var(--accent);flex-shrink:0}
.ai-phase-label{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
.ai-phase-status{font-size:13.5px;font-weight:900;color:var(--navy);line-height:1.35}
.ai-candidate{font-size:11px;font-weight:800;color:var(--muted);margin-top:2px}

/* Confirmed detection card */
.detection-card{display:none;padding:22px 20px;border-radius:18px;background:linear-gradient(135deg,#f8faff,#eef1ff);border:2px solid rgba(100,112,210,.2);margin-bottom:16px;position:relative;overflow:hidden}
.detection-card::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80% 10%,rgba(100,112,210,.07) 0%,transparent 65%);pointer-events:none}
.detection-card.show{display:block}
.dc-label{font-size:9.5px;font-weight:900;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);margin-bottom:8px}
.dc-name{font-size:34px;font-weight:900;color:var(--navy);letter-spacing:-1px;font-family:var(--mono);line-height:1;margin-bottom:10px;animation:dcPop .35s cubic-bezier(.34,1.56,.64,1) both}
@keyframes dcPop{from{transform:scale(.75);opacity:0}to{transform:scale(1);opacity:1}}
.dc-conf-row{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.dc-conf-bar-wrap{flex:1;height:6px;background:rgba(0,0,0,.07);border-radius:99px;overflow:hidden}
.dc-conf-bar{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--accent),#34d399);transition:width .2s ease}
.dc-conf-pct{font-size:13px;font-weight:900;color:var(--navy);font-family:var(--mono);min-width:36px;text-align:right}
.dc-meta{display:flex;gap:12px;font-size:10.5px;font-weight:800;color:var(--muted)}
.dc-meta span{display:flex;align-items:center;gap:4px}
/* "Lower hand to sign again" hint strip */
.dc-reset-hint{margin-top:10px;padding:7px 10px;background:rgba(100,112,210,.08);border-radius:9px;font-size:10.5px;font-weight:800;color:var(--accent);display:none;align-items:center;gap:6px}
.dc-reset-hint.show{display:flex}

/* Session stats */
.session-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px}
.stat-cell{background:var(--bg);border:1px solid var(--border);border-radius:13px;padding:12px 10px;text-align:center}
.stat-value{font-size:20px;font-weight:900;color:var(--navy);font-family:var(--mono);line-height:1}
.stat-label{font-size:10px;font-weight:800;color:var(--muted);margin-top:3px;letter-spacing:.03em}

/* Detection timeline */
.timeline-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.timeline-label{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
.timeline-clear{font-size:11px;font-weight:800;color:var(--muted);background:none;border:none;cursor:pointer;padding:3px 8px;border-radius:7px;transition:background .14s,color .14s;font-family:"Nunito",sans-serif}
.timeline-clear:hover{background:#fee2e2;color:var(--red)}
.timeline-list{display:flex;flex-direction:column;gap:6px;max-height:200px;overflow-y:auto;padding-right:3px;margin-bottom:16px}
.timeline-list::-webkit-scrollbar{width:3px}
.timeline-list::-webkit-scrollbar-thumb{background:rgba(100,112,210,.2);border-radius:99px}
.tl-item{display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:10px;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:12px;animation:slideIn .2s ease both}
@keyframes slideIn{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
.tl-name{font-size:13px;font-weight:900;color:var(--navy);font-family:var(--mono)}
.tl-conf{font-size:10px;font-weight:900;color:var(--accent);background:var(--light);padding:2px 7px;border-radius:99px}
.tl-time{font-size:10px;font-weight:700;color:var(--muted);font-family:var(--mono)}
.tl-empty{padding:20px;text-align:center;font-size:12.5px;font-weight:700;color:#a0a3c0}

/* Action buttons — 3 columns */
.action-btns{display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px}
.action-btn{display:flex;align-items:center;justify-content:center;gap:8px;height:46px;border-radius:14px;border:none;font-family:"Nunito",sans-serif;font-size:13px;font-weight:900;cursor:pointer;transition:transform .14s,box-shadow .14s,background .14s}
.action-btn:hover:not(:disabled){transform:translateY(-1px)}
.action-btn:disabled{opacity:.35;cursor:not-allowed;transform:none}
.btn-speak{background:linear-gradient(135deg,var(--purple),var(--accent));color:#fff;box-shadow:0 5px 16px rgba(100,112,210,.24)}
.btn-mute{background:var(--bg);color:var(--muted);border:1.5px solid var(--border)}
.btn-mute:hover:not(:disabled){background:var(--light);color:var(--accent)}
.btn-mute.muted{background:#fef2f2;color:var(--red);border-color:rgba(220,38,38,.18)}
.btn-reset{background:var(--bg);color:var(--muted);border:1.5px solid var(--border)}
.btn-reset:hover:not(:disabled){background:#fee2e2;color:var(--red);border-color:rgba(220,38,38,.2)}

/* Score debug panel */
.score-panel{margin-top:12px;padding:12px 14px;background:var(--bg);border:1px solid var(--border);border-radius:14px;display:none}
.score-panel.visible{display:block}
.score-panel-title{font-size:9.5px;font-weight:900;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:9px;display:flex;align-items:center;justify-content:space-between}
.score-row{display:flex;align-items:center;gap:8px;margin-bottom:5px}
.score-row:last-child{margin-bottom:0}
.score-name{width:74px;font-size:10.5px;font-weight:800;color:var(--navy);flex-shrink:0;font-family:var(--mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.score-track{flex:1;height:5px;background:rgba(0,0,0,.06);border-radius:99px;overflow:hidden}
.score-fill{height:100%;border-radius:99px;background:var(--accent);transition:width .1s ease}
.score-fill.leading{background:var(--green)}
.score-pct{width:30px;font-size:9.5px;font-weight:900;color:var(--muted);text-align:right;font-family:var(--mono);flex-shrink:0}
.score-toggle{font-size:10.5px;font-weight:800;color:var(--muted);background:none;border:none;cursor:pointer;padding:2px 6px;border-radius:6px;font-family:"Nunito",sans-serif;transition:background .13s,color .13s}
.score-toggle:hover{background:var(--light);color:var(--accent)}

/* ── Gesture library ── */
.library-section{margin-top:28px}
.library-section h2{font-size:20px;font-weight:900;color:var(--navy);margin-bottom:6px;letter-spacing:-.3px}
.library-section>p{font-size:13px;font-weight:600;color:var(--muted);margin-bottom:18px;line-height:1.65}

.category-group{margin-bottom:16px}
.cat-header{display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--card);border:1px solid var(--border);border-radius:16px;cursor:pointer;user-select:none;transition:background .15s;margin-bottom:0}
.cat-header:hover{background:var(--light)}
.cat-header.open{border-radius:16px 16px 0 0;margin-bottom:0;border-bottom:1px solid var(--border)}
.cat-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;color:#fff}
.cat-name{font-size:14px;font-weight:900;color:var(--navy);flex:1}
.cat-count{font-size:11px;font-weight:800;color:var(--muted);background:var(--bg);padding:3px 9px;border-radius:999px}
.cat-chevron{font-size:12px;color:var(--muted);transition:transform .2s}
.cat-header.open .cat-chevron{transform:rotate(180deg)}

/* Cards container — hidden by default, shown when .open */
.cat-cards{
  display:none;
  grid-template-columns:repeat(auto-fill,minmax(152px,1fr));
  gap:10px;
  padding:14px;
  background:var(--card);
  border:1px solid var(--border);
  border-top:none;
  border-radius:0 0 16px 16px;
  align-items:stretch;
}
.cat-header.open+.cat-cards{display:grid}

/* Gesture card — enriched layout */
.gesture-card{
  background:var(--bg);
  border:1.5px solid var(--border);
  border-radius:16px;
  padding:14px 13px 12px;
  cursor:default;
  transition:transform .15s,box-shadow .15s,border-color .15s;
  position:relative;
  display:flex;
  flex-direction:column;
  gap:3px;
}
.gesture-card:hover,.gesture-card:focus{transform:translateY(-3px);box-shadow:var(--sh-lg);border-color:rgba(100,112,210,.28);outline:none}
.gesture-card.active-gesture{border-color:var(--accent);background:linear-gradient(135deg,#f8faff,#eef1ff);box-shadow:0 0 0 3px rgba(100,112,210,.12)}
.gesture-card.future{opacity:.72}

.gc-emoji{font-size:22px;line-height:1;margin-bottom:2px}
.gc-name{font-size:12px;font-weight:900;color:var(--navy);font-family:var(--mono)}
.gc-arabic{font-size:10.5px;font-weight:700;color:var(--muted);direction:rtl;margin-bottom:2px}
.gc-hint{font-size:10px;font-weight:700;color:var(--muted);line-height:1.45;flex:1}

/* Badges */
.gesture-card-badge{
  display:inline-block;
  margin-top:6px;
  align-self:flex-start;
  font-size:8.5px;font-weight:900;
  padding:2px 7px;border-radius:999px;
  text-transform:uppercase;letter-spacing:.04em;
}
.badge-ready {background:#dcfce7;color:#166534}
.badge-soon  {background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0}
.badge-active{background:#dcfce7;color:#166534;animation:blink .7s ease-in-out infinite}

/* Reduce empty space under gesture library */
.wrap {
  padding-bottom: 24px !important;
}

.library-section {
  margin-top: 20px !important;
  margin-bottom: 10px !important;
}

.category-group {
  margin-bottom: 8px !important;
}

.cat-header {
  margin-bottom: 0 !important;
}

.cat-cards {
  padding: 10px !important;
}
/* ── Responsive ── */
@media(max-width:860px){.main-grid{grid-template-columns:1fr}.hero{padding:28px 28px 36px;margin:0 -24px}.hero-icon{font-size:54px}.action-btns{grid-template-columns:1fr 1fr 1fr}}
@media(max-width:540px){.wrap{padding:0 14px 48px}.hero{padding:22px 18px 28px;margin:0 -14px}.session-stats{grid-template-columns:1fr 1fr}.action-btns{grid-template-columns:1fr 1fr;gap:8px}.btn-speak{grid-column:1/-1}}
@media(max-width:420px){.cat-cards{grid-template-columns:repeat(auto-fill,minmax(130px,1fr))}}
@media(forced-colors:active){.phase-dot,.prog-bar,.dc-conf-bar{forced-color-adjust:auto}.cam-btn,.action-btn{border:2px solid ButtonText}}
</style>
</head>
<body>

<div id="mpLoadOverlay" role="status" aria-live="assertive" aria-label="Loading AI hand detection">
  <div class="mp-spinner" aria-hidden="true"></div>
  <div class="mp-load-title">Loading Rafiq Sign AI…</div>
  <div class="mp-load-sub">Preparing hand detection models</div>
</div>

<?php include 'nav_patient.php'; ?>

<div class="wrap">

  <!-- Hero -->
  <div class="hero">
    <div class="hero-orb hero-orb-1" aria-hidden="true"></div>
    <div class="hero-orb hero-orb-2" aria-hidden="true"></div>
    <div class="hero-orb hero-orb-3" aria-hidden="true"></div>
    <div class="hero-inner">
      <div class="hero-text">
        <div class="hero-badge">
          <i class="fa-solid fa-microchip" aria-hidden="true"></i>
          Real-Time · 3D Joint Analysis · Multi-Frame Confirmation
        </div>
        <h1>AI <span>Sign Language</span> Assistant</h1>
        <p>Show your hand to the camera. The AI tracks 21 hand landmarks, analyses joint angles in 3-D, and only confirms a sign after it holds steady across multiple frames — eliminating false detections.</p>
        <div class="hero-tags">
          <span class="hero-tag"><i class="fa-solid fa-list-check" aria-hidden="true"></i> 15 signs live now</span>
          <span class="hero-tag"><i class="fa-solid fa-volume-high" aria-hidden="true"></i> Voice feedback</span>
          <span class="hero-tag"><i class="fa-solid fa-shield-check" aria-hidden="true"></i> No repeat detection</span>
        </div>
      </div>
      <div class="hero-icon" aria-hidden="true"><i class="fa-solid fa-hands-asl-interpreting"></i></div>
    </div>
  </div>

  <!-- Main grid -->
  <div class="main-grid" role="main">

    <!-- Camera Panel -->
    <div class="card" aria-labelledby="camHeading">
      <div class="card-head">
        <div class="card-head-icon"><i class="fa-solid fa-camera" aria-hidden="true"></i></div>
        <div>
          <div class="card-head-title" id="camHeading">Live Camera</div>
          <div class="card-head-sub">MediaPipe Hands · 21 landmarks · 30 fps</div>
        </div>
      </div>
      <div class="card-body">

        <div class="camera-viewport" id="cameraViewport" role="img" aria-label="Live camera with hand tracking overlay">
          <video id="videoEl" playsinline muted aria-hidden="true"></video>
          <canvas id="outputCanvas" aria-hidden="true"></canvas>
          <div class="cam-corner tl" id="ccTL" aria-hidden="true"></div>
          <div class="cam-corner tr" id="ccTR" aria-hidden="true"></div>
          <div class="cam-corner bl" id="ccBL" aria-hidden="true"></div>
          <div class="cam-corner br" id="ccBR" aria-hidden="true"></div>
          <div class="hand-guide hidden" id="handGuide" aria-hidden="true">
            <div class="hand-guide-label">Place hand here</div>
          </div>
          <div id="lightingWarn" role="alert" aria-live="polite">
            <i class="fa-solid fa-sun" aria-hidden="true"></i>
            Low lighting — improve brightness for better accuracy
          </div>
          <div class="camera-off-state" id="cameraOffState">
            <i class="fa-solid fa-video-slash" aria-hidden="true"></i>
            <div class="off-title">Camera is off</div>
            <div class="off-hint">Press Start Camera to begin</div>
          </div>
        </div>

        <!-- Phase status bar -->
        <div class="phase-bar" role="status" aria-live="polite" aria-atomic="true">
          <div class="phase-dot off" id="phaseDot" aria-hidden="true"></div>
          <div class="phase-text" id="phaseText">Camera off — click Start Camera</div>
          <div class="phase-conf" id="phaseConf" aria-label="Confidence"></div>
        </div>

        <!-- Confidence progress -->
        <div class="prog-wrap" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="progWrap">
          <div class="prog-bar" id="progBar"></div>
        </div>

        <!-- Camera buttons -->
        <div class="cam-btns">
          <button class="cam-btn btn-start" id="startBtn" aria-label="Start camera and AI detection">
            <i class="fa-solid fa-play" aria-hidden="true"></i> Start Camera
          </button>
          <button class="cam-btn btn-stop" id="stopBtn" disabled aria-label="Stop camera">
            <i class="fa-solid fa-stop" aria-hidden="true"></i> Stop
          </button>
        </div>

        <div class="alert-banner alert-error" id="errorBanner" role="alert">
          <strong>Camera access denied.</strong><br>
          Allow camera permission in your browser settings and reload.
        </div>
        <div class="alert-banner alert-warn" id="libErrorBanner" role="alert">
          <strong>AI libraries failed to load.</strong><br>
          Check your internet connection — MediaPipe downloads models on first load.
        </div>

        <!-- Live score panel -->
        <div class="score-panel" id="scorePanel" aria-label="Live gesture scores">
          <div class="score-panel-title">
            <span>Live confidence — all signs</span>
            <button class="score-toggle" id="scoreHide" aria-label="Hide scores">Hide</button>
          </div>
          <div id="scoreRows"></div>
        </div>
        <button class="score-toggle" id="scoreShow" style="display:none;margin-top:8px" aria-label="Show live scores">Show live scores</button>

      </div>
    </div>

    <!-- AI Analysis Panel -->
    <div class="card" aria-labelledby="aiHeading">
      <div class="card-head">
        <div class="card-head-icon"><i class="fa-solid fa-brain" aria-hidden="true"></i></div>
        <div>
          <div class="card-head-title" id="aiHeading">AI Analysis</div>
          <div class="card-head-sub">Phase engine · 3-stage confirmation · no repeat detection</div>
        </div>
      </div>
      <div class="card-body">

        <!-- Phase display card -->
        <div class="ai-phase-card" id="aiPhaseCard" aria-live="polite">
          <div class="ai-phase-row">
            <div class="ai-phase-icon" id="aiPhaseIcon"><i class="fa-solid fa-circle-pause" aria-hidden="true"></i></div>
            <div>
              <div class="ai-phase-label" id="aiPhaseLabel">System status</div>
              <div class="ai-phase-status" id="aiPhaseStatus">Start the camera to begin AI detection</div>
              <div class="ai-candidate" id="aiCandidate"></div>
            </div>
          </div>
        </div>

        <!-- Confirmed detection card -->
        <div class="detection-card" id="detectionCard" aria-live="assertive" aria-atomic="true">
          <div class="dc-label">Confirmed Sign</div>
          <div class="dc-name" id="dcName"></div>
          <div class="dc-conf-row">
            <div class="dc-conf-bar-wrap"><div class="dc-conf-bar" id="dcConfBar"></div></div>
            <div class="dc-conf-pct" id="dcConfPct"></div>
          </div>
          <div class="dc-meta">
            <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <span id="dcTime"></span></span>
            <span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> <span id="dcCat"></span></span>
          </div>
          <!-- Shown after confirmation, until hand is lowered -->
          <div class="dc-reset-hint" id="dcResetHint" aria-live="polite">
            <i class="fa-solid fa-hand" aria-hidden="true"></i>
            Lower your hand to sign again
          </div>
        </div>

        <!-- Session stats -->
        <div class="session-stats" role="region" aria-label="Session statistics">
          <div class="stat-cell">
            <div class="stat-value" id="statSigns">0</div>
            <div class="stat-label">Signs</div>
          </div>
          <div class="stat-cell">
            <div class="stat-value" id="statDuration">00:00</div>
            <div class="stat-label">Duration</div>
          </div>
          <div class="stat-cell">
            <div class="stat-value" id="statTop" style="font-size:13px">—</div>
            <div class="stat-label">Top Sign</div>
          </div>
        </div>

        <!-- Detection timeline -->
        <div class="timeline-header">
          <div class="timeline-label">Detection History</div>
          <button class="timeline-clear" id="clearBtn" aria-label="Clear detection history">Clear</button>
        </div>
        <div class="timeline-list" id="timelineList" role="log" aria-label="Detection history" aria-live="polite">
          <div class="tl-empty" id="tlEmpty">
            <i class="fa-regular fa-clock" aria-hidden="true"></i> No signs confirmed yet
          </div>
        </div>

        <!-- Action buttons: Speak | Mute | Reset -->
        <div class="action-btns">
          <button class="action-btn btn-speak" id="speakBtn" aria-label="Speak last detected sign" disabled>
            <i class="fa-solid fa-volume-high" aria-hidden="true"></i> Speak Last
          </button>
          <button class="action-btn btn-mute" id="muteBtn" aria-label="Toggle voice mute">
            <i class="fa-solid fa-volume-high" id="muteIcon" aria-hidden="true"></i>
          </button>
          <button class="action-btn btn-reset" id="resetBtn" aria-label="Reset session">
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- Gesture Library -->
  <section class="library-section" aria-labelledby="libHeading">
    <h2 id="libHeading">Gesture Library</h2>
    <p>
      Signs marked <strong style="color:var(--green)">Supported</strong> are detectable right now by the AI.
      Signs marked <strong style="color:#64748b">Coming Soon</strong> will be added in future updates as the model is extended.
    </p>
    <div id="libraryContainer"><!-- categories injected by JS --></div>
  </section>
</div><!-- .wrap -->

<!-- MediaPipe CDN -->
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js" crossorigin="anonymous"></script>
<!-- Classifier + Model -->
<script src="<?= $_base ?>/assets/js/sign-language-classifier.js"></script>
<script src="<?= $_base ?>/assets/js/sign-language-model.js"></script>

<script>
(function(){
'use strict';

const BASE = '<?= $_base ?>';

/* ── Gesture visual guide: emoji + short hand hint for every sign ── */
const GESTURE_GUIDE = {
  'hello':         { icon: 'fa-hand', hint: 'Open palm · all 5 fingers spread wide' },
  'yes':           { icon: 'fa-thumbs-up', hint: 'Fist · thumb pointing straight up' },
  'no':            { icon: 'fa-hand', hint: 'Index + pinky up · horns shape' },
  'thank_you':     { icon: 'fa-hand', hint: 'Thumb + index + middle up · rest folded' },
  'ily':           { icon: 'fa-hands', hint: 'Thumb + index + pinky · ILY handshape' },
  'please':        { icon: 'fa-hand', hint: 'Flat hand · circles clockwise on chest' },
  'sorry':         { icon: 'fa-hand-fist', hint: 'Fist · circles on chest' },
  'goodbye':       { icon: 'fa-hand', hint: 'Open palm · wave side to side' },
  'youre_welcome': { icon: 'fa-hands', hint: 'Flat hand sweeps inward from chin' },
  'emergency':     { icon: 'fa-triangle-exclamation', hint: 'Tight closed fist · hold up clearly' },
  'help':          { icon: 'fa-hand', hint: '4 fingers straight up · thumb tucked to palm' },
  'stop':          { icon: 'fa-hand', hint: 'Palm facing forward · thrust outward' },
  'danger':        { icon: 'fa-triangle-exclamation', hint: 'D-handshape · sharp downward motion' },
  'call_ambulance':{ icon: 'fa-phone', hint: 'Phone Y-shape near ear' },
  'hospital':      { icon: 'fa-hospital', hint: 'Clear V sign · index and middle only, thumb folded' },
  'pain':          { icon: 'fa-circle-exclamation', hint: 'Both index fingers point at each other' },
  'medicine':      { icon: 'fa-pills', hint: 'Middle finger flicks off thumb' },
  'doctor':        { icon: 'fa-user-doctor', hint: 'D-shape · taps on wrist' },
  'sick':          { icon: 'fa-head-side-cough', hint: 'Bent middle finger · forehead to stomach' },
  'allergy':       { icon: 'fa-head-side-mask', hint: 'Index traces down from nose' },
  'water':         { icon: 'fa-droplet', hint: 'Index + middle + ring up · W shape' },
  'food':          { icon: 'fa-utensils', hint: 'Fingertips pinched · moves toward mouth' },
  'hungry':        { icon: 'fa-bowl-food', hint: 'C-shape · moves down on chest' },
  'thirsty':       { icon: 'fa-glass-water', hint: 'Index traces down the throat' },
  'hot':           { icon: 'fa-temperature-high', hint: 'Claw near mouth · opens sharply out' },
  'cold':          { icon: 'fa-snowflake', hint: 'Both fists tremble and shake' },
  'car':           { icon: 'fa-car', hint: 'Both hands grip imaginary steering wheel' },
  'wheelchair':    { icon: 'fa-wheelchair', hint: 'Index fingers roll forward in circles' },
  'taxi':          { icon: 'fa-taxi', hint: 'Bent index waves downward · hailing' },
  'bus':           { icon: 'fa-bus', hint: 'B-shapes pull apart · bus doors opening' },
  'one':           { icon: 'fa-hand-point-up', hint: 'Index finger only · all others closed' },
  'bad':           { icon: 'fa-thumbs-down', hint: 'Fist · thumb pointing straight down' },
  'more':          { icon: 'fa-plus', hint: 'Fingertips pinch + tap together' },
  'finished':      { icon: 'fa-check', hint: 'Both hands flip outward from wrists' },
  'wait':          { icon: 'fa-hand', hint: 'Both hands wiggle fingers softly' },
  'understand':    { icon: 'fa-lightbulb', hint: 'Index flicks up at forehead' },
  'caregiver':     { icon: 'fa-hand-holding-heart', hint: 'One hand cradles and supports the other' },
  'interpreter':   { icon: 'fa-language', hint: 'Index fingers alternate left and right' },
  'blind':         { icon: 'fa-eye-low-vision', hint: 'V-shape touches eyes · moves away' },
  'deaf':          { icon: 'fa-ear-deaf', hint: 'Index traces from ear to chin' },
  'accessible':    { icon: 'fa-universal-access', hint: 'Both hands open outward from center' },
};

/* ── DOM refs ── */
const videoEl        = document.getElementById('videoEl');
const canvas         = document.getElementById('outputCanvas');
const ctx            = canvas.getContext('2d');
const cameraOffState = document.getElementById('cameraOffState');
const handGuide      = document.getElementById('handGuide');
const lightingWarn   = document.getElementById('lightingWarn');
const ccTL           = document.getElementById('ccTL');
const ccTR           = document.getElementById('ccTR');
const ccBL           = document.getElementById('ccBL');
const ccBR           = document.getElementById('ccBR');
const phaseDot       = document.getElementById('phaseDot');
const phaseText      = document.getElementById('phaseText');
const phaseConf      = document.getElementById('phaseConf');
const progBar        = document.getElementById('progBar');
const progWrap       = document.getElementById('progWrap');
const startBtn       = document.getElementById('startBtn');
const stopBtn        = document.getElementById('stopBtn');
const errorBanner    = document.getElementById('errorBanner');
const libErrorBanner = document.getElementById('libErrorBanner');
const aiPhaseCard    = document.getElementById('aiPhaseCard');
const aiPhaseIcon    = document.getElementById('aiPhaseIcon');
const aiPhaseLabel   = document.getElementById('aiPhaseLabel');
const aiPhaseStatus  = document.getElementById('aiPhaseStatus');
const aiCandidate    = document.getElementById('aiCandidate');
const detectionCard  = document.getElementById('detectionCard');
const dcName         = document.getElementById('dcName');
const dcConfBar      = document.getElementById('dcConfBar');
const dcConfPct      = document.getElementById('dcConfPct');
const dcTime         = document.getElementById('dcTime');
const dcCat          = document.getElementById('dcCat');
const dcResetHint    = document.getElementById('dcResetHint');
const statSigns      = document.getElementById('statSigns');
const statDuration   = document.getElementById('statDuration');
const statTop        = document.getElementById('statTop');
const timelineList   = document.getElementById('timelineList');
const tlEmpty        = document.getElementById('tlEmpty');
const clearBtn       = document.getElementById('clearBtn');
const speakBtn       = document.getElementById('speakBtn');
const muteBtn        = document.getElementById('muteBtn');
const muteIcon       = document.getElementById('muteIcon');
const resetBtn       = document.getElementById('resetBtn');
const scorePanel     = document.getElementById('scorePanel');
const scoreRows      = document.getElementById('scoreRows');
const scoreHide      = document.getElementById('scoreHide');
const scoreShow      = document.getElementById('scoreShow');
const mpLoadOverlay  = document.getElementById('mpLoadOverlay');
const libraryContainer = document.getElementById('libraryContainer');

/* ── State ── */
let handsModel       = null;
let mpCamera         = null;
let videoStream      = null;
let isRunning        = false;
let framesSinceHand  = 0;
let lowLightFrames   = 0;
let currentSpeak     = '';
let isMuted          = false;
let scorePanelOpen   = false;
let statsTimer       = null;
let lastConfirmedPhase = false;  // true while phase=confirmed + !isNew (holding)
const _scoreEls      = {};

/* ── Load gesture dataset & build library ── */
SLModel.init(BASE + '/assets/data/gesture-dataset.json').then(() => {
    buildLibrary();
}).catch(() => buildLibrary());

function buildLibrary() {
    const cats  = SLModel.getCategories();
    const signs = SLModel.getAllSigns();

    if (!cats.length) {
        libraryContainer.innerHTML = '<p style="color:var(--muted);font-size:13px;font-weight:700">Gesture library unavailable.</p>';
        return;
    }

    cats.forEach(cat => {
        const catSigns  = signs.filter(s => s.category === cat.id);
        const liveCount = catSigns.filter(s => s.mvp).length;

        const group  = document.createElement('div');
        group.className = 'category-group';

        const header = document.createElement('div');
        header.className = 'cat-header';
        if (cat.id === 'greetings' || cat.id === 'emergency') header.classList.add('open');
        header.innerHTML = `
            <div class="cat-icon" style="background:${cat.color || '#6470d2'}">
                <i class="fa-solid ${cat.icon || 'fa-hand'}" aria-hidden="true"></i>
            </div>
            <div class="cat-name">${cat.name}</div>
            <div class="cat-count">${liveCount} live · ${catSigns.length} total</div>
            <i class="fa-solid fa-chevron-down cat-chevron" aria-hidden="true"></i>`;
        header.setAttribute('tabindex','0');
        header.setAttribute('role','button');
        header.setAttribute('aria-expanded', header.classList.contains('open') ? 'true' : 'false');
        header.addEventListener('click', () => {
            header.classList.toggle('open');
            header.setAttribute('aria-expanded', header.classList.contains('open') ? 'true' : 'false');
        });
        header.addEventListener('keydown', e => {
            if (e.key==='Enter' || e.key===' ') { e.preventDefault(); header.click(); }
        });

        const cards = document.createElement('div');
        cards.className = 'cat-cards';

        catSigns.forEach(sign => {
            const guide = GESTURE_GUIDE[sign.id] || { icon: 'fa-hand', hint: sign.description || '' };
            const card  = document.createElement('div');
            card.className = 'gesture-card' + (sign.mvp ? '' : ' future');
            card.setAttribute('tabindex','0');
            card.setAttribute('role','listitem');
            card.setAttribute('aria-label', sign.name + ': ' + guide.hint);
            card.dataset.gesture = sign.name;

            const badgeClass = sign.mvp ? 'badge-ready' : 'badge-soon';
            const badgeLabel = sign.mvp ? 'Supported' : 'Coming Soon';

            card.innerHTML = `
                <div class="gc-emoji" aria-hidden="true"><i class="fa-solid ${guide.icon || 'fa-hand'}"></i></div>
                <div class="gc-name">${sign.name}</div>
                ${sign.arabic ? `<div class="gc-arabic">${sign.arabic}</div>` : ''}
                <div class="gc-hint">${guide.hint}</div>
                <span class="gesture-card-badge ${badgeClass}">${badgeLabel}</span>`;
            cards.appendChild(card);
        });

        group.appendChild(header);
        group.appendChild(cards);
        libraryContainer.appendChild(group);
    });
}

/* ── Build score rows ── */
(function buildScoreRows() {
    const signs = SLClassifier ? Object.keys(SLClassifier.SIGNS) : [];
    signs.forEach(name => {
        const row  = document.createElement('div');
        row.className = 'score-row';
        const safe = name.replace(/\s/g,'_');
        row.innerHTML = `
            <span class="score-name">${name}</span>
            <div class="score-track"><div class="score-fill" id="sf_${safe}"></div></div>
            <span class="score-pct" id="sp_${safe}">0%</span>`;
        scoreRows.appendChild(row);
        _scoreEls[name] = {
            fill: document.getElementById('sf_' + safe),
            pct:  document.getElementById('sp_' + safe),
        };
    });
})();

/* ── Score panel toggle ── */
scoreHide.addEventListener('click', () => {
    scorePanelOpen = false;
    scorePanel.classList.remove('visible');
    scoreShow.style.display = 'inline-block';
});
scoreShow.addEventListener('click', () => {
    scorePanelOpen = true;
    scorePanel.classList.add('visible');
    scoreShow.style.display = 'none';
});

function updateScores(scores, leading) {
    if (!scorePanelOpen || !scores) return;
    for (const [name, el] of Object.entries(_scoreEls)) {
        const v   = scores[name] || 0;
        const pct = Math.round(v * 100);
        el.fill.style.width = pct + '%';
        el.fill.className   = 'score-fill' + (name === leading ? ' leading' : '');
        el.pct.textContent  = pct + '%';
    }
}

/* ── Highlight active gesture card ── */
function highlightCard(name) {
    document.querySelectorAll('.gesture-card').forEach(c => {
        const match = c.dataset.gesture === name;
        c.classList.toggle('active-gesture', match);
        const badge = c.querySelector('.gesture-card-badge');
        if (!badge) return;
        if (match) {
            badge.className = 'gesture-card-badge badge-active';
        } else {
            const sign = SLModel.getAllSigns().find(s => s.name === c.dataset.gesture);
            if (sign) badge.className = 'gesture-card-badge ' + (sign.mvp ? 'badge-ready' : 'badge-soon');
        }
    });
}

/* ── Phase UI constants ── */
const PHASE_DOT_MAP = {
    idle:'off', detecting:'scanning', thinking:'scanning',
    analyzing:'found', confirming:'found', confirmed:'locked', unknown:'warn'
};
const PHASE_ICON_MAP = {
    idle:'fa-circle-pause', detecting:'fa-magnifying-glass', thinking:'fa-brain',
    analyzing:'fa-waveform-lines', confirming:'fa-circle-half-stroke',
    confirmed:'fa-circle-check', unknown:'fa-circle-question'
};
const PHASE_CARD_CLASSES = ['phase-thinking','phase-analyzing','phase-confirming','phase-confirmed','phase-unknown'];

/* ── Apply phase UI ── */
function applyPhaseUI(result) {
    const STRICT_CONFIRM_CONFIDENCE = 0.84;

    if (result && result.phase === 'confirmed' && Number(result.confidence || 0) < STRICT_CONFIRM_CONFIDENCE) {
        result = Object.assign({}, result, {
            phase: 'confirming',
            gesture: null,
            isNew: false,
            fillRatio: Math.min(Number(result.confidence || 0) / STRICT_CONFIRM_CONFIDENCE, 0.98),
            meta: { label: 'Almost confirmed — hold the sign steady' }
        });
    }

    const { phase, gesture, candidate, confidence, fillRatio, meta, scores, isNew } = result;

    // Dot
    phaseDot.className = 'phase-dot ' + (PHASE_DOT_MAP[phase] || 'off');

    // Phase text — special case: confirmed but holding (no re-trigger)
    if (phase === 'confirmed' && !isNew && gesture) {
        phaseText.textContent = '↓ Lower hand to sign again';
    } else {
        phaseText.textContent = meta ? meta.label : phase;
    }

    // Confidence pill
    if (confidence > 0.05) {
        const pct = Math.round(confidence * 100);
        phaseConf.textContent = pct + '%';
        phaseConf.className   = 'phase-conf show ' + (pct >= 80 ? 'hi' : pct >= 60 ? 'mid' : 'lo');
    } else {
        phaseConf.className = 'phase-conf';
    }

    // Progress bar
    const pct = Math.min(Math.round(fillRatio * 100), 100);
    progBar.style.width = pct + '%';
    progBar.className   = 'prog-bar' + (pct >= 100 ? ' full' : '');
    progWrap.setAttribute('aria-valuenow', pct);

    // Phase card class
    aiPhaseCard.className = 'ai-phase-card';
    PHASE_CARD_CLASSES.forEach(c => aiPhaseCard.classList.remove(c));
    if (phase !== 'idle') aiPhaseCard.classList.add('phase-' + phase);

    // Phase icon + label
    aiPhaseIcon.innerHTML = `<i class="fa-solid ${PHASE_ICON_MAP[phase] || 'fa-circle-pause'}" aria-hidden="true"></i>`;
    aiPhaseLabel.textContent = phase.charAt(0).toUpperCase() + phase.slice(1);
    aiPhaseStatus.textContent = meta ? meta.label : '';
    aiCandidate.textContent   = candidate && phase !== 'confirmed' ? 'Candidate: ' + candidate : '';

    // Reset hint visibility — only show when holding a confirmed sign
    const isHolding = phase === 'confirmed' && !isNew && gesture;
    dcResetHint.classList.toggle('show', isHolding);

    // On NEW confirmed detection
    if (phase === 'confirmed' && gesture && isNew) {
        const pctNum = Math.round(confidence * 100);
        const now    = new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'});
        const cat    = SLModel.getCategory(gesture.toLowerCase().replace(/\s/g,'_'));

        dcName.textContent    = gesture;
        dcConfBar.style.width = pctNum + '%';
        dcConfPct.textContent = pctNum + '%';
        dcTime.textContent    = now;
        dcCat.textContent     = cat ? cat.name : 'Sign Language';

        // Restart the pop animation
        detectionCard.classList.remove('show');
        void detectionCard.offsetWidth;
        detectionCard.classList.add('show');
        dcResetHint.classList.remove('show');

        addToTimeline(gesture, pctNum, now);
        speakBtn.disabled = false;
        currentSpeak      = gesture;
        highlightCard(gesture);
        speak(gesture);
    }

    if (phase !== 'confirmed') highlightCard(candidate || null);
    updateScores(scores, candidate || gesture);
}

/* ── Hand guide toggle ── */
function setHandPresence(present) {
    handGuide.className = 'hand-guide' + (present ? ' active' : ' hidden');
    [ccTL,ccTR,ccBL,ccBR].forEach(c => {
        c.classList.toggle('active', present);
    });
}

/* ── Timeline ── */
function addToTimeline(name, pct, time) {
    if (tlEmpty.parentNode === timelineList) tlEmpty.remove();
    const item = document.createElement('div');
    item.className = 'tl-item';
    item.setAttribute('aria-label', `${name} at ${pct}% confidence, ${time}`);
    item.innerHTML = `<span class="tl-name">${name}</span><span class="tl-conf">${pct}%</span><span class="tl-time">${time}</span>`;
    timelineList.prepend(item);
}

function clearAll() {
    timelineList.innerHTML = '';
    timelineList.appendChild(tlEmpty);
    tlEmpty.style.display  = 'block';
    detectionCard.classList.remove('show');
    dcResetHint.classList.remove('show');
    speakBtn.disabled = true;
    currentSpeak      = '';
    highlightCard(null);
    updateScores({}, null);
    SLModel.resetSession();
    updateStats();
    window.speechSynthesis && window.speechSynthesis.cancel();
}

/* ── Stats timer ── */
function updateStats() {
    const s = SLModel.getStats();
    statSigns.textContent    = s.total;
    statDuration.textContent = s.duration;
    statTop.textContent      = s.topSign || '—';
}

/* ── Lighting check ── */
let _lightCnt = 0;
function checkLighting() {
    _lightCnt++;
    if (_lightCnt % 30 !== 0) return;
    const brightness = SLClassifier.checkLighting(ctx, canvas.width, canvas.height);
    if (brightness < 55) { lowLightFrames = Math.min(lowLightFrames + 1, 5); }
    else                 { lowLightFrames = Math.max(lowLightFrames - 1, 0); }
    lightingWarn.classList.toggle('show', lowLightFrames >= 3);
}

/* ── MediaPipe results ── */
async function onResults(results) {
    canvas.width  = videoEl.videoWidth  || 640;
    canvas.height = videoEl.videoHeight || 480;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const hasHand = !!(results.multiHandLandmarks && results.multiHandLandmarks.length);
    setHandPresence(hasHand);

    if (!hasHand) {
        framesSinceHand = 0;
        SLModel.onNoHand();
        applyPhaseUI({ phase:'detecting', gesture:null, candidate:null, confidence:0, fillRatio:0, isNew:false, scores:{}, meta: SLModel.PHASE_META.detecting });
        checkLighting();
        return;
    }

    framesSinceHand++;
    const lm = results.multiHandLandmarks[0];

    if (typeof drawConnectors !== 'undefined') {
        drawConnectors(ctx, lm, HAND_CONNECTIONS, { color:'rgba(100,112,210,0.7)', lineWidth:2.5 });
        drawLandmarks(ctx, lm, { color:'#fff', fillColor:'rgba(100,112,210,0.55)', lineWidth:1, radius:4 });
    }

    checkLighting();

    const result = await SLModel.onFrame(lm, framesSinceHand);
    applyPhaseUI(result);
}

/* ── Camera start / stop ── */
async function startCamera() {
    startBtn.disabled  = true;
    startBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Starting…';
    errorBanner.classList.remove('show');
    mpLoadOverlay.classList.remove('hidden');

    try {
        videoStream = await navigator.mediaDevices.getUserMedia({
            video:{ facingMode:'user', width:{ideal:1280}, height:{ideal:720}, frameRate:{ideal:30} }
        });
        videoEl.srcObject = videoStream;
        await videoEl.play();

        cameraOffState.style.display = 'none';
        handGuide.classList.remove('hidden');

        handsModel = new Hands({ locateFile: f => `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${f}` });
        handsModel.setOptions({ maxNumHands:1, modelComplexity:1, minDetectionConfidence:0.78, minTrackingConfidence:0.72 });
        handsModel.onResults(onResults);

        mpCamera = new Camera(videoEl, {
            onFrame: async () => { if (handsModel && isRunning) await handsModel.send({ image: videoEl }); },
            width:1280, height:720
        });
        await mpCamera.start();

        isRunning = true;
        mpLoadOverlay.classList.add('hidden');
        startBtn.disabled  = true;
        stopBtn.disabled   = false;
        startBtn.innerHTML = '<i class="fa-solid fa-play" aria-hidden="true"></i> Start Camera';

        SLModel.resetSession();
        statsTimer = setInterval(updateStats, 1000);
        phaseDot.className    = 'phase-dot scanning';
        phaseText.textContent = 'Waiting for hand…';

    } catch(e) {
        console.error(e);
        mpLoadOverlay.classList.add('hidden');
        startBtn.disabled  = false;
        startBtn.innerHTML = '<i class="fa-solid fa-play" aria-hidden="true"></i> Start Camera';
        errorBanner.classList.add('show');
        phaseDot.className    = 'phase-dot off';
        phaseText.textContent = 'Camera access denied';
    }
}

function stopCamera() {
    isRunning = false;
    clearInterval(statsTimer);
    if (mpCamera)    { mpCamera.stop();  mpCamera = null; }
    if (videoStream) { videoStream.getTracks().forEach(t=>t.stop()); videoStream = null; }
    if (handsModel)  { handsModel.close(); handsModel = null; }

    videoEl.srcObject = null;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    cameraOffState.style.display = 'flex';
    handGuide.classList.add('hidden');
    lightingWarn.classList.remove('show');
    setHandPresence(false);
    SLModel.onNoHand();
    window.speechSynthesis && window.speechSynthesis.cancel();

    framesSinceHand = 0;
    lowLightFrames  = 0;
    _lightCnt       = 0;
    dcResetHint.classList.remove('show');

    startBtn.disabled = false;
    stopBtn.disabled  = true;
    phaseDot.className    = 'phase-dot off';
    phaseText.textContent = 'Camera off — click Start Camera';
    phaseConf.className   = 'phase-conf';
    progBar.style.width   = '0%';
    progWrap.setAttribute('aria-valuenow','0');
    aiPhaseCard.className = 'ai-phase-card';
    aiPhaseLabel.textContent  = 'Idle';
    aiPhaseStatus.textContent = 'Start the camera to begin AI detection';
    aiCandidate.textContent   = '';
    aiPhaseIcon.innerHTML     = '<i class="fa-solid fa-circle-pause" aria-hidden="true"></i>';
}

/* ── TTS ── */
function speak(text) {
    if (!text || !window.speechSynthesis || isMuted) return;
    window.speechSynthesis.cancel();
    const utt   = new SpeechSynthesisUtterance(text);
    utt.rate  = 0.88;
    utt.pitch = 1;
    utt.lang  = 'en-US';
    window.speechSynthesis.speak(utt);
}

/* ── Mute toggle ── */
function toggleMute() {
    isMuted = !isMuted;
    muteBtn.classList.toggle('muted', isMuted);
    muteIcon.className = isMuted
        ? 'fa-solid fa-volume-xmark'
        : 'fa-solid fa-volume-high';
    muteBtn.setAttribute('aria-label', isMuted ? 'Unmute voice' : 'Mute voice');
    if (isMuted) window.speechSynthesis && window.speechSynthesis.cancel();
}

/* ── Events ── */
startBtn.addEventListener('click', startCamera);
stopBtn.addEventListener('click', stopCamera);
speakBtn.addEventListener('click', () => speak(currentSpeak));
muteBtn.addEventListener('click', toggleMute);
resetBtn.addEventListener('click', clearAll);
clearBtn.addEventListener('click', clearAll);

document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.code === 'Space')                             { e.preventDefault(); isRunning ? stopCamera() : startCamera(); }
    if (e.code === 'KeyS' && !e.ctrlKey && !e.metaKey) { e.preventDefault(); speak(currentSpeak); }
    if (e.code === 'KeyM' && !e.ctrlKey && !e.metaKey) { e.preventDefault(); toggleMute(); }
    if (e.code === 'KeyR' && !e.ctrlKey && !e.metaKey) { e.preventDefault(); clearAll(); }
});

window.addEventListener('load', () => {
    setTimeout(() => mpLoadOverlay.classList.add('hidden'), 700);
    if (typeof Hands === 'undefined') {
        mpLoadOverlay.classList.add('hidden');
        libErrorBanner.classList.add('show');
        startBtn.disabled = true;
    }
});

window.addEventListener('beforeunload', () => {
    if (isRunning) stopCamera();
    window.speechSynthesis && window.speechSynthesis.cancel();
});

})();
</script>

<?php include 'footer.php'; ?>
</body>
</html>
