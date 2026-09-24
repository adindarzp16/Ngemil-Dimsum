<?php
/* ============================================================
   NGEMIL DIMSUM — Instruksi Pembayaran
   ============================================================ */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) redirect('admin/index.php');

$userId  = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) redirect('orders.php');

$stmt = $koneksi->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    flash('success', 'Pesanan tidak ditemukan.');
    redirect('orders.php');
}

// Kalau bukan transfer atau sudah bukan pending, balik ke detail
if ($order['metode_bayar'] !== 'transfer' || $order['status'] !== 'pending') {
    redirect('order-detail.php?id=' . $orderId);
}

// Info user
$stmt = $koneksi->prepare("SELECT nama_lengkap, foto_profil FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$namaUser = $userData['nama_lengkap'];
$userFoto = $userData['foto_profil'];
$initials = strtoupper(substr($namaUser, 0, 2));

$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
$flashMsg  = flash('success');

// ============================================================
// KONFIGURASI PEMBAYARAN
// ============================================================
$paymentInfo = [
    'bca'        => ['label'=>'Bank BCA',        'icon'=>'fa-university', 'color'=>'#0060aa', 'nomor'=>'1234567890',       'an'=>'Ngemil Dimsum'],
    'bri'        => ['label'=>'Bank BRI',        'icon'=>'fa-university', 'color'=>'#00529c', 'nomor'=>'0987654321',       'an'=>'Ngemil Dimsum'],
    'mandiri'    => ['label'=>'Bank Mandiri',    'icon'=>'fa-university', 'color'=>'#003d79', 'nomor'=>'1234567890123',    'an'=>'Ngemil Dimsum'],
    'bni'        => ['label'=>'Bank BNI',        'icon'=>'fa-university', 'color'=>'#f57e20', 'nomor'=>'1234567890',       'an'=>'Ngemil Dimsum'],
    'qris'       => ['label'=>'QRIS',            'icon'=>'fa-qrcode',     'color'=>'#e74c3c', 'nomor'=>null,               'an'=>null],
    'ovo'        => ['label'=>'OVO',             'icon'=>'fa-wallet',     'color'=>'#4c3494', 'nomor'=>'0812-3456-7890',   'an'=>'Ngemil Dimsum'],
    'dana'       => ['label'=>'DANA',            'icon'=>'fa-wallet',     'color'=>'#118eea', 'nomor'=>'0812-3456-7890',   'an'=>'Ngemil Dimsum'],
    'gopay'      => ['label'=>'GoPay',           'icon'=>'fa-wallet',     'color'=>'#00aa13', 'nomor'=>'0812-3456-7890',   'an'=>'Ngemil Dimsum'],
    'shopeepay'  => ['label'=>'ShopeePay',       'icon'=>'fa-wallet',     'color'=>'#ee4d2d', 'nomor'=>'0812-3456-7890',   'an'=>'Ngemil Dimsum'],
];

$kode = $order['metode_detail'];
$info = $paymentInfo[$kode] ?? ['label'=>strtoupper($kode), 'icon'=>'fa-credit-card', 'color'=>'#666', 'nomor'=>null, 'an'=>null];

// Path QRIS — taruh file di images/qris.png
$qrisPath = 'images/kris.jpeg';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pembayaran #<?= e($order['kode_order']) ?> — Ngemil Dimsum</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Poppins',sans-serif;background:#f5f0eb;display:flex;min-height:100vh}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}

.sidebar{width:240px;background:linear-gradient(180deg,#7a1a2e 0%,#5c1020 100%);min-height:100vh;padding:20px 0;position:fixed;left:0;top:0;z-index:100;display:flex;flex-direction:column}
.sidebar::after{content:'';position:absolute;bottom:0;left:0;right:0;height:80px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 80'%3E%3Cpath d='M0,40 Q60,0 120,40 T240,40 L240,80 L0,80 Z' fill='%23f5f0eb'/%3E%3C/svg%3E") no-repeat bottom;background-size:cover}
.logo-container{text-align:center;padding:10px 20px 30px}
.logo-text{color:#fff;font-size:22px;font-weight:800;margin-top:8px}
.logo-text span{color:#f0c040}
.nav-menu{list-style:none;padding:0 15px;flex:1}
.nav-menu li{margin-bottom:5px}
.nav-menu a{display:flex;align-items:center;gap:12px;padding:12px 18px;color:#e0d0c0;border-radius:10px;font-size:14px;font-weight:500;transition:.3s}
.nav-menu a:hover{background:rgba(255,255,255,.1);color:#fff}
.nav-menu a.active{background:#d4a843;color:#fff;font-weight:600}
.nav-menu a i{width:20px;text-align:center;font-size:16px}
.nav-badge{background:#e74c3c;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;margin-left:auto}

.main-content{margin-left:240px;flex:1;min-height:100vh}

.topbar{background:#fff;padding:15px 30px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.05);position:sticky;top:0;z-index:50}
.search-box{display:flex;align-items:center;background:#f8f4f0;border-radius:25px;padding:10px 20px;width:350px;border:1px solid #e8e0d8}
.search-box input{border:none;background:transparent;outline:none;font-family:'Poppins',sans-serif;font-size:13px;width:100%;margin-left:10px;color:#555}
.search-box i{color:#999}
.topbar-right{display:flex;align-items:center;gap:20px}
.topbar-icon{position:relative;cursor:pointer;font-size:20px;color:#7a1a2e;transition:.2s}
.topbar-icon:hover{transform:scale(1.1)}
.topbar-icon .badge{position:absolute;top:-8px;right:-8px;background:#e74c3c;color:#fff;font-size:10px;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.user-profile{display:flex;align-items:center;gap:10px;cursor:pointer;position:relative}
.user-avatar{width:38px;height:38px;border-radius:50%;background:#7a1a2e;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:14px;overflow:hidden;flex-shrink:0}
.user-avatar img{width:100%;height:100%;object-fit:cover}
.user-info .name{font-size:13px;font-weight:600;color:#333}
.user-info .role{font-size:11px;color:#999}
.user-dropdown{position:absolute;top:110%;right:0;background:#fff;border-radius:12px;box-shadow:0 5px 20px rgba(0,0,0,.12);min-width:180px;padding:8px 0;opacity:0;visibility:hidden;transform:translateY(-10px);transition:.3s;z-index:60}
.user-profile:hover .user-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
.user-dropdown a{display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:#555;transition:.2s}
.user-dropdown a:hover{background:#f8f4f0;color:#7a1a2e}
.user-dropdown a.logout{color:#e74c3c}
.user-dropdown a.logout:hover{background:#fdecec}

.page-content{padding:30px;max-width:760px;margin:0 auto}
.page-title{font-size:24px;font-weight:700;color:#333;margin-bottom:5px;text-align:center}
.breadcrumb{font-size:12px;color:#999;margin-bottom:25px;text-align:center}
.breadcrumb a{color:#7a1a2e}

.alert{padding:12px 18px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500;display:flex;align-items:center;gap:10px}
.alert-success{background:#e9f7eb;color:#1e6a2a;border:1px solid #bce0c2}

/* STATUS BANNER */
.status-banner{background:linear-gradient(135deg,#fef5e7,#fdecd0);border-radius:16px;padding:24px;text-align:center;margin-bottom:20px;border:2px dashed #f39c12}
.status-banner i{font-size:44px;color:#f39c12;margin-bottom:10px}
.status-banner h2{font-size:20px;font-weight:800;color:#2c0a0e;margin-bottom:6px}
.status-banner p{font-size:13px;color:#666;line-height:1.6}

/* CARD */
.card{background:#fff;border-radius:16px;padding:24px;margin-bottom:20px;box-shadow:0 3px 12px rgba(0,0,0,.05);border:1px solid #f0ebe6}
.card-title{font-size:14px;font-weight:700;color:#333;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #f0ebe6;display:flex;align-items:center;gap:8px;text-transform:uppercase;letter-spacing:.5px}
.card-title i{color:#7a1a2e;font-size:15px}

/* METODE BADGE */
.metode-badge{display:flex;align-items:center;gap:14px;padding:18px;background:#f8f4f0;border-radius:12px;margin-bottom:16px;border:2px solid #e8e0d8}
.metode-icon{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;color:#fff;flex-shrink:0}
.metode-info{flex:1}
.metode-label{font-size:11px;color:#999;text-transform:uppercase;letter-spacing:1px;font-weight:700}
.metode-name{font-size:18px;font-weight:800;color:#2c0a0e;margin-top:2px}

/* DETAIL PEMBAYARAN */
.pay-detail{background:#fdf6ee;border-radius:12px;padding:20px;text-align:center;border:2px solid #f0e2cb}
.pay-label{font-size:12px;color:#666;font-weight:600;margin-bottom:8px}
.pay-nomor{font-size:24px;font-weight:800;color:#7a1a2e;font-family:monospace;letter-spacing:1.5px;margin-bottom:6px;word-break:break-all}
.pay-nama{font-size:13px;color:#666;margin-bottom:14px}
.pay-nama b{color:#2c0a0e}
.copy-btn{background:#7a1a2e;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Poppins',sans-serif;transition:.2s}
.copy-btn:hover{background:#5c1020;transform:translateY(-2px);box-shadow:0 4px 12px rgba(122,26,46,.25)}
.copy-btn.copied{background:#27ae60}

/* QRIS */
.qris-wrap{text-align:center;padding:20px;background:#f8f4f0;border-radius:12px}
.qris-img{max-width:280px;margin:0 auto 15px;border-radius:12px;border:4px solid #fff;box-shadow:0 4px 16px rgba(0,0,0,.1);background:#fff;padding:10px}
.qris-hint{font-size:12px;color:#666;line-height:1.6;max-width:300px;margin:0 auto}
.qris-hint i{color:#7a1a2e}

/* TOTAL BOX */
.total-big{background:linear-gradient(135deg,#7a1a2e,#5c1020);color:#fff;padding:24px;border-radius:16px;text-align:center;margin-bottom:20px}
.total-big .lbl{font-size:12px;opacity:.8;text-transform:uppercase;letter-spacing:1.5px;font-weight:700}
.total-big .val{font-size:36px;font-weight:800;margin-top:6px}

/* LANGKAH */
.steps{list-style:none;counter-reset:s}
.steps li{position:relative;padding:12px 0 12px 42px;font-size:13px;color:#555;line-height:1.6;border-bottom:1px solid #f0ebe6}
.steps li:last-child{border:0}
.steps li::before{counter-increment:s;content:counter(s);position:absolute;left:0;top:11px;width:28px;height:28px;background:#7a1a2e;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}

/* ACTION */
.actions{margin-top:25px;display:flex;gap:10px;flex-wrap:wrap}
.btn{flex:1;min-width:180px;padding:14px 24px;border-radius:12px;border:none;font-family:'Poppins',sans-serif;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:.25s;text-decoration:none;line-height:1}
.btn-wa{background:#25d366;color:#fff}
.btn-wa:hover{background:#1da851;transform:translateY(-2px);box-shadow:0 6px 18px rgba(37,211,102,.3)}
.btn-primary{background:#7a1a2e;color:#fff}
.btn-primary:hover{background:#5c1020;transform:translateY(-2px);box-shadow:0 6px 18px rgba(122,26,46,.25)}
.btn-outline{background:#fff;color:#7a1a2e;border:2px solid #e8e0d8}
.btn-outline:hover{background:#f8f4f0;border-color:#7a1a2e}

.wave-decoration{position:fixed;bottom:0;left:240px;right:0;height:60px;pointer-events:none;z-index:10}

@media (max-width:768px){
    .sidebar{width:60px}
    .sidebar .logo-text,.nav-menu a span{display:none}
    .main-content{margin-left:60px}
    .search-box{width:auto;flex:1}
    .user-info{display:none}
    .wave-decoration{left:60px}
    .page-content{padding:16px}
    .total-big .val{font-size:28px}
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="logo-container">
        <div style="width:100px;height:100px;background:#d4a843;border-radius:50%;margin:0 auto;display:flex;align-items:center;justify-content:center;">
            <img src="k/lo.png" alt="Dimsum" style="width:80px;height:80px;">
        </div>
        <div class="logo-text">Ngemil <span>Dimsum</span></div>
    </div>
    <ul class="nav-menu">
        <li><a href="dashboard.php"><i class="fas fa-th-large"></i> <span>Katalog</span></a></li>
        <li><a href="index.php"><i class="fas fa-home"></i> <span>Beranda</span></a></li>
        <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> <span>Keranjang</span><?php if ($cartCount>0): ?><span class="nav-badge"><?= $cartCount ?></span><?php endif; ?></a></li>
        <li><a href="orders.php" class="active"><i class="fas fa-clipboard-list"></i> <span>Pesanan Saya</span></a></li>
        <li><a href="profil.php"><i class="fas fa-user"></i> <span>Profil</span></a></li>
    </ul>
</aside>

<div class="main-content">
    <div class="topbar">
        <form class="search-box" method="get" action="dashboard.php">
            <i class="fas fa-search"></i>
            <input type="text" name="q" placeholder="Cari menu favoritmu...">
        </form>
        <div class="topbar-right">
            <a href="cart.php" class="topbar-icon"><i class="fas fa-shopping-cart"></i><?php if ($cartCount>0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?></a>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php if ($userFoto && file_exists(__DIR__.'/'.$userFoto)): ?>
                        <img src="<?= e($userFoto) ?>?v=<?= time() ?>" alt="Avatar">
                    <?php else: ?>
                        <?= e($initials) ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="name"><?= e($namaUser) ?></div>
                    <div class="role">Pelanggan</div>
                </div>
                <div class="user-dropdown">
                    <a href="profil.php"><i class="fas fa-user"></i> Profil</a>
                    <a href="orders.php"><i class="fas fa-clipboard-list"></i> Pesanan</a>
                    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="page-content">

        <?php if ($flashMsg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($flashMsg) ?></div>
        <?php endif; ?>

        <div class="status-banner">
            <i class="fas fa-clock"></i>
            <h2>Menunggu Pembayaran</h2>
            <p>Selesaikan pembayaran sebelum pesanan diproses.<br>Pesanan akan otomatis dibatalkan jika tidak dibayar dalam <b>24 jam</b>.</p>
        </div>

        <div class="total-big">
            <div class="lbl">Total yang harus dibayar</div>
            <div class="val"><?= rupiah($order['grand_total']) ?></div>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-credit-card"></i> Metode Pembayaran</div>

            <div class="metode-badge">
                <div class="metode-icon" style="background:<?= $info['color'] ?>;">
                    <i class="fas <?= $info['icon'] ?>"></i>
                </div>
                <div class="metode-info">
                    <div class="metode-label">Metode Dipilih</div>
                    <div class="metode-name"><?= e($info['label']) ?></div>
                </div>
            </div>

            <?php if ($kode === 'qris'): ?>
                <!-- QRIS -->
                <div class="qris-wrap">
                    <?php if (file_exists(__DIR__ . '/' . $qrisPath)): ?>
                        <img src="<?= e($qrisPath) ?>?v=<?= time() ?>" alt="QRIS" class="qris-img">
                    <?php else: ?>
                        <div style="padding:40px 20px;background:#fff;border-radius:10px;border:2px dashed #ddd;margin-bottom:15px;color:#999;">
                            <i class="fas fa-qrcode" style="font-size:60px;opacity:.3;display:block;margin-bottom:10px;"></i>
                            <div style="font-size:12px;">File <code>images/qris.png</code> belum ada</div>
                        </div>
                    <?php endif; ?>
                    <div class="qris-hint">
                        <i class="fas fa-mobile-alt"></i> Buka aplikasi <b>m-banking</b> atau <b>e-wallet</b>, pilih menu <b>Scan QRIS</b>, lalu arahkan kamera ke barcode di atas.
                    </div>
                </div>

            <?php else: ?>
                <!-- BANK / E-WALLET -->
                <div class="pay-detail">
                    <div class="pay-label">Nomor <?= $kode === 'ovo' || $kode === 'dana' || $kode === 'gopay' || $kode === 'shopeepay' ? 'HP' : 'Rekening' ?></div>
                    <div class="pay-nomor" id="nomorPay"><?= e($info['nomor']) ?></div>
                    <div class="pay-nama">a/n <b><?= e($info['an']) ?></b></div>
                    <button type="button" class="copy-btn" onclick="copyNomor(this)">
                        <i class="fas fa-copy"></i> Salin Nomor
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-list-ol"></i> Cara Pembayaran</div>
            <ol class="steps">
                <li>Buka aplikasi <b><?= $kode === 'qris' ? 'm-banking / e-wallet' : ($kode === 'ovo' || $kode === 'dana' || $kode === 'gopay' || $kode === 'shopeepay' ? 'e-wallet ' . $info['label'] : 'm-banking ' . $info['label']) ?></b> kamu</li>
                <?php if ($kode === 'qris'): ?>
                    <li>Pilih menu <b>Scan QRIS</b> atau <b>Bayar</b></li>
                    <li>Scan barcode di atas, pastikan nominal <b><?= rupiah($order['grand_total']) ?></b></li>
                    <li>Konfirmasi pembayaran</li>
                <?php else: ?>
                    <li>Pilih menu <b>Transfer</b> atau <b>Kirim</b></li>
                    <li>Masukkan nomor <b><?= $kode === 'ovo' || $kode === 'dana' || $kode === 'gopay' || $kode === 'shopeepay' ? 'HP' : 'rekening' ?></b> di atas</li>
                    <li>Masukkan nominal <b><?= rupiah($order['grand_total']) ?></b></li>
                    <li>Konfirmasi pembayaran</li>
                <?php endif; ?>
                <li>Klik tombol <b>Konfirmasi via WhatsApp</b> di bawah & kirim bukti transfer</li>
            </ol>
        </div>

        <div class="actions">
            <?php
            $waText = "Halo Ngemil Dimsum, saya mau konfirmasi pembayaran:%0A%0A";
            $waText .= "Kode Order: *" . $order['kode_order'] . "*%0A";
            $waText .= "Metode: *" . $info['label'] . "*%0A";
            $waText .= "Total: *" . rupiah($order['grand_total']) . "*%0A%0A";
            $waText .= "Bukti transfer saya lampirkan di chat ini. Terima kasih 🙏";
            ?>
            <a href="https://wa.me/6285171000063?text=<?= $waText ?>" target="_blank" class="btn btn-wa">
                <i class="fab fa-whatsapp"></i> Konfirmasi via WhatsApp
            </a>
            <a href="order-detail.php?id=<?= $order['id'] ?>" class="btn btn-outline">
                <i class="fas fa-receipt"></i> Lihat Detail Pesanan
            </a>
        </div>

    </div>
</div>

<div class="wave-decoration">
    <svg viewBox="0 0 1440 60" preserveAspectRatio="none" style="width:100%;height:100%;">
        <path d="M0,30 Q360,0 720,30 T1440,30 L1440,60 L0,60 Z" fill="#7a1a2e"/>
        <path d="M0,40 Q360,10 720,40 T1440,40 L1440,60 L0,60 Z" fill="#d4a843" opacity="0.6"/>
    </svg>
</div>

<script>
function copyNomor(btn) {
    var nomor = document.getElementById('nomorPay').textContent.trim();
    navigator.clipboard.writeText(nomor).then(function() {
        var html = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
        setTimeout(function() {
            btn.classList.remove('copied');
            btn.innerHTML = html;
        }, 2000);
    });
}
</script>
</body>
</html>