<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
.pnav{
    position:sticky;
    top:0;
    z-index:1000;
    width:100%;
    height:66px;
    background:rgba(255,255,255,.92);
    backdrop-filter:blur(20px);
    -webkit-backdrop-filter:blur(20px);
    border-bottom:1px solid rgba(36,39,66,.07);
    box-shadow:0 2px 16px rgba(36,39,66,.05);
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 32px;
    font-family:"Nunito",system-ui,sans-serif;
    transition:box-shadow .25s;
}
.pnav.scrolled{
    box-shadow:0 4px 24px rgba(36,39,66,.10);
    background:rgba(255,255,255,.97);
}

/* LEFT */
.pnav-left{
    display:flex;
    align-items:center;
    gap:28px;
}
.pnav-logo{
    display:flex;
    align-items:center;
    gap:10px;
    text-decoration:none;
    flex-shrink:0;
}
.pnav-logo img{
    height:38px;
    display:block;
}
.pnav-home{
    display:flex;
    align-items:center;
    gap:7px;
    text-decoration:none;
    font-size:14px;
    font-weight:800;
    color:#4a4e74;
    padding:8px 14px;
    border-radius:12px;
    transition:background .18s,color .18s;
}
.pnav-home:hover{ background:#f0f2fb; color:#2B2C41; }
.pnav-home.active{ background:#eef2ff; color:#4b4f83; }

/* RIGHT */
.pnav-right{
    display:flex;
    align-items:center;
    gap:6px;
}

/* Rafiq palette pills */
.pnav-map-pill,
.pnav-sl-pill,
.pnav-ocr-pill{
    display:flex;
    align-items:center;
    gap:7px;
    text-decoration:none;
    font-size:13px;
    font-weight:800;
    color:#404066;
    background:#f3f4fb;
    border:1.5px solid rgba(64,64,102,.18);
    padding:8px 16px;
    border-radius:12px;
    transition:.18s ease;
    margin-right:4px;
}

.pnav-map-pill:hover,
.pnav-sl-pill:hover,
.pnav-ocr-pill:hover{
    background:#eef0ff;
    border-color:rgba(64,64,102,.35);
    color:#2B2C41;
    transform:translateY(-1px);
}

.pnav-map-pill.active,
.pnav-sl-pill.active,
.pnav-ocr-pill.active{
    background:#eef0ff;
    border-color:rgba(64,64,102,.35);
    color:#2B2C41;
}

.pnav-map-pill i,
.pnav-sl-pill i,
.pnav-ocr-pill i{
    color:#404066;
}
.pnav-vc-pill:hover{
    background:#e0e7ff;
    border-color:rgba(79,70,229,.40);
    color:#1e1b7a;
}

/* Icon buttons */
.pnav-icon{
    position:relative;
    width:40px;
    height:40px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:17px;
    color:#4a4e74;
    text-decoration:none;
    background:none;
    border:none;
    cursor:pointer;
    font-family:inherit;
    transition:background .18s,color .18s;
}
.pnav-icon:hover{ background:#f0f2fb; color:#2B2C41; }
.pnav-icon.active{ background:#eef2ff; color:#4b4f83; }
.pnav-icon.pnav-logout:hover{ background:#fef2f2; color:#b53535; }

/* Badge */
.pnav-badge{
    position:absolute;
    top:5px; right:5px;
    min-width:16px; height:16px;
    border-radius:99px;
    background:#e53e3e;
    color:#fff;
    font-size:9px;
    font-weight:900;
    display:none;
    align-items:center;
    justify-content:center;
    padding:0 3px;
    border:2px solid #fff;
    line-height:1;
    font-family:"Nunito",sans-serif;
}
.pnav-badge.show{ display:flex; }

/* Divider */
.pnav-divider{
    width:1px;
    height:22px;
    background:rgba(36,39,66,.10);
    margin:0 4px;
}

/* Notification dropdown */
.pnav-bell-wrap{ position:relative; }
.pnav-notif-drop{
    display:none;
    position:absolute;
    top:calc(100% + 12px);
    right:0;
    width:308px;
    background:#fff;
    border:1px solid rgba(100,112,210,.14);
    border-radius:20px;
    box-shadow:0 20px 52px rgba(41,43,74,.15);
    z-index:2100;
    overflow:hidden;
}
.pnav-notif-drop.open{ display:block; }
.pnav-notif-head{
    padding:14px 18px 12px;
    font-weight:900;
    font-size:13px;
    color:#353b69;
    border-bottom:1px solid rgba(100,112,210,.09);
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.pnav-notif-clear{
    font-size:11px;
    color:#9b9eb8;
    cursor:pointer;
    font-weight:700;
}
.pnav-notif-clear:hover{ color:#353b69; }
.pnav-notif-scroll{ max-height:270px; overflow-y:auto; }
.pnav-notif-item{
    padding:12px 18px;
    border-bottom:1px solid rgba(100,112,210,.06);
    display:flex;
    gap:10px;
    align-items:flex-start;
    text-decoration:none;
    color:inherit;
    transition:background .14s;
}
.pnav-notif-item:hover{ background:#f8f8ff; }
.pnav-notif-item:last-child{ border-bottom:none; }
.pnav-notif-dot{
    width:8px; height:8px;
    border-radius:50%;
    background:#6470d2;
    flex-shrink:0;
    margin-top:5px;
}
.pnav-notif-dot.read{ background:#e2e8f0; }
.pnav-notif-text{ font-size:13px; font-weight:700; color:#2e3154; line-height:1.4; }
.pnav-notif-sub{ font-size:11px; color:#9b9eb8; margin-top:2px; font-weight:600; }
.pnav-notif-empty{
    padding:32px 18px;
    text-align:center;
    color:#b0b3cc;
    font-size:13px;
    font-weight:700;
}

/* Mobile */
.pnav-hamburger{
    display:none;
    flex-direction:column;
    gap:5px;
    cursor:pointer;
    padding:8px;
    border-radius:10px;
    border:none;
    background:none;
    transition:background .18s;
}
.pnav-hamburger:hover{ background:#f0f2fb; }
.pnav-hamburger span{
    display:block;
    width:22px; height:2px;
    background:#2B2C41;
    border-radius:2px;
    transition:all .22s;
}
.pnav-mobile{
    display:none;
    position:fixed;
    top:66px; left:0; right:0;
    background:rgba(255,255,255,.98);
    backdrop-filter:blur(20px);
    border-bottom:1px solid rgba(36,39,66,.08);
    box-shadow:0 8px 28px rgba(36,39,66,.10);
    padding:14px 20px 18px;
    flex-direction:column;
    gap:4px;
    z-index:999;
    font-family:"Nunito",system-ui,sans-serif;
}
.pnav-mobile.open{ display:flex; }
.pnav-mobile a{
    display:flex;
    align-items:center;
    gap:10px;
    text-decoration:none;
    font-size:15px;
    font-weight:700;
    color:#2B2C41;
    padding:12px 14px;
    border-radius:12px;
    transition:background .14s;
}
.pnav-mobile a:hover{ background:#f0f2fb; }
.pnav-mobile .mob-logout{ color:#b53535; }
.pnav-mobile .mob-logout:hover{ background:#fef2f2; }

@media(max-width:700px){
    .pnav{ padding:0 18px; }
    .pnav-home, .pnav-map-pill, .pnav-sl-pill, .pnav-ocr-pill, .pnav-vc-pill{ display:none; }
    .pnav-hamburger{ display:flex; }
}
@media(max-width:480px){
    .pnav-divider{ display:none; }
}

/* Floating Helpy chatbot button */
.pnav-chat-float{
    position:fixed;
    right:28px;
    bottom:28px;
    width:72px;
    height:72px;
    border-radius:24px;
    background:#404066;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:8px;
    box-shadow:0 18px 38px rgba(64,64,102,.30);
    z-index:9999;
    text-decoration:none;
    overflow:hidden;
    transition:transform .2s ease, box-shadow .2s ease, background .2s ease;
}

.pnav-chat-float:hover{
    transform:translateY(-3px);
    box-shadow:0 22px 45px rgba(64,64,102,.38);
    background:#353b69;
}

.pnav-chat-float img{
    width:100%;
    height:100%;
    object-fit:contain;
    display:block;
}

@media(max-width:700px){
    .pnav-chat-float{
        right:18px;
        bottom:18px;
        width:64px;
        height:64px;
        border-radius:22px;
        padding:7px;
    }
}

</style>

<nav class="pnav" id="pnav">
    <!-- LEFT -->
    <div class="pnav-left">
        <a class="pnav-logo" href="../patient/patient_homepage.php">
            <img src="../pictures/rafiq_logo.png" alt="Rafiq">
        </a>
        <a class="pnav-home <?= $currentPage === 'patient_homepage.php' ? 'active' : '' ?>"
           href="../patient/patient_homepage.php">
            <i class="fa-solid fa-house" style="font-size:13px"></i>
            Home
        </a>
    </div>

    <!-- RIGHT -->
    <div class="pnav-right">

        <a class="pnav-map-pill <?= $currentPage === 'map.php' ? 'active' : '' ?>"
           href="../patient/accessibility_map.php">
            <i class="fa-solid fa-location-dot"></i>
            Map
        </a>

        <a class="pnav-sl-pill <?= $currentPage === 'sign_language.php' ? 'active' : '' ?>"
           href="../general/sign_language.php">
            <i class="fa-solid fa-hands"></i>
            Sign Language AI
        </a>

        <a class="pnav-ocr-pill <?= $currentPage === 'ocr_reader.php' ? 'active' : '' ?>"
           href="../general/ocr_reader.php">
            <i class="fa-solid fa-eye"></i>
            OCR Reader
        </a>

        <a class="pnav-icon <?= $currentPage === 'my_bookings.php' ? 'active' : '' ?>"
           href="../patient/my_bookings.php"
           title="My Bookings">
            <i class="fa-regular fa-calendar-check"></i>
        </a>

        <!-- Bell -->
        <div class="pnav-bell-wrap">
            <button class="pnav-icon" id="pnavBell" title="Notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="pnav-badge" id="pnavBadge"></span>
            </button>
            <div class="pnav-notif-drop" id="pnavDrop">
                <div class="pnav-notif-head">
                    Booking Updates
                    <span class="pnav-notif-clear" id="pnavClear">Clear all</span>
                </div>
                <div class="pnav-notif-scroll" id="pnavList">
                    <div class="pnav-notif-empty">No updates yet</div>
                </div>
            </div>
        </div>

        <div class="pnav-divider"></div>

        <a class="pnav-icon" href="../patient/patient_profile.php" title="Profile">
            <i class="fa-regular fa-circle-user"></i>
        </a>
        <a class="pnav-icon pnav-logout" href="../general/logout.php" title="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>

        <button class="pnav-hamburger" id="pnavHamburger" type="button" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- Mobile menu -->
<div class="pnav-mobile" id="pnavMobile">
    <a href="../patient/patient_homepage.php"><i class="fa-solid fa-house"></i> Home</a>
    <a href="../patient/map.php"><i class="fa-solid fa-location-dot"></i> Map</a>
    <a href="../general/sign_language.php"><i class="fa-solid fa-hands"></i> Sign Language AI</a>
    <a href="../general/ocr_reader.php"><i class="fa-solid fa-eye"></i> OCR Reader</a>
    <a href="../general/voice_companion.php"><i class="fa-solid fa-microphone-lines"></i> Voice Companion</a>
    <a href="../patient/my_bookings.php"><i class="fa-regular fa-calendar-check"></i> My Bookings</a>
    <a href="../patient/patient_profile.php"><i class="fa-regular fa-circle-user"></i> Profile</a>
    <a href="../general/logout.php" class="mob-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<script>
(function(){
    /* ── scroll shadow ── */
    window.addEventListener('scroll', function(){
        document.getElementById('pnav').classList.toggle('scrolled', window.scrollY > 6);
    }, { passive:true });

    /* ── mobile menu ── */
    const ham    = document.getElementById('pnavHamburger');
    const mobile = document.getElementById('pnavMobile');
    ham.addEventListener('click', function(e){
        e.stopPropagation();
        mobile.classList.toggle('open');
    });
    document.addEventListener('click', function(e){
        if(mobile.classList.contains('open') && !mobile.contains(e.target) && !ham.contains(e.target))
            mobile.classList.remove('open');
    });

    /* ── notification bell ── */
    const bell    = document.getElementById('pnavBell');
    const drop    = document.getElementById('pnavDrop');
    const badge   = document.getElementById('pnavBadge');
    const list    = document.getElementById('pnavList');
    const clrBtn  = document.getElementById('pnavClear');

    const statusLabels = {
        pending:'Waiting for provider', accepted:'Provider accepted!',
        arrived:'Provider arrived!', in_trip:'Trip started',
        in_session:'Session started', completed:'Completed',
        declined:'Request declined', cancelled:'Cancelled',
    };
    const serviceIcons = { doctor:'', caregiver:'', interpreter:'', driver:'' };

    let notes      = JSON.parse(localStorage.getItem('rafiq_notif_list')  || '[]');
    let known      = JSON.parse(localStorage.getItem('rafiq_notif_states') || '{}');
    let unread     = parseInt(localStorage.getItem('rafiq_notif_unread')   || '0', 10);

    function save(){
        localStorage.setItem('rafiq_notif_list',   JSON.stringify(notes.slice(0,40)));
        localStorage.setItem('rafiq_notif_states', JSON.stringify(known));
        localStorage.setItem('rafiq_notif_unread', String(unread));
    }

    function renderBadge(){
        if(unread > 0){ badge.textContent = unread > 9 ? '9+' : unread; badge.classList.add('show'); }
        else badge.classList.remove('show');
    }

    function renderList(){
        if(!notes.length){ list.innerHTML = '<div class="pnav-notif-empty">No updates yet</div>'; return; }
        list.innerHTML = notes.slice(0,15).map(n => `
            <a class="pnav-notif-item" href="../patient/booking_status.php?booking_id=${n.booking_id}">
                <div class="pnav-notif-dot ${n.read?'read':''}"></div>
                <div>
                    <div class="pnav-notif-text">${n.service} — ${n.label}</div>
                </div>
            </a>`).join('');
    }

    function poll(){
        const path = window.location.pathname;
        const base = path.includes('/patient/') ? '../general/notifications_api.php'
                   : path.includes('/providers/') ? '../../general/notifications_api.php'
                   : 'general/notifications_api.php';
        fetch(base, { cache:'no-store' })
            .then(r => r.json())
            .then(data => {
                (data.bookings || []).forEach(b => {
                    const key = String(b.booking_id);
                    const ns  = (b.status || '').toLowerCase().trim();
                    const os  = known[key];
                    if(os === undefined){ known[key] = ns; return; }
                    if(os !== ns){
                        known[key] = ns;
                        notes.unshift({
                            booking_id: b.booking_id,
                            service: b.service_type || 'Booking',
                            icon: '',
                            label: statusLabels[ns] || ns,
                            read: false
                        });
                        unread++;
                    }
                });
                save(); renderBadge(); renderList();
            }).catch(()=>{});
        setTimeout(poll, 8000);
    }

    bell.addEventListener('click', function(e){
        e.stopPropagation();
        drop.classList.toggle('open');
        if(drop.classList.contains('open')){
            unread = 0;
            notes  = notes.map(n => ({...n, read:true}));
            save(); renderBadge(); renderList();
        }
    });
    clrBtn.addEventListener('click', function(){
        notes = []; known = {}; unread = 0;
        save(); renderBadge(); renderList();
    });
    document.addEventListener('click', function(e){
        if(!bell.contains(e.target) && !drop.contains(e.target))
            drop.classList.remove('open');
    });

    renderBadge(); renderList();
    setTimeout(poll, 2000);
})();
</script>
