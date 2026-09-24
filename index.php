<?php
/* ============================================================
   NGEMIL DIMSUM — Landing Page (index.php)
   Semua styling & struktur asli tetap sama.
   Yang ditambahkan hanya blok PHP di atas + tombol login dinamis.
   ============================================================ */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$loggedIn = isLogin();
$namaUser = $_SESSION['nama'] ?? '';
$roleUser = $_SESSION['role'] ?? '';
$userFoto = null;

// Ambil foto profil kalau sudah login
if ($loggedIn && isset($_SESSION['user_id'])) {
    $stmtUser = $koneksi->prepare("SELECT foto_profil FROM users WHERE id = ?");
    $stmtUser->bind_param('i', $_SESSION['user_id']);
    $stmtUser->execute();
    $row = $stmtUser->get_result()->fetch_assoc();
    $userFoto = $row['foto_profil'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Ngemil Dimsum — Dimsum Premium untuk Setiap Momen</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">

<style>

*{ margin:0; padding:0; box-sizing:border-box; }
html{ scroll-behavior:smooth; }
body{
    font-family:"DM Sans",sans-serif;
    background:#f8f5ef;
    color:#302521;
    overflow-x:hidden;
}
img{ display:block; max-width:100%; }
a{ text-decoration:none; color:inherit; }

:root{
    --cream:#f8f5ef;
    --white:#fff;
    --black:#302521;
    --gray:#746a64;
    --red:#8b2632;
    --red-dark:#651b25;
    --gold:#c7a06b;
    --border:rgba(48,37,33,.13);
}

/* =========================
   LOADER
========================= */
.loader{
    position:fixed; inset:0; background:var(--cream); z-index:9999;
    display:flex; align-items:center; justify-content:center;
    transition:opacity .6s cubic-bezier(0.16, 1, 0.3, 1), visibility .6s;
}
.loader.hide{ opacity:0; visibility:hidden; pointer-events:none; }
.loader-logo{
    font-family:"Playfair Display",serif;
    font-size:42px; color:var(--red);
    animation:pulse 1.8s ease-in-out infinite;
}
.loader-line{
    width:120px; height:2px; background:var(--gold);
    margin:15px auto 0;
    animation:loaderLine 1.8s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}
@keyframes pulse{ 0%, 100%{ transform:scale(1); } 50%{ transform:scale(1.05); } }
@keyframes loaderLine{
    0%{ transform:scaleX(0); transform-origin:left; }
    50%{ transform:scaleX(1); transform-origin:left; }
    50.1%{ transform:scaleX(1); transform-origin:right; }
    100%{ transform:scaleX(0); transform-origin:right; }
}

/* =========================
   CONTAINER
========================= */
.container{ width:min(1180px,90%); margin:auto; }

/* =========================
   NAVBAR
========================= */
header{
    position:fixed; top:0; left:0; width:100%; z-index:1000;
    transition:background .4s ease, backdrop-filter .4s ease, border-bottom .4s ease;
}
header.scrolled{
    background:rgba(248,245,239,.92);
    backdrop-filter:blur(18px);
    -webkit-backdrop-filter:blur(18px);
    border-bottom:1px solid var(--border);
}
.navbar{
    height:85px;
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.logo{
    font-family:"Playfair Display",serif;
    font-size:28px; color:var(--red);
}
.logo small{
    display:block;
    font-family:"DM Sans",sans-serif;
    font-size:8px; letter-spacing:3px;
    text-transform:uppercase; color:var(--gray);
}
.nav-links{
    display:flex; gap:32px;
    font-size:13px; font-weight:600;
}
.nav-links a{ position:relative; }
.nav-links a::after{
    content:""; position:absolute; left:0; bottom:-5px;
    width:0; height:1px; background:var(--red);
    transition:width .3s cubic-bezier(0.16, 1, 0.3, 1);
}
.nav-links a:hover::after{ width:100%; }
.nav-button{
    background:var(--red); color:#fff;
    padding:12px 20px; border-radius:100px;
    font-size:12px; font-weight:700;
    transition:background .3s ease, transform .3s cubic-bezier(0.16, 1, 0.3, 1);
}
.nav-button:hover{
    background:var(--red-dark);
    transform:translateY(-3px);
}
.menu-button{
    display:none; border:0; background:none;
    font-size:25px; cursor:pointer;
}

/* =========================
   USER MENU (BARU — muncul kalau sudah login)
========================= */
.user-menu{
    display:flex;
    align-items:center;
    gap:10px;
}
.user-info{
    display:flex;
    align-items:center;
    gap:8px;
    padding:6px 14px 6px 6px;
    background:#fff;
    border:1px solid var(--border);
    border-radius:100px;
}
.user-avatar{
    width:28px; height:28px;
    border-radius:50%;
    background:var(--red);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    font-weight:700;
}
.user-name{
    font-size:12px;
    font-weight:600;
    color:var(--black);
}
.user-role{
    font-size:9px;
    color:var(--gray);
    text-transform:uppercase;
    letter-spacing:1px;
}
.user-logout{
    padding:10px 16px;
    border-radius:100px;
    font-size:11px;
    font-weight:700;
    color:var(--red);
    border:1px solid var(--border);
    background:#fff;
    transition:all .3s ease;
}
.user-logout:hover{
    background:var(--red);
    color:#fff;
    border-color:var(--red);
}
.user-dashboard{
    padding:10px 16px;
    border-radius:100px;
    font-size:11px;
    font-weight:700;
    background:var(--red);
    color:#fff;
    transition:all .3s ease;
}
.user-dashboard:hover{
    background:var(--red-dark);
    transform:translateY(-2px);
}

/* =========================
   HERO
========================= */
.hero{
    min-height:100vh;
    padding-top:130px;
    padding-bottom:90px;
    display:grid;
    grid-template-columns:1fr 1fr;
    align-items:center;
    gap:70px;
}
.hero-content{
    opacity:0; transform:translateY(30px);
    animation:heroText 1s cubic-bezier(0.16, 1, 0.3, 1) .3s forwards;
}
@keyframes heroText{
    to{ opacity:1; transform:translateY(0); }
}
.eyebrow{
    color:var(--red);
    font-size:11px; font-weight:700;
    letter-spacing:3px; text-transform:uppercase;
    margin-bottom:20px;
}
.hero h1{
    font-family:"Playfair Display",serif;
    font-size:clamp(50px,6vw,78px);
    font-weight:400; line-height:1.03;
    letter-spacing:-2px; margin-bottom:25px;
}
.hero-description{
    max-width:550px; color:var(--gray);
    font-size:15px; line-height:1.9;
    margin-bottom:32px;
}
.hero-buttons{
    display:flex; gap:12px; flex-wrap:wrap;
}
.button{
    padding:14px 22px;
    border-radius:100px;
    font-size:12px; font-weight:700;
    transition:background .3s ease, transform .3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .3s ease;
}
.button-primary{
    background:var(--red); color:#fff;
}
.button-primary:hover{
    background:var(--red-dark);
    transform:translateY(-4px);
    box-shadow:0 15px 30px rgba(139,38,50,.2);
}
.button-outline{ border:1px solid var(--border); }
.button-outline:hover{ background:#fff; transform:translateY(-4px); }

/* =========================
   HERO IMAGE / LOGO PNG
========================= */
.hero-image{
    position:relative;
    display:flex;
    justify-content:center;
    align-items:center;
    opacity:0; transform:scale(.95);
    animation:heroImage 1.1s cubic-bezier(0.16, 1, 0.3, 1) .1s forwards;
}
@keyframes heroImage{
    to{ opacity:1; transform:scale(1); }
}
.hero-logo{
    width:min(520px,100%);
    aspect-ratio:1/1;
    object-fit:contain;
    position:relative; z-index:2;
    animation:floating 5s ease-in-out infinite;
    filter:drop-shadow(0 25px 35px rgba(60,30,20,.18));
    transition:transform .5s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: transform;
}
.hero-logo:hover{ transform:scale(1.04) rotate(1deg); }
@keyframes floating{
    0%,100%{ transform:translateY(0); }
    50%{ transform:translateY(-15px); }
}
.hero-image::before{
    content:"";
    position:absolute;
    width:85%; aspect-ratio:1;
    border-radius:50%;
    background:var(--red);
    opacity:.06;
    animation:rotateGlow 15s linear infinite;
}
@keyframes rotateGlow{
    from{ transform:rotate(0); }
    to{ transform:rotate(360deg); }
}

/* =========================
   TRUST
========================= */
.trust{
    background:#fff;
    padding:100px 0;
    border-top:1px solid var(--border);
    border-bottom:1px solid var(--border);
}
.section-title{
    text-align:center;
    max-width:680px;
    margin:0 auto 55px;
}
.section-title h2{
    font-family:"Playfair Display",serif;
    font-size:48px;
    font-weight:400;
    line-height:1.1;
    margin-bottom:15px;
}
.section-title p{
    color:var(--gray);
    font-size:14px;
}
.trust-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
}
.trust-card{
    padding:35px 20px;
    border:1px solid var(--border);
    text-align:center;
    background:#fff;
    transition:transform .4s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .4s cubic-bezier(0.16, 1, 0.3, 1);
}
.trust-card:hover{
    transform:translateY(-10px);
    box-shadow:0 20px 45px rgba(50,30,20,.09);
}
.trust-icon{
    color:var(--red);
    font-size:25px;
    margin-bottom:15px;
}
.trust-card strong{
    display:block;
    font-size:13px;
    margin-bottom:7px;
}
.trust-card span{
    color:var(--gray);
    font-size:11px;
}

/* =========================
   REVIEW
========================= */
.reviews{
    margin-top:45px;
    display:flex; gap:15px;
    overflow-x:auto;
    scrollbar-width:none;
    scroll-behavior: smooth;
}
.reviews::-webkit-scrollbar{ display:none; }
.review{
    min-width:calc(20% - 12px);
    padding:15px;
    border:1px solid var(--border);
    background:#faf9f6;
    font-size:11px;
    transition:transform .3s cubic-bezier(0.16, 1, 0.3, 1);
}
.review:hover{ transform:translateY(-5px); }
.review-stars{ color:var(--gold); margin-bottom:8px; }

/* =========================
   CLIENTS
========================= */
.clients{ padding:95px 0; }
.client-grid{
    display:grid;
    grid-template-columns:repeat(6,1fr);
    gap:12px;
}
.client{
    height:85px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--border);
    background:rgba(255,255,255,.35);
    color:#766d67;
    font-size:13px;
    font-weight:700;
    transition:background .35s ease, color .35s ease, transform .35s cubic-bezier(0.16, 1, 0.3, 1);
}
.client:hover{
    background:#fff;
    color:var(--red);
    transform:translateY(-5px);
}

/* =========================
   STORY
========================= */
.story{
    background:#fff;
    padding:110px 0;
}
.story-grid{
    display:grid;
    grid-template-columns:.95fr 1.05fr;
    gap:80px;
    align-items:center;
}
.story-image{
    width:100%; height:580px;
    object-fit:cover;
    border-radius:4px;
    box-shadow:0 30px 60px rgba(50,30,20,.12);
    transition:transform .6s cubic-bezier(0.16, 1, 0.3, 1);
    animation:storyFloat 6s ease-in-out infinite;
}
.story-image:hover{ transform:scale(.98); }
@keyframes storyFloat{
    0%,100%{ transform:translateY(0); }
    50%{ transform:translateY(-8px); }
}
.story-content h2{
    font-family:"Playfair Display",serif;
    font-size:53px;
    font-weight:400;
    line-height:1.08;
    margin-bottom:25px;
}
.story-content p{
    color:var(--gray);
    font-size:14px;
    line-height:1.9;
    margin-bottom:18px;
}

/* =========================
   GALLERY
========================= */
.gallery{ padding:110px 0; }
.gallery-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    grid-auto-rows:260px;
    gap:12px;
}
.gallery-item{
    width:100%; height:100%;
    object-fit:cover;
    border-radius:3px;
    cursor:pointer;
    transition:transform .6s cubic-bezier(0.16, 1, 0.3, 1), filter .6s ease, box-shadow .6s ease;
    filter:saturate(.95);
    will-change: transform;
}
.gallery-item:hover{
    transform:scale(1.025);
    filter:saturate(1.08);
    box-shadow:0 20px 40px rgba(40,25,20,.15);
}
.gallery-item.big{ grid-column:span 2; grid-row:span 2; }
.gallery-item.wide{ grid-column:span 2; }

/* =========================
   CTA
========================= */
.cta{ padding:30px 0 110px; }
.cta-box{
    background:var(--red);
    color:#fff;
    padding:75px 8%;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:30px;
    position:relative;
    overflow:hidden;
}
.cta-box::before{
    content:"";
    position:absolute;
    width:350px; height:350px;
    border-radius:50%;
    border:1px solid rgba(255,255,255,.1);
    right:-100px; top:-120px;
    animation:ctaRotate 15s linear infinite;
}
@keyframes ctaRotate{
    from{ transform:rotate(0); }
    to{ transform:rotate(360deg); }
}
.cta-box h2{
    font-family:"Playfair Display",serif;
    font-size:50px;
    font-weight:400;
    line-height:1.05;
    margin-top:10px;
}
.cta-box p{ opacity:.8; font-size:14px; margin-top:12px; }
.cta-button{
    position:relative;
    z-index:2;
    white-space:nowrap;
    padding:15px 24px;
    border-radius:100px;
    background:#fff;
    color:var(--red);
    font-size:12px;
    font-weight:700;
    transition:transform .3s cubic-bezier(0.16, 1, 0.3, 1);
}
.cta-button:hover{ transform:translateY(-5px) scale(1.03); }

/* =========================
   FOOTER
========================= */
footer{
    background:#30221e;
    color:#fff;
    padding:70px 0 25px;
}
.footer-grid{
    display:grid;
    grid-template-columns:1.5fr .8fr .8fr 1fr;
    gap:50px;
}
.footer-logo{
    font-family:"Playfair Display",serif;
    font-size:30px;
    margin-bottom:18px;
}
.footer p, .footer a{
    color:#b9aaa3;
    font-size:12px;
    line-height:1.8;
}
.footer h4{
    color:#d3b27d;
    font-size:11px;
    letter-spacing:2px;
    text-transform:uppercase;
    margin-bottom:18px;
}
.footer a{
    display:block;
    margin-bottom:7px;
    transition:color .25s ease, transform .25s cubic-bezier(0.16, 1, 0.3, 1);
}
.footer a:hover{ color:#fff; transform:translateX(4px); }
.copyright{
    border-top:1px solid rgba(255,255,255,.1);
    margin-top:50px;
    padding-top:20px;
    color:#83756f;
    font-size:10px;
}

/* =========================
   REVEAL ANIMATION
========================= */
.reveal{
    opacity:0;
    transform:translateY(35px);
    transition:opacity .9s cubic-bezier(0.16, 1, 0.3, 1), transform .9s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}
.reveal.active{ opacity:1; transform:translateY(0); }
.delay-1{ transition-delay:.1s; }
.delay-2{ transition-delay:.2s; }
.delay-3{ transition-delay:.3s; }

/* =========================
   MOBILE
========================= */
@media(max-width:900px){
    .nav-links{
        display:flex;
        flex-direction:column;
        position:absolute;
        top:72px;
        left:0;
        width:100%;
        padding:25px;
        background:rgba(248,245,239,.98);
        backdrop-filter:blur(18px);
        gap:18px;
        box-shadow:0 15px 30px rgba(0,0,0,0.05);
        opacity:0;
        visibility:hidden;
        transform:translateY(-10px);
        transition:opacity .3s ease, transform .3s ease, visibility .3s;
    }
    .nav-links.show{
        opacity:1;
        visibility:visible;
        transform:translateY(0);
    }
    .nav-button{ display:none; }
    .menu-button{ display:block; }
    .user-menu{ display:none; } /* sembunyikan di mobile, opsional */
    .hero{ grid-template-columns:1fr; padding-top:120px; gap:50px; }
    .hero-image{ order:-1; }
    .hero-logo{ width:80%; }
    .trust-grid{ grid-template-columns:1fr 1fr; }
    .client-grid{ grid-template-columns:repeat(3,1fr); }
    .story-grid{ grid-template-columns:1fr; }
    .story-image{ height:430px; }
    .gallery-grid{ grid-template-columns:1fr 1fr; }
    .footer-grid{ grid-template-columns:1fr 1fr; }
}

@media(max-width:600px){
    .navbar{ height:72px; }
    .hero h1{ font-size:48px; }
    .section-title h2{ font-size:38px; }
    .story-content h2{ font-size:40px; }
    .trust-grid{ grid-template-columns:1fr; }
    .client-grid{ grid-template-columns:1fr 1fr; }
    .review{ min-width:75%; }
    .gallery-grid{ grid-template-columns:1fr 1fr; grid-auto-rows:170px; }
    .gallery-item.big{ grid-column:span 2; grid-row:span 2; }
    .gallery-item.wide{ grid-column:span 2; }
    .cta-box{ display:block; padding:55px 30px; }
    .cta-box h2{ font-size:40px; }
    .cta-button{ display:inline-block; margin-top:25px; }
    .footer-grid{ grid-template-columns:1fr; }
}
.user-avatar{
    width:28px; height:28px;
    border-radius:50%;
    background:var(--red);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    font-weight:700;
    overflow:hidden;      /* ← TAMBAH */
    flex-shrink:0;        /* ← TAMBAH */
}
.user-avatar img{          /* ← TAMBAH block baru */
    width:100%;
    height:100%;
    object-fit:cover;
}
</style>
</head>


<body>

<!-- =========================
     LOADER
========================= -->
<div class="loader" id="loader">
    <div>
        <div class="loader-logo">NGEMIL DIMSUM</div>
        <div class="loader-line"></div>
    </div>
</div>

<!-- =========================
     NAVBAR
========================= -->
<header id="header">
<div class="container navbar">
    <a href="#home" class="logo">
        NGEMIL
        <small>DIMSUM</small>
    </a>

    <nav class="nav-links" id="navLinks">
        <a href="#home" onclick="closeMobileMenu()">Home</a>
        <a href="#story" onclick="closeMobileMenu()">Tentang</a>
        <a href="#clients" onclick="closeMobileMenu()">Clients</a>
        <a href="#gallery" onclick="closeMobileMenu()">Momen</a>
        <a href="#contact" onclick="closeMobileMenu()">Kontak</a>
    </nav>

    <?php if ($loggedIn): ?>
        <!-- ========== SUDAH LOGIN ========== -->
        <div class="user-menu">
            <div class="user-info">
<div class="user-avatar">
    <?php if ($userFoto && file_exists(__DIR__.'/'.$userFoto)): ?>
        <img src="<?= e($userFoto) ?>?v=<?= time() ?>" alt="Avatar">
    <?php else: ?>
        <?= strtoupper(substr($namaUser, 0, 1)) ?>
    <?php endif; ?>
</div>                <div>
                    <div class="user-name"><?= e($namaUser) ?></div>
                    <div class="user-role"><?= $roleUser === 'admin' ? 'Admin' : 'Pelanggan' ?></div>
                </div>
            </div>
            <?php if ($roleUser === 'admin'): ?>
                <a href="admin/index.php" class="user-dashboard">Dashboard</a>
            <?php else: ?>
                <a href="dashboard.php" class="user-dashboard">Pesan Sekarang</a>
            <?php endif; ?>
            <a href="logout.php" class="user-logout">Logout</a>
        </div>
    <?php else: ?>
        <!-- ========== BELUM LOGIN ========== -->
        <a href="login.php" class="nav-button">Login</a>
    <?php endif; ?>

    <button class="menu-button" onclick="toggleMobileMenu()">☰</button>
</div>
</header>

<!-- =========================
     HERO
========================= -->
<main id="home">
<section class="container hero">

<div class="hero-content">
    <div class="eyebrow">Homemade • Halal • 90% Daging Ayam</div>
    <h1>Dimsum Premium untuk Setiap Momen</h1>
    <p class="hero-description">
        Nikmati dimsum homemade dengan bahan berkualitas, rasa yang lembut dan penuh cita rasa.
        Dibuat dengan perhatian untuk menemani berbagai momen spesialmu.
    </p>
    <div class="hero-buttons">
        <a href="https://wa.me/6285171000063" target="_blank" class="button button-primary">
            Pesan via WhatsApp →
        </a>
        <a href="#gallery" class="button button-outline">Lihat Momen</a>
    </div>
</div>

<div class="hero-image">
    <img src="asse/lo1.png" alt="Logo Dimsum" class="hero-logo">
</div>

</section>

<!-- =========================
     TRUST
========================= -->
<section class="trust">
<div class="container">
<div class="section-title reveal">
    <div class="eyebrow">Kepercayaan pelanggan</div>
    <h2>Rasa yang dipercaya, kualitas yang terjaga.</h2>
    <p>Kami menjaga kualitas dari dapur sampai ke momen spesialmu.</p>
</div>

<div class="trust-grid">
    <div class="trust-card reveal">
        <div class="trust-icon">✓</div>
        <strong>Halal Certified Resmi</strong>
        <span>Produk halal</span>
    </div>
    <div class="trust-card reveal delay-1">
        <div class="trust-icon">★</div>
        <strong>5.0 ★ Google Rating</strong>
        <span>Dipercaya pelanggan</span>
    </div>
    <div class="trust-card reveal delay-2">
        <div class="trust-icon">◉</div>
        <strong>RRI Liputan</strong>
        <span>Perjalanan ngemil dimsum</span>
    </div>
    <div class="trust-card reveal delay-3">
        <div class="trust-icon">5+</div>
        <strong>5+ Tahun Perjalanan</strong>
        <span>Dari dapur rumahan</span>
    </div>
</div>

<div class="reviews">
    <div class="review"><div class="review-stars">★★★★★</div>Rasa dan tampilannya cocok untuk acara.</div>
    <div class="review"><div class="review-stars">★★★★★</div>Dimsumnya fresh dan packaging-nya rapi.</div>
    <div class="review"><div class="review-stars">★★★★★</div>Cocok untuk hadiah dan perayaan.</div>
    <div class="review"><div class="review-stars">★★★★★</div>Pelayanan cepat dan produknya menarik.</div>
    <div class="review"><div class="review-stars">★★★★★</div>Cocok untuk acara keluarga.</div>
</div>
</div>
</section>

<!-- =========================
     CLIENTS
========================= -->
<section class="clients" id="clients">
<div class="container">
<div class="section-title reveal">
    <div class="eyebrow">Clients Ngemil Dimsum</div>
    <h2>Dipercaya untuk berbagai momen.</h2>
    <p>Dari kebutuhan kantor hingga berbagai acara dan institusi.</p>
</div>

<div class="client-grid">
    <div class="client reveal">BCA</div>
    <div class="client reveal delay-1">BRI</div>
    <div class="client reveal delay-2">BSI</div>
    <div class="client reveal delay-3">BTN</div>
    <div class="client reveal">DBS</div>
    <div class="client reveal delay-1">PERTAMINA</div>
    <div class="client reveal delay-2">MABES POLRI</div>
    <div class="client reveal delay-3">KEMENTERIAN PU</div>
    <div class="client reveal">KEMENDAGRI</div>
    <div class="client reveal delay-1">UNIVERSITAS INDONESIA</div>
    <div class="client reveal delay-2">JASA MARGA</div>
    <div class="client reveal delay-3">SCTV</div>
</div>
</div>
</section>

<!-- =========================
     STORY
========================= -->
<section class="story" id="story">
<div class="container story-grid">
    <img src="asse/lo1.png" alt="Dimsum" class="story-image reveal">
    <div class="story-content reveal">
        <div class="eyebrow">Dari Dapur Ngemil Dimsum</div>
        <h2>Di Balik Setiap Rasa, Ada Perjuangan.</h2>
        <p>Ngemil Dimsum berawal dari dapur rumahan dengan keyakinan bahwa makanan yang baik membutuhkan ketelitian, kesabaran dan proses yang tidak instan.</p>
        <p>Setiap sajian dibuat dengan bahan pilihan dan perhatian pada rasa serta kualitas.</p>
        <p>Karena setiap sajian bukan sekadar makanan, tetapi bagian dari kebahagiaan dalam sebuah momen.</p>
        <a href="#gallery" class="button button-primary">Lihat Momen →</a>
    </div>
</div>
</section>

<!-- =========================
     GALLERY FOTO
========================= -->
<section class="gallery" id="gallery">
<div class="container">
<div class="section-title reveal">
    <div class="eyebrow">Momen bersama Ngemil Dimsum</div>
    <h2>Dibuat untuk dirayakan.</h2>
    <p>Dari perayaan kecil hingga acara besar, setiap sajian disiapkan dengan perhatian.</p>
</div>

<div class="gallery-grid">
    <img src="images/products/dimsum-original.jpeg" alt="Dimsum Original" class="gallery-item reveal">
    <img src="images/products/dimsum-mentai.jpeg" alt="Dimsum Mentai" class="gallery-item reveal delay-1">
    <img src="images/products/dimsum-udang.jpeg" alt="Dimsum Udang" class="gallery-item reveal delay-2">   
    <img src="images/products/dimsum-creamy.png" alt="Dimsum Creamy" class="gallery-item reveal delay-3">  
    <img src="images/products/dimsum-goreng-original.png" alt="Dimsum Goreng Original" class="gallery-item reveal">
    <img src="images/products/dimsum-goreng-keju.png" alt="Dimsum Goreng Keju" class="gallery-item reveal delay-1">
    <img src="images/products/dimsum-chilioil.png" alt="Dimsum Chili Oil" class="gallery-item reveal delay-2">
    <img src="images/products/dimsum-mozza.png" alt="Dimsum Mozza" class="gallery-item reveal delay-3">
    <img src="images/products/dimsum-mix.jpeg" alt="Dimsum Mix" class="gallery-item reveal">
    <img src="images/products/dimsum-ayam.jpeg" alt="Dimsum Ayam" class="gallery-item reveal delay-1">
    <img src="images/products/dimsum-sayur.jpeg" alt="Dimsum Sayur" class="gallery-item reveal delay-2">
    <img src="images/products/AQUA.jpeg" alt="Aqua" class="gallery-item reveal">
    <img src="images/products/ESTEH.jpeg" alt="Es Teh" class="gallery-item reveal delay-1">
    <img src="images/products/MIX AQUA.jpeg" alt="Dimsum Mix + Aqua" class="gallery-item reveal delay-2">
    <img src="images/products/MIX ESTEH.jpeg" alt="Dimsum Mix + Es Teh" class="gallery-item reveal delay-3">
</div>
</div>
</section>

<!-- =========================
     CTA
========================= -->
<section class="cta" id="contact">
<div class="container">
<div class="cta-box reveal">
    <div>
        <div class="eyebrow" style="color:#d8b57b">Pesan langsung dari dapur kami</div>
        <h2>Jadi, hari ini mau dimsum apa?</h2>
        <p>Untuk surprise, keluarga, kantor ataupun acara besar.</p>
    </div>
    <a href="https://wa.me/6285171000063" target="_blank" class="cta-button">Konsultasi via WhatsApp</a>
</div>
</div>
</section>

</main>

<!-- =========================
     FOOTER
========================= -->
<footer>
<div class="container">
<div class="footer-grid">
<div>
    <div class="footer-logo">Ngemil Dimsum</div>
    <p>Dimsum homemade halal dari Jakarta, dibuat hangat untuk berbagai momen.</p>
    <br>
    <p>no Halal.</p>
</div>
<div>
    <h4>Navigasi</h4>
    <a href="#home">Home</a>
    <a href="#story">Tentang</a>
    <a href="#clients">Clients</a>
    <a href="#gallery">Momen</a>
</div>
<div>
    <h4>Layanan</h4>
    <a href="#gallery">Menu Dimsum</a>
    <a href="#gallery">Dimsum Cake</a>
    <a href="#contact">Pesanan Acara</a>
</div>
<div>
    <h4>Kontak</h4>
    <a href="https://wa.me/6285171000063" target="_blank">+62 851-7100-0063</a>
    <a href="#">@ngemil.dimsum</a>
    <a href="#">@ngemildimsum</a>
    <p>jakarta, depok</p>
</div>
</div>

<div class="copyright">
    © <span id="year"></span> Ngemil Dimsum. All rights reserved.
</div>
</div>
</footer>

<script>
/* =========================
   LOADER
========================= */
window.addEventListener("load", function(){
    setTimeout(function(){
        document.getElementById("loader").classList.add("hide");
    }, 900);
});

/* =========================
   NAVBAR SCROLL EFFECT
========================= */
const header = document.getElementById("header");
window.addEventListener("scroll", function(){
    if(window.scrollY > 50){
        header.classList.add("scrolled");
    } else {
        header.classList.remove("scrolled");
    }
});

/* =========================
   SCROLL REVEAL
========================= */
const revealElements = document.querySelectorAll(".reveal");
const observer = new IntersectionObserver(function(entries){
    entries.forEach(function(entry){
        if(entry.isIntersecting){
            entry.target.classList.add("active");
            observer.unobserve(entry.target);
        }
    });
}, { threshold: .12 });

revealElements.forEach(function(element){
    observer.observe(element);
});

/* =========================
   MOBILE MENU
========================= */
const navLinks = document.getElementById("navLinks");

function toggleMobileMenu(){
    navLinks.classList.toggle("show");
}

function closeMobileMenu(){
    navLinks.classList.remove("show");
}

/* =========================
   YEAR
========================= */
document.getElementById("year").textContent = new Date().getFullYear();

/* =========================
   HERO PARALLAX
========================= */
const heroLogo = document.querySelector(".hero-logo");
let mouseX = 0, mouseY = 0;
let currentX = 0, currentY = 0;

window.addEventListener("mousemove", function(event){
    if(window.innerWidth < 900) return;
    mouseX = (window.innerWidth / 2 - event.clientX) / 40;
    mouseY = (window.innerHeight / 2 - event.clientY) / 40;
});

function animateParallax(){
    currentX += (mouseX - currentX) * 0.1;
    currentY += (mouseY - currentY) * 0.1;
    if(heroLogo){
        heroLogo.style.transform = `translate(${currentX}px, ${currentY}px)`;
    }
    requestAnimationFrame(animateParallax);
}
animateParallax();

/* =========================
   GALLERY CLICK
========================= */
document.querySelectorAll(".gallery-item").forEach(function(image){
    image.addEventListener("click", function(){
        this.classList.toggle("zoomed");
    });
});
</script>

</body>
</html>