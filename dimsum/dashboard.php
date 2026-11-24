<?php
/* ============================================================
   NGEMIL DIMSUM — Dashboard + Voucher Sistem Kembali Otomatis
   - Voucher dipakai → masuk keranjang → tombol "Pakai Promo" HILANG
   - Batal / kosongkan keranjang → voucher KEMBALI
   - Checkout → voucher HANGUS PERMANEN
   ============================================================ */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) redirect('admin/index.php');

$userId = $_SESSION['user_id'];

/* ============================================================
   ⚙️  SETTING COOLDOWN CLAIM VOUCHER
============================================================ */
define('PROMO_CLAIM_COOLDOWN_DAYS', 7);

/* ---------- USER INFO ---------- */
$stmtUser = $koneksi->prepare("SELECT nama_lengkap, foto_profil FROM users WHERE id = ?");
$stmtUser->bind_param('i', $userId);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();
$namaUser  = $userData['nama_lengkap'] ?? 'Pelanggan';
$userFoto  = $userData['foto_profil'] ?? null;
$initials  = strtoupper(substr($namaUser, 0, 2));

/* ---------- FALLBACK IMG ---------- */
$fallbackSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 150">' .
    '<defs><radialGradient id="g1" cx=".5" cy=".35" r=".8">' .
    '<stop offset="0" stop-color="#f7de96"/><stop offset="1" stop-color="#d09a42"/>' .
    '</radialGradient></defs>' .
    '<rect fill="#f5e3c8" width="200" height="150"/>' .
    '<g transform="translate(100 85)">' .
    '<ellipse cx="0" cy="22" rx="55" ry="12" fill="#d4a05a" opacity="0.35"/>' .
    '<path d="M-45 12 C-48 -25, -25 -38, 0 -38 C25 -38, 48 -25, 45 12 Z" fill="#f0d49c" stroke="#c98a3a" stroke-width="2"/>' .
    '<circle cx="0" cy="-2" r="7" fill="url(#g1)"/>' .
    '<circle cx="-16" cy="4" r="6" fill="#dcae52"/>' .
    '<circle cx="16" cy="4" r="6" fill="#dcae52"/>' .
    '</g></svg>';
$fallbackImg = "data:image/svg+xml;utf8," . rawurlencode($fallbackSvg);

/* ============================================================
   🔥 AUTO-RESTORE VOUCHER
   ------------------------------------------------------------
   Kembalikan voucher yang status-nya 'in_cart' TAPI sudah tidak ada
   lagi di keranjang session user (misal user hapus item dari cart,
   atau session cart kosong / user ganti device).
============================================================ */
$promoIdsDiKeranjang = [];
foreach (($_SESSION['cart'] ?? []) as $item) {
    if (!empty($item['promo']) && !empty($item['promo_id'])) {
        $promoIdsDiKeranjang[] = (int)$item['promo_id'];
    }
}
$promoIdsDiKeranjang = array_unique($promoIdsDiKeranjang);

