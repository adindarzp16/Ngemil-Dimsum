<?php
/* ============================================================
   NGEMIL DIMSUM — Dashboard Pelanggan (Full Fixed)
   ============================================================ */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) redirect('admin/index.php');

$userId = $_SESSION['user_id'];

// Ambil data user dari DB
$stmtUser = $koneksi->prepare("SELECT nama_lengkap, foto_profil FROM users WHERE id = ?");
$stmtUser->bind_param('i', $userId);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();

$namaUser  = $userData['nama_lengkap'] ?? 'Pelanggan';
$userFoto  = $userData['foto_profil'] ?? null;
$initials  = strtoupper(substr($namaUser, 0, 2));

// ============================================================
// HANDLE ADD TO CART
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty       = max(1, (int)($_POST['qty'] ?? 1));
    $variant   = trim($_POST['variant'] ?? '');

    if ($productId > 0) {
        $stmt = $koneksi->prepare("SELECT id, nama, harga, harga_diskon, gambar, has_variant FROM products WHERE id = ? AND is_active = 1");
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();

        if ($p) {
            $basePrice = $p['harga_diskon'] ?: $p['harga'];

            if ($p['has_variant']) {
                // MODE VARIAN
                $pcsMap = ['3 pcs' => 3, '6 pcs' => 6, '8 pcs' => 8];
                if (!isset($pcsMap[$variant])) {
                    redirect('dashboard.php?id=' . $productId);
                }
                $pcs        = $pcsMap[$variant];
                $perPcs     = $basePrice / 3;
                $hargaFinal = (int)round($perPcs * $pcs);
                $variantKey = $variant;
            } else {
                // MODE TANPA VARIAN
                $hargaFinal = $basePrice;
                $variantKey = 'Default';
                $variant    = 'Default';
            }

            $key = $productId . '|' . $variantKey;

            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            if (isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$key] = [
                    'product_id' => $p['id'],
                    'nama'       => $p['nama'],
                    'harga'      => $hargaFinal,
                    'gambar'     => $p['gambar'],
                    'variant'    => $variant,
                    'qty'        => $qty,
                ];
            }

            $label = ($variant === 'Default') ? '' : ' (' . $variant . ')';
            flash('success', '✅ ' . $p['nama'] . $label . ' × ' . $qty . ' ditambahkan ke keranjang!');
        }
    }
    redirect('dashboard.php' . (isset($_POST['back_to']) ? '?id=' . (int)$_POST['back_to'] : ''));
}

// Cart count
$cartCount = 0;
foreach (($_SESSION['cart'] ?? []) as $item) $cartCount += $item['qty'];

// ============================================================
// DETAIL PRODUK
// ============================================================
$detailId     = (int)($_GET['id'] ?? 0);
$detail       = null;
$detailGaleri = [];
$produkLain   = [];

