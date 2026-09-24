<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// ===== STATISTIK =====
$totalProduk     = (int)$koneksi->query("SELECT COUNT(*) t FROM products WHERE is_active=1")->fetch_assoc()['t'];
$totalPesanan    = (int)$koneksi->query("SELECT COUNT(*) t FROM orders")->fetch_assoc()['t'];
$totalPendapatan = (int)$koneksi->query("SELECT COALESCE(SUM(grand_total),0) t FROM orders WHERE status='selesai'")->fetch_assoc()['t'];
$totalPelanggan  = (int)$koneksi->query("SELECT COUNT(*) t FROM users WHERE role='pelanggan'")->fetch_assoc()['t'];
$pendingOrders   = (int)$koneksi->query("SELECT COUNT(*) t FROM orders WHERE status='pending'")->fetch_assoc()['t'];

// ===== PESANAN TERBARU =====
$pesananTerbaru = $koneksi->query("
    SELECT o.*, u.nama_lengkap
    FROM orders o LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// ===== STOK RENDAH =====
$stokRendah = $koneksi->query("
    SELECT id, nama, stok, gambar FROM products
    WHERE stok < 10 AND is_active = 1
    ORDER BY stok ASC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// ===== STATUS BADGE MAP =====
$statusMap = [
    'pending'    => ['Menunggu',   'badge-warning'],
    'diproses'   => ['Diproses',   'badge-info'],
    'dikirim'    => ['Dikirim',    'badge-gold'],
    'selesai'    => ['Selesai',    'badge-success'],
    'dibatalkan' => ['Dibatalkan', 'badge-danger'],
];

$pageTitle = 'Dashboard';
$pageCrumb = ['Dashboard' => 'index.php'];
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/sidebar.php';
?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="admin-breadcrumb">Admin / Dashboard</div>
            <h1 class="admin-page-title">Dashboard</h1>
        </div>
        <a href="product-form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Produk
        </a>
    </div>

    <div class="admin-content">

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
        <?php endif; ?>

        <!-- STATISTIK -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-head">
                    <div class="stat-label">Total Pendapatan</div>
                    <div class="stat-icon" style="background:#e9f7eb;color:#1e6a2a"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                <div class="stat-value"><?= rupiah($totalPendapatan) ?></div>
                <div class="stat-hint">Dari pesanan selesai</div>
            </div>
            <div class="stat-card">
                <div class="stat-head">
                    <div class="stat-label">Total Pesanan</div>
                    <div class="stat-icon" style="background:#e8f2fd;color:#1e4a8a"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="stat-value"><?= number_format($totalPesanan, 0, ',', '.') ?></div>
                <div class="stat-hint"><?= $pendingOrders ?> menunggu konfirmasi</div>
            </div>
            <div class="stat-card">
                <div class="stat-head">
                    <div class="stat-label">Produk Aktif</div>
                    <div class="stat-icon" style="background:#fef7e4;color:#8b6a1e"><i class="fas fa-box"></i></div>
                </div>
                <div class="stat-value"><?= number_format($totalProduk, 0, ',', '.') ?></div>
                <div class="stat-hint">Menu yang bisa dipesan</div>
            </div>
            <div class="stat-card">
                <div class="stat-head">
                    <div class="stat-label">Pelanggan</div>
                    <div class="stat-icon" style="background:#f3e8ff;color:#6b21a8"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-value"><?= number_format($totalPelanggan, 0, ',', '.') ?></div>
                <div class="stat-hint">Terdaftar di sistem</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">

            <!-- PESANAN TERBARU -->
            <div class="table-wrap">
                <div class="table-header">
                    <div>
                        <div class="table-title">Pesanan Terbaru</div>
                        <div style="font-size:11px;color:#999;margin-top:2px;">5 pesanan terakhir</div>
                    </div>
                    <span class="badge badge-warning"><?= $pendingOrders ?> pending</span>
                </div>
                <?php if (empty($pesananTerbaru)): ?>
                    <div class="empty">
                        <i class="fas fa-receipt"></i>
                        <h3>Belum ada pesanan</h3>
                        <p>Pesanan pelanggan akan muncul di sini.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Pelanggan</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pesananTerbaru as $o):
                                $s = $statusMap[$o['status']] ?? [ucfirst($o['status']), 'badge-gray'];
                            ?>
                                <tr>
                                    <td><b style="color:#2c0a0e;font-family:monospace;"><?= e($o['kode_order']) ?></b></td>
                                    <td><?= e($o['nama_lengkap'] ?? 'Guest') ?></td>
                                    <td><b><?= rupiah($o['grand_total']) ?></b></td>
                                    <td><span class="badge <?= $s[1] ?>"><?= $s[0] ?></span></td>
                                    <td style="font-size:12px;color:#888;"><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- STOK RENDAH -->
            <div class="table-wrap">
                <div class="table-header">
                    <div class="table-title">⚠️ Stok Rendah</div>
                </div>
                <?php if (empty($stokRendah)): ?>
                    <div class="empty" style="padding:40px 20px;">
                        <i class="fas fa-check-circle" style="color:#1e6a2a;opacity:.6;"></i>
                        <h3 style="font-size:13px;">Semua stok aman</h3>
                    </div>
                <?php else: ?>
                    <div style="padding:12px 16px;">
                        <?php foreach ($stokRendah as $p): ?>
                            <div style="display:flex;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f3;">
                                <?php if ($p['gambar'] && file_exists(__DIR__.'/../'.$p['gambar'])): ?>
                                    <img src="../<?= e($p['gambar']) ?>" class="thumb" style="width:44px;height:44px;">
                                <?php else: ?>
                                    <div class="thumb-placeholder" style="width:44px;height:44px;">🥟</div>
                                <?php endif; ?>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:13px;font-weight:600;color:#2c0a0e;"><?= e($p['nama']) ?></div>
                                    <div style="font-size:11px;color:#999;">Sisa stok</div>
                                </div>
                                <span class="badge badge-danger"><?= (int)$p['stok'] ?></span>
                            </div>
                        <?php endforeach; ?>
                        <a href="products.php" class="btn btn-outline btn-sm" style="width:100%;margin-top:12px;">
                            <i class="fas fa-arrow-right"></i> Kelola Produk
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<?php include __DIR__ . '/partials/foot.php'; ?>