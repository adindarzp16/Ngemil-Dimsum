<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) redirect('admin/index.php');

$userId = $_SESSION['user_id'];

// Ambil data user langsung dari DB (biar foto profil selalu fresh)
$stmtUser = $koneksi->prepare("SELECT nama_lengkap, foto_profil FROM users WHERE id = ?");
$stmtUser->bind_param('i', $userId);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();

$namaUser = $userData['nama_lengkap'] ?? 'Pelanggan';
$userFoto = $userData['foto_profil'] ?? null;
$initials = strtoupper(substr($namaUser, 0, 2));

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $key    = $_POST['key'] ?? '';

    if ($action === 'update_qty' && isset($_SESSION['cart'][$key])) {
        $newQty = max(1, min(99, (int)($_POST['qty'] ?? 1)));
        $_SESSION['cart'][$key]['qty'] = $newQty;
    } elseif ($action === 'remove' && isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
        flash('success', 'Item berhasil dihapus dari keranjang.');
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        flash('success', 'Keranjang berhasil dikosongkan.');
    }
    redirect('cart.php');
}

$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;
foreach ($cart as $item) $subtotal += $item['harga'] * $item['qty'];
$ongkir = empty($cart) ? 0 : ONGKIR_DEFAULT;
$total  = $subtotal + $ongkir;
$cartCount = array_sum(array_column($cart, 'qty'));

