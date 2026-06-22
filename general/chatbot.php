<?php
session_start();

/* ── Base path ───────────────────────────────────────────── */
$_doc  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_dir  = str_replace('\\', '/', dirname(__DIR__));
$_rel  = ltrim(str_replace($_doc, '', $_dir), '/');
$_base = '/' . $_rel;

/* ── Back-link by role ───────────────────────────────────── */
$back_link = "$_base/general/login.php";
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'patient') {
        $back_link = "$_base/patient/patient_homepage.php";
    } elseif ($_SESSION['role'] === 'provider') {
        $map = [
            'doctor'      => "$_base/providers/doctor/doctor_homepage.php",
            'interpreter' => "$_base/providers/interpreter/int_homepage.php",
            'driver'      => "$_base/providers/driver/driver_portal.php",
            'caregiver'   => "$_base/providers/caregiver/caregiver_home.php",
        ];
        $back_link = $map[$_SESSION['provider_type'] ?? ''] ?? $back_link;
    }
}

$api_url = "$_base/general/chatbot_api.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rafiq AI Assistant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* ── Reset & tokens ──────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --navy:   #1e2040;
    --purple: #353b69;
    --accent: #6470d2;
    --a2:     #494788;
    --light:  #eef0ff;
    --bg:     #f4f5fb;
    --card:   #ffffff;
    --text:   #1e2040;
    --muted:  #6b7080;
    --border: rgba(100,112,210,.14);
    --sh:     0 4px 20px rgba(30,32,64,.09);
    --r:      18px;
}
html, body { min-height: 100%; font-family: "Nunito", system-ui, sans-serif; background: radial-gradient(circle at top left, #eef0ff 0, var(--bg) 38%, #ffffff 100%); color: var(--text); }

/* ── Polished app shell ─────────────────────────────────── */
.page {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 40px);
    max-width: 980px;
    margin: 18px auto 22px;
    background: rgba(255,255,255,.88);
    border: 1px solid rgba(100,112,210,.16);
    border-radius: 26px;
    overflow: hidden;
    box-shadow: 0 20px 55px rgba(30,32,64,.13);
}

/* ── Topbar ──────────────────────────────────────────────── */
.topbar {
    flex-shrink: 0;
    display: flex; align-items: center; gap: 14px;
    padding: 15px 24px;
    background: rgba(255,255,255,.96);
    border-bottom: 1px solid var(--border);
    box-shadow: 0 2px 14px rgba(30,32,64,.05);
}

.topbar-mid { flex: 1; display: flex; align-items: center; gap: 12px; justify-content: center; }
.bot-icon {
    width: 42px; height: 42px; border-radius: 50%;
    background: linear-gradient(135deg, var(--purple), var(--accent));
    display: flex; align-items: center; justify-content: center;
    font-size: 19px; flex-shrink: 0; color: #fff;
    box-shadow: 0 4px 14px rgba(100,112,210,.26);
}
.topbar-title { font-size: 17px; font-weight: 900; color: var(--navy); letter-spacing: -.3px; }
.topbar-sub   { font-size: 11.5px; font-weight: 700; color: var(--muted); display: flex; align-items: center; gap: 5px; margin-top: 2px; }
.live-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; animation: livePulse 2.2s ease-in-out infinite; }
@keyframes livePulse { 0%,100%{opacity:1} 50%{opacity:.35} }
.topbar-logo img { height: 34px; flex-shrink: 0; }

