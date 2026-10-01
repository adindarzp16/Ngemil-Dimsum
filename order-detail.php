<?php
/* ============================================================
   NGEMIL DIMSUM — Detail Pesanan (Pelanggan)
   Fitur: Lihat detail, batalkan, hapus (kalau selesai/dibatalkan)
   ============================================================ */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) redirect('admin/index.php');

$userId  = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) redirect('orders.php');

// Ambil order (pastikan milik user yang login)
$stmt = $koneksi->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    flash('success', 'Pesanan tidak ditemukan.');
    redirect('orders.php');
}

// ============================================================
// HANDLE: BATALKAN PESANAN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $bisaDibatalkan = in_array($order['status'], ['pending', 'diproses']);

    if (!$bisaDibatalkan) {
        flash('success', '⚠️ Pesanan sudah ' . $order['status'] . ' dan tidak bisa dibatalkan.');
    } else {
        $koneksi->begin_transaction();
        try {
            // Update status
            $stmt = $koneksi->prepare("UPDATE orders SET status = 'dibatalkan' WHERE id = ?");
            $stmt->bind_param('i', $orderId);
            $stmt->execute();

            // Kembalikan stok produk
            $stmtItems = $koneksi->prepare("SELECT product_id, qty FROM order_items WHERE order_id = ?");
            $stmtItems->bind_param('i', $orderId);
            $stmtItems->execute();
            $items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);

            $stmtStok = $koneksi->prepare("UPDATE products SET stok = stok + ? WHERE id = ?");
            foreach ($items as $it) {
                if ($it['product_id']) {
                    $stmtStok->bind_param('ii', $it['qty'], $it['product_id']);
                    $stmtStok->execute();
                }
            }

            $koneksi->commit();
            flash('success', '✅ Pesanan #' . $order['kode_order'] . ' berhasil dibatalkan. Stok produk telah dikembalikan.');
        } catch (Exception $ex) {
            $koneksi->rollback();
            flash('success', '❌ Gagal membatalkan pesanan. Coba lagi.');
        }
    }
    redirect('order-detail.php?id=' . $orderId);
}