$flashMsg = flash('success');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Keranjang — Ngemil Dimsum</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/customer.css">
<style>
.cart-layout{display:grid;grid-template-columns:1fr 360px;gap:25px;align-items:start}
.cart-item{display:flex;gap:15px;padding:15px;background:#fff;border-radius:12px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,.04);align-items:center}
.cart-item-img{width:80px;height:80px;border-radius:10px;object-fit:cover;background:#f8f4f0;flex-shrink:0}
.cart-item-info{flex:1;min-width:0}
.cart-item-name{font-size:14px;font-weight:600;color:#333;margin-bottom:3px}
.cart-item-variant{font-size:11px;color:#999;margin-bottom:6px;display:inline-block;background:#f8f4f0;padding:2px 8px;border-radius:6px}
.cart-item-price{font-size:13px;font-weight:700;color:#7a1a2e}
.cart-item-qty{display:flex;align-items:center;border:1px solid #e0d8d0;border-radius:8px;overflow:hidden;margin-right:12px}
.cart-item-qty button{width:30px;height:30px;border:none;background:#f8f4f0;cursor:pointer;font-size:14px;color:#555}
.cart-item-qty button:hover{background:#e8e0d8}
.cart-item-qty input{width:40px;height:30px;border:none;text-align:center;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;outline:none}
.cart-item-remove{background:none;border:none;color:#e74c3c;cursor:pointer;font-size:16px;padding:8px;transition:.2s}
.cart-item-remove:hover{transform:scale(1.2)}
.cart-item-subtotal{font-size:14px;font-weight:700;color:#7a1a2e;text-align:right;min-width:100px}
.summary-card{background:#fff;border-radius:15px;padding:22px;box-shadow:0 3px 15px rgba(0,0,0,.06);position:sticky;top:100px}
.summary-title{font-size:16px;font-weight:700;color:#333;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid #f0ebe6}
.summary-row{display:flex;justify-content:space-between;margin-bottom:10px;font-size:13px;color:#666}
.summary-row.total{font-size:16px;font-weight:800;color:#7a1a2e;margin-top:15px;padding-top:15px;border-top:2px dashed #f0ebe6}
.summary-actions{margin-top:20px;display:flex;flex-direction:column;gap:10px}
@media (max-width:900px){.cart-layout{grid-template-columns:1fr}}
@media (max-width:500px){.cart-item{flex-wrap:wrap}.cart-item-subtotal{width:100%;text-align:left;margin-top:8px}}
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
        <li><a href="cart.php" class="active"><i class="fas fa-shopping-cart"></i> <span>Keranjang</span> <?php if ($cartCount>0): ?><span class="nav-badge"><?= $cartCount ?></span><?php endif; ?></a></li>
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
            <a href="cart.php" class="topbar-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cartCount>0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?>
            </a>
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
                <i class="fas fa-chevron-down" style="font-size:12px;color:#999;"></i>
                <div class="user-dropdown">
                    <a href="profil.php"><i class="fas fa-user"></i> Profil</a>
                    <a href="orders.php"><i class="fas fa-clipboard-list"></i> Pesanan</a>
                    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="page-content">
        <h1 class="page-title">Keranjang Belanja</h1>
        <div class="breadcrumb"><a href="dashboard.php">Beranda</a> / Keranjang</div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($flashMsg) ?></div>
        <?php endif; ?>

        <?php if (empty($cart)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Keranjang kamu masih kosong nih.</p>
                    <a href="dashboard.php" class="btn btn-primary" style="margin-top:8px;"><i class="fas fa-th-large"></i> Mulai Belanja</a>
                </div>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div>
                    <?php foreach ($cart as $key => $item): ?>
                        <div class="cart-item">
                            <img src="<?= e($item['gambar'] ?: 'https://via.placeholder.com/80?text=?') ?>" alt="<?= e($item['nama']) ?>" class="cart-item-img">
                            <div class="cart-item-info">
                                <div class="cart-item-name"><?= e($item['nama']) ?></div>
                                <?php if (($item['variant'] ?? '') !== 'Default' && ($item['variant'] ?? '') !== ''): ?>
                                    <span class="cart-item-variant"><?= e($item['variant']) ?></span>
                                <?php endif; ?>
                                <div class="cart-item-price"><?= rupiah($item['harga']) ?></div>
                            </div>
                            <form method="post" style="display:flex;align-items:center;">
                                <input type="hidden" name="action" value="update_qty">
                                <input type="hidden" name="key" value="<?= e($key) ?>">
                                <div class="cart-item-qty">
                                    <button type="submit" name="qty" value="<?= $item['qty']-1 ?>" onclick="return <?= $item['qty'] > 1 ?>">−</button>
                                    <input type="text" value="<?= $item['qty'] ?>" readonly>
                                    <button type="submit" name="qty" value="<?= $item['qty']+1 ?>">+</button>
                                </div>
                            </form>
                            <div class="cart-item-subtotal"><?= rupiah($item['harga'] * $item['qty']) ?></div>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="key" value="<?= e($key) ?>">
                                <button type="submit" class="cart-item-remove" onclick="return confirm('Hapus item ini?')" title="Hapus"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>

                    <form method="post" style="text-align:right;margin-top:10px;">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn btn-outline" onclick="return confirm('Kosongkan semua keranjang?')"><i class="fas fa-broom"></i> Kosongkan Keranjang</button>
                    </form>
                </div>

                <div class="summary-card">
                    <div class="summary-title">Ringkasan Belanja</div>
                    <div class="summary-row"><span>Subtotal (<?= $cartCount ?> item)</span><span><b><?= rupiah($subtotal) ?></b></span></div>
                    <div class="summary-row"><span>Ongkos Kirim</span><span><b><?= rupiah($ongkir) ?></b></span></div>
                    <div class="summary-row total"><span>Total</span><span><?= rupiah($total) ?></span></div>
                    <div class="summary-actions">
                        <a href="checkout.php" class="btn btn-primary btn-lg btn-block"><i class="fas fa-arrow-right"></i> Lanjut ke Checkout</a>
                        <a href="dashboard.php" class="btn btn-outline btn-block"><i class="fas fa-plus"></i> Tambah Menu Lagi</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="wave-decoration">
    <svg viewBox="0 0 1440 60" preserveAspectRatio="none" style="width:100%;height:100%;">
        <path d="M0,30 Q360,0 720,30 T1440,30 L1440,60 L0,60 Z" fill="#7a1a2e"/>
        <path d="M0,40 Q360,10 720,40 T1440,40 L1440,60 L0,60 Z" fill="#d4a843" opacity="0.6"/>
    </svg>
</div>
</body>
</html>