/* ── Setup banner (shows when API key missing) ───────────── */
.setup-banner {
    flex-shrink: 0;
    display: flex; align-items: flex-start; gap: 14px;
    padding: 16px 22px;
    background: #fffbeb;
    border-bottom: 2px solid #f59e0b;
    font-size: 13px; font-weight: 700; color: #78350f;
    line-height: 1.6;
}
.setup-banner[hidden] { display: none !important; }
.setup-icon { font-size: 22px; flex-shrink: 0; margin-top: 2px; }
.setup-banner strong { font-weight: 900; }
.setup-banner code {
    background: rgba(245,158,11,.15);
    padding: 2px 7px; border-radius: 6px;
    font-family: "Courier New", monospace;
    font-size: 12px;
}
.setup-banner a { color: #92400e; font-weight: 900; }

/* ── Intro hero (collapses after first message) ──────────── */
.intro-hero {
    flex-shrink: 0;
    padding: 34px 28px 38px;
    background:
        radial-gradient(circle at 16% 85%, rgba(255,255,255,.09), transparent 25%),
        radial-gradient(circle at 88% 5%, rgba(255,255,255,.12), transparent 28%),
        linear-gradient(135deg, var(--navy) 0%, var(--purple) 52%, var(--accent) 100%);
    color: #fff; text-align: center; position: relative; overflow: hidden;
    transition: max-height .45s ease, opacity .35s ease, padding .45s ease;
}
.intro-orb { position: absolute; border-radius: 50%; pointer-events: none; }
.intro-orb-1 { width:220px; height:220px; top:-90px; right:-70px; background:rgba(255,255,255,.05); }
.intro-orb-2 { width:130px; height:130px; bottom:-50px; left:4%;  background:rgba(255,255,255,.04); }
.intro-hero h1 {
    font-size: clamp(19px, 4vw, 29px); font-weight: 900;
    letter-spacing: -.4px; line-height: 1.12; position: relative;
}
.intro-hero h1 span {
    background: linear-gradient(90deg, #c4caff, #fff);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.intro-hero p {
    font-size: 13.5px; font-weight: 600; color: rgba(255,255,255,.78);
    margin-top: 10px; line-height: 1.75; max-width: 520px;
    margin-left: auto; margin-right: auto; position: relative;
}

/* ── Messages ─────────────────────────────────────────────── */
.messages {
    flex: 1; overflow-y: auto;
    padding: 20px 24px 14px;
    display: flex; flex-direction: column; gap: 14px;
    scroll-behavior: smooth;
    min-height: 260px;
    background:
        linear-gradient(180deg, rgba(238,240,255,.52), rgba(255,255,255,.9)),
        radial-gradient(circle at top right, rgba(100,112,210,.08), transparent 30%);
}
.messages:empty::before {
    content: "Start a conversation or choose one of the quick suggestions below.";
    margin: auto;
    max-width: 430px;
    padding: 18px 22px;
    border: 1px dashed rgba(100,112,210,.26);
    border-radius: 18px;
    color: var(--muted);
    background: rgba(255,255,255,.76);
    text-align: center;
    font-weight: 800;
    line-height: 1.6;
}
.messages::-webkit-scrollbar { width: 5px; }
.messages::-webkit-scrollbar-thumb { background: rgba(100,112,210,.2); border-radius: 99px; }

.msg-row { display: flex; gap: 9px; align-items: flex-end; animation: msgIn .24s ease both; }
@keyframes msgIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
.msg-row.user { flex-direction: row-reverse; }

.msg-av {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; border: 2px solid var(--card);
    box-shadow: 0 2px 8px rgba(30,32,64,.11);
}
.msg-row.bot  .msg-av { background: linear-gradient(135deg, var(--purple), var(--accent)); color: #fff; }
.msg-row.user .msg-av { background: linear-gradient(135deg, #2f3060, #4a3fa0); color: #fff; }

.msg-bub {
    max-width: min(74%, 560px);
    padding: 12px 17px;
    border-radius: 20px;
    font-size: 14px; font-weight: 600; line-height: 1.7;
    word-break: break-word;
}
.msg-row.bot  .msg-bub {
    background: var(--card); color: var(--text);
    border: 1px solid var(--border); border-radius: 4px 20px 20px 20px;
    box-shadow: var(--sh);
}
.msg-row.user .msg-bub {
    background: linear-gradient(135deg, var(--a2), var(--accent));
    color: #fff; border-radius: 20px 20px 4px 20px;
    box-shadow: 0 5px 18px rgba(100,112,210,.30);
}
.msg-bub strong { font-weight: 900; }
.msg-bub ul { margin: 7px 0 4px 18px; }
.msg-bub li { margin-bottom: 4px; }
.msg-bub p  { margin-top: 5px; }
.msg-bub p:first-child { margin-top: 0; }

.msg-time {
    font-size: 10.5px; color: var(--muted); font-weight: 700;
    margin: 0 44px; opacity: .65;
}
.msg-row.user + .msg-time { text-align: right; }

/* ── Typing indicator ────────────────────────────────────── */
.typing-row { display: flex; gap: 9px; align-items: flex-end; animation: msgIn .24s ease both; }
.typing-bub {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 4px 20px 20px 20px;
    padding: 14px 18px; display: flex; gap: 5px; align-items: center;
    box-shadow: var(--sh);
}
.typing-bub span {
    width: 7px; height: 7px; border-radius: 50%;
    background: #9598c0;
    animation: typingBounce 1.3s infinite ease-in-out;
}
.typing-bub span:nth-child(2) { animation-delay: .18s; }
.typing-bub span:nth-child(3) { animation-delay: .36s; }
@keyframes typingBounce { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-8px)} }

/* ── Bottom panel ────────────────────────────────────────── */
.bottom {
    flex-shrink: 0;
    background: rgba(255,255,255,.97);
    border-top: 1px solid var(--border);
    box-shadow: 0 -4px 22px rgba(30,32,64,.05);
}

/* Quick chips */
.chips-wrap { padding: 11px 18px 5px; }
.chips-label { font-size: 10px; font-weight: 900; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 7px; }
.chips-scroll { display: flex; gap: 7px; overflow-x: auto; padding-bottom: 8px; scrollbar-width: none; }
.chips-scroll::-webkit-scrollbar { display: none; }
.chip {
    flex-shrink: 0; padding: 8px 15px; border-radius: 999px;
    border: 1.5px solid rgba(100,112,210,.2); background: linear-gradient(180deg, #f7f8ff, var(--light));
    color: var(--purple); font-size: 12px; font-weight: 800;
    cursor: pointer; white-space: nowrap; font-family: "Nunito", sans-serif;
    transition: background .13s, border-color .13s, transform .1s, box-shadow .13s;
}
.chip:hover  { background: #d8dcff; border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(100,112,210,.14); }
.chip:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.chip:disabled { opacity: .4; cursor: not-allowed; transform: none; }

/* Input bar */
.input-bar { display: flex; align-items: flex-end; gap: 10px; padding: 12px 18px 16px; }
.input-wrap { flex: 1; position: relative; }
.chat-input {
    width: 100%; min-height: 50px; max-height: 130px;
    padding: 13px 48px 13px 17px;
    border-radius: 16px; border: 1.5px solid rgba(100,112,210,.2);
    background: var(--bg); font-family: "Nunito", sans-serif;
    font-size: 14px; font-weight: 700; color: var(--text);
    resize: none; outline: none; line-height: 1.55;
    overflow-y: auto;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
.chat-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(100,112,210,.11); background: var(--card); }
.chat-input::placeholder { color: #a5a8c8; font-weight: 600; }
.char-hint { position: absolute; right: 12px; bottom: 10px; font-size: 10px; font-weight: 800; color: var(--muted); opacity: .55; pointer-events: none; }
.char-hint.warn { color: #f59e0b; opacity: 1; }
.char-hint.over { color: #ef4444; opacity: 1; }

.send-btn {
    width: 50px; height: 50px; flex-shrink: 0; border: none; border-radius: 15px;
    background: linear-gradient(135deg, var(--a2), var(--accent));
    color: #fff; font-size: 17px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 6px 18px rgba(100,112,210,.32);
    transition: transform .15s, box-shadow .15s, opacity .15s;
}
.send-btn:hover   { transform: translateY(-2px); box-shadow: 0 9px 26px rgba(100,112,210,.42); }
.send-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
.send-btn:disabled { opacity: .38; cursor: not-allowed; transform: none; box-shadow: none; }

.kb-hint { text-align: center; font-size: 11px; font-weight: 700; color: var(--muted); padding: 0 18px 10px; opacity: .65; }
.kb-hint kbd { background: #e8eaf6; border-radius: 5px; padding: 1px 5px; font-family: inherit; font-size: 10px; }

.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 620px) {
    .page { max-width: 100%; min-height: calc(100vh - 18px); margin: 12px 0 0; border-radius: 0; }
    .topbar { padding: 11px 14px; }
    .topbar-logo { display: none; }
    .messages { padding: 15px 12px 10px; }
    .intro-hero { padding: 20px 16px 24px; }
    .input-bar { padding: 10px 12px 14px; }
    .chips-wrap { padding: 10px 12px 4px; }
    .msg-bub { max-width: 88%; font-size: 13.5px; }
}

/* Reduce top whitespace after the included navigation */
body { margin: 0; padding: 0; }
.page { margin-top: 18px !important; }
@media (max-width: 620px) { .page { margin-top: 12px !important; } }


/* Helpy bot image icon */
.bot-icon{
    background:#1e2040 !important;
    overflow:hidden;
    padding:5px;
}
.bot-icon img{
    width:100%;
    height:100%;
    object-fit:contain;
    display:block;
}
.msg-av.bot-av{
    background:#1e2040 !important;
    overflow:hidden;
    padding:4px;
}
.msg-av.bot-av img{
    width:100%;
    height:100%;
    object-fit:contain;
    display:block;
}

</style>
</head>

<body>
<?php include 'nav_patient.php'; ?>
<div class="page">

    <!-- ── Topbar ───────────────────────────────────────── -->
    <header class="topbar" role="banner">
        <div class="topbar-mid">
            <div class="bot-icon" aria-hidden="true"><img src="<?= $_base ?>/pictures/helpy.png" alt=""></div>
            <div>
                <div class="topbar-title">Rafiq AI Assistant</div>
                <div class="topbar-sub">
                    <span class="live-dot" aria-hidden="true"></span>
                    Powered by AI · Ask me anything
                </div>
            </div>
        </div>
        <div class="topbar-logo" aria-hidden="true">
            <img src="<?= $_base ?>/pictures/rafiq_logo.png" alt="">
        </div>
    </header>

    <!-- ── Setup banner (visible only when API key missing) ── -->
    <div class="setup-banner" id="setupBanner" role="alert" hidden>
        <span class="setup-icon"><i class="fa-solid fa-circle-info"></i></span>
        <div>
            <strong>AI key not configured yet.</strong>
            To enable real AI responses, open <code>/pgdb/chatbot_config.php</code>,
            get a free key at <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a>,
            and paste it into <code>RAFIQ_AI_KEY</code>.
        </div>
    </div>

    <!-- ── Intro hero ────────────────────────────────────── -->
    <div class="intro-hero" id="introHero" aria-hidden="false">
        <div class="intro-orb intro-orb-1"></div>
        <div class="intro-orb intro-orb-2"></div>
        <h1>Hi, I'm your <span>Rafiq Assistant</span></h1>
        <p>I'm powered by Gemini AI — I can answer any question you type, not just the suggestions below. Try me!</p>
    </div>

    <!-- ── Messages ─────────────────────────────────────── -->
    <main class="messages" id="messages"
          role="log" aria-live="polite" aria-label="Chat conversation" tabindex="0">
    </main>

    <!-- ── Bottom panel ──────────────────────────────────── -->
    <div class="bottom">
        <div class="chips-wrap" aria-label="Quick question suggestions">
            <div class="chips-label">Quick suggestions — or type your own question below</div>
            <div class="chips-scroll">
                <button class="chip" data-q="How do I find accessible places near me?"> Find accessible places</button>
                <button class="chip" data-q="How do I book a driver?">Book a driver</button>
                <button class="chip" data-q="How do I book a doctor for a home visit?">Book a doctor</button>
                <button class="chip" data-q="I am in an emergency. What should I do?">Emergency help</button>
                <button class="chip" data-q="How do I use the map and its filters?">Using the map</button>
                <button class="chip" data-q="I use a wheelchair. How can Rafiq help me?">Wheelchair support</button>
                <button class="chip" data-q="I am visually impaired. Is Rafiq accessible?">Visual impairment</button>
                <button class="chip" data-q="I am deaf or hard of hearing. What services are available?">Hearing impaired</button>
                <button class="chip" data-q="How do I book a caregiver?">Book a caregiver</button>
                <button class="chip" data-q="How do I track my booking status?">Booking status</button>
                <button class="chip" data-q="What payment methods does Rafiq accept?">Payment options</button>
                <button class="chip" data-q="How do I report incorrect information on the map?">Report wrong info</button>
                <button class="chip" data-q="How can I join Rafiq as a service provider?">Join as provider</button>
                <button class="chip" data-q="Is my personal data safe on Rafiq?">Privacy and safety</button>
                <button class="chip" data-q="How do I contact Rafiq support?">Contact support</button>
            </div>
        </div>

        <div class="input-bar">
            <div class="input-wrap">
                <label for="chatInput" class="sr-only">Type your message to Rafiq AI Assistant</label>
                <textarea
                    id="chatInput"
                    class="chat-input"
                    placeholder="Type any question — I'm a real AI, not a FAQ bot…"
                    rows="1"
                    maxlength="1000"
                    aria-label="Message input"
                    aria-multiline="true"></textarea>
                <span class="char-hint" id="charHint" aria-hidden="true"></span>
            </div>
            <button class="send-btn" id="sendBtn" aria-label="Send message" disabled>
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
            </button>
        </div>
        <p class="kb-hint" aria-hidden="true"><kbd>Enter</kbd> to send &nbsp;·&nbsp; <kbd>Shift+Enter</kbd> for new line</p>
    </div>

</div><!-- .page -->

<script>
(function () {
'use strict';

/* ── Config ─────────────────────────────────────────────── */
const API_URL = <?= json_encode($api_url) ?>;

/* ── Elements ────────────────────────────────────────────── */
const msgArea     = document.getElementById('messages');
const inputEl     = document.getElementById('chatInput');
const sendBtn     = document.getElementById('sendBtn');
const charHint    = document.getElementById('charHint');
const introHero   = document.getElementById('introHero');
const setupBanner = document.getElementById('setupBanner');
const chips       = document.querySelectorAll('.chip');

/* ── State ───────────────────────────────────────────────── */
let busy    = false;
let introDone = false;

/*
 * Conversation history — sent to the backend with every request
 * so the AI has full context (like ChatGPT multi-turn).
 * Format: [ { role: "user"|"model", content: "..." }, … ]
 */
let history = [];

/* ── Collapse the intro hero on first message ────────────── */
function collapseIntro() {
    if (introDone) return;
    introDone = true;
    const h = introHero.offsetHeight;
    introHero.style.overflow = 'hidden';
    introHero.style.maxHeight = h + 'px';
    requestAnimationFrame(() => {
        introHero.style.maxHeight  = '0';
        introHero.style.opacity    = '0';
        introHero.style.paddingTop = '0';
        introHero.style.paddingBottom = '0';
        introHero.setAttribute('aria-hidden', 'true');
    });
}

/* ── Auto-resize textarea ────────────────────────────────── */
inputEl.addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 130) + 'px';

    const len = this.value.length;
    sendBtn.disabled = len === 0 || busy;

    if      (len > 900) { charHint.textContent = len + '/1000'; charHint.className = 'char-hint over'; }
    else if (len > 750) { charHint.textContent = len + '/1000'; charHint.className = 'char-hint warn'; }
    else                { charHint.textContent = '';            charHint.className = 'char-hint'; }
});

/* ── Send on Enter (Shift+Enter = newline) ───────────────── */
inputEl.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (!sendBtn.disabled) doSend();
    }
});
sendBtn.addEventListener('click', doSend);

function doSend() {
    const text = inputEl.value.trim();
    if (!text || busy) return;
    inputEl.value = '';
    inputEl.style.height = 'auto';
    charHint.textContent = '';
    sendBtn.disabled = true;
    sendMessage(text);
}

/* ── Chips: same path as typed messages ──────────────────── */
chips.forEach(chip => {
    chip.addEventListener('click', function () {
        if (busy) return;
        sendMessage(this.dataset.q);
    });
});

/* ══════════════════════════════════════════════════════════
   CORE: send message → call AI API → render response
   ══════════════════════════════════════════════════════════ */
async function sendMessage(text) {
    if (busy) return;
    busy = true;
    setChipsDisabled(true);
    collapseIntro();

    /* 1. Show user bubble */
    addUserBubble(text);

    /* 2. Show typing indicator */
    await sleep(280);
    const typingEl = showTyping();

    /* 3. POST to backend — include full conversation history */
    let replyText;
    try {
        const res = await fetch(API_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ message: text, history })
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        /* Show setup banner if API key is missing */
        if (data.setup === true) {
            setupBanner.hidden = false;
        }

        await sleep(350); /* minimum "thinking" pause */
        replyText = data.reply || "Sorry, I couldn't generate a response. Please try again.";

    } catch (err) {
        await sleep(300);
        replyText = "I'm having a connection issue right now. Please try again, or email **support@rafiq.eg**";
    }

    /* 4. Remove typing indicator, show AI reply */
    typingEl.remove();
    addBotBubble(replyText);

    /* 5. Append to history for multi-turn context */
    history.push({ role: 'user',  content: text });
    history.push({ role: 'model', content: replyText });

    /* Keep history to last 20 turns to avoid token overload */
    if (history.length > 40) history = history.slice(-40);

    busy = false;
    setChipsDisabled(false);
    sendBtn.disabled = inputEl.value.trim() === '';
    inputEl.focus();
}

/* ── Bubble builders ──────────────────────────────────────── */
function addUserBubble(text) {
    const row = el('div', 'msg-row user');
    row.setAttribute('role', 'listitem');
    row.innerHTML = `
        <div class="msg-av" aria-hidden="true"><i class="fa-solid fa-user"></i></div>
        <div class="msg-bub" aria-label="You said: ${escAttr(text)}">${escHtml(text)}</div>`;
    msgArea.appendChild(row);
    appendTime('right');
    scrollBottom();
}

function addBotBubble(text) {
    const row = el('div', 'msg-row bot');
    row.setAttribute('role', 'listitem');
    row.innerHTML = `
        <div class="msg-av bot-av" aria-hidden="true"><img src="<?= $_base ?>/pictures/helpy.png" alt=""></div>
        <div class="msg-bub" aria-label="Rafiq Assistant replied">${renderMarkdown(text)}</div>`;
    msgArea.appendChild(row);
    appendTime('left');
    scrollBottom();
}

function showTyping() {
    const row = el('div', 'typing-row');
    row.setAttribute('aria-label', 'Rafiq Assistant is typing');
    row.innerHTML = `
        <div class="msg-av bot-av" aria-hidden="true"><img src="<?= $_base ?>/pictures/helpy.png" alt=""></div>
        <div class="typing-bub"><span></span><span></span><span></span></div>`;
    msgArea.appendChild(row);
    scrollBottom();
    return row;
}

function appendTime(align) {
    const d = el('div', 'msg-time');
    if (align === 'right') d.style.textAlign = 'right';
    d.setAttribute('aria-hidden', 'true');
    d.textContent = now();
    msgArea.appendChild(d);
}

/* ── Markdown renderer ────────────────────────────────────── */
function renderMarkdown(raw) {
    const lines = raw.split('\n');
    let out = '', inList = false, inOl = false;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const ul = line.match(/^[\*\-•]\s+(.*)/);
        const ol = line.match(/^\d+\.\s+(.*)/);

        if (ul) {
            if (inOl) { out += '</ol>'; inOl = false; }
            if (!inList) { out += '<ul>'; inList = true; }
            out += `<li>${inline(escHtml(ul[1]))}</li>`;
        } else if (ol) {
            if (inList) { out += '</ul>'; inList = false; }
            if (!inOl)  { out += '<ol>'; inOl = true; }
            out += `<li>${inline(escHtml(ol[1]))}</li>`;
        } else {
            if (inList) { out += '</ul>'; inList = false; }
            if (inOl)   { out += '</ol>'; inOl   = false; }
            const t = line.trim();
            out += t === '' ? '' : `<p>${inline(escHtml(t))}</p>`;
        }
    }
    if (inList) out += '</ul>';
    if (inOl)   out += '</ol>';
    return out;
}

/* Bold, italic, inline code */
function inline(s) {
    return s
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g,     '<em>$1</em>')
        .replace(/`(.*?)`/g,       '<code style="background:#f0f2ff;padding:1px 5px;border-radius:5px;font-size:.92em">$1</code>');
}

/* ── Utilities ────────────────────────────────────────────── */
function el(tag, cls) { const e = document.createElement(tag); e.className = cls; return e; }
function scrollBottom() { msgArea.scrollTop = msgArea.scrollHeight; }
function setChipsDisabled(v) { chips.forEach(c => c.disabled = v); }
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function now()  { return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) { return String(s).replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

/* ── Focus input on load ──────────────────────────────────── */
window.addEventListener('load', () => inputEl.focus());

})();
</script>
<?php include 'footer.php'; ?>
</body>
</html>
