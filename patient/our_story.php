<?php
session_start();

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$team = [
    [
        'name' => 'Mariam Anwar',
        'role' => 'CEO',
        'photo' => '../pictures/mariam_anwar.jpeg'
    ],
    [
        'name' => 'Nouran Ayman',
        'role' => 'Data Analyst',
        'photo' => '../pictures/nouran.jpeg'
    ],
    [
        'name' => 'Maryam Elkilany',
        'role' => 'Web Developer',
        'photo' => '../pictures/maryam_elkilany.jpeg'
    ],
    [
        'name' => 'Jomana Ahmed',
        'role' => 'Graphic Designer',
        'photo' => '../pictures/jomana.jpeg'
    ],
    [
        'name' => 'Rawan Ehab',
        'role' => 'Operations Director',
        'photo' => '../pictures/rawan.jpeg'
    ],
    [
        'name' => 'Farida Shaarawy',
        'role' => 'Quality & Risk Director',
        'photo' => '../pictures/farida.jpeg'
    ],
    [
        'name' => 'Abdelrahman Mohamed',
        'role' => 'CFO',
        'photo' => '../pictures/abdelrahman.jpeg'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Our Story — Rafiq</title>

<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:'Manrope',sans-serif;
    background:#f6f8fd;
    color:#222335;
}

.story-page{
    width:min(1060px,calc(100% - 32px));
    margin:0 auto;
    padding-bottom:72px;
}

/* ── HERO ── */
.story-hero{
    position:relative;
    overflow:hidden;
    border-radius:0 0 40px 40px;
    background:linear-gradient(135deg,#20233c 0%,#353b69 52%,#6470d2 100%);
    padding:72px 48px 80px;
    text-align:center;
    color:#fff;
    margin-bottom:60px;
}

.story-hero::before{
    content:"";
    position:absolute;
    width:360px;
    height:360px;
    border-radius:50%;
    right:-100px;
    top:-120px;
    background:rgba(255,255,255,.08);
}

.story-hero::after{
    content:"";
    position:absolute;
    width:220px;
    height:220px;
    border-radius:44px;
    left:-60px;
    bottom:-90px;
    background:rgba(255,255,255,.05);
    transform:rotate(20deg);
}

.story-hero-inner{
    position:relative;
    z-index:2;
    max-width:680px;
    margin:0 auto;
}

.hero-eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:9px 18px;
    border-radius:99px;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.16);
    font-size:12px;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
    margin-bottom:22px;
}

.story-hero h1{
    font-size:52px;
    font-weight:800;
    letter-spacing:-2px;
    line-height:1.08;
    margin-bottom:18px;
}

.story-hero p{
    color:rgba(255,255,255,.82);
    font-size:16px;
    line-height:1.9;
    font-weight:600;
}

/* ── MISSION ── */
.mission-section{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:24px;
    margin-bottom:56px;
}

.mission-card{
    padding:36px;
    border-radius:28px;
    background:#fff;
    border:1px solid rgba(36,39,66,.07);
    box-shadow:0 16px 36px rgba(36,39,66,.07);
}

.mission-card.accent{
    background:linear-gradient(135deg,#353b69,#6470d2);
    color:#fff;
}

.mission-icon{
    width:52px;
    height:52px;
    border-radius:18px;
    display:grid;
    place-items:center;
    font-size:24px;
    background:rgba(75,79,131,.10);
    margin-bottom:20px;
}

.mission-card.accent .mission-icon{
    background:rgba(255,255,255,.14);
}

.mission-card h2{
    font-size:22px;
    font-weight:800;
    margin-bottom:12px;
    letter-spacing:-.4px;
}

.mission-card p{
    font-size:14px;
    line-height:1.9;
    color:#6b7188;
    font-weight:600;
}

.mission-card.accent p{
    color:rgba(255,255,255,.82);
}

/* ── VALUES ── */
.values-section{
    margin-bottom:56px;
}

.section-label{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 16px;
    border-radius:99px;
    background:#eef2ff;
    color:#4b4f83;
    font-size:12px;
    font-weight:800;
    letter-spacing:.06em;
    text-transform:uppercase;
    margin-bottom:20px;
}

.section-label.dark{
    background:#2B2C41;
    color:#fff;
}

.values-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:16px;
}

.value-item{
    padding:28px 24px;
    border-radius:24px;
    background:#fff;
    border:1px solid rgba(36,39,66,.07);
    box-shadow:0 10px 26px rgba(36,39,66,.06);
    transition:transform .2s,box-shadow .2s;
}