if ($detailId > 0) {
    $stmt = $koneksi->prepare("
        SELECT p.*, c.nama AS kategori
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->bind_param('i', $detailId);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();

    if ($detail) {
        // === GALERI: semua gambar dari PRODUK INI SENDIRI ===
        $stmt = $koneksi->prepare("
            SELECT gambar FROM product_images
            WHERE product_id = ?
            ORDER BY urutan ASC, id ASC
        ");
        $stmt->bind_param('i', $detailId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $detailGaleri[] = $r['gambar'];

        // Fallback kalau belum ada di product_images
        if (empty($detailGaleri) && !empty($detail['gambar'])) {
            $detailGaleri[] = $detail['gambar'];
        }

        // === PRODUK LAIN untuk tab ===
        $stmtLain = $koneksi->prepare("
            SELECT id, nama, harga, harga_diskon, gambar
            FROM products
            WHERE is_active = 1 AND id != ?
            ORDER BY RAND()
            LIMIT 6
        ");
        $stmtLain->bind_param('i', $detailId);
        $stmtLain->execute();
        $produkLain = $stmtLain->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// ============================================================
// KATALOG
// ============================================================
$q          = trim($_GET['q'] ?? '');
$kategoriId = (int)($_GET['kategori'] ?? 0);

$sql = "SELECT p.*, c.nama AS kategori, c.slug AS kategori_slug
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1";
$params = [];
$types  = '';

if ($q !== '') {
    $sql .= " AND p.nama LIKE ?";
    $params[] = '%' . $q . '%';
    $types .= 's';
}
if ($kategoriId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $kategoriId;
    $types .= 'i';
}
$sql .= " ORDER BY p.id ASC";

$stmt = $koneksi->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$produkList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = $koneksi->query("SELECT * FROM categories ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$flashSuccess = flash('success');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $detail ? e($detail['nama']) . ' — Ngemil Dimsum' : 'Katalog — Ngemil Dimsum' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Poppins',sans-serif;background-color:#f5f0eb;display:flex;min-height:100vh}

/* Sidebar */
.sidebar{width:240px;background:linear-gradient(180deg,#7a1a2e 0%,#5c1020 100%);min-height:100vh;padding:20px 0;position:fixed;left:0;top:0;z-index:100;display:flex;flex-direction:column}
.sidebar::after{content:'';position:absolute;bottom:0;left:0;right:0;height:80px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 80'%3E%3Cpath d='M0,40 Q60,0 120,40 T240,40 L240,80 L0,80 Z' fill='%23f5f0eb'/%3E%3C/svg%3E") no-repeat bottom;background-size:cover}
.logo-container{text-align:center;padding:10px 20px 30px}
.logo-text{color:#fff;font-size:22px;font-weight:800;margin-top:8px;text-shadow:2px 2px 4px rgba(0,0,0,0.3)}
.logo-text span{color:#f0c040}
.nav-menu{list-style:none;padding:0 15px;flex:1}
.nav-menu li{margin-bottom:5px}
.nav-menu a{display:flex;align-items:center;gap:12px;padding:12px 18px;color:#e0d0c0;text-decoration:none;border-radius:10px;font-size:14px;font-weight:500;transition:all 0.3s ease}
.nav-menu a:hover{background:rgba(255,255,255,0.1);color:#fff}
.nav-menu a.active{background:#d4a843;color:#fff;font-weight:600}
.nav-menu a i{width:20px;text-align:center;font-size:16px}
.nav-badge{background:#e74c3c;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;margin-left:auto}

/* Main */
.main-content{margin-left:240px;flex:1;min-height:100vh}

/* Topbar */
.topbar{background:#fff;padding:15px 30px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,0.05);position:sticky;top:0;z-index:50}
.search-box{display:flex;align-items:center;background:#f8f4f0;border-radius:25px;padding:10px 20px;width:350px;border:1px solid #e8e0d8}
.search-box input{border:none;background:transparent;outline:none;font-family:'Poppins',sans-serif;font-size:13px;width:100%;margin-left:10px;color:#555}
.search-box i{color:#999}
.topbar-right{display:flex;align-items:center;gap:20px}
.topbar-icon{position:relative;cursor:pointer;font-size:20px;color:#7a1a2e;transition:transform 0.2s;text-decoration:none}
.topbar-icon:hover{transform:scale(1.1)}
.topbar-icon .badge{position:absolute;top:-8px;right:-8px;background:#e74c3c;color:#fff;font-size:10px;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.user-profile{display:flex;align-items:center;gap:10px;cursor:pointer;position:relative}
.user-avatar{width:38px;height:38px;border-radius:50%;background:#7a1a2e;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:14px;overflow:hidden;flex-shrink:0}
.user-avatar img{width:100%;height:100%;object-fit:cover}
.user-info .name{font-size:13px;font-weight:600;color:#333}
.user-info .role{font-size:11px;color:#999}
.user-dropdown{position:absolute;top:110%;right:0;background:#fff;border-radius:12px;box-shadow:0 5px 20px rgba(0,0,0,0.12);min-width:180px;padding:8px 0;opacity:0;visibility:hidden;transform:translateY(-10px);transition:all 0.3s}
.user-profile:hover .user-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
.user-dropdown a{display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:#555;text-decoration:none;transition:background 0.2s}
.user-dropdown a:hover{background:#f8f4f0;color:#7a1a2e}
.user-dropdown a.logout{color:#e74c3c}
.user-dropdown a.logout:hover{background:#fdecec}

/* Page */
.page-content{padding:30px}
.page-title{font-size:24px;font-weight:700;color:#333;margin-bottom:5px}
.breadcrumb{font-size:12px;color:#999;margin-bottom:25px}
.breadcrumb a{color:#7a1a2e;text-decoration:none}

/* Alert */
.alert{padding:12px 18px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500;display:flex;align-items:center;gap:10px}
.alert-success{background:#e9f7eb;color:#1e6a2a;border:1px solid #bce0c2}

/* Filter */
.filter-chips{display:flex;gap:10px;margin-bottom:25px;overflow-x:auto;padding-bottom:5px}
.filter-chips::-webkit-scrollbar{display:none}
.chip{padding:8px 18px;border-radius:20px;background:#fff;color:#7a1a2e;border:1px solid #e8e0d8;font-size:13px;font-weight:500;text-decoration:none;white-space:nowrap;transition:all 0.3s}
.chip:hover{background:#f8f4f0}
.chip.active{background:#7a1a2e;color:#fff;border-color:#7a1a2e}

/* Product Grid */
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px}
.product-card{background:#fff;border-radius:15px;overflow:hidden;box-shadow:0 3px 15px rgba(0,0,0,0.06);transition:all 0.3s ease;cursor:pointer;position:relative;text-decoration:none;color:inherit;display:block}
.product-card:hover{transform:translateY(-5px);box-shadow:0 8px 25px rgba(122,26,46,0.15)}
.product-card .wishlist-btn{position:absolute;top:12px;right:12px;width:32px;height:32px;background:rgba(255,255,255,0.9);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:5;border:none;transition:all 0.3s}
.product-card .wishlist-btn:hover{background:#e74c3c;color:#fff}
.product-card .wishlist-btn i{font-size:14px;color:#e74c3c}
.product-card .wishlist-btn:hover i{color:#fff}
.product-image{width:100%;height:180px;object-fit:cover;background:#f8f4f0}
.product-info{padding:15px;position:relative}
.product-name{font-size:15px;font-weight:600;color:#333;margin-bottom:5px}
.product-rating{display:flex;align-items:center;gap:5px;margin-bottom:8px}
.product-rating .stars{font-size:12px;color:#ddd}
.product-rating .count{font-size:11px;color:#bbb;font-style:italic}
.product-price{font-size:16px;font-weight:700;color:#7a1a2e}
.product-price-discount{font-size:11px;color:#999;text-decoration:line-through;margin-left:6px;font-weight:400}
.product-card .add-btn{position:absolute;bottom:15px;right:15px;width:32px;height:32px;background:#7a1a2e;color:#fff;border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;transition:all 0.3s;text-decoration:none}
.product-card .add-btn:hover{background:#d4a843;transform:scale(1.1)}

/* Detail */
.detail-container{display:flex;gap:40px;background:#fff;border-radius:20px;padding:30px;box-shadow:0 3px 15px rgba(0,0,0,0.06)}
.detail-image-section{flex:0 0 400px}
.detail-main-image{width:100%;height:350px;border-radius:15px;object-fit:cover;background:#f8f4f0;margin-bottom:15px}
.detail-thumbnails{display:flex;gap:10px;flex-wrap:wrap}
.detail-thumbnails img{width:70px;height:70px;border-radius:10px;object-fit:cover;cursor:pointer;border:2px solid transparent;transition:all 0.3s}
.detail-thumbnails img.active{border-color:#7a1a2e}
.detail-thumbnails img:hover{border-color:#d4a843}
.detail-info-section{flex:1}
.detail-title{font-size:26px;font-weight:700;color:#333;margin-bottom:8px}
.detail-rating{display:flex;align-items:center;gap:8px;margin-bottom:15px}
.detail-rating .stars{font-size:14px;color:#ddd}
.detail-rating .count{font-size:13px;color:#bbb;font-style:italic}
.detail-price{font-size:28px;font-weight:800;color:#7a1a2e;margin-bottom:5px}
.detail-price-old{font-size:15px;color:#999;text-decoration:line-through;margin-bottom:15px}
.detail-description{font-size:14px;color:#666;line-height:1.7;margin-bottom:25px}

.variant-section{margin-bottom:20px}
.variant-label{font-size:14px;font-weight:600;color:#333;margin-bottom:10px}
.variant-options{display:flex;gap:10px;flex-wrap:wrap}
.variant-btn{display:flex;flex-direction:column;align-items:center;padding:10px 22px;border:2px solid #e0d8d0;border-radius:10px;background:#fff;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;color:#555;min-width:92px}
.variant-btn:hover{border-color:#7a1a2e;transform:translateY(-2px);box-shadow:0 4px 12px rgba(122,26,46,.1)}
.variant-btn .v-label{font-size:13px;font-weight:700;color:inherit;line-height:1.2}
.variant-btn .v-price{font-size:11px;color:#999;margin-top:3px;font-weight:500;line-height:1.2}
.variant-btn:hover .v-label,.variant-btn:hover .v-price{color:#7a1a2e}
.variant-btn.active{border-color:#7a1a2e;background:#7a1a2e;color:#fff;box-shadow:0 5px 15px rgba(122,26,46,.3);transform:translateY(-2px)}
.variant-btn.active .v-label{color:#fff}
.variant-btn.active .v-price{color:rgba(255,255,255,.85)}

.stock-info{display:flex;align-items:center;gap:8px;margin-bottom:20px}
.stock-dot{width:8px;height:8px;background:#27ae60;border-radius:50%}
.stock-dot.low{background:#e67e22}
.stock-dot.out{background:#e74c3c}
.stock-text{font-size:13px;color:#27ae60;font-weight:500}
.stock-text.low{color:#e67e22}
.stock-text.out{color:#e74c3c}

.quantity-section{display:flex;align-items:center;gap:15px;margin-bottom:25px;flex-wrap:wrap}
.quantity-label{font-size:14px;font-weight:600;color:#333}
.quantity-control{display:flex;align-items:center;border:1px solid #e0d8d0;border-radius:8px;overflow:hidden}
.quantity-control button{width:36px;height:36px;border:none;background:#f8f4f0;cursor:pointer;font-size:16px;color:#555;transition:background 0.3s}
.quantity-control button:hover{background:#e8e0d8}
.quantity-control input{width:50px;height:36px;border:none;text-align:center;font-family:'Poppins',sans-serif;font-size:14px;font-weight:600;outline:none}
.add-to-cart-btn{display:flex;align-items:center;justify-content:center;gap:10px;background:#7a1a2e;color:#fff;border:none;border-radius:10px;padding:14px 30px;font-family:'Poppins',sans-serif;font-size:15px;font-weight:600;cursor:pointer;transition:all 0.3s;flex:1;min-width:180px}
.add-to-cart-btn:hover{background:#5c1020;transform:translateY(-2px);box-shadow:0 5px 15px rgba(122,26,46,0.3)}
.add-to-cart-btn:disabled{background:#ccc;cursor:not-allowed;transform:none;box-shadow:none}
.features-section{display:flex;gap:30px;margin-top:25px;padding-top:20px;border-top:1px solid #f0ebe6;flex-wrap:wrap}
.feature-item{display:flex;flex-direction:column;align-items:center;gap:8px}
.feature-item i{font-size:22px;color:#7a1a2e}
.feature-item span{font-size:12px;color:#666;font-weight:500}

.detail-tabs{display:flex;margin-top:30px;border-bottom:2px solid #f0ebe6}
.detail-tab{padding:12px 25px;font-size:14px;font-weight:600;color:#999;cursor:pointer;border-bottom:3px solid transparent;transition:all 0.3s;background:none;border-top:none;border-left:none;border-right:none;font-family:'Poppins',sans-serif}
.detail-tab.active{color:#7a1a2e;border-bottom-color:#7a1a2e}
.tab-content{padding:20px 0}

.empty-comment{text-align:center;padding:50px 20px;color:#999}
.empty-comment-icon{width:70px;height:70px;border-radius:50%;background:#f8f4f0;color:#7a1a2e;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 15px;opacity:.7}
.empty-comment h4{font-size:15px;font-weight:700;color:#333;margin-bottom:6px}
.empty-comment p{font-size:13px;color:#999;line-height:1.6}

.other-products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:15px}
.other-product-card{display:block;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #f0ebe6;transition:.3s;color:inherit;text-decoration:none;cursor:pointer}
.other-product-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(122,26,46,.12);border-color:#d4a843}
.other-product-card img{width:100%;height:120px;object-fit:cover;background:#f8f4f0;display:block}
.other-product-info{padding:12px}
.other-product-name{font-size:13px;font-weight:600;color:#333;margin-bottom:4px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:34px}
.other-product-price{font-size:14px;font-weight:800;color:#7a1a2e}
.other-product-price .price-strike{font-size:11px;color:#999;text-decoration:line-through;font-weight:400;margin-left:5px}

.back-btn{display:inline-flex;align-items:center;gap:8px;color:#7a1a2e;text-decoration:none;font-size:14px;font-weight:500;margin-bottom:20px;cursor:pointer;transition:all 0.3s}
.back-btn:hover{color:#d4a843}

.wave-decoration{position:fixed;bottom:0;left:240px;right:0;height:60px;pointer-events:none;z-index:10}

.empty-state{text-align:center;padding:80px 20px;color:#999}
.empty-state i{font-size:60px;margin-bottom:15px;color:#d4a843}
.empty-state p{font-size:14px;margin-bottom:5px}
.empty-state a{color:#7a1a2e;font-weight:600;text-decoration:none}

@media (max-width:768px){
    .sidebar{width:60px}
    .sidebar .logo-text,.nav-menu a span{display:none}
    .main-content{margin-left:60px}
    .detail-container{flex-direction:column}
    .detail-image-section{flex:none}
    .search-box{width:auto;flex:1}
    .user-info{display:none}
    .wave-decoration{left:60px}
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
        <li><a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Katalog</span></a></li>
        <li><a href="index.php"><i class="fas fa-home"></i> <span>Beranda</span></a></li>
        <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> <span>Keranjang</span> <?php if ($cartCount > 0): ?><span class="nav-badge"><?= $cartCount ?></span><?php endif; ?></a></li>
        <li><a href="orders.php"><i class="fas fa-clipboard-list"></i> <span>Pesanan Saya</span></a></li>
        <li><a href="profil.php"><i class="fas fa-user"></i> <span>Profil</span></a></li>
    </ul>
</aside>

<div class="main-content">

    <div class="topbar">
        <form class="search-box" method="get" action="dashboard.php">
            <i class="fas fa-search"></i>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari menu favoritmu...">
        </form>
        <div class="topbar-right">
            <a href="cart.php" class="topbar-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cartCount > 0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?>
            </a>
            <div class="topbar-icon"><i class="fas fa-bell"></i></div>
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
                    <a href="profil.php"><i class="fas fa-user"></i> Profil Saya</a>
                    <a href="orders.php"><i class="fas fa-clipboard-list"></i> Pesanan</a>
                    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($flashSuccess): ?>
        <div style="padding:0 30px;padding-top:20px;">
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($flashSuccess) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($detail): ?>
    <!-- ==================== DETAIL PRODUK ==================== -->
    <div class="page-content">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Kembali ke Katalog</a>
        <h1 class="page-title">Detail Produk</h1>
        <div class="breadcrumb">
            <a href="dashboard.php">Beranda</a> / <a href="dashboard.php">Katalog</a> / <span><?= e($detail['nama']) ?></span>
        </div>

        <form method="post" action="dashboard.php">
            <input type="hidden" name="action" value="add_to_cart">
            <input type="hidden" name="product_id" value="<?= $detail['id'] ?>">
            <input type="hidden" name="back_to" value="<?= $detail['id'] ?>">
            <input type="hidden" name="variant" id="variantInput" value="<?= $detail['has_variant'] ? '' : 'Default' ?>">

            <div class="detail-container">
                <div class="detail-image-section">
                    <?php $mainGambar = $detailGaleri[0] ?? $detail['gambar']; ?>
                    <img src="<?= e($mainGambar ?: 'https://via.placeholder.com/600x400?text=No+Image') ?>"
                         alt="<?= e($detail['nama']) ?>" class="detail-main-image" id="detailImage">

                    <?php if (count($detailGaleri) > 1): ?>
                        <div class="detail-thumbnails">
                            <?php foreach ($detailGaleri as $i => $g): ?>
                                <img src="<?= e($g) ?>"
                                     class="<?= $i === 0 ? 'active' : '' ?>"
                                     onclick="changeImage(this, '<?= e($g) ?>')">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="detail-info-section">
                    <h2 class="detail-title"><?= e($detail['nama']) ?></h2>

                    <div class="detail-rating">
                        <span class="stars">★★★★★</span>
                        <span class="count">Belum ada ulasan</span>
                    </div>

                    <?php $basePriceDetail = $detail['harga_diskon'] ?: $detail['harga']; ?>
                    <?php if ($detail['harga_diskon'] && $detail['harga_diskon'] < $detail['harga']): ?>
                        <div class="detail-price" id="detailPrice"><?= rupiah($detail['harga_diskon']) ?></div>
                        <div class="detail-price-old" id="detailPriceOld"><?= rupiah($detail['harga']) ?></div>
                    <?php else: ?>
                        <div class="detail-price" id="detailPrice"><?= rupiah($detail['harga']) ?></div>
                        <div style="height:20px;"></div>
                    <?php endif; ?>

                    <p class="detail-description"><?= e($detail['deskripsi']) ?></p>

                    <?php if ($detail['has_variant']): ?>
                        <!-- MODE VARIAN -->
                        <div class="variant-section">
                            <div class="variant-label">Pilih Varian <span style="color:#e74c3c;">*</span></div>
                            <div class="variant-options" id="variantOptions">
                                <?php
                                $perPcsDetail = $basePriceDetail / 3;
                                $varianList = [
                                    ['label'=>'3 pcs', 'pcs'=>3],
                                    ['label'=>'6 pcs', 'pcs'=>6],
                                    ['label'=>'8 pcs', 'pcs'=>8],
                                ];
                                foreach ($varianList as $v):
                                    $vp = (int)round($perPcsDetail * $v['pcs']);
                                ?>
                                    <button type="button"
                                            class="variant-btn"
                                            onclick="pilihVarian(this, '<?= e($v['label']) ?>', <?= $vp ?>)">
                                        <span class="v-label"><?= e($v['label']) ?></span>
                                        <span class="v-price"><?= rupiah($vp) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="variant-hint" id="variantHint" style="font-size:11px;color:#e74c3c;margin-top:8px;">
                                <i class="fas fa-info-circle"></i> Pilih varian dulu sebelum tambah ke keranjang
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    $stok = (int)$detail['stok'];
                    $stockClass = $stok === 0 ? 'out' : ($stok < 10 ? 'low' : '');
                    $stockText  = $stok === 0 ? 'Stok Habis' : ($stok < 10 ? "Stok Terbatas ($stok tersisa)" : 'Stok Tersedia');
                    ?>
                    <div class="stock-info">
                        <div class="stock-dot <?= $stockClass ?>"></div>
                        <span class="stock-text <?= $stockClass ?>"><?= $stockText ?></span>
                    </div>

                    <div class="quantity-section">
                        <span class="quantity-label">Jumlah</span>
                        <div class="quantity-control">
                            <button type="button" onclick="changeQty(-1)">−</button>
                            <input type="text" name="qty" value="1" id="qtyInput" readonly>
                            <button type="button" onclick="changeQty(1)">+</button>
                        </div>
                        <?php if ($detail['has_variant']): ?>
                            <button type="submit" class="add-to-cart-btn" id="btnAddCart"
                                    <?= $stok === 0 ? 'disabled' : 'disabled' ?>>
                                <i class="fas fa-shopping-cart"></i>
                                <span id="btnAddCartText"><?= $stok === 0 ? 'Stok Habis' : 'Pilih Varian Dulu' ?></span>
                            </button>
                        <?php else: ?>
                            <button type="submit" class="add-to-cart-btn" id="btnAddCart"
                                    <?= $stok === 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-shopping-cart"></i>
                                <span id="btnAddCartText"><?= $stok === 0 ? 'Stok Habis' : '+ Keranjang' ?></span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="features-section">
                        <div class="feature-item"><i class="fas fa-award"></i><span>Bahan Premium</span></div>
                        <div class="feature-item"><i class="fas fa-fire"></i><span>Rasa Autentik</span></div>
                        <div class="feature-item"><i class="fas fa-shield-alt"></i><span>Kualitas Terjamin</span></div>
                    </div>
                </div>
            </div>
        </form>

        <!-- TAB -->
        <div class="detail-tabs">
            <button type="button" class="detail-tab active" onclick="switchTab(this, 'tab-komentar')">
                <i class="far fa-comment-dots"></i> Komentar
            </button>
            <button type="button" class="detail-tab" onclick="switchTab(this, 'tab-produk-lain')">
                <i class="fas fa-utensils"></i> Produk Lain
            </button>
        </div>

        <div class="tab-content">

            <div id="tab-komentar">
                <div class="empty-comment">
                    <div class="empty-comment-icon"><i class="far fa-comment-dots"></i></div>
                    <h4>Belum Ada Komentar</h4>
                    <p>Jadilah yang pertama memberi ulasan setelah membeli produk ini!</p>
                </div>
            </div>

            <div id="tab-produk-lain" style="display:none;">
                <?php if (empty($produkLain)): ?>
                    <div class="empty-comment">
                        <div class="empty-comment-icon"><i class="fas fa-box-open"></i></div>
                        <h4>Belum Ada Produk Lain</h4>
                        <p>Produk rekomendasi akan muncul di sini.</p>
                    </div>
                <?php else: ?>
                    <div class="other-products-grid">
                        <?php foreach ($produkLain as $pl):
                            $hargaPl = $pl['harga_diskon'] ?: $pl['harga'];
                            $hasDiskonPl = $pl['harga_diskon'] && $pl['harga_diskon'] < $pl['harga'];
                        ?>
                            <a href="dashboard.php?id=<?= $pl['id'] ?>" class="other-product-card">
                                <img src="<?= e($pl['gambar'] ?: 'https://via.placeholder.com/200x150?text=?') ?>" alt="<?= e($pl['nama']) ?>">
                                <div class="other-product-info">
                                    <div class="other-product-name"><?= e($pl['nama']) ?></div>
                                    <div class="other-product-price">
                                        <?= rupiah($hargaPl) ?>
                                        <?php if ($hasDiskonPl): ?>
                                            <span class="price-strike"><?= rupiah($pl['harga']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ==================== KATALOG ==================== -->
    <div class="page-content">
        <h1 class="page-title">Katalog Produk</h1>
        <div class="breadcrumb">
            <a href="dashboard.php">Beranda</a> / <a href="dashboard.php">Katalog</a> / Semua Produk
        </div>

        <div class="filter-chips">
            <a href="dashboard.php<?= $q ? '?q=' . urlencode($q) : '' ?>" class="chip <?= $kategoriId === 0 ? 'active' : '' ?>">Semua</a>
            <?php foreach ($categories as $c):
                $qs = http_build_query(array_filter(['q' => $q, 'kategori' => $c['id']]));
            ?>
                <a href="dashboard.php?<?= $qs ?>" class="chip <?= $kategoriId === $c['id'] ? 'active' : '' ?>"><?= e($c['nama']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($produkList)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>Produk tidak ditemukan<?= $q ? ' untuk "' . e($q) . '"' : '' ?>.</p>
                <a href="dashboard.php">Lihat semua produk →</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($produkList as $p):
                    $hargaFinal = $p['harga_diskon'] ?: $p['harga'];
                    $hasDiskon  = $p['harga_diskon'] && $p['harga_diskon'] < $p['harga'];
                    $stok = (int)$p['stok'];
                ?>
                    <a href="dashboard.php?id=<?= $p['id'] ?>" class="product-card">
                        <button type="button" class="wishlist-btn" onclick="event.preventDefault(); event.stopPropagation(); toggleWishlist(this);">
                            <i class="far fa-heart"></i>
                        </button>
                        <img src="<?= e($p['gambar'] ?: 'https://via.placeholder.com/400x300?text=No+Image') ?>"
                             alt="<?= e($p['nama']) ?>" class="product-image">
                        <div class="product-info">
                            <div class="product-name"><?= e($p['nama']) ?></div>
                            <div class="product-rating">
                                <span class="stars">★★★★★</span>
                                <span class="count">Belum ada ulasan</span>
                            </div>
                            <div class="product-price">
                                <?= rupiah($hargaFinal) ?>
                                <?php if ($hasDiskon): ?>
                                    <span class="product-price-discount"><?= rupiah($p['harga']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($stok > 0): ?>
                            <span class="add-btn" title="Lihat detail"><i class="fas fa-plus"></i></span>
                        <?php else: ?>
                            <span class="add-btn" style="background:#ccc;" title="Stok habis"><i class="fas fa-times"></i></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<div class="wave-decoration">
    <svg viewBox="0 0 1440 60" preserveAspectRatio="none" style="width:100%;height:100%;">
        <path d="M0,30 Q360,0 720,30 T1440,30 L1440,60 L0,60 Z" fill="#7a1a2e"/>
        <path d="M0,40 Q360,10 720,40 T1440,40 L1440,60 L0,60 Z" fill="#d4a843" opacity="0.6"/>
    </svg>
</div>

<script>
/* ============ PILIH VARIAN ============ */
function pilihVarian(btn, variant, price) {
    var all = document.querySelectorAll('.variant-btn');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('active');
    btn.classList.add('active');

    var inputEl = document.getElementById('variantInput');
    if (inputEl) inputEl.value = variant;

    var priceEl = document.getElementById('detailPrice');
    if (priceEl) priceEl.textContent = 'Rp ' + price.toLocaleString('id-ID');

    var oldPriceEl = document.getElementById('detailPriceOld');
    if (oldPriceEl) oldPriceEl.style.display = 'none';

    var hint = document.getElementById('variantHint');
    if (hint) hint.style.display = 'none';

    var btnAdd = document.getElementById('btnAddCart');
    var btnText = document.getElementById('btnAddCartText');
    if (btnAdd && btnText && btnText.textContent.indexOf('Stok Habis') === -1) {
        btnAdd.disabled = false;
        btnText.textContent = '+ Keranjang';
    }
}

/* ============ GANTI FOTO UTAMA ============ */
function changeImage(thumb, src) {
    var all = document.querySelectorAll('.detail-thumbnails img');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('active');
    thumb.classList.add('active');
    document.getElementById('detailImage').src = src;
}

/* ============ QTY ============ */
function changeQty(delta) {
    var input = document.getElementById('qtyInput');
    var val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > 99) val = 99;
    input.value = val;
}

/* ============ SWITCH TAB ============ */
function switchTab(btn, targetId) {
    var tabs = document.querySelectorAll('.detail-tab');
    for (var i = 0; i < tabs.length; i++) tabs[i].classList.remove('active');
    btn.classList.add('active');

    document.getElementById('tab-komentar').style.display    = (targetId === 'tab-komentar')    ? 'block' : 'none';
    document.getElementById('tab-produk-lain').style.display = (targetId === 'tab-produk-lain') ? 'block' : 'none';
}

/* ============ WISHLIST ============ */
function toggleWishlist(btn) {
    var icon = btn.querySelector('i');
    if (icon.classList.contains('far')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
    }
}
</script>
</body>
</html>