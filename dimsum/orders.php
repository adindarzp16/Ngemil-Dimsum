<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) redirect('admin/index.php');

$userId = $_SESSION['user_id'];

$stmt = $koneksi->prepare("
    SELECT o.*, 
           (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS total_item
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Ambil info user untuk avatar
$stmt = $koneksi->prepare("SELECT nama_lengkap, foto_profil FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$namaUser = $userData['nama_lengkap'];
$userFoto = $userData['foto_profil'];
$initials = strtoupper(substr($namaUser, 0, 2));

$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
$flashMsg  = flash('success');

$statusMap = [
    'pending'    => ['label'=>'Menunggu Konfirmasi', 'color'=>'#f39c12', 'bg'=>'#fef5e7', 'icon'=>'fa-clock'],
    'diproses'   => ['label'=>'Sedang Diproses',     'color'=>'#3498db', 'bg'=>'#eaf4fc', 'icon'=>'fa-utensils'],
    'dikirim'    => ['label'=>'Sedang Dikirim',      'color'=>'#9b59b6', 'bg'=>'#f4ecf7', 'icon'=>'fa-motorcycle'],
    'selesai'    => ['label'=>'Selesai',             'color'=>'#27ae60', 'bg'=>'#e9f7eb', 'icon'=>'fa-check-circle'],
    'dibatalkan' => ['label'=>'Dibatalkan',          'color'=>'#e74c3c', 'bg'=>'#fdecec', 'icon'=>'fa-times-circle'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesanan Saya — Ngemil Dimsum</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/customer.css">
<style>
.order-card{background:#fff;border-radius:15px;padding:20px;margin-bottom:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);transition:.3s;border-left:4px solid #7a1a2e}
.order-card:hover{box-shadow:0 5px 20px rgba(122,26,46,.1);transform:translateY(-2px)}
.order-head{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:14px;border-bottom:1px solid #f0ebe6;margin-bottom:14px;gap:10px;flex-wrap:wrap}
.order-code{font-size:15px;font-weight:700;color:#333;margin-bottom:3px}
.order-date{font-size:11px;color:#999}
.order-status{padding:6px 14px;border-radius:20px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:6px}
.order-body{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}
.order-meta{font-size:12px;color:#666}
.order-meta b{color:#7a1a2e;font-size:16px;display:block;margin-top:3px}
.order-actions{display:flex;gap:8px}

.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-family:'Poppins',sans-serif;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:.2s;text-decoration:none;line-height:1}
.btn-outline{background:#fff;color:#7a1a2e;border:1.5px solid #e8e0d8}
.btn-outline:hover{background:#f8f4f0;border-color:#7a1a2e}
.btn-danger{background:#fff;color:#e74c3c;border:1.5px solid #fcc}
.btn-danger:hover{background:#e74c3c;color:#fff;border-color:#e74c3c}
.btn-sm{padding:7px 12px;font-size:12px}

/* Modal konfirmasi */
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.show{display:flex}
.modal-box{background:#fff;border-radius:16px;max-width:400px;width:100%;padding:26px;text-align:center;animation:pop .25s}
@keyframes pop{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
.modal-icon{width:64px;height:64px;border-radius:50%;background:#fdecec;color:#e74c3c;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 15px}
.modal-box h3{font-size:17px;font-weight:800;color:#2c0a0e;margin-bottom:8px}
.modal-box p{font-size:13px;color:#666;margin-bottom:20px;line-height:1.6}
.modal-actions{display:flex;gap:10px}
.modal-actions .btn{flex:1;justify-content:center;padding:12px 20px;font-size:13px}
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
        <form class="search-box" method="get" action="dashboard.php"><i class="fas fa-search"></i><input type="text" name="q" placeholder="Cari menu favoritmu..."></form>
        <div class="topbar-right">
            <a href="cart.php" class="topbar-icon"><i class="fas fa-shopping-cart"></i><?php if ($cartCount>0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?></a>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php if ($userFoto && file_exists(__DIR__.'/'.$userFoto)): ?><img src="<?= e($userFoto) ?>" alt="Avatar"><?php else: ?><?= e($initials) ?><?php endif; ?>
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
        <h1 class="page-title">Pesanan Saya</h1>
        <div class="breadcrumb"><a href="dashboard.php">Beranda</a> / Pesanan</div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($flashMsg) ?></div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <p>Kamu belum pernah memesan nih.</p>
                    <a href="dashboard.php" class="btn btn-primary" style="margin-top:8px;"><i class="fas fa-th-large"></i> Mulai Pesan Dimsum</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $o):
                $s = $statusMap[$o['status']] ?? ['label'=>ucfirst($o['status']),'color'=>'#888','bg'=>'#f5f5f5','icon'=>'fa-info-circle'];
            ?>
                <div class="order-card">
                    <div class="order-head">
                        <div>
                            <div class="order-code">#<?= e($o['kode_order']) ?></div>
                            <div class="order-date"><i class="far fa-clock"></i> <?= date('d M Y, H:i', strtotime($o['created_at'])) ?></div>
                        </div>
                        <span class="order-status" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;">
                            <i class="fas <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
                        </span>
                    </div>
                    <div class="order-body">
                        <div class="order-meta">
                            <?= (int)$o['total_item'] ?> item dipesan
                            <b><?= rupiah($o['grand_total']) ?></b>
                        </div>
                        <div class="order-actions">
    <?php if (in_array($o['status'], ['dibatalkan', 'selesai'])): ?>
    <button type="button" class="btn btn-danger btn-sm"
            onclick="confirmDeleteOrder(<?= $o['id'] ?>, '<?= e(addslashes($o['kode_order'])) ?>')">
        <i class="fas fa-trash"></i> Hapus
    </button>
<?php else: ?>
    <a href="order-detail.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">
        <i class="fas fa-eye"></i> Lihat Detail
    </a>
<?php endif; ?>
</div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="wave-decoration">
    <svg viewBox="0 0 1440 60" preserveAspectRatio="none" style="width:100%;height:100%;">
        <path d="M0,30 Q360,0 720,30 T1440,30 L1440,60 L0,60 Z" fill="#7a1a2e"/>
        <path d="M0,40 Q360,10 720,40 T1440,40 L1440,60 L0,60 Z" fill="#d4a843" opacity="0.6"/>
    </svg>
</div>
<!-- MODAL HAPUS PESANAN -->
<div class="modal-backdrop" id="deleteOrderModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-trash"></i></div>
        <h3>Hapus Pesanan?</h3>
        <p>Yakin mau hapus pesanan <b id="delOrderKode"></b> dari daftar?<br>Tindakan ini tidak bisa dibatalkan.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeDeleteOrder()">Batal</button>
            <form method="post" action="order-delete.php" style="flex:1;">
                <input type="hidden" name="order_id" id="delOrderId">
                <button type="submit" class="btn btn-danger" style="width:100%;">
                    <i class="fas fa-check"></i> Ya, Hapus
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDeleteOrder(id, kode) {
    document.getElementById('delOrderId').value = id;
    document.getElementById('delOrderKode').textContent = '#' + kode;
    document.getElementById('deleteOrderModal').classList.add('show');
}
function closeDeleteOrder() {
    document.getElementById('deleteOrderModal').classList.remove('show');
}
document.getElementById('deleteOrderModal').addEventListener('click', function(e){
    if (e.target === this) closeDeleteOrder();
});
</script>
</body>
</html>