.value-item:hover{
    transform:translateY(-4px);
    box-shadow:0 20px 42px rgba(36,39,66,.10);
}

.value-num{
    font-size:32px;
    font-weight:800;
    color:#e8ebf5;
    margin-bottom:10px;
    font-family:'Manrope',sans-serif;
}

.value-item h3{
    font-size:17px;
    font-weight:800;
    color:#2B2C41;
    margin-bottom:8px;
}

.value-item p{
    font-size:13px;
    line-height:1.8;
    color:#6b7188;
    font-weight:600;
}

/* ── TEAM ── */
.team-section{
    margin-bottom:56px;
}

.team-header{
    margin-bottom:32px;
}

.team-header h2{
    font-size:34px;
    font-weight:800;
    letter-spacing:-.8px;
    color:#2B2C41;
    margin-top:10px;
}

.team-header p{
    color:#6b7188;
    font-size:15px;
    line-height:1.8;
    margin-top:8px;
    max-width:520px;
}

.team-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:24px;
}

/* Mariam card takes 2 cards space */
.team-card-wide{
    grid-column:span 2;
}

.team-card{
    background:#fff;
    border-radius:24px;
    padding:28px 20px;
    text-align:center;
    border:1px solid rgba(36,39,66,.07);
    box-shadow:0 12px 28px rgba(36,39,66,.07);
    transition:transform .22s,box-shadow .22s;
    min-height:280px;
}

.team-card:hover{
    transform:translateY(-6px);
    box-shadow:0 24px 48px rgba(36,39,66,.12);
}

.team-photo{
    width:118px;
    height:118px;
    border-radius:30px;
    overflow:hidden;
    margin:0 auto 18px;
    background:#eef2ff;
    box-shadow:0 10px 24px rgba(53,59,105,.18);
}

.team-photo-big{
    width:130px;
    height:130px;
    border-radius:34px;
}

.team-photo img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.team-name{
    font-size:16px;
    font-weight:800;
    color:#2B2C41;
    margin-bottom:6px;
}

.team-card-wide .team-name{
    font-size:18px;
}

.team-role{
    display:inline-block;
    padding:6px 12px;
    border-radius:99px;
    background:#eef2ff;
    color:#4b4f83;
    font-size:11px;
    font-weight:800;
}

/* responsive */
@media(max-width:900px){
    .team-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .team-card-wide{
        grid-column:span 2;
    }
}

@media(max-width:600px){
    .team-grid{
        grid-template-columns:1fr;
    }

    .team-card-wide{
        grid-column:span 1;
    }
}

