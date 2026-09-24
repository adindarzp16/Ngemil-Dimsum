<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$q          = trim($_GET['q'] ?? '');
$kategoriId = (int)($_GET['kategori'] ?? 0);

$sql = "SELECT p.*, c.nama AS kategori FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 1=1";
$params = [];
$types  = '';

if ($q !== '') {
    $sql .= " AND (p.nama LIKE ? OR p.sku LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like;
    $types .= 'ss';
}
if ($kategoriId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $kategoriId;
    $types .= 'i';
}
$sql .= " ORDER BY p.is_active DESC, p.id DESC";

$stmt = $koneksi->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$produkList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = $koneksi->query("SELECT * FROM categories ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$totalAktif = (int)$koneksi->query("SELECT COUNT(*) t FROM products WHERE is_active=1")->fetch_assoc()['t'];

$pageTitle = 'Kelola Produk';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/sidebar.php';
?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="admin-breadcrumb"><a href="index.php">Dashboard</a> / Produk</div>
            <h1 class="admin-page-title">Kelola Produk</h1>
        </div>
        <a href="product-form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Produk
        </a>
    </div>

    <div class="admin-content">

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
        <?php endif; ?>

        <div class="filter-bar">
            <form method="get" style="display:flex;gap:10px;flex:1;max-width:400px;">
                <input type="text" name="q" class="form-input" placeholder="Cari nama..." value="<?= e($q) ?>">
                <?php if ($kategoriId): ?><input type="hidden" name="kategori" value="<?= $kategoriId ?>"><?php endif; ?>
                <button class="btn btn-outline"><i class="fas fa-search"></i></button>
            </form>
            <div class="chip-filter">
                <a href="products.php<?= $q ? '?q='.urlencode($q) : '' ?>" class="<?= !$kategoriId ? 'active' : '' ?>">Semua</a>
                <?php foreach ($categories as $c):
                    $qs = http_build_query(array_filter(['q'=>$q, 'kategori'=>$c['id']]));
                ?>
                    <a href="products.php?<?= $qs ?>" class="<?= $kategoriId == $c['id'] ? 'active' : '' ?>"><?= e($c['nama']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="table-wrap">
            <div class="table-header">
                <div>
                    <div class="table-title">Daftar Produk</div>
                    <div style="font-size:11px;color:#999;margin-top:2px;">
                        <?= count($produkList) ?> produk ditampilkan · <?= $totalAktif ?> aktif
                    </div>
                </div>
            </div>

            <?php if (empty($produkList)): ?>
                <div class="empty">
                    <i class="fas fa-box-open"></i>
                    <h3>Belum ada produk</h3>
                    <p><?= $q ? 'Tidak ditemukan produk untuk "' . e($q) . '"' : 'Mulai tambahkan produk pertamamu!' ?></p>
                    <a href="product-form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Produk</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>SKU</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th style="text-align:right;">Harga</th>
                            <th style="text-align:center;">Stok</th>
                            <th style="text-align:center;">Status</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produkList as $p):
                            $stok = (int)$p['stok'];
                            $stokClass = $stok === 0 ? 'badge-danger' : ($stok < 10 ? 'badge-warning' : 'badge-success');
                            $hargaFinal = $p['harga_diskon'] ?: $p['harga'];
                            $hasDiskon  = $p['harga_diskon'] && $p['harga_diskon'] < $p['harga'];
                        ?>
                            <tr>
                                <td>
                                    <?php if ($p['gambar'] && file_exists(__DIR__.'/../'.$p['gambar'])): ?>
                                        <img src="../<?= e($p['gambar']) ?>" class="thumb">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">🥟</div>
                                    <?php endif; ?>
                                </td>
                                <td><span style="font-family:monospace;font-size:12px;color:#666;"><?= e($p['sku']) ?></span></td>
                                <td>
                                    <div style="font-weight:700;color:#2c0a0e;"><?= e($p['nama']) ?></div>
                                    <div style="font-size:11px;color:#999;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?= e($p['deskripsi']) ?>
                                    </div>
                                </td>
                                <td><span class="badge badge-gold"><?= e($p['kategori'] ?: '—') ?></span></td>
                                <td style="text-align:right;">
                                    <?php if ($hasDiskon): ?>
                                        <div style="font-size:10px;color:#999;text-decoration:line-through;"><?= rupiah($p['harga']) ?></div>
                                        <div style="font-weight:700;color:#7a1a2e;"><?= rupiah($hargaFinal) ?></div>
                                    <?php else: ?>
                                        <div style="font-weight:700;color:#2c0a0e;"><?= rupiah($p['harga']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;"><span class="badge <?= $stokClass ?>"><?= $stok ?></span></td>
                                <td style="text-align:center;">
                                    <?php if ($p['is_active']): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="product-form.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-icon" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-icon" title="Hapus"
                                                onclick="confirmDelete(<?= $p['id'] ?>, '<?= e(addslashes($p['nama'])) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- MODAL CONFIRM DELETE -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal-box">
        <div class="icon-warn"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Hapus Produk?</h3>
        <p>Yakin mau hapus <b id="deleteName"></b>? Tindakan ini tidak bisa dibatalkan.</p>
        <div class="actions">
            <button type="button" class="btn btn-outline" onclick="closeDelete()">Batal</button>
            <form method="post" action="product-delete.php" style="flex:1;">
                <input type="hidden" name="id" id="deleteId">
                <button type="submit" class="btn btn-danger" style="width:100%;">
                    <i class="fas fa-trash"></i> Ya, Hapus
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, nama) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = nama;
    document.getElementById('deleteModal').classList.add('show');
}
function closeDelete() {
    document.getElementById('deleteModal').classList.remove('show');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDelete();
});
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>