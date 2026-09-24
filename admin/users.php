<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$q = trim($_GET['q'] ?? '');

$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS total_orders,
        (SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE user_id = u.id AND status = 'selesai') AS total_belanja
        FROM users u
        WHERE u.role = 'pelanggan'";
if ($q !== '') {
    $sql .= " AND (u.nama_lengkap LIKE ? OR u.email LIKE ? OR u.no_telp LIKE ? OR u.username LIKE ?)";
}
$sql .= " ORDER BY u.created_at DESC";

$stmt = $koneksi->prepare($sql);
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt->bind_param('ssss', $like, $like, $like, $like);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalUsers = (int)$koneksi->query("SELECT COUNT(*) c FROM users WHERE role='pelanggan'")->fetch_assoc()['c'];

$pageTitle = 'Kelola Pelanggan';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/sidebar.php';
?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="admin-breadcrumb"><a href="index.php">Dashboard</a> / Pelanggan</div>
            <h1 class="admin-page-title">Kelola Pelanggan</h1>
        </div>
    </div>

    <div class="admin-content">

        <div class="filter-bar">
            <form method="get" style="display:flex;gap:10px;flex:1;max-width:420px;">
                <input type="text" name="q" class="form-input" placeholder="Cari nama, email, no HP..." value="<?= e($q) ?>">
                <button class="btn btn-outline"><i class="fas fa-search"></i></button>
            </form>
            <div style="margin-left:auto;font-size:12px;color:#888;padding:8px 14px;background:#f5f5f7;border-radius:8px;">
                <i class="fas fa-users"></i> Total: <b><?= $totalUsers ?></b> pelanggan
            </div>
        </div>

        <div class="table-wrap">
            <div class="table-header">
                <div>
                    <div class="table-title">Daftar Pelanggan</div>
                    <div style="font-size:11px;color:#999;margin-top:2px;"><?= count($users) ?> pelanggan ditampilkan</div>
                </div>
            </div>

            <?php if (empty($users)): ?>
                <div class="empty">
                    <i class="fas fa-users"></i>
                    <h3>Belum ada pelanggan</h3>
                    <p><?= $q ? 'Tidak ditemukan pelanggan untuk "' . e($q) . '"' : 'Pelanggan yang mendaftar akan muncul di sini.' ?></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Kontak</th>
                            <th style="text-align:center;">Total Pesanan</th>
                            <th style="text-align:right;">Total Belanja</th>
                            <th>Bergabung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u):
                            $inisial = strtoupper(substr($u['nama_lengkap'] ?? 'U', 0, 2));
                            $fotoPath = $u['foto_profil'] ?? null;
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div style="width:40px;height:40px;border-radius:50%;background:#7a1a2e;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;overflow:hidden;flex-shrink:0;">
                                            <?php if (!empty($fotoPath) && file_exists(__DIR__.'/../'.$fotoPath)): ?>
                                                <img src="../<?= e($fotoPath) ?>?v=<?= time() ?>" style="width:100%;height:100%;object-fit:cover;">
                                            <?php else: ?>
                                                <?= e($inisial) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:700;color:#2c0a0e;"><?= e($u['nama_lengkap']) ?></div>
                                            <?php if (!empty($u['nickname'])): ?>
                                                <div style="font-size:11px;color:#999;">@<?= e($u['nickname']) ?></div>
                                            <?php else: ?>
                                                <div style="font-size:11px;color:#999;">@<?= e($u['username']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($u['email'])): ?>
                                        <div style="font-size:12px;color:#555;"><i class="fas fa-envelope" style="color:#d4a843;width:14px;"></i> <?= e($u['email']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($u['no_telp'])): ?>
                                        <div style="font-size:12px;color:#555;"><i class="fas fa-phone" style="color:#d4a843;width:14px;"></i> <?= e($u['no_telp']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge badge-info"><?= (int)$u['total_orders'] ?>×</span>
                                </td>
                                <td style="text-align:right;">
                                    <b style="color:#7a1a2e;"><?= rupiah($u['total_belanja']) ?></b>
                                </td>
                                <td style="font-size:12px;color:#888;">
                                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/partials/foot.php'; ?>