// Ambil items + gambar produk
$stmt = $koneksi->prepare("
    SELECT oi.*, p.gambar AS produk_gambar
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Ambil user (avatar)
$stmt = $koneksi->prepare("SELECT nama_lengkap, foto_profil FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$namaUser = $userData['nama_lengkap'];
$userFoto = $userData['foto_profil'];
$initials = strtoupper(substr($namaUser, 0, 2));

$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
$flashMsg  = flash('success');

// Status map
$statusMap = [
    'pending'    => ['label'=>'Menunggu Konfirmasi', 'color'=>'#f39c12', 'bg'=>'#fef5e7', 'icon'=>'fa-clock'],
    'diproses'   => ['label'=>'Sedang Diproses',     'color'=>'#3498db', 'bg'=>'#eaf4fc', 'icon'=>'fa-utensils'],
    'dikirim'    => ['label'=>'Sedang Dikirim',      'color'=>'#9b59b6', 'bg'=>'#f4ecf7', 'icon'=>'fa-motorcycle'],
    'selesai'    => ['label'=>'Selesai',             'color'=>'#27ae60', 'bg'=>'#e9f7eb', 'icon'=>'fa-check-circle'],
    'dibatalkan' => ['label'=>'Dibatalkan',          'color'=>'#e74c3c', 'bg'=>'#fdecec', 'icon'=>'fa-times-circle'],
];
$s = $statusMap[$order['status']] ?? ['label'=>ucfirst($order['status']), 'color'=>'#888', 'bg'=>'#f0f0f3', 'icon'=>'fa-info-circle'];

$bisaDibatalkan = in_array($order['status'], ['pending', 'diproses']);
$bisaDihapus    = in_array($order['status'], ['dibatalkan', 'selesai']);

// Metode pembayaran label
$metodeLabel = [
    'cod' => 'COD (Bayar di Tempat)',
    'transfer' => 'Transfer',
];
$metodeDetailLabel = [
    'bca' => 'Bank BCA',
    'bri' => 'Bank BRI',
    'mandiri' => 'Bank Mandiri',
    'bni' => 'Bank BNI',
    'qris' => 'QRIS',
    'ovo' => 'OVO',
    'dana' => 'DANA',
    'gopay' => 'GoPay',
    'shopeepay' => 'ShopeePay',
];
$metodeText = $metodeLabel[$order['metode_bayar']] ?? ucfirst($order['metode_bayar'] ?? 'COD');
if (($order['metode_bayar'] ?? '') === 'transfer' && !empty($order['metode_detail'])) {
    $metodeText .= ' - ' . ($metodeDetailLabel[$order['metode_detail']] ?? strtoupper($order['metode_detail']));
}

// Hitung subtotal items
$subtotalItems = 0;
foreach ($items as $it) $subtotalItems += $it['subtotal'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Pesanan #<?= e($order['kode_order']) ?> — Ngemil Dimsum</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Poppins',sans-serif;background:#f5f0eb;display:flex;min-height:100vh}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}

/* Sidebar */
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

/* Main */
.main-content{margin-left:240px;flex:1;min-height:100vh}

/* Topbar */
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

/* Page */
.page-content{padding:30px;max-width:1000px}
.page-title{font-size:24px;font-weight:700;color:#333;margin-bottom:5px}
.breadcrumb{font-size:12px;color:#999;margin-bottom:25px}
.breadcrumb a{color:#7a1a2e}

.alert{padding:12px 18px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500;display:flex;align-items:center;gap:10px;line-height:1.5}
.alert-success{background:#e9f7eb;color:#1e6a2a;border:1px solid #bce0c2}

/* Header card */
.order-header{background:#fff;border-radius:16px;padding:24px;margin-bottom:20px;border-left:5px solid #7a1a2e;box-shadow:0 3px 12px rgba(0,0,0,.05)}
.order-header-top{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:15px;margin-bottom:15px}
.order-code-big{font-size:20px;font-weight:800;color:#2c0a0e;margin-bottom:4px;font-family:monospace;letter-spacing:1px}
.order-date-big{font-size:12px;color:#888}
.order-status-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:25px;font-size:12px;font-weight:700;border:2px solid currentColor}

/* Grid */
.detail-grid{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start}

/* Card */
.card{background:#fff;border-radius:16px;padding:22px;box-shadow:0 3px 12px rgba(0,0,0,.05);border:1px solid #f0ebe6;margin-bottom:20px}
.card:last-child{margin-bottom:0}
.card-title{font-size:14px;font-weight:700;color:#333;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #f0ebe6;display:flex;align-items:center;gap:8px;text-transform:uppercase;letter-spacing:.5px}
.card-title i{color:#7a1a2e;font-size:15px}

/* Info rows */
.info-row{display:flex;justify-content:space-between;padding:10px 0;font-size:13px;border-bottom:1px dashed #f0ebe6;gap:10px}
.info-row:last-child{border:0}
.info-row .label{color:#888;flex-shrink:0}
.info-row .value{font-weight:600;color:#333;text-align:right;max-width:60%;word-break:break-word}

/* Item */
.item{display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f5f0eb;align-items:center}
.item:last-child{border-bottom:0;padding-bottom:0}
.item:first-child{padding-top:0}
.item-img{width:70px;height:70px;border-radius:12px;object-fit:cover;background:#f8f4f0;flex-shrink:0;border:1px solid #f0ebe6}
.item-info{flex:1;min-width:0}
.item-name{font-size:14px;font-weight:700;color:#2c0a0e;margin-bottom:4px}
.item-variant{display:inline-block;font-size:11px;color:#7a1a2e;background:#f8f4f0;padding:2px 9px;border-radius:6px;font-weight:600;margin-bottom:6px}
.item-price{font-size:12px;color:#888}
.item-total{font-size:15px;font-weight:800;color:#7a1a2e;text-align:right;flex-shrink:0}

/* Ringkasan total */
.total-box{background:#fdf6ee;border-radius:12px;padding:16px;margin-top:14px}
.total-row{display:flex;justify-content:space-between;font-size:13px;color:#666;margin-bottom:8px}
.total-row.grand{font-size:17px;font-weight:800;color:#7a1a2e;padding-top:12px;margin-top:8px;border-top:2px dashed #e8d9b8;margin-bottom:0}

/* Tombol aksi */
.action-group{display:flex;flex-direction:column;gap:10px;margin-top:6px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;border-radius:10px;border:none;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:.25s;line-height:1;text-decoration:none}
.btn-block{width:100%}
.btn-primary{background:#7a1a2e;color:#fff}
.btn-primary:hover{background:#5c1020;transform:translateY(-2px);box-shadow:0 6px 18px rgba(122,26,46,.25)}
.btn-danger{background:#fff;color:#e74c3c;border:2px solid #fcc}
.btn-danger:hover{background:#e74c3c;color:#fff;border-color:#e74c3c}
.btn-outline{background:#fff;color:#7a1a2e;border:2px solid #e8e0d8}
.btn-outline:hover{background:#f8f4f0;border-color:#7a1a2e}
.btn-warning{background:#f39c12;color:#fff}
.btn-warning:hover{background:#e67e22;transform:translateY(-2px);box-shadow:0 6px 18px rgba(243,156,18,.3)}
.btn:disabled{opacity:.6;cursor:not-allowed;transform:none!important;background:#f0f0f3;color:#999;border-color:#e5e5e8}

/* Modal konfirmasi */
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.show{display:flex}
.modal-box{background:#fff;border-radius:18px;max-width:420px;width:100%;padding:28px;text-align:center;animation:pop .25s ease}
@keyframes pop{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
.modal-icon{width:68px;height:68px;border-radius:50%;background:#fdecec;color:#e74c3c;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 16px}
.modal-icon.warn{background:#fef5e7;color:#f39c12}
.modal-box h3{font-size:18px;font-weight:800;color:#2c0a0e;margin-bottom:8px}
.modal-box p{font-size:13px;color:#666;margin-bottom:22px;line-height:1.6}
.modal-actions{display:flex;gap:10px}
.modal-actions .btn{flex:1;justify-content:center}

.wave-decoration{position:fixed;bottom:0;left:240px;right:0;height:60px;pointer-events:none;z-index:10}

@media (max-width:900px){
    .detail-grid{grid-template-columns:1fr}
    .sidebar{width:60px}
    .sidebar .logo-text,.nav-menu a span{display:none}
    .main-content{margin-left:60px}
    .search-box{width:auto;flex:1}
    .user-info{display:none}
    .wave-decoration{left:60px}
    .page-content{padding:16px}
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="logo-container">
        <div style="width:100px;height:100px;background:#d4a843;border-radius:50%;margin:0 auto;display:flex;align-items:center;justify-content:center;">
            <img src="asse/lo1.png" alt="Dimsum" style="width:80px;height:80px;">
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
        <a href="orders.php" style="color:#7a1a2e;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;margin-bottom:15px;">
            <i class="fas fa-arrow-left"></i> Kembali ke Pesanan Saya
        </a>

        <h1 class="page-title">Detail Pesanan</h1>
        <div class="breadcrumb">
            <a href="dashboard.php">Beranda</a> / <a href="orders.php">Pesanan</a> / <span><?= e($order['kode_order']) ?></span>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($flashMsg) ?></div>
        <?php endif; ?>

        <!-- ===================== HEADER ===================== -->
        <div class="order-header">
            <div class="order-header-top">
                <div>
                    <div class="order-code-big">#<?= e($order['kode_order']) ?></div>
                    <div class="order-date-big">
                        <i class="far fa-clock"></i> <?= date('d F Y, H:i', strtotime($order['created_at'])) ?>
                    </div>
                </div>
                <span class="order-status-badge" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;">
                    <i class="fas <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
                </span>
            </div>
        </div>

        <!-- Notifikasi khusus kalau pending & transfer -->
        <?php if ($order['status'] === 'pending' && ($order['metode_bayar'] ?? '') === 'transfer'): ?>
            <div class="card" style="background:linear-gradient(135deg,#fef5e7,#fdecd0);border:2px dashed #f39c12;">
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                    <div style="width:48px;height:48px;border-radius:12px;background:#f39c12;color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <div style="font-size:14px;font-weight:700;color:#2c0a0e;margin-bottom:3px;">Menunggu Pembayaran</div>
                        <div style="font-size:12px;color:#666;line-height:1.5;">Silakan selesaikan pembayaran via <b><?= e($metodeText) ?></b>.</div>
                    </div>
                    <a href="payment.php?id=<?= $order['id'] ?>" class="btn btn-warning" style="flex-shrink:0;">
                        <i class="fas fa-credit-card"></i> Bayar Sekarang
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="detail-grid">

            <!-- ===================== KIRI ===================== -->
            <div>
                <!-- ITEM PESANAN -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-shopping-bag"></i> Item Pesanan (<?= count($items) ?> item)</div>

                    <?php foreach ($items as $it): ?>
                        <div class="item">
                            <?php if (!empty($it['produk_gambar']) && file_exists(__DIR__.'/'.$it['produk_gambar'])): ?>
                                <img src="<?= e($it['produk_gambar']) ?>?v=<?= time() ?>" alt="<?= e($it['nama_produk']) ?>" class="item-img">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/70x70/f8f4f0/7a1a2e?text=🥟" alt="<?= e($it['nama_produk']) ?>" class="item-img">
                            <?php endif; ?>
                            <div class="item-info">
                                <div class="item-name"><?= e($it['nama_produk']) ?></div>
                                <div class="item-price">
                                    <?= rupiah($it['harga']) ?> × <?= $it['qty'] ?>
                                </div>
                            </div>
                            <div class="item-total"><?= rupiah($it['subtotal']) ?></div>
                        </div>
                    <?php endforeach; ?>

                    <div class="total-box">
                        <div class="total-row"><span>Subtotal</span><span><b><?= rupiah($order['subtotal']) ?></b></span></div>
                        <div class="total-row"><span>Ongkos Kirim</span><span><b><?= rupiah($order['ongkir']) ?></b></span></div>
                        <div class="total-row grand"><span>Total</span><span><?= rupiah($order['grand_total']) ?></span></div>
                    </div>
                </div>

                <!-- ALAMAT PENGIRIMAN -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</div>
                    <div style="font-size:13px;color:#555;line-height:1.8;white-space:pre-line;"><?= e($order['alamat_kirim']) ?></div>
                </div>

                <!-- CATATAN -->
                <?php if (!empty($order['catatan'])): ?>
                    <div class="card">
                        <div class="card-title"><i class="fas fa-sticky-note"></i> Catatan</div>
                        <div style="font-size:13px;color:#555;line-height:1.8;font-style:italic;">"<?= e($order['catatan']) ?>"</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===================== KANAN ===================== -->
            <div>
                <!-- INFO PESANAN -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-info-circle"></i> Info Pesanan</div>
                    <div class="info-row">
                        <span class="label">Kode Pesanan</span>
                        <span class="value" style="font-family:monospace;"><?= e($order['kode_order']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Tanggal</span>
                        <span class="value"><?= date('d M Y', strtotime($order['created_at'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Status</span>
                        <span class="value" style="color:<?= $s['color'] ?>;"><?= $s['label'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Pembayaran</span>
                        <span class="value" style="font-size:12px;"><?= e($metodeText) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Total Bayar</span>
                        <span class="value" style="color:#7a1a2e;font-size:15px;"><?= rupiah($order['grand_total']) ?></span>
                    </div>
                </div>

                <!-- AKSI -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-bolt"></i> Aksi</div>
                    <div class="action-group">

                        <?php if ($bisaDibatalkan): ?>
                            <!-- Status pending/diproses → bisa batalkan -->
                            <button type="button" class="btn btn-danger btn-block" onclick="openCancelModal()">
                                <i class="fas fa-times-circle"></i> Batalkan Pesanan
                            </button>

                        <?php elseif ($bisaDihapus): ?>
                            <!-- Status dibatalkan/selesai → bisa hapus -->
                            <button type="button" class="btn btn-danger btn-block" onclick="openDeleteModal()">
                                <i class="fas fa-trash"></i> Hapus Pesanan
                            </button>
                            <div style="font-size:11px;color:#999;text-align:center;line-height:1.5;">
                                Pesanan sudah <b><?= strtolower($s['label']) ?></b>. Bisa dihapus dari riwayat.
                            </div>

                        <?php else: ?>
                            <!-- Status dikirim → tidak bisa apa-apa -->
                            <button type="button" class="btn btn-block" disabled>
                                <i class="fas fa-truck"></i> Sedang Dikirim
                            </button>
                            <div style="font-size:11px;color:#999;text-align:center;line-height:1.5;">
                                Pesanan yang sedang dikirim tidak bisa dibatalkan.
                            </div>
                        <?php endif; ?>

                        <!-- Bayar (kalau pending & transfer) -->
                        <?php if ($order['status'] === 'pending' && ($order['metode_bayar'] ?? '') === 'transfer'): ?>
                            <a href="payment.php?id=<?= $order['id'] ?>" class="btn btn-warning btn-block">
                                <i class="fas fa-credit-card"></i> Bayar Sekarang
                            </a>
                        <?php endif; ?>

                        <!-- Belanja Lagi -->
                        <a href="dashboard.php" class="btn btn-primary btn-block">
                            <i class="fas fa-plus"></i> Pesan Lagi
                        </a>

                        <!-- Chat Admin -->
                        <a href="https://wa.me/6285695218053?text=Halo%20Ngemil%20Dimsum%2C%20saya%20mau%20tanya%20tentang%20pesanan%20<?= urlencode($order['kode_order']) ?>"
                           target="_blank" class="btn btn-outline btn-block">
                            <i class="fab fa-whatsapp"></i> Hubungi Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================== MODAL BATALKAN ===================== -->
<div class="modal-backdrop" id="cancelModal">
    <div class="modal-box">
        <div class="modal-icon warn"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Batalkan Pesanan?</h3>
        <p>Yakin mau batalkan pesanan <b>#<?= e($order['kode_order']) ?></b>?<br>Stok produk akan dikembalikan, dan tindakan ini tidak bisa dibatalkan.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeCancelModal()">Tidak</button>
            <form method="post" style="flex:1;">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-danger" style="width:100%;">
                    <i class="fas fa-check"></i> Ya, Batalkan
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ===================== MODAL HAPUS ===================== -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-trash"></i></div>
        <h3>Hapus Pesanan?</h3>
        <p>Yakin mau hapus pesanan <b>#<?= e($order['kode_order']) ?></b> dari riwayat?<br>Tindakan ini tidak bisa dibatalkan.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Batal</button>
            <form method="post" action="order-delete.php" style="flex:1;">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" class="btn btn-danger" style="width:100%;">
                    <i class="fas fa-check"></i> Ya, Hapus
                </button>
            </form>
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
/* ============ MODAL BATALKAN ============ */
function openCancelModal() {
    document.getElementById('cancelModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('show');
    document.body.style.overflow = '';
}

/* ============ MODAL HAPUS ============ */
function openDeleteModal() {
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

/* ============ CLICK OUTSIDE ============ */
document.querySelectorAll('.modal-backdrop').forEach(function(m){
    m.addEventListener('click', function(e){
        if (e.target === this) {
            this.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
});
</script>
</body>
</html>