/* ── INSTAGRAM CTA ── */
.instagram-cta{
    background:linear-gradient(135deg,#2B2C41,#404066 55%,#6d73c8);
    border-radius:28px;
    padding:42px 36px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:24px;
    flex-wrap:wrap;
}

.instagram-cta .cta-left h2{
    font-size:26px;
    font-weight:800;
    color:#fff;
    margin-bottom:8px;
}

.instagram-cta .cta-left p{
    color:rgba(255,255,255,.85);
    font-size:14px;
    line-height:1.8;
}

.ig-btn{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:14px 26px;
    border-radius:14px;
    background:rgba(255,255,255,.18);
    border:1px solid rgba(255,255,255,.28);
    color:#fff;
    font-size:15px;
    font-weight:800;
    text-decoration:none;
    transition:background .18s;
    white-space:nowrap;
}

.ig-btn:hover{
    background:rgba(255,255,255,.28);
}

/* ── ANIMATIONS ── */
.fade-up{
    opacity:0;
    transform:translateY(24px);
    transition:opacity .55s cubic-bezier(.22,.68,0,1.2),transform .55s cubic-bezier(.22,.68,0,1.2);
}

.fade-up.visible{
    opacity:1;
    transform:translateY(0);
}

/* ── RESPONSIVE ── */
@media(max-width:900px){
    .mission-section{
        grid-template-columns:1fr;
    }

    .values-grid{
        grid-template-columns:1fr 1fr;
    }

    .team-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .story-hero h1{
        font-size:38px;
    }
}

@media(max-width:600px){
    .story-hero{
        padding:52px 24px 60px;
        border-radius:0 0 28px 28px;
    }

    .story-hero h1{
        font-size:30px;
        letter-spacing:-1px;
    }

    .values-grid{
        grid-template-columns:1fr;
    }

    .team-grid{
        grid-template-columns:1fr;
    }

    .instagram-cta{
        flex-direction:column;
        text-align:center;
    }
}
</style>
</head>

<body>

<?php include '../general/nav_patient.php'; ?>

<div class="story-hero">
    <div class="story-hero-inner">
        <div class="hero-eyebrow">
            <i class="fa-solid fa-seedling"></i>
            Our Story
        </div>
        <h1>Built for people.<br>Driven by purpose.</h1>
        <p>
            Rafiq was founded to make daily life more accessible for people with disabilities —
            connecting them with the right people and the right places, easily and with dignity.
        </p>
    </div>
</div>

<div class="story-page">

    <!-- Mission & Vision -->
    <div class="mission-section fade-up">
        <div class="mission-card accent">
            <h2>Our Mission</h2>
            <p>
                To build a connected ecosystem that combines community-driven data with accessible services, fostering independence and dismantling the daily barriers to social inclusion.
            </p>
        </div>

        <div class="mission-card">
            <h2>Our Vision</h2>
            <p>
                To eliminate the disconnect between individuals and the built environment, creating a world that is truly open for everyone.
            </p>
        </div>
    </div>

    <!-- Values -->
    <div class="values-section fade-up">
        <div class="section-label">
            <i class="fa-solid fa-star"></i>
            What we stand for
        </div>

        <div class="values-grid">
            <div class="value-item">
                <div class="value-num">01</div>
                <h3>Dignity First</h3>
                <p>
                    Every feature we build starts with respect. Our users deserve services that treat
                    them as capable, whole individuals — not as a problem to be solved.
                </p>
            </div>

            <div class="value-item">
                <div class="value-num">02</div>
                <h3>Trust & Safety</h3>
                <p>
                    Every provider on Rafiq is reviewed and approved. We verify credentials and
                    monitor feedback to keep our community safe and accountable.
                </p>
            </div>

            <div class="value-item">
                <div class="value-num">03</div>
                <h3>Simplicity</h3>
                <p>
                    Accessibility should be accessible. We design every screen and every flow to be
                    as clear and simple as possible — no confusion, no frustration.
                </p>
            </div>

            <div class="value-item">
                <div class="value-num">04</div>
                <h3>Community</h3>
                <p>
                    We grow together. Patients, providers, and the Rafiq team form one community
                    working toward the same goal — a more inclusive Egypt.
                </p>
            </div>

            <div class="value-item">
                <div class="value-num">05</div>
                <h3>Continuous Improvement</h3>
                <p>
                    We listen, we learn, and we build. Every piece of feedback shapes our next
                    feature so Rafiq gets better for everyone who uses it.
                </p>
            </div>

            <div class="value-item">
                <div class="value-num">06</div>
                <h3>Impact Over Profit</h3>
                <p>
                    Our measure of success is the lives we improve. Revenue enables our mission —
                    it doesn't define it. We reinvest in people, not just in products.
                </p>
            </div>
        </div>
    </div>

<!-- Team -->
<div class="team-section fade-up">
    <div class="team-header">
        <div class="section-label dark">
            <i class="fa-solid fa-users"></i>
            The team
        </div>
        <h2>Meet the people behind Rafiq</h2>
        <p>
            Seven passionate people who built this platform from scratch because they believe everyone deserves access.
        </p>
    </div>

    <div class="team-grid">
        <?php foreach ($team as $index => $member): ?>
            <div class="team-card <?= $index === 0 ? 'team-card-wide' : '' ?>">
                <div class="team-photo <?= $index === 0 ? 'team-photo-big' : '' ?>">
                    <img src="<?= h($member['photo']) ?>" alt="<?= h($member['name']) ?>">
                </div>

                <div class="team-name"><?= h($member['name']) ?></div>
                <span class="team-role"><?= h($member['role']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

    <!-- Instagram CTA -->
    <div class="instagram-cta fade-up">
        <div class="cta-left">
            <h2>Follow us on Instagram</h2>
            <p>
                Stay updated with news, accessibility tips, and behind-the-scenes from the Rafiq team.
            </p>
        </div>

        <a class="ig-btn"
           href="https://www.instagram.com/rafiq_eg?igsh=OGF4OXJ6eHJ1bHpo&utm_source=qr"
           target="_blank"
           rel="noopener">
            <i class="fa-brands fa-instagram fa-lg"></i>
            @rafiq_eg
        </a>
    </div>

</div>

<script>
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            observer.unobserve(e.target);
        }
    });
}, {
    threshold: 0.1,
    rootMargin:'0px 0px -40px 0px'
});

document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
</script>

<?php include '../general/footer.php'; ?>

</body>
</html>