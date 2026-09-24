<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// ============================================================
// HANDLE UPDATE STATUS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status  = $_POST['status'] ?? '';

    $validStatus = ['pending', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];

    if ($orderId > 0 && in_array($status, $validStatus)) {
        $stmt = $koneksi->prepare("SELECT kode_order, status FROM orders WHERE id = ?");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if ($order) {
            // Kalau status baru = dibatalkan & lama bukan dibatalkan → kembalikan stok
            if ($status === 'dibatalkan' && $order['status'] !== 'dibatalkan') {
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
            }
            // Kalau status lama = dibatalkan & baru bukan → kurangi stok kembali
            elseif ($order['status'] === 'dibatalkan' && $status !== 'dibatalkan') {
                $stmtItems = $koneksi->prepare("SELECT product_id, qty FROM order_items WHERE order_id = ?");
                $stmtItems->bind_param('i', $orderId);
                $stmtItems->execute();
                $items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);

                $stmtStok = $koneksi->prepare("UPDATE products SET stok = GREATEST(stok - ?, 0) WHERE id = ?");
                foreach ($items as $it) {
                    if ($it['product_id']) {
                        $stmtStok->bind_param('ii', $it['qty'], $it['product_id']);
                        $stmtStok->execute();
                    }
                }
            }

            $upd = $koneksi->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $upd->bind_param('si', $status, $orderId);
            $upd->execute();

            flash('success', "Status pesanan #{$order['kode_order']} berhasil diubah.");
        }
    }
    redirect('orders.php' . (!empty($_POST['filter']) ? '?status=' . $_POST['filter'] : ''));
}

// ============================================================
// FILTER
// ============================================================
$filterStatus = $_GET['status'] ?? '';
$validFilters = ['pending', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];