if (!empty($promoIdsDiKeranjang)) {
    $inQuery = implode(',', array_map('intval', $promoIdsDiKeranjang));
    $koneksi->query("UPDATE user_promo_claims 
                     SET status='claimed', used_at=NULL 
                     WHERE user_id = $userId 
                       AND status='in_cart' 
                       AND promo_id NOT IN ($inQuery)");
} else {
    // Cart kosong → semua voucher 'in_cart' dikembalikan ke 'claimed'
    $koneksi->query("UPDATE user_promo_claims 
                     SET status='claimed', used_at=NULL 
                     WHERE user_id = $userId AND status='in_cart'");
}

/* ============================================================
   AMBIL SEMUA VOUCHER + STATUS USER
============================================================ */
$semuaPromo = $koneksi->query("SELECT * FROM promos WHERE is_active = 1 ORDER BY persen_diskon ASC")->fetch_all(MYSQLI_ASSOC);

$promoStatus = [];
foreach ($semuaPromo as $pr) {
    $stmt = $koneksi->prepare("SELECT claimed_at, status, used_at FROM user_promo_claims WHERE user_id = ? AND promo_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('ii', $userId, $pr['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $status = [
        'claimed'    => false,
        'bisa_claim' => true,
        'in_cart'    => false,
        'used'       => false,
        'sisa_hari'  => 0,
        'next_claim' => null,
    ];

    if ($row) {
        $status['claimed'] = true;
        $lastClaim   = strtotime($row['claimed_at']);
        $cooldownSec = PROMO_CLAIM_COOLDOWN_DAYS * 86400;
        $bolehLagi   = (time() - $lastClaim) >= $cooldownSec;

        if ($row['status'] === 'in_cart') {
            $status['in_cart'] = true;
            $status['bisa_claim'] = false;
        } elseif ($row['status'] === 'used') {
            $status['used'] = true;
            if ($bolehLagi) {
                $status['bisa_claim'] = true;
            } else {
                $status['bisa_claim'] = false;
                $status['sisa_hari'] = ceil(($lastClaim + $cooldownSec - time()) / 86400);
                $status['next_claim'] = $lastClaim + $cooldownSec;
            }
        } else {
            if ($bolehLagi) {
                $status['bisa_claim'] = true;
            } else {
                $status['bisa_claim'] = false;
                $status['sisa_hari'] = ceil(($lastClaim + $cooldownSec - time()) / 86400);
                $status['next_claim'] = $lastClaim + $cooldownSec;
            }
        }
    }
    $promoStatus[$pr['id']] = $status;
}

/* ============================================================
   Voucher yang TERSEDIA untuk dipakai di tombol "Pakai Promo"
   ------------------------------------------------------------
   - Sudah diklaim
   - TIDAK sedang di keranjang (in_cart)
   - TIDAK sudah hangus (used)
============================================================ */
$voucherDimiliki = [];
foreach ($semuaPromo as $pr) {
    $st = $promoStatus[$pr['id']] ?? null;
    if (!$st) continue;
    if ($st['claimed'] && !$st['used'] && !$st['in_cart']) {
        $voucherDimiliki[] = $pr;
    }
}

/* Promo aktif (session) */
$activePromoId = (int)($_SESSION['active_promo_id'] ?? 0);
$promoAktif    = null;
foreach ($semuaPromo as $pr) {
    if ((int)$pr['id'] === $activePromoId) { $promoAktif = $pr; break; }
}
if (!$promoAktif) { $activePromoId = 0; unset($_SESSION['active_promo_id']); }
$usePromo = $activePromoId > 0;

function hitungDiskonPromo($harga, $promo) {
    if (!$promo) return 0;
    $d = $harga * $promo['persen_diskon'] / 100;
    if (!empty($promo['max_diskon']) && $promo['max_diskon'] > 0) $d = min($d, $promo['max_diskon']);
    return (int)round($d);
}

/* ---------- BANNERS ---------- */
$banners = [];
$resB = $koneksi->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY urutan ASC, id ASC");
if ($resB) $banners = $resB->fetch_all(MYSQLI_ASSOC);

/* ============================================================
   AJAX — CLAIM VOUCHER
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'claim_voucher') {
    $promoId = (int)($_POST['promo_id'] ?? 0);
    header('Content-Type: application/json');

    $target = null;
    foreach ($semuaPromo as $pr) if ((int)$pr['id'] === $promoId) { $target = $pr; break; }

    if (!$target) { echo json_encode(['status'=>'error','message'=>'Voucher tidak ditemukan.']); exit; }

    $st = $promoStatus[$promoId] ?? ['claimed'=>false,'bisa_claim'=>true];

    if (!$st['bisa_claim']) {
        echo json_encode([
            'status'  => 'cooldown',
            'message' => 'Belum bisa klaim. Coba lagi dalam ' . $st['sisa_hari'] . ' hari.',
        ]);
        exit;
    }

    if ($st['claimed']) {
        $stmt = $koneksi->prepare("UPDATE user_promo_claims SET claimed_at = NOW(), status='claimed', used_at=NULL WHERE user_id = ? AND promo_id = ?");
        $stmt->bind_param('ii', $userId, $promoId);
        $stmt->execute();
    } else {
        $stmt = $koneksi->prepare("INSERT INTO user_promo_claims (user_id, promo_id, status) VALUES (?, ?, 'claimed')");
        $stmt->bind_param('ii', $userId, $promoId);
        $stmt->execute();
    }

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Voucher ' . $target['persen_diskon'] . '% berhasil diklaim! Sekarang bisa dipakai di halaman produk.',
        'promo_id' => $promoId,
        'persen'   => $target['persen_diskon'],
    ]);
    exit;
}

/* ============================================================
   AJAX — PILIH VOUCHER
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pilih_voucher') {
    $promoId = (int)($_POST['promo_id'] ?? 0);
    header('Content-Type: application/json');

    if ($promoId === 0) {
        unset($_SESSION['active_promo_id']);
        echo json_encode(['status'=>'off','message'=>'Voucher dilepas. Harga kembali normal.']);
        exit;
    }

    $st = $promoStatus[$promoId] ?? null;
    if (!$st || !$st['claimed'] || $st['used'] || $st['in_cart']) {
        echo json_encode(['status'=>'error','message'=>'Voucher ini tidak tersedia.']);
        exit;
    }

    $target = null;
    foreach ($semuaPromo as $pr) if ((int)$pr['id'] === $promoId) { $target = $pr; break; }

    $_SESSION['active_promo_id'] = $promoId;
    echo json_encode([
        'status'   => 'on',
        'message'  => 'Voucher ' . $target['persen_diskon'] . '% dipakai!',
        'promo_id' => $promoId,
        'persen'   => $target['persen_diskon'],
    ]);
    exit;
}

/* ============================================================
   AJAX — RESET VOUCHER dari session (dari tombol Voucher topbar)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_voucher') {
    unset($_SESSION['active_promo_id']);
    header('Content-Type: application/json');
    echo json_encode(['status'=>'ok']);
    exit;
}

/* ============================================================
   ADD TO CART — Voucher ditandai 'in_cart' (belum hangus)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    $productId  = (int)($_POST['product_id'] ?? 0);
    $qty        = max(1, (int)($_POST['qty'] ?? 1));
    $variant    = trim($_POST['variant'] ?? '');
    $pakaiPromo = !empty($_POST['pakai_promo']);

    if ($productId > 0) {
        $stmt = $koneksi->prepare("SELECT id, nama, harga, harga_diskon, gambar, has_variant FROM products WHERE id = ? AND is_active = 1");
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();

        if ($p) {
            $basePrice = $p['harga_diskon'] ?: $p['harga'];

            if ($p['has_variant']) {
                $pcsMap = ['3 pcs' => 3, '6 pcs' => 6, '8 pcs' => 8];
                if (!isset($pcsMap[$variant])) redirect('dashboard.php?id=' . $productId);
                $pcs        = $pcsMap[$variant];
                $perPcs     = $basePrice / 3;
                $hargaFinal = (int)round($perPcs * $pcs);
                $variantKey = $variant;
            } else {
                $hargaFinal = $basePrice;
                $variantKey = 'Default';
                $variant    = 'Default';
            }

            if ($pakaiPromo && $promoAktif) {
                $diskonPromo = hitungDiskonPromo($hargaFinal, $promoAktif);
                $hargaFinal  = max(0, $hargaFinal - $diskonPromo);
            }

            $key = $productId . '|' . $variantKey . '|' . ($pakaiPromo ? 'promo' . $activePromoId : 'normal');
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
                    'promo'      => $pakaiPromo ? 1 : 0,
                    'promo_id'   => $pakaiPromo ? $activePromoId : 0,
                ];
            }

            $label = ($variant === 'Default') ? '' : ' (' . $variant . ')';
            if ($pakaiPromo && $promoAktif) {
                flash('success', $p['nama'] . $label . ' x ' . $qty . ' ditambahkan. Voucher ' . $promoAktif['persen_diskon'] . '% terpakai di keranjang ini.');
            } else {
                flash('success', $p['nama'] . $label . ' x ' . $qty . ' ditambahkan ke keranjang.');
            }

            /* 🔥 Tandai voucher sebagai 'in_cart' (belum hangus) */
            if ($pakaiPromo && $activePromoId > 0) {
                $stmtUpd = $koneksi->prepare("UPDATE user_promo_claims SET status='in_cart' WHERE user_id = ? AND promo_id = ?");
                $stmtUpd->bind_param('ii', $userId, $activePromoId);
                $stmtUpd->execute();
            }
        }
    }

    unset($_SESSION['active_promo_id']);
    redirect('dashboard.php' . (isset($_POST['back_to']) ? '?id=' . (int)$_POST['back_to'] : ''));
}

/* ---------- CART COUNT ---------- */
$cartCount = 0;
foreach (($_SESSION['cart'] ?? []) as $item) $cartCount += (int)$item['qty'];

/* ---------- DETAIL PRODUK ---------- */
$detailId     = (int)($_GET['id'] ?? 0);
$detail       = null;
$detailGaleri = [];
$produkLain   = [];

