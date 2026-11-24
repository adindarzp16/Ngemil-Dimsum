<?php
/* ============================================================
   NGEMIL DIMSUM — Checkout (Full Fix)
   ============================================================ */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) redirect('admin/index.php');

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    flash('success', 'Keranjang kamu kosong. Silakan pilih menu dulu.');
    redirect('dashboard.php');
}

$userId = $_SESSION['user_id'];

// Ambil data user untuk default form
$stmt = $koneksi->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$errors = [];
$form = [
    'nama_penerima' => $_POST['nama_penerima'] ?? $user['nama_lengkap'],
    'no_telp'       => $_POST['no_telp']       ?? ($user['no_telp'] ?? ''),
    'alamat'        => $_POST['alamat']        ?? ($user['alamat'] ?? ''),
    'catatan'       => $_POST['catatan']       ?? '',
    'metode_bayar'  => $_POST['metode_bayar']  ?? 'cod',
    'metode_detail' => $_POST['metode_detail'] ?? '',
];

$subtotal = 0;
foreach ($cart as $item) $subtotal += $item['harga'] * $item['qty'];
$ongkir = ONGKIR_DEFAULT;
$grandTotal = $subtotal + $ongkir;

// ============================================================
// SUBMIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($form['nama_penerima'] === '') $errors[] = 'Nama penerima wajib diisi.';
    if ($form['no_telp'] === '')       $errors[] = 'No. HP wajib diisi.';
    if ($form['alamat'] === '')        $errors[] = 'Alamat pengiriman wajib diisi.';
    if (!in_array($form['metode_bayar'], ['cod', 'transfer'])) $errors[] = 'Metode pembayaran tidak valid.';

    // Validasi sub-options transfer
    if ($form['metode_bayar'] === 'transfer') {
        $validDetail = ['bca', 'bri', 'mandiri', 'bni', 'qris', 'ovo', 'dana', 'gopay', 'shopeepay'];
        if (!in_array($form['metode_detail'], $validDetail)) {
            $errors[] = 'Pilih metode transfer yang ingin digunakan.';
        }
    }

    if (empty($errors)) {
        $koneksi->begin_transaction();
        try {
            $kodeOrder = 'ORD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $metodeDetailSave = ($form['metode_bayar'] === 'transfer') ? $form['metode_detail'] : null;
            $alamatLengkap = $form['alamat'] . "\nPenerima: {$form['nama_penerima']} | HP: {$form['no_telp']}";

            $stmt = $koneksi->prepare("
                INSERT INTO orders (user_id, kode_order, subtotal, ongkir, grand_total, status, metode_bayar, metode_detail, alamat_kirim, catatan)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
            ");
            $stmt->bind_param('isiiissss',
                $userId, $kodeOrder, $subtotal, $ongkir, $grandTotal,
                $form['metode_bayar'], $metodeDetailSave, $alamatLengkap, $form['catatan']
            );
            $stmt->execute();
            $orderId = $koneksi->insert_id;

            // Items
            $stmtItem = $koneksi->prepare("
                INSERT INTO order_items (order_id, product_id, nama_produk, harga, qty, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($cart as $item) {
                $sub = $item['harga'] * $item['qty'];
                $namaProduk = $item['nama'];
                if (($item['variant'] ?? '') !== 'Default' && ($item['variant'] ?? '') !== '') {
                    $namaProduk .= ' (' . $item['variant'] . ')';
                }
                $stmtItem->bind_param('iisiii', $orderId, $item['product_id'], $namaProduk, $item['harga'], $item['qty'], $sub);
                $stmtItem->execute();
            }

            // Kurangi stok
            $stmtStok = $koneksi->prepare("UPDATE products SET stok = GREATEST(stok - ?, 0) WHERE id = ?");
            foreach ($cart as $item) {
                $stmtStok->bind_param('ii', $item['qty'], $item['product_id']);
                $stmtStok->execute();
            }

            $koneksi->commit();
            $_SESSION['cart'] = [];

            if ($form['metode_bayar'] === 'transfer') {
                flash('success', "✅ Pesanan $kodeOrder berhasil dibuat! Silakan selesaikan pembayaran.");
                redirect('payment.php?id=' . $orderId);
            } else {
                flash('success', "✅ Pesanan $kodeOrder berhasil dibuat! Bayar saat pengiriman (COD).");
                redirect('orders.php');
            }
        } catch (Exception $ex) {
            $koneksi->rollback();
            $errors[] = 'Gagal membuat pesanan: ' . $ex->getMessage();
        }
    }
}

$namaUser = $user['nama_lengkap'];
$userFoto = $user['foto_profil'] ?? null;
$initials = strtoupper(substr($namaUser, 0, 2));
$cartCount = array_sum(array_column($cart, 'qty'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — Ngemil Dimsum</title>
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
.page-content{padding:30px}
.page-title{font-size:24px;font-weight:700;color:#333;margin-bottom:5px}
.breadcrumb{font-size:12px;color:#999;margin-bottom:25px}
.breadcrumb a{color:#7a1a2e}

.alert{padding:12px 18px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500;display:flex;align-items:flex-start;gap:10px;line-height:1.5}
.alert-error{background:#fdecec;color:#b02a2a;border:1px solid #f5bcbc}
.alert ul{margin:5px 0 0 18px}

/* Card */
.card{background:#fff;border-radius:15px;padding:22px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,.05);border:1px solid #f0ebe6}
.section-title{font-size:15px;font-weight:700;color:#333;margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid #f0ebe6;display:flex;align-items:center;gap:10px}
.section-title i{color:#7a1a2e}

/* Layout */
.checkout-layout{display:grid;grid-template-columns:1fr 380px;gap:25px;align-items:start}

/* Form */
.form-group{margin-bottom:16px}
.form-group:last-child{margin-bottom:0}
.form-label{display:block;font-size:12px;font-weight:700;color:#444;margin-bottom:6px}
.form-label .req{color:#e74c3c}
.form-input{width:100%;padding:11px 14px;border:1.5px solid #e0d8d0;border-radius:10px;font-family:'Poppins',sans-serif;font-size:13px;background:#fff;color:#333;outline:none;transition:.2s}
.form-input:focus{border-color:#7a1a2e;box-shadow:0 0 0 3px rgba(122,26,46,.1)}
textarea.form-input{resize:vertical;min-height:90px;line-height:1.6;font-family:'Poppins',sans-serif}

/* Payment options */
.payment-options{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.payment-option{padding:16px;border:2px solid #e0d8d0;border-radius:12px;cursor:pointer;transition:.2s;text-align:center;background:#fff;user-select:none}
.payment-option:hover{border-color:#d4a843;background:#fdf9f4}
.payment-option.selected{border-color:#7a1a2e;background:#fdf4f6;box-shadow:0 3px 12px rgba(122,26,46,.1)}
.payment-option input[type="radio"]{display:none}
.payment-option .po-icon{font-size:26px;color:#7a1a2e;margin-bottom:8px;display:block}
.payment-option .po-name{font-size:14px;font-weight:700;color:#333;display:block}
.payment-option .po-desc{font-size:11px;color:#999;display:block;margin-top:3px}

/* Sub-options */
#transferSubOptions{margin-top:20px;padding-top:20px;border-top:2px dashed #e0d8d0;animation:fadeIn .3s}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.sub-group{margin-bottom:16px}
.sub-group:last-child{margin-bottom:0}
.sub-title{font-size:12px;font-weight:700;color:#7a1a2e;margin-bottom:10px;display:flex;align-items:center;gap:7px;text-transform:uppercase;letter-spacing:.5px}
.sub-title i{font-size:14px}
.sub-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px}
.sub-item{display:flex;align-items:center;gap:8px;padding:10px 14px;border:2px solid #e0d8d0;border-radius:9px;cursor:pointer;transition:.2s;background:#fff;user-select:none}
.sub-item:hover{border-color:#d4a843;background:#fdf9f4}
.sub-item input[type="radio"]{accent-color:#7a1a2e;cursor:pointer;flex-shrink:0;width:16px;height:16px}
.sub-item span{font-size:12px;font-weight:600;color:#555;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sub-item input[type="radio"]:checked ~ span{color:#7a1a2e;font-weight:700}
.sub-item:has(input[type="radio"]:checked){border-color:#7a1a2e;background:#fdf4f6}

/* Summary */
.summary-card{position:sticky;top:100px}
.summary-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #f5f0eb;align-items:center}
.summary-item:last-of-type{border-bottom:0}
.summary-item img{width:52px;height:52px;border-radius:10px;object-fit:cover;background:#f8f4f0;flex-shrink:0}
.summary-item-info{flex:1;min-width:0}
.summary-item-name{font-size:13px;font-weight:600;color:#333;margin-bottom:3px;line-height:1.3}
.summary-item-qty{font-size:11px;color:#999}
.summary-item-price{font-size:13px;font-weight:700;color:#7a1a2e;text-align:right;flex-shrink:0}
.summary-total{border-top:2px dashed #e0d8d0;padding-top:14px;margin-top:14px}
.summary-row{display:flex;justify-content:space-between;font-size:13px;color:#666;margin-bottom:8px}
.summary-row.grand{font-size:18px;font-weight:800;color:#7a1a2e;margin-top:12px;padding-top:12px;border-top:2px dashed #e0d8d0;margin-bottom:0}

/* Buttons */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;border-radius:10px;border:none;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:.25s;line-height:1;text-decoration:none}
.btn-primary{background:#7a1a2e;color:#fff}
.btn-primary:hover{background:#5c1020;transform:translateY(-2px);box-shadow:0 6px 18px rgba(122,26,46,.25)}
.btn-block{width:100%}
.btn-lg{padding:14px 26px;font-size:14px}

.wave-decoration{position:fixed;bottom:0;left:240px;right:0;height:60px;pointer-events:none;z-index:10}

@media (max-width:900px){
    .checkout-layout{grid-template-columns:1fr}
    .sidebar{width:60px}
    .sidebar .logo-text,.nav-menu a span{display:none}
    .main-content{margin-left:60px}
    .search-box{width:auto;flex:1}
    .user-info{display:none}
    .wave-decoration{left:60px}
    .page-content{padding:16px}
}
@media (max-width:480px){
    .payment-options{grid-template-columns:1fr}
    .sub-grid{grid-template-columns:1fr 1fr}
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
        <li><a href="cart.php" class="active"><i class="fas fa-shopping-cart"></i> <span>Keranjang</span><span class="nav-badge"><?= $cartCount ?></span></a></li>
        <li><a href="orders.php"><i class="fas fa-clipboard-list"></i> <span>Pesanan Saya</span></a></li>
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
            <a href="cart.php" class="topbar-icon"><i class="fas fa-shopping-cart"></i><span class="badge"><?= $cartCount ?></span></a>
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
        <h1 class="page-title">Checkout</h1>
        <div class="breadcrumb"><a href="dashboard.php">Beranda</a> / <a href="cart.php">Keranjang</a> / Checkout</div>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><b>Ada kesalahan:</b><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            </div>
        <?php endif; ?>

        <form method="post" id="checkoutForm">
            <div class="checkout-layout">

                <!-- ============ KIRI ============ -->
                <div>
                    <!-- INFO PENGIRIMAN -->
                    <div class="card">
                        <div class="section-title"><i class="fas fa-truck"></i> Informasi Pengiriman</div>

                        <div class="form-group">
                            <label class="form-label">Nama Penerima <span class="req">*</span></label>
                            <input type="text" name="nama_penerima" class="form-input" value="<?= e($form['nama_penerima']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">No. HP / WhatsApp <span class="req">*</span></label>
                            <input type="text" name="no_telp" class="form-input" value="<?= e($form['no_telp']) ?>" placeholder="0812xxxxxxxx" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Alamat Lengkap <span class="req">*</span></label>
                            <textarea name="alamat" class="form-input" placeholder="Jalan, No. Rumah, RT/RW, Kelurahan, Kecamatan, Kota" required><?= e($form['alamat']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea name="catatan" class="form-input" placeholder="Contoh: Jangan pakai sambal, kirim siang, dll."><?= e($form['catatan']) ?></textarea>
                        </div>
                    </div>

                    <!-- METODE PEMBAYARAN -->
                    <div class="card">
                        <div class="section-title"><i class="fas fa-credit-card"></i> Metode Pembayaran</div>

                        <div class="payment-options">
                            <!-- COD -->
                            <div class="payment-option <?= $form['metode_bayar']==='cod' ? 'selected' : '' ?>"
                                 id="optCODPayment"
                                 onclick="selectPayment('cod')">
                                <input type="radio" name="metode_bayar" value="cod" <?= $form['metode_bayar']==='cod'?'checked':'' ?>>
                                <i class="fas fa-money-bill-wave po-icon"></i>
                                <span class="po-name">COD</span>
                                <span class="po-desc">Bayar di tempat</span>
                            </div>

                            <!-- TRANSFER -->
                            <div class="payment-option <?= $form['metode_bayar']==='transfer' ? 'selected' : '' ?>"
                                 id="optTransferPayment"
                                 onclick="selectPayment('transfer')">
                                <input type="radio" name="metode_bayar" value="transfer" <?= $form['metode_bayar']==='transfer'?'checked':'' ?>>
                                <i class="fas fa-university po-icon"></i>
                                <span class="po-name">Transfer</span>
                                <span class="po-desc">Bank / QRIS / E-Wallet</span>
                            </div>
                        </div>

                        <!-- SUB OPTIONS TRANSFER -->
                        <div id="transferSubOptions" style="display:<?= $form['metode_bayar']==='transfer' ? 'block' : 'none' ?>;">

                            <!-- BANK -->
                            <div class="sub-group">
                                <div class="sub-title"><i class="fas fa-university"></i> Transfer Bank</div>
                                <div class="sub-grid">
                                    <label class="sub-item">
                                        <input type="radio" name="metode_detail" value="bca" <?= $form['metode_detail']==='bca'?'checked':'' ?>>
                                        <span>BCA</span>
                                    </label>
                                    <label class="sub-item">
                                        <input type="radio" name="metode_detail" value="bri" <?= $form['metode_detail']==='bri'?'checked':'' ?>>
                                        <span>BRI</span>
                                    </label>
                                    <label class="sub-item">
                                        <input type="radio" name="metode_detail" value="mandiri" <?= $form['metode_detail']==='mandiri'?'checked':'' ?>>
                                        <span>Mandiri</span>
                                    </label>
                                    <label class="sub-item">
                                        <input type="radio" name="metode_detail" value="bni" <?= $form['metode_detail']==='bni'?'checked':'' ?>>
                                        <span>BNI</span>
                                    </label>
                                </div>
                            </div>

                            <!-- QRIS -->
                            <div class="sub-group">
                                <div class="sub-title"><i class="fas fa-qrcode"></i> QRIS</div>
                                <div class="sub-grid">
                                    <label class="sub-item" style="grid-column:1/-1;">
                                        <input type="radio" name="metode_detail" value="qris" <?= $form['metode_detail']==='qris'?'checked':'' ?>>
                                        <span>QRIS (Scan Barcode)</span>
                                    </label>
                                </div>
                            </div>

                            <!-- E-WALLET -->
                            <div class="sub-group">
                                <div class="sub-title"><i class="fas fa-wallet"></i> E-Wallet</div>
                                <div class="sub-grid">
                                    <label class="sub-item">
                                        <input type="radio" name="metode_detail" value="ovo" <?= $form['metode_detail']==='ovo'?'checked':'' ?>>
                                        <span>OVO</span>
                                    </label>
                                    <label class="sub-item">
                                        <input type="radio" name="metode_detail" value="dana" <?= $form['metode_detail']==='dana'?'checked':'' ?>>
                                        <span>DANA</span>
                                    </label>
                                    <label class="sub-item">
                                        <input type="radio" name="metode_detail" value="gopay" <?= $form['metode_detail']==='gopay'?'checked':'' ?>>
                                        <span>GoPay</span>
                                    </label>
                                    <label class="sub-item">
                                        <input type="radio" name="metode_detail" value="shopeepay" <?= $form['metode_detail']==='shopeepay'?'checked':'' ?>>
                                        <span>ShopeePay</span>
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ============ KANAN ============ -->
                <div class="summary-card">
                    <div class="card">
                        <div class="section-title"><i class="fas fa-shopping-bag"></i> Ringkasan Pesanan</div>

                        <?php foreach ($cart as $item): ?>
                            <div class="summary-item">
                                <img src="<?= e($item['gambar'] ?: 'https://via.placeholder.com/50?text=?') ?>" alt="">
                                <div class="summary-item-info">
                                    <div class="summary-item-name"><?= e($item['nama']) ?></div>
                                    <div class="summary-item-qty">
                                        <?php if (($item['variant'] ?? '') !== 'Default' && ($item['variant'] ?? '') !== ''): ?>
                                            <?= e($item['variant']) ?> × 
                                        <?php endif; ?>
                                        <?= $item['qty'] ?> pcs
                                    </div>
                                </div>
                                <div class="summary-item-price"><?= rupiah($item['harga'] * $item['qty']) ?></div>
                            </div>
                        <?php endforeach; ?>

                        <div class="summary-total">
                            <div class="summary-row"><span>Subtotal</span><b><?= rupiah($subtotal) ?></b></div>
                            <div class="summary-row"><span>Ongkos Kirim</span><b><?= rupiah($ongkir) ?></b></div>
                            <div class="summary-row grand"><span>Total</span><span><?= rupiah($grandTotal) ?></span></div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top:20px;">
                            <i class="fas fa-check-circle"></i> Buat Pesanan
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<div class="wave-decoration">
    <svg viewBox="0 0 1440 60" preserveAspectRatio="none" style="width:100%;height:100%;">
        <path d="M0,30 Q360,0 720,30 T1440,30 L1440,60 L0,60 Z" fill="#7a1a2e"/>
        <path d="M0,40 Q360,10 720,40 T1440,40 L1440,60 L0,60 Z" fill="#d4a843" opacity="0.6"/>
    </svg>
</div>

<script>
function selectPayment(value) {
    // Reset selected di payment options
    document.getElementById('optCODPayment').classList.remove('selected');
    document.getElementById('optTransferPayment').classList.remove('selected');

    // Set selected
    if (value === 'cod') {
        document.getElementById('optCODPayment').classList.add('selected');
        document.querySelector('input[name="metode_bayar"][value="cod"]').checked = true;
    } else {
        document.getElementById('optTransferPayment').classList.add('selected');
        document.querySelector('input[name="metode_bayar"][value="transfer"]').checked = true;
    }

    // Toggle sub-options
    var sub = document.getElementById('transferSubOptions');
    if (value === 'transfer') {
        sub.style.display = 'block';
    } else {
        sub.style.display = 'none';
        // Reset pilihan detail
        document.querySelectorAll('input[name="metode_detail"]').forEach(function(r) { r.checked = false; });
    }
}

// Highlight sub-item saat dipilih
document.querySelectorAll('input[name="metode_detail"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.sub-item').forEach(function(el) { el.style.borderColor = '#e0d8d0'; el.style.background = '#fff'; });
        var parent = this.closest('.sub-item');
        if (parent) {
            parent.style.borderColor = '#7a1a2e';
            parent.style.background = '#fdf4f6';
        }
    });

    // Set style saat load kalau sudah checked
    if (radio.checked) {
        var parent = radio.closest('.sub-item');
        if (parent) {
            parent.style.borderColor = '#7a1a2e';
            parent.style.background = '#fdf4f6';
        }
    }
});
</script>
</body>
</html>