$sql = "SELECT o.*, u.nama_lengkap,
        (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS total_item
        FROM orders o LEFT JOIN users u ON o.user_id = u.id";
if (in_array($filterStatus, $validFilters)) {
    $sql .= " WHERE o.status = ?";
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $koneksi->prepare($sql);
if (in_array($filterStatus, $validFilters)) $stmt->bind_param('s', $filterStatus);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistik per status
$statCounts = [];
$res = $koneksi->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
while ($r = $res->fetch_assoc()) $statCounts[$r['status']] = (int)$r['c'];

$statusMap = [
    'pending'    => ['label'=>'Menunggu',   'color'=>'#f39c12', 'bg'=>'#fef5e7', 'icon'=>'fa-clock'],
    'diproses'   => ['label'=>'Diproses',   'color'=>'#3498db', 'bg'=>'#eaf4fc', 'icon'=>'fa-utensils'],
    'dikirim'    => ['label'=>'Dikirim',    'color'=>'#9b59b6', 'bg'=>'#f4ecf7', 'icon'=>'fa-motorcycle'],
    'selesai'    => ['label'=>'Selesai',    'color'=>'#27ae60', 'bg'=>'#e9f7eb', 'icon'=>'fa-check-circle'],
    'dibatalkan' => ['label'=>'Dibatalkan', 'color'=>'#e74c3c', 'bg'=>'#fdecec', 'icon'=>'fa-times-circle'],
];

$pageTitle = 'Kelola Pesanan';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/sidebar.php';
?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="admin-breadcrumb"><a href="index.php">Dashboard</a> / Pesanan</div>
            <h1 class="admin-page-title">Kelola Pesanan</h1>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
        <?php endif; ?>

        <!-- FILTER STATUS -->
        <div class="chip-filter" style="margin-bottom:20px;">
            <a href="orders.php" class="<?= $filterStatus === '' ? 'active' : '' ?>">
                Semua (<?= array_sum($statCounts) ?>)
            </a>
            <?php foreach ($statusMap as $key => $s): ?>
                <a href="orders.php?status=<?= $key ?>" class="<?= $filterStatus === $key ? 'active' : '' ?>">
                    <?= $s['label'] ?> (<?= $statCounts[$key] ?? 0 ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <div class="table-header">
                <div>
                    <div class="table-title">Daftar Pesanan</div>
                    <div style="font-size:11px;color:#999;margin-top:2px;"><?= count($orders) ?> pesanan ditampilkan</div>
                </div>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty">
                    <i class="fas fa-receipt"></i>
                    <h3>Tidak ada pesanan</h3>
                    <p><?= $filterStatus ? 'Belum ada pesanan dengan status ini.' : 'Pesanan pelanggan akan muncul di sini.' ?></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Pelanggan</th>
                            <th style="text-align:center;">Item</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:center;">Status</th>
                            <th>Tanggal</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o):
                            $s = $statusMap[$o['status']] ?? ['label'=>ucfirst($o['status']),'color'=>'#888','bg'=>'#f0f0f3','icon'=>'fa-info-circle'];
                        ?>
                            <tr>
                                <td><b style="color:#2c0a0e;font-family:monospace;"><?= e($o['kode_order']) ?></b></td>
                                <td><?= e($o['nama_lengkap'] ?? 'Guest') ?></td>
                                <td style="text-align:center;"><span class="badge badge-gray"><?= (int)$o['total_item'] ?> item</span></td>
                                <td style="text-align:right;"><b style="color:#7a1a2e;"><?= rupiah($o['grand_total']) ?></b></td>
                                <td style="text-align:center;">
                                    <span class="badge" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;">
                                        <i class="fas <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
                                    </span>
                                </td>
                                <td style="font-size:12px;color:#888;"><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn btn-outline btn-sm" onclick='openDetail(<?= json_encode($o, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="fas fa-eye"></i> Detail
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

<!-- MODAL DETAIL PESANAN -->
<div class="modal-backdrop" id="detailModal">
    <div class="modal-box" style="text-align:left;max-width:560px;max-height:85vh;overflow-y:auto;">
        <h3 style="text-align:left;margin-bottom:15px;">Detail Pesanan <span id="dKode" style="color:#7a1a2e;font-family:monospace;"></span></h3>

        <div style="margin-bottom:15px;padding:12px;background:#f8f8fa;border-radius:10px;">
            <div style="font-size:12px;color:#666;margin-bottom:4px;">Pelanggan</div>
            <div style="font-size:14px;font-weight:700;color:#2c0a0e;" id="dPelanggan"></div>
        </div>

        <div style="font-size:12px;color:#666;margin-bottom:6px;">Alamat Pengiriman</div>
        <div style="font-size:13px;padding:10px;background:#fdf6ee;border-radius:8px;margin-bottom:15px;white-space:pre-line;line-height:1.6;" id="dAlamat"></div>

        <div id="dCatatan" style="display:none;margin-bottom:15px;">
            <div style="font-size:12px;color:#666;margin-bottom:6px;">Catatan</div>
            <div style="font-size:13px;padding:10px;background:#fdf6ee;border-radius:8px;font-style:italic;" id="dCatatanText"></div>
        </div>

        <div style="font-size:12px;color:#666;margin-bottom:6px;">Item Pesanan</div>
        <div id="dItems" style="margin-bottom:15px;"></div>

        <div style="background:#fdf6ee;border-radius:10px;padding:14px;margin-bottom:15px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#666;margin-bottom:6px;">
                <span>Subtotal</span><span id="dSubtotal"></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#666;margin-bottom:6px;">
                <span>Ongkir</span><span id="dOngkir"></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;color:#7a1a2e;padding-top:10px;border-top:2px dashed #e0d8d0;">
                <span>Total</span><span id="dTotal"></span>
            </div>
        </div>

        <div style="font-size:12px;color:#666;margin-bottom:8px;">Ubah Status</div>
        <form method="post" id="statusForm">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="dOrderId">
            <input type="hidden" name="filter" value="<?= e($filterStatus) ?>">
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:15px;">
                <?php foreach ($statusMap as $key => $s): ?>
                    <label style="display:flex;align-items:center;gap:10px;padding:10px;border:2px solid #e0d8d0;border-radius:9px;cursor:pointer;transition:.2s;" class="status-option">
                        <input type="radio" name="status" value="<?= $key ?>" style="accent-color:#7a1a2e;">
                        <span style="font-size:12px;font-weight:600;color:#333;">
                            <i class="fas <?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;"></i> <?= $s['label'] ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;justify-content:center;" onclick="closeDetailModal()">Tutup</button>
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">
                    <i class="fas fa-save"></i> Simpan Status
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function rupiah(n){ return 'Rp ' + Number(n).toLocaleString('id-ID'); }

function openDetail(o){
    document.getElementById('dKode').textContent = '#' + o.kode_order;
    document.getElementById('dPelanggan').textContent = o.nama_lengkap || 'Guest';
    document.getElementById('dAlamat').textContent = o.alamat_kirim || '-';
    document.getElementById('dSubtotal').textContent = rupiah(o.subtotal);
    document.getElementById('dOngkir').textContent = rupiah(o.ongkir);
    document.getElementById('dTotal').textContent = rupiah(o.grand_total);
    document.getElementById('dOrderId').value = o.id;

    if (o.catatan && o.catatan.trim() !== '') {
        document.getElementById('dCatatan').style.display = 'block';
        document.getElementById('dCatatanText').textContent = '"' + o.catatan + '"';
    } else {
        document.getElementById('dCatatan').style.display = 'none';
    }

    // Set radio checked
    document.querySelectorAll('input[name="status"]').forEach(function(r){
        r.checked = (r.value === o.status);
    });

    // Load items
    document.getElementById('dItems').innerHTML = '<div style="text-align:center;color:#999;font-size:12px;padding:20px;">Loading...</div>';

    fetch('order-items-api.php?order_id=' + o.id)
        .then(res => res.json())
        .then(items => {
            let html = '';
            if (!items.length) {
                html = '<div style="text-align:center;color:#999;font-size:12px;">Tidak ada item</div>';
            } else {
                items.forEach(it => {
                    html += '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f3;font-size:13px;">';
                    html += '<span style="color:#333;">' + it.nama_produk + ' <span style="color:#999;">×' + it.qty + '</span></span>';
                    html += '<b style="color:#7a1a2e;">' + rupiah(it.subtotal) + '</b>';
                    html += '</div>';
                });
            }
            document.getElementById('dItems').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('dItems').innerHTML = '<div style="color:#e74c3c;font-size:12px;">Gagal memuat item.</div>';
        });

    document.getElementById('detailModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDetailModal(){
    document.getElementById('detailModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('detailModal').addEventListener('click', function(e){
    if (e.target === this) closeDetailModal();
});
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>