if ($detailId > 0) {
    $stmt = $koneksi->prepare("SELECT p.*, c.nama AS kategori FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.is_active = 1");
    $stmt->bind_param('i', $detailId);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();

    if ($detail) {
        $stmt = $koneksi->prepare("SELECT gambar FROM product_images WHERE product_id = ? ORDER BY urutan ASC, id ASC");
        $stmt->bind_param('i', $detailId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $detailGaleri[] = $r['gambar'];
        if (empty($detailGaleri) && !empty($detail['gambar'])) $detailGaleri[] = $detail['gambar'];

        $stmtLain = $koneksi->prepare("SELECT id, nama, harga, harga_diskon, gambar FROM products WHERE is_active = 1 AND id != ? ORDER BY RAND() LIMIT 6");
        $stmtLain->bind_param('i', $detailId);
        $stmtLain->execute();
        $produkLain = $stmtLain->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

/* ---------- KATALOG ---------- */
$q          = trim($_GET['q'] ?? '');
$kategoriId = (int)($_GET['kategori'] ?? 0);

$sql = "SELECT p.*, c.nama AS kategori, c.slug AS kategori_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1";
$params = []; $types = '';
if ($q !== '') { $sql .= " AND p.nama LIKE ?"; $params[] = '%' . $q . '%'; $types .= 's'; }
if ($kategoriId > 0) { $sql .= " AND p.category_id = ?"; $params[] = $kategoriId; $types .= 'i'; }
$sql .= " ORDER BY p.id DESC";

$stmt = $koneksi->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$produkList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = $koneksi->query("SELECT * FROM categories ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$flashMsg = flash('success');
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

.sidebar{width:240px;background:linear-gradient(180deg,#7a1a2e 0%,#5c1020 100%);min-height:100vh;padding:20px 0;position:fixed;left:0;top:0;z-index:100;display:flex;flex-direction:column}
.logo-container{text-align:center;padding:10px 20px 30px}
.logo-text{color:#fff;font-size:22px;font-weight:800;margin-top:8px}
.logo-text span{color:#f0c040}
.nav-menu{list-style:none;padding:0 15px;flex:1}
.nav-menu li{margin-bottom:5px}
.nav-menu a{display:flex;align-items:center;gap:12px;padding:12px 18px;color:#e0d0c0;text-decoration:none;border-radius:10px;font-size:14px;font-weight:500;transition:all .3s}
.nav-menu a:hover{background:rgba(255,255,255,.1);color:#fff}
.nav-menu a.active{background:#d4a843;color:#fff;font-weight:600}
.nav-menu a i{width:20px;text-align:center;font-size:16px}
.nav-badge{background:#e74c3c;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;margin-left:auto}

.main-content{margin-left:240px;flex:1;min-height:100vh}

.topbar{background:#fff;padding:15px 30px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.05);position:sticky;top:0;z-index:50;gap:15px}
.search-box{display:flex;align-items:center;background:#f8f4f0;border-radius:25px;padding:10px 20px;width:350px;border:1px solid #e8e0d8}
.search-box input{border:none;background:transparent;outline:none;font-family:'Poppins',sans-serif;font-size:13px;width:100%;margin-left:10px;color:#555}
.search-box i{color:#999}
.topbar-right{display:flex;align-items:center;gap:15px}

.btn-voucher{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#d4a843,#e88a0e);color:#fff;border:none;padding:10px 18px;border-radius:24px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(212,168,67,.35);transition:.25s}
.btn-voucher:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(212,168,67,.5)}
.btn-voucher i{font-size:14px}

.topbar-icon{position:relative;cursor:pointer;font-size:20px;color:#7a1a2e;text-decoration:none;transition:.2s}
.topbar-icon:hover{transform:scale(1.1)}
.topbar-icon .badge{position:absolute;top:-8px;right:-8px;background:#e74c3c;color:#fff;font-size:10px;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center}

.user-profile{display:flex;align-items:center;gap:10px;cursor:pointer;position:relative}
.user-avatar{width:38px;height:38px;border-radius:50%;background:#7a1a2e;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:14px;overflow:hidden;flex-shrink:0}
.user-avatar img{width:100%;height:100%;object-fit:cover}
.user-info .name{font-size:13px;font-weight:600;color:#333}
.user-info .role{font-size:11px;color:#999}
.user-dropdown{position:absolute;top:110%;right:0;background:#fff;border-radius:12px;box-shadow:0 5px 20px rgba(0,0,0,.12);min-width:180px;padding:8px 0;opacity:0;visibility:hidden;transform:translateY(-10px);transition:.3s;z-index:60}
.user-profile:hover .user-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
.user-dropdown a{display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:#555;text-decoration:none;transition:.2s}
.user-dropdown a:hover{background:#f8f4f0;color:#7a1a2e}
.user-dropdown a.logout{color:#e74c3c}

.page-content{padding:30px}
.page-title{font-size:24px;font-weight:700;color:#333;margin-bottom:5px}
.breadcrumb{font-size:12px;color:#999;margin-bottom:25px}
.breadcrumb a{color:#7a1a2e;text-decoration:none}

.alert{padding:12px 18px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500;display:flex;align-items:center;gap:10px}
.alert-success{background:#e9f7eb;color:#1e6a2a;border:1px solid #bce0c2}

.modal-overlay{position:fixed;inset:0;background:rgba(30,10,20,.55);backdrop-filter:blur(3px);z-index:900;opacity:0;visibility:hidden;transition:.3s;display:flex;align-items:center;justify-content:center;padding:20px}
.modal-overlay.show{opacity:1;visibility:visible}
.modal-voucher{background:#fff;border-radius:20px;max-width:640px;width:100%;max-height:85vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);transform:scale(.9);transition:.3s}
.modal-overlay.show .modal-voucher{transform:scale(1)}
.modal-header{background:linear-gradient(135deg,#7a1a2e,#5c1020);color:#fff;padding:22px 26px;display:flex;align-items:center;gap:14px;position:relative}
.modal-header-icon{width:48px;height:48px;background:rgba(255,214,128,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#ffd680;font-size:22px;flex-shrink:0}
.modal-header-text h3{font-size:18px;font-weight:800;margin-bottom:3px}
.modal-header-text p{font-size:12px;opacity:.85}
.modal-close{position:absolute;top:16px;right:16px;background:rgba(255,255,255,.15);color:#fff;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:15px;transition:.2s}
.modal-close:hover{background:rgba(255,255,255,.3);transform:rotate(90deg)}
.modal-body{padding:22px 26px;overflow-y:auto;flex:1}
.modal-body::-webkit-scrollbar{width:6px}
.modal-body::-webkit-scrollbar-thumb{background:#ddd;border-radius:10px}

.voucher-list{display:flex;flex-direction:column;gap:14px}
.voucher-item{border:2px solid #f0ebe6;border-radius:14px;padding:16px;display:flex;align-items:center;gap:16px;transition:.25s;position:relative;background:#fff}
.voucher-item.claimed{border-color:#27ae60;background:#f5fbf7}
.voucher-item.in-cart{border-color:#e67e22;background:#fff9ee}

.voucher-left{flex-shrink:0;width:70px;height:70px;background:linear-gradient(135deg,#7a1a2e,#5c1020);border-radius:14px;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;overflow:hidden}
.voucher-left::before{content:'';position:absolute;width:30px;height:30px;background:radial-gradient(circle,rgba(255,214,128,.35),transparent 70%);top:-10px;right:-10px}
.voucher-left .persen{font-size:22px;font-weight:800;line-height:1;color:#ffd680}
.voucher-left .lbl{font-size:9px;text-transform:uppercase;letter-spacing:.5px;opacity:.9;margin-top:2px}

.voucher-mid{flex:1;min-width:0}
.voucher-mid h4{font-size:15px;font-weight:700;color:#333;margin-bottom:3px}
.voucher-mid p{font-size:12px;color:#888;line-height:1.5;margin-bottom:6px}
.voucher-tags{display:flex;gap:6px;flex-wrap:wrap}
.voucher-tag{font-size:10px;padding:3px 9px;border-radius:20px;font-weight:600;background:#f8f4f0;color:#666}
.voucher-tag.hemat{background:#fff7e3;color:#a07d10}
.voucher-tag.min{background:#eef5ff;color:#2c6bc4}
.voucher-tag.status-aktif{background:#e9f7eb;color:#1e6a2a}
.voucher-tag.status-cart{background:#fff1e6;color:#c9671c}

.voucher-right{flex-shrink:0;display:flex;flex-direction:column;gap:6px;align-items:flex-end;min-width:130px}
.btn-voucher-action{padding:9px 16px;border-radius:10px;border:none;font-family:'Poppins',sans-serif;font-size:12px;font-weight:700;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;width:100%;justify-content:center}
.btn-voucher-action.claim{background:#7a1a2e;color:#fff}
.btn-voucher-action.claim:hover{background:#5c1020;transform:translateY(-1px)}
.btn-voucher-action.cooldown{background:#f0ebe6;color:#999;cursor:not-allowed}
.btn-voucher-action.reclaim{background:#27ae60;color:#fff}
.btn-voucher-action.reclaim:hover{background:#1e8a4b;transform:translateY(-1px)}

.modal-pickvoucher{background:#fff;border-radius:18px;max-width:520px;width:100%;max-height:80vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);transform:scale(.9);transition:.3s}
.modal-overlay.show .modal-pickvoucher{transform:scale(1)}

.vpick-item{display:flex;align-items:center;gap:14px;padding:14px 16px;border:2px solid #f0ebe6;border-radius:12px;cursor:pointer;transition:.2s;margin-bottom:10px;background:#fff}
.vpick-item:hover{border-color:#d4a843;background:#fffdf5}
.vpick-item.selected{border-color:#27ae60;background:#f5fbf7;box-shadow:0 0 0 3px rgba(39,174,96,.1)}
.vpick-badge{flex-shrink:0;width:58px;height:58px;border-radius:12px;background:linear-gradient(135deg,#7a1a2e,#5c1020);color:#ffd680;display:flex;flex-direction:column;align-items:center;justify-content:center}
.vpick-badge .p{font-size:18px;font-weight:800;line-height:1}
.vpick-badge .l{font-size:9px;text-transform:uppercase;opacity:.9}
.vpick-info{flex:1;min-width:0}
.vpick-info h4{font-size:14px;font-weight:700;color:#333;margin-bottom:3px}
.vpick-info p{font-size:11px;color:#888;line-height:1.4}
.vpick-check{flex-shrink:0;width:26px;height:26px;border-radius:50%;border:2px solid #ddd;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;transition:.2s}
.vpick-item.selected .vpick-check{background:#27ae60;border-color:#27ae60}

.vpick-none{display:flex;align-items:center;gap:14px;padding:14px 16px;border:2px dashed #e0d8d0;border-radius:12px;cursor:pointer;transition:.2s;background:#fafafa}
.vpick-none:hover{border-color:#7a1a2e;background:#fff}
.vpick-none.selected{border-style:solid;border-color:#e74c3c;background:#fef2f2}
.vpick-none-icon{flex-shrink:0;width:58px;height:58px;border-radius:12px;background:#f0ebe6;color:#999;display:flex;align-items:center;justify-content:center;font-size:20px}
.vpick-none.selected .vpick-none-icon{background:#fce4e4;color:#e74c3c}

.action-buttons{display:flex;gap:12px;flex-wrap:wrap;width:100%}
.btn-pakai-promo{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,#d4a843,#e88a0e);color:#fff;border:none;border-radius:10px;padding:14px 22px;font-family:'Poppins',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:.25s;box-shadow:0 4px 12px rgba(212,168,67,.3);white-space:nowrap;position:relative;min-width:150px}
.btn-pakai-promo:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(212,168,67,.5)}
.btn-pakai-promo.active{background:linear-gradient(135deg,#27ae60,#1e8a4b);box-shadow:0 4px 12px rgba(39,174,96,.35)}
.btn-pakai-promo.active:hover{box-shadow:0 6px 18px rgba(39,174,96,.5)}
.btn-pakai-promo .icon-check{font-size:16px}
.btn-pakai-promo .persen-label{background:rgba(255,255,255,.25);padding:2px 8px;border-radius:8px;font-size:12px;font-weight:800}

.banner-carousel{position:relative;border-radius:18px;overflow:hidden;box-shadow:0 8px 28px rgba(122,26,46,.18);margin-bottom:22px;background:#5c1020}
.banner-track{display:flex;transition:transform .55s cubic-bezier(.65,.05,.36,1)}
.banner-slide{min-width:100%;position:relative;display:block}
.banner-slide img{width:100%;height:280px;object-fit:cover;display:block}
.banner-overlay{position:absolute;left:0;right:0;bottom:0;padding:20px 28px;background:linear-gradient(180deg,transparent,rgba(60,10,20,.85));color:#fff;pointer-events:none}
.banner-overlay h3{font-size:20px;font-weight:800;margin-bottom:3px}
.banner-overlay p{font-size:12px;opacity:.9}
.banner-arrow{position:absolute;top:50%;transform:translateY(-50%);width:42px;height:42px;background:rgba(255,255,255,.9);border:none;border-radius:50%;cursor:pointer;color:#7a1a2e;font-size:15px;display:flex;align-items:center;justify-content:center;transition:.25s;z-index:5;box-shadow:0 4px 12px rgba(0,0,0,.15)}
.banner-arrow:hover{background:#fff;transform:translateY(-50%) scale(1.1)}
.banner-arrow.prev{left:14px}
.banner-arrow.next{right:14px}
.banner-dots{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:5}
.banner-dot{width:9px;height:9px;border-radius:50%;background:rgba(255,255,255,.55);cursor:pointer;transition:.25s;border:none;padding:0}
.banner-dot.active{background:#ffd680;width:26px;border-radius:6px}

.filter-chips{display:flex;gap:10px;margin-bottom:25px;overflow-x:auto;padding-bottom:5px}
.filter-chips::-webkit-scrollbar{display:none}
.chip{padding:8px 18px;border-radius:20px;background:#fff;color:#7a1a2e;border:1px solid #e8e0d8;font-size:13px;font-weight:500;text-decoration:none;white-space:nowrap;transition:.3s}
.chip.active{background:#7a1a2e;color:#fff;border-color:#7a1a2e}

.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px}
.product-card{background:#fff;border-radius:15px;overflow:hidden;box-shadow:0 3px 15px rgba(0,0,0,.06);transition:.3s;cursor:pointer;position:relative;text-decoration:none;color:inherit;display:block}
.product-card:hover{transform:translateY(-5px);box-shadow:0 8px 25px rgba(122,26,46,.15)}
.product-card .wishlist-btn{position:absolute;top:12px;right:12px;width:32px;height:32px;background:rgba(255,255,255,.9);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:5;border:none;transition:.3s}
.product-card .wishlist-btn i{font-size:14px;color:#e74c3c}
.product-image{width:100%;height:180px;object-fit:cover;background:#f8f4f0}
.product-info{padding:15px;position:relative}
.product-name{font-size:15px;font-weight:600;color:#333;margin-bottom:5px}
.product-rating{display:flex;align-items:center;gap:5px;margin-bottom:8px}
.product-rating .stars{font-size:12px;color:#ddd}
.product-rating .count{font-size:11px;color:#bbb;font-style:italic}
.product-price{font-size:16px;font-weight:700;color:#7a1a2e;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.product-price .strike-normal{font-size:12px;color:#999;text-decoration:line-through;font-weight:400}
.product-card .add-btn{position:absolute;bottom:15px;right:15px;width:32px;height:32px;background:#7a1a2e;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;transition:.3s}
.product-card .add-btn:hover{background:#d4a843;transform:scale(1.1)}

.detail-container{display:flex;gap:40px;background:#fff;border-radius:20px;padding:30px;box-shadow:0 3px 15px rgba(0,0,0,.06)}
.detail-image-section{flex:0 0 400px}
.detail-main-image{width:100%;height:350px;border-radius:15px;object-fit:cover;background:#f8f4f0;margin-bottom:15px}
.detail-thumbnails{display:flex;gap:10px;flex-wrap:wrap}
.detail-thumbnails img{width:70px;height:70px;border-radius:10px;object-fit:cover;cursor:pointer;border:2px solid transparent;transition:.3s}
.detail-thumbnails img.active{border-color:#7a1a2e}
.detail-info-section{flex:1}
.detail-title{font-size:26px;font-weight:700;color:#333;margin-bottom:8px}
.detail-rating{display:flex;align-items:center;gap:8px;margin-bottom:15px}
.detail-rating .stars{font-size:14px;color:#ddd}
.detail-rating .count{font-size:13px;color:#bbb;font-style:italic}
.detail-price{font-size:28px;font-weight:800;color:#7a1a2e;margin-bottom:5px;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
.detail-price .promo-price{color:#27ae60}
.detail-description{font-size:14px;color:#666;line-height:1.7;margin-bottom:25px}

.variant-section{margin-bottom:20px}
.variant-label{font-size:14px;font-weight:600;color:#333;margin-bottom:10px}
.variant-options{display:flex;gap:10px;flex-wrap:wrap}
.variant-btn{display:flex;flex-direction:column;align-items:center;padding:10px 22px;border:2px solid #e0d8d0;border-radius:10px;background:#fff;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:.2s;color:#555;min-width:92px}
.variant-btn:hover{border-color:#7a1a2e;transform:translateY(-2px)}
.variant-btn .v-label{font-size:13px;font-weight:700;line-height:1.2}
.variant-btn .v-price{font-size:11px;color:#999;margin-top:3px}
.variant-btn.active{border-color:#7a1a2e;background:#7a1a2e;color:#fff}
.variant-btn.active .v-label{color:#fff}
.variant-btn.active .v-price{color:rgba(255,255,255,.85)}

.stock-info{display:flex;align-items:center;gap:8px;margin-bottom:20px}
.stock-dot{width:8px;height:8px;background:#27ae60;border-radius:50%}
.stock-dot.low{background:#e67e22}
.stock-dot.out{background:#e74c3c}
.stock-text{font-size:13px;color:#27ae60;font-weight:500}
.stock-text.low{color:#e67e22}
.stock-text.out{color:#e74c3c}

.quantity-section{display:flex;align-items:center;gap:15px;margin-bottom:20px;flex-wrap:wrap}
.quantity-label{font-size:14px;font-weight:600;color:#333}
.quantity-control{display:flex;align-items:center;border:1px solid #e0d8d0;border-radius:8px;overflow:hidden}
.quantity-control button{width:36px;height:36px;border:none;background:#f8f4f0;cursor:pointer;font-size:16px;color:#555}
.quantity-control input{width:50px;height:36px;border:none;text-align:center;font-family:'Poppins',sans-serif;font-size:14px;font-weight:600;outline:none}

.add-to-cart-btn{display:flex;align-items:center;justify-content:center;gap:10px;background:#7a1a2e;color:#fff;border:none;border-radius:10px;padding:14px 30px;font-family:'Poppins',sans-serif;font-size:15px;font-weight:600;cursor:pointer;transition:.3s;flex:1;min-width:180px}
.add-to-cart-btn:hover{background:#5c1020;transform:translateY(-2px)}
.add-to-cart-btn:disabled{background:#ccc;cursor:not-allowed;transform:none}

.features-section{display:flex;gap:30px;margin-top:25px;padding-top:20px;border-top:1px solid #f0ebe6;flex-wrap:wrap}
.feature-item{display:flex;flex-direction:column;align-items:center;gap:8px}
.feature-item i{font-size:22px;color:#7a1a2e}
.feature-item span{font-size:12px;color:#666;font-weight:500}

.detail-tabs{display:flex;margin-top:30px;border-bottom:2px solid #f0ebe6}
.detail-tab{padding:12px 25px;font-size:14px;font-weight:600;color:#999;cursor:pointer;border-bottom:3px solid transparent;transition:.3s;background:none;font-family:'Poppins',sans-serif;display:flex;align-items:center;gap:8px}
.detail-tab.active{color:#7a1a2e;border-bottom-color:#7a1a2e}
.tab-content{padding:20px 0}
.empty-comment{text-align:center;padding:50px 20px;color:#999}
.empty-comment-icon{width:70px;height:70px;border-radius:50%;background:#f8f4f0;color:#7a1a2e;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 15px;opacity:.7}
.empty-comment h4{font-size:15px;font-weight:700;color:#333;margin-bottom:6px}
.other-products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:15px}
.other-product-card{display:block;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #f0ebe6;transition:.3s;color:inherit;text-decoration:none}
.other-product-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(122,26,46,.12)}
.other-product-card img{width:100%;height:120px;object-fit:cover;background:#f8f4f0;display:block}
.other-product-info{padding:12px}
.other-product-name{font-size:13px;font-weight:600;color:#333;margin-bottom:4px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:34px}
.other-product-price{font-size:14px;font-weight:800;color:#7a1a2e}

.back-btn{display:inline-flex;align-items:center;gap:8px;color:#7a1a2e;text-decoration:none;font-size:14px;font-weight:500;margin-bottom:20px}
.empty-state{text-align:center;padding:80px 20px;color:#999}
.empty-state i{font-size:60px;margin-bottom:15px;color:#d4a843}

#toastBox{position:fixed;top:22px;left:50%;transform:translateX(-50%);z-index:2000;display:flex;flex-direction:column;gap:10px;pointer-events:none;width:90%;max-width:420px}
.toast{background:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 10px 30px rgba(0,0,0,.18);display:flex;align-items:center;gap:12px;font-size:13px;font-weight:600;color:#333;border-left:5px solid #999;animation:toastIn .35s ease;pointer-events:auto}
@keyframes toastIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
@keyframes toastOut{to{opacity:0;transform:translateY(-20px)}}
.toast.hide{animation:toastOut .3s ease forwards}
.toast.success{border-color:#27ae60;background:#f0fdf4;color:#1e6a2a}
.toast.success i{color:#27ae60}
.toast.warning{border-color:#e67e22;background:#fff9ee;color:#8a5610}
.toast.warning i{color:#e67e22}
.toast.error{border-color:#e74c3c;background:#fef2f2;color:#a02121}
.toast.error i{color:#e74c3c}
.toast.info{border-color:#3498db;background:#eff7fd;color:#1c5981}
.toast.info i{color:#3498db}
.toast i{font-size:18px;flex-shrink:0}

@media (max-width:768px){
    .sidebar{width:60px}
    .sidebar .logo-text,.nav-menu a span{display:none}
    .main-content{margin-left:60px}
    .detail-container{flex-direction:column}
    .detail-image-section{flex:none}
    .search-box{width:auto;flex:1}
    .user-info{display:none}
    .btn-voucher span{display:none}
    .btn-voucher{padding:10px 12px}
    .banner-slide img{height:180px}
    .voucher-item{flex-direction:column;align-items:stretch;text-align:center}
    .voucher-left{margin:0 auto}
    .voucher-right{min-width:0}
    .btn-pakai-promo{flex:1;min-width:140px}
    .add-to-cart-btn{min-width:140px}
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
            <button type="button" class="btn-voucher" onclick="openVoucherModal()">
                <i class="fas fa-ticket-alt"></i> <span>Voucher</span>
            </button>

            <a href="cart.php" class="topbar-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cartCount > 0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?>
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
                    <a href="profil.php"><i class="fas fa-user"></i> Profil Saya</a>
                    <a href="orders.php"><i class="fas fa-clipboard-list"></i> Pesanan</a>
                    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($flashMsg): ?>
        <div style="padding:0 30px;padding-top:20px;">
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($flashMsg) ?></div>
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
            <input type="hidden" name="pakai_promo" id="pakaiPromoInput" value="<?= $usePromo ? 1 : 0 ?>">

            <div class="detail-container">
                <div class="detail-image-section">
                    <?php $mainGambar = $detailGaleri[0] ?? $detail['gambar']; ?>
                    <img src="<?= e($mainGambar ?: $fallbackImg) ?>"
                         alt="<?= e($detail['nama']) ?>" class="detail-main-image" id="detailImage"
                         onerror="this.src='<?= $fallbackImg ?>';">
                    <?php if (count($detailGaleri) > 1): ?>
                        <div class="detail-thumbnails">
                            <?php foreach ($detailGaleri as $i => $g): ?>
                                <img src="<?= e($g) ?>" class="<?= $i === 0 ? 'active' : '' ?>" onclick="changeImage(this, '<?= e($g) ?>')" onerror="this.src='<?= $fallbackImg ?>';">
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

                    <?php
                    $basePriceDetail   = $detail['harga_diskon'] ?: $detail['harga'];
                    $diskonPromoDetail = $promoAktif ? hitungDiskonPromo($basePriceDetail, $promoAktif) : 0;
                    $hargaSetelahPromo = max(0, $basePriceDetail - $diskonPromoDetail);
                    ?>

                    <div class="detail-price" id="detailPriceWrap">
                        <?php if ($promoAktif && $diskonPromoDetail > 0): ?>
                            <span class="promo-price" id="detailPrice"><?= rupiah($hargaSetelahPromo) ?></span>
                            <span id="detailPriceOld" style="font-size:15px;color:#999;text-decoration:line-through;font-weight:400;"><?= rupiah($basePriceDetail) ?></span>
                        <?php else: ?>
                            <span id="detailPrice"><?= rupiah($basePriceDetail) ?></span>
                            <span id="detailPriceOld" style="font-size:15px;color:#999;text-decoration:line-through;font-weight:400;display:none;"><?= rupiah($basePriceDetail) ?></span>
                        <?php endif; ?>
                    </div>
                    <div id="detailPromoInfo" style="font-size:12px;color:#27ae60;font-weight:600;margin-bottom:12px;<?= ($promoAktif && $diskonPromoDetail > 0) ? '' : 'display:none;' ?>">
                        <i class="fas fa-ticket-alt"></i> Voucher aktif — hemat <span id="detailPromoHemat"><?= rupiah($diskonPromoDetail) ?></span>
                    </div>

                    <p class="detail-description"><?= e($detail['deskripsi']) ?></p>

                    <?php if ($detail['has_variant']): ?>
                        <div class="variant-section">
                            <div class="variant-label">Pilih Varian <span style="color:#e74c3c;">*</span></div>
                            <div class="variant-options" id="variantOptions">
                                <?php
                                $perPcsDetail = $basePriceDetail / 3;
                                $varianList = [['label'=>'3 pcs','pcs'=>3],['label'=>'6 pcs','pcs'=>6],['label'=>'8 pcs','pcs'=>8]];
                                foreach ($varianList as $v):
                                    $vp       = (int)round($perPcsDetail * $v['pcs']);
                                    $diskonVar = $promoAktif ? hitungDiskonPromo($vp, $promoAktif) : 0;
                                    $vpFinal  = max(0, $vp - $diskonVar);
                                ?>
                                    <button type="button" class="variant-btn"
                                            data-variant="<?= e($v['label']) ?>"
                                            data-normal="<?= $vp ?>"
                                            onclick="pilihVarian(this)">
                                        <span class="v-label"><?= e($v['label']) ?></span>
                                        <span class="v-price" data-normal="<?= rupiah($vp) ?>"><?= rupiah($vpFinal) ?></span>
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
                    </div>

                    <div class="action-buttons">
                        <?php if (!empty($voucherDimiliki)): ?>
                            <button type="button" class="btn-pakai-promo <?= $usePromo ? 'active' : '' ?>" id="btnPakaiPromo" onclick="openPickVoucher()">
                                <?php if ($usePromo): ?>
                                    <i class="fas fa-check-circle icon-check"></i>
                                    <span>Pakai Promo</span>
                                    <span class="persen-label"><?= (int)$promoAktif['persen_diskon'] ?>%</span>
                                <?php else: ?>
                                    <i class="fas fa-ticket-alt"></i>
                                    <span>Pakai Promo</span>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($detail['has_variant']): ?>
                            <button type="submit" class="add-to-cart-btn" id="btnAddCart" disabled>
                                <i class="fas fa-shopping-cart"></i>
                                <span id="btnAddCartText"><?= $stok === 0 ? 'Stok Habis' : 'Pilih Varian Dulu' ?></span>
                            </button>
                        <?php else: ?>
                            <button type="submit" class="add-to-cart-btn" id="btnAddCart" <?= $stok === 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-shopping-cart"></i>
                                <span id="btnAddCartText"><?= $stok === 0 ? 'Stok Habis' : 'Tambah ke Keranjang' ?></span>
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
                    </div>
                <?php else: ?>
                    <div class="other-products-grid">
                        <?php foreach ($produkLain as $pl):
                            $hargaPl = $pl['harga_diskon'] ?: $pl['harga'];
                        ?>
                            <a href="dashboard.php?id=<?= $pl['id'] ?>" class="other-product-card">
                                <img src="<?= e($pl['gambar'] ?: $fallbackImg) ?>" alt="<?= e($pl['nama']) ?>" onerror="this.src='<?= $fallbackImg ?>';">
                                <div class="other-product-info">
                                    <div class="other-product-name"><?= e($pl['nama']) ?></div>
                                    <div class="other-product-price"><?= rupiah($hargaPl) ?></div>
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

        <?php if (!empty($banners)): ?>
            <div class="banner-carousel" id="bannerCarousel">
                <div class="banner-track" id="bannerTrack">
                    <?php foreach ($banners as $i => $b): ?>
                        <div class="banner-slide" data-index="<?= $i ?>">
                            <img src="<?= e($b['gambar']) ?>" alt="<?= e($b['judul'] ?: 'Banner') ?>"
                                 onerror="this.src='<?= $fallbackImg ?>';">
                            <?php if (!empty($b['judul']) || !empty($b['deskripsi'])): ?>
                                <div class="banner-overlay">
                                    <?php if (!empty($b['judul'])): ?><h3><?= e($b['judul']) ?></h3><?php endif; ?>
                                    <?php if (!empty($b['deskripsi'])): ?><p><?= e($b['deskripsi']) ?></p><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($banners) > 1): ?>
                    <button type="button" class="banner-arrow prev" onclick="bannerPrev(event)"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="banner-arrow next" onclick="bannerNext(event)"><i class="fas fa-chevron-right"></i></button>
                    <div class="banner-dots" id="bannerDots">
                        <?php foreach ($banners as $i => $b): ?>
                            <button type="button" class="banner-dot <?= $i === 0 ? 'active' : '' ?>" onclick="bannerGoTo(event, <?= $i ?>)"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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
                <p>Produk tidak ditemukan.</p>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($produkList as $p):
                    $hargaFinal = $p['harga_diskon'] ?: $p['harga'];
                    $hasDiskon  = $p['harga_diskon'] && $p['harga_diskon'] < $p['harga'];
                    $stok       = (int)$p['stok'];
                ?>
                    <a href="dashboard.php?id=<?= $p['id'] ?>" class="product-card">
                        <button type="button" class="wishlist-btn" onclick="event.preventDefault(); event.stopPropagation(); toggleWishlist(this);">
                            <i class="far fa-heart"></i>
                        </button>
                        <img src="<?= e($p['gambar'] ?: $fallbackImg) ?>" alt="<?= e($p['nama']) ?>" class="product-image" onerror="this.src='<?= $fallbackImg ?>';">
                        <div class="product-info">
                            <div class="product-name"><?= e($p['nama']) ?></div>
                            <div class="product-rating">
                                <span class="stars">★★★★★</span>
                                <span class="count">Belum ada ulasan</span>
                            </div>
                            <div class="product-price">
                                <?= rupiah($hargaFinal) ?>
                                <?php if ($hasDiskon): ?>
                                    <span class="strike-normal"><?= rupiah($p['harga']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($stok > 0): ?>
                            <span class="add-btn"><i class="fas fa-plus"></i></span>
                        <?php else: ?>
                            <span class="add-btn" style="background:#ccc;"><i class="fas fa-times"></i></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- ==================== MODAL VOUCHER ==================== -->
<div class="modal-overlay" id="voucherModal">
    <div class="modal-voucher" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-header-icon"><i class="fas fa-ticket-alt"></i></div>
            <div class="modal-header-text">
                <h3>Voucher & Promo</h3>
                <p>Klaim voucher dulu supaya bisa dipakai saat checkout.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeVoucherModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <?php if (empty($semuaPromo)): ?>
                <div class="empty-comment" style="padding:40px 20px;">
                    <div class="empty-comment-icon"><i class="fas fa-ticket-alt"></i></div>
                    <h4>Belum Ada Voucher</h4>
                </div>
            <?php else: ?>
                <div class="voucher-list">
                    <?php foreach ($semuaPromo as $pr):
                        $st          = $promoStatus[$pr['id']];
                        $isClaimed   = $st['claimed'];
                        $bisaClaim   = $st['bisa_claim'];
                        $inCart      = $st['in_cart'];
                        $used        = $st['used'];
                    ?>
                        <div class="voucher-item <?= $isClaimed && !$inCart && !$used ? 'claimed' : '' ?> <?= $inCart ? 'in-cart' : '' ?>">
                            <div class="voucher-left">
                                <span class="persen"><?= (int)$pr['persen_diskon'] ?>%</span>
                                <span class="lbl">OFF</span>
                            </div>
                            <div class="voucher-mid">
                                <h4><?= e($pr['nama']) ?></h4>
                                <p><?= e($pr['deskripsi']) ?></p>
                                <div class="voucher-tags">
                                    <?php if (!empty($pr['max_diskon']) && $pr['max_diskon'] > 0): ?>
                                        <span class="voucher-tag hemat">Maks hemat <?= rupiah($pr['max_diskon']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($pr['min_belanja']) && $pr['min_belanja'] > 0): ?>
                                        <span class="voucher-tag min">Min. <?= rupiah($pr['min_belanja']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($inCart): ?>
                                        <span class="voucher-tag status-cart"><i class="fas fa-shopping-cart"></i> Di Keranjang</span>
                                    <?php elseif ($isClaimed): ?>
                                        <span class="voucher-tag status-aktif"><i class="fas fa-check-circle"></i> Sudah Diklaim</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="voucher-right">
                                <?php if ($inCart): ?>
                                    <button type="button" class="btn-voucher-action cooldown" disabled>
                                        <i class="fas fa-shopping-cart"></i> Sedang Dipakai
                                    </button>
                                <?php elseif (!$isClaimed): ?>
                                    <button type="button" class="btn-voucher-action claim" onclick="claimVoucher(<?= $pr['id'] ?>)">
                                        <i class="fas fa-hand-holding-heart"></i> Klaim
                                    </button>
                                <?php elseif ($bisaClaim): ?>
                                    <button type="button" class="btn-voucher-action reclaim" onclick="claimVoucher(<?= $pr['id'] ?>)">
                                        <i class="fas fa-redo"></i> Klaim Ulang
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-voucher-action cooldown" onclick="showCooldownNotice(<?= (int)$st['sisa_hari'] ?>)">
                                        <i class="fas fa-clock"></i> Tunggu <?= (int)$st['sisa_hari'] ?> Hari
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ==================== MODAL PILIH VOUCHER ==================== -->
<div class="modal-overlay" id="pickVoucherModal">
    <div class="modal-pickvoucher" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-header-icon"><i class="fas fa-tags"></i></div>
            <div class="modal-header-text">
                <h3>Pakai Voucher</h3>
                <p>Pilih voucher yang mau dipakai untuk produk ini.</p>
            </div>
            <button type="button" class="modal-close" onclick="closePickVoucher()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <?php if (empty($voucherDimiliki)): ?>
                <div class="empty-comment" style="padding:30px 20px;">
                    <div class="empty-comment-icon"><i class="fas fa-ticket-alt"></i></div>
                    <h4>Belum Punya Voucher</h4>
                    <p style="font-size:12px;">Klaim voucher dulu di tombol Voucher di atas.</p>
                </div>
            <?php else: ?>
                <div class="vpick-none <?= !$usePromo ? 'selected' : '' ?>" onclick="pilihVoucher(0)">
                    <div class="vpick-none-icon"><i class="fas fa-ban"></i></div>
                    <div class="vpick-info">
                        <h4>Tanpa Promo</h4>
                        <p>Bayar dengan harga normal</p>
                    </div>
                    <div class="vpick-check"><i class="fas fa-check"></i></div>
                </div>

                <div style="height:12px;"></div>

                <?php foreach ($voucherDimiliki as $vp):
                    $isActive = ($activePromoId === (int)$vp['id']);
                ?>
                    <div class="vpick-item <?= $isActive ? 'selected' : '' ?>" onclick="pilihVoucher(<?= (int)$vp['id'] ?>)">
                        <div class="vpick-badge">
                            <span class="p"><?= (int)$vp['persen_diskon'] ?>%</span>
                            <span class="l">OFF</span>
                        </div>
                        <div class="vpick-info">
                            <h4><?= e($vp['nama']) ?></h4>
                            <p>
                                <?php if (!empty($vp['min_belanja']) && $vp['min_belanja'] > 0): ?>
                                    Min. belanja <?= rupiah($vp['min_belanja']) ?>
                                <?php endif; ?>
                                <?php if (!empty($vp['max_diskon']) && $vp['max_diskon'] > 0): ?>
                                    <?= (!empty($vp['min_belanja']) && $vp['min_belanja'] > 0) ? ' · ' : '' ?>Maks hemat <?= rupiah($vp['max_diskon']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="vpick-check"><i class="fas fa-check"></i></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="toastBox"></div>

<script>
var PROMO_LIST = <?= json_encode(array_map(function($p){
    return [
        'id' => (int)$p['id'],
        'persen' => (int)$p['persen_diskon'],
        'max_diskon' => (int)($p['max_diskon'] ?? 0),
        'nama' => $p['nama'],
    ];
}, $semuaPromo)) ?>;

var BASE_PRICE_DETAIL = <?= $detail ? (int)($detail['harga_diskon'] ?: $detail['harga']) : 0 ?>;
var HAS_VARIANT = <?= $detail && $detail['has_variant'] ? 'true' : 'false' ?>;
var CURRENT_PROMO_ID = <?= (int)$activePromoId ?>;

function showToast(msg, type, duration) {
    type = type || 'info';
    duration = duration || 3500;
    var box = document.getElementById('toastBox');
    var el = document.createElement('div');
    el.className = 'toast ' + type;
    var icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    else if (type === 'warning') icon = 'fa-exclamation-triangle';
    else if (type === 'error')   icon = 'fa-times-circle';
    el.innerHTML = '<i class="fas ' + icon + '"></i><span>' + msg + '</span>';
    box.appendChild(el);
    setTimeout(function(){
        el.classList.add('hide');
        setTimeout(function(){ el.remove(); }, 300);
    }, duration);
}

function openVoucherModal() {
    var fd = new FormData();
    fd.append('action', 'reset_voucher');
    fetch('dashboard.php', { method: 'POST', body: fd }).catch(function(){});

    CURRENT_PROMO_ID = 0;
    var input = document.getElementById('pakaiPromoInput');
    if (input) input.value = 0;
    updateTombolPakaiPromo(0);
    updateHargaDetail();

    document.getElementById('voucherModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeVoucherModal() {
    document.getElementById('voucherModal').classList.remove('show');
    document.body.style.overflow = '';
}
document.getElementById('voucherModal').addEventListener('click', function(e){
    if (e.target === this) closeVoucherModal();
});

function openPickVoucher() {
    document.getElementById('pickVoucherModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closePickVoucher() {
    document.getElementById('pickVoucherModal').classList.remove('show');
    document.body.style.overflow = '';
}
document.getElementById('pickVoucherModal').addEventListener('click', function(e){
    if (e.target === this) closePickVoucher();
});
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') { closeVoucherModal(); closePickVoucher(); }
});

function pilihVoucher(promoId) {
    var fd = new FormData();
    fd.append('action', 'pilih_voucher');
    fd.append('promo_id', promoId);

    fetch('dashboard.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.status === 'on') {
                showToast(res.message, 'success', 2500);
                CURRENT_PROMO_ID = res.promo_id;
                updateHargaDetail();
                updateTombolPakaiPromo(res.persen);
                updateSelectedItem(res.promo_id);
                updatePakaiPromoInput(1);
                setTimeout(closePickVoucher, 400);
            } else if (res.status === 'off') {
                showToast(res.message, 'info', 2500);
                CURRENT_PROMO_ID = 0;
                updateHargaDetail();
                updateTombolPakaiPromo(0);
                updateSelectedItem(0);
                updatePakaiPromoInput(0);
                setTimeout(closePickVoucher, 400);
            } else {
                showToast(res.message || 'Gagal.', 'error', 3000);
            }
        })
        .catch(function(){ showToast('Gagal terhubung ke server.', 'error'); });
}

function updateSelectedItem(promoId) {
    var items = document.querySelectorAll('.vpick-item, .vpick-none');
    items.forEach(function(el){
        el.classList.remove('selected');
        var onclickStr = el.getAttribute('onclick') || '';
        if (promoId === 0 && onclickStr.indexOf('pilihVoucher(0)') !== -1) el.classList.add('selected');
        if (promoId > 0 && onclickStr.indexOf('pilihVoucher(' + promoId + ')') !== -1) el.classList.add('selected');
    });
}
function updatePakaiPromoInput(val) {
    var el = document.getElementById('pakaiPromoInput');
    if (el) el.value = val;
}
function updateTombolPakaiPromo(persen) {
    var btn = document.getElementById('btnPakaiPromo');
    if (!btn) return;
    if (persen > 0) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-check-circle icon-check"></i><span>Pakai Promo</span><span class="persen-label">' + persen + '%</span>';
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fas fa-ticket-alt"></i><span>Pakai Promo</span>';
    }
}

function hitungDiskon(harga) {
    if (!CURRENT_PROMO_ID) return 0;
    var promo = null;
    for (var i = 0; i < PROMO_LIST.length; i++) {
        if (PROMO_LIST[i].id === CURRENT_PROMO_ID) { promo = PROMO_LIST[i]; break; }
    }
    if (!promo) return 0;
    var d = Math.round(harga * promo.persen / 100);
    if (promo.max_diskon > 0) d = Math.min(d, promo.max_diskon);
    return d;
}
function formatRp(n) {
    return 'Rp ' + n.toLocaleString('id-ID');
}
function updateHargaDetail() {
    var priceEl = document.getElementById('detailPrice');
    var priceOldEl = document.getElementById('detailPriceOld');
    var promoInfo = document.getElementById('detailPromoInfo');
    var promoHematEl = document.getElementById('detailPromoHemat');
    if (!priceEl) return;

    var basePrice = BASE_PRICE_DETAIL;
    var activeVariant = document.querySelector('.variant-btn.active');
    if (HAS_VARIANT && activeVariant) {
        basePrice = parseInt(activeVariant.getAttribute('data-normal')) || BASE_PRICE_DETAIL;
    }

    var diskon = hitungDiskon(basePrice);
    var finalPrice = Math.max(0, basePrice - diskon);

    var variantBtns = document.querySelectorAll('.variant-btn');
    variantBtns.forEach(function(vb){
        var vNormal = parseInt(vb.getAttribute('data-normal'));
        var vDiskon = hitungDiskon(vNormal);
        var vFinal  = Math.max(0, vNormal - vDiskon);
        var vPriceEl = vb.querySelector('.v-price');
        if (vPriceEl) vPriceEl.textContent = formatRp(vFinal);
    });

    priceEl.textContent = formatRp(finalPrice);
    if (diskon > 0) {
        priceOldEl.style.display = '';
        priceOldEl.textContent = formatRp(basePrice);
        promoInfo.style.display = '';
        if (promoHematEl) promoHematEl.textContent = formatRp(diskon);
    } else {
        priceOldEl.style.display = 'none';
        promoInfo.style.display = 'none';
    }
}

function claimVoucher(promoId) {
    var fd = new FormData();
    fd.append('action', 'claim_voucher');
    fd.append('promo_id', promoId);
    fetch('dashboard.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.status === 'success') {
                showToast(res.message, 'success', 4000);
                setTimeout(function(){ location.reload(); }, 900);
            } else if (res.status === 'cooldown') {
                showToast(res.message, 'warning', 4000);
            } else {
                showToast(res.message || 'Terjadi kesalahan.', 'error');
            }
        })
        .catch(function(){ showToast('Gagal terhubung ke server.', 'error'); });
}
function showCooldownNotice(sisaHari) {
    showToast('Voucher ini sedang cooldown. Klaim lagi dalam ' + sisaHari + ' hari.', 'warning', 4000);
}

(function(){
    var track = document.getElementById('bannerTrack');
    if (!track) return;
    var slides = track.querySelectorAll('.banner-slide');
    var dots   = document.querySelectorAll('.banner-dot');
    var total  = slides.length;
    var index  = 0;
    var autoTimer = null;
    window.bannerGoTo = function(e, i){
        if (e) e.stopPropagation();
        index = (i + total) % total;
        track.style.transform = 'translateX(-' + (index * 100) + '%)';
        dots.forEach(function(d, k){ d.classList.toggle('active', k === index); });
        restartAuto();
    };
    window.bannerNext = function(e){ if (e) e.stopPropagation(); bannerGoTo(null, index + 1); };
    window.bannerPrev = function(e){ if (e) e.stopPropagation(); bannerGoTo(null, index - 1); };
    function restartAuto(){ clearInterval(autoTimer); if (total > 1) autoTimer = setInterval(function(){ bannerGoTo(null, index + 1); }, 4500); }
    restartAuto();
    var startX = 0, isDown = false;
    track.addEventListener('touchstart', function(e){ startX = e.touches[0].clientX; isDown = true; }, {passive:true});
    track.addEventListener('touchend', function(e){
        if (!isDown) return;
        var diff = e.changedTouches[0].clientX - startX;
        if (Math.abs(diff) > 40) { if (diff > 0) bannerPrev(); else bannerNext(); }
        isDown = false;
    }, {passive:true});
})();

function pilihVarian(btn) {
    var all = document.querySelectorAll('.variant-btn');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('active');
    btn.classList.add('active');
    document.getElementById('variantInput').value = btn.getAttribute('data-variant');
    updateHargaDetail();
    var hint = document.getElementById('variantHint');
    if (hint) hint.style.display = 'none';
    var btnAdd = document.getElementById('btnAddCart');
    var btnText = document.getElementById('btnAddCartText');
    if (btnAdd && btnText && btnText.textContent.indexOf('Stok Habis') === -1) {
        btnAdd.disabled = false;
        btnText.textContent = 'Tambah ke Keranjang';
    }
}
function changeImage(thumb, src) {
    var all = document.querySelectorAll('.detail-thumbnails img');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('active');
    thumb.classList.add('active');
    document.getElementById('detailImage').src = src;
}
function changeQty(delta) {
    var input = document.getElementById('qtyInput');
    var val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > 99) val = 99;
    input.value = val;
}
function switchTab(btn, targetId) {
    var tabs = document.querySelectorAll('.detail-tab');
    for (var i = 0; i < tabs.length; i++) tabs[i].classList.remove('active');
    btn.classList.add('active');
    document.getElementById('tab-komentar').style.display    = (targetId === 'tab-komentar')    ? 'block' : 'none';
    document.getElementById('tab-produk-lain').style.display = (targetId === 'tab-produk-lain') ? 'block' : 'none';
}
function toggleWishlist(btn) {
    var icon = btn.querySelector('i');
    if (icon.classList.contains('far')) { icon.classList.remove('far'); icon.classList.add('fas'); }
    else { icon.classList.remove('fas'); icon.classList.add('far'); }
}
</script>
</body>
</html>