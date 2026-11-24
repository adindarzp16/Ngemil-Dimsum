<?php
/* ============================================================
   NGEMIL DIMSUM — Admin Stok Bahan Baku
   ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

/* ============================================================
   HANDLE POST — TAMBAH BAHAN BARU
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tambah_bahan') {
    $nama         = trim($_POST['nama'] ?? '');
    $stok         = (float)str_replace(',', '.', $_POST['stok'] ?? 0);
    $satuan       = trim($_POST['satuan'] ?? 'kg');
    $stok_minimum = (float)str_replace(',', '.', $_POST['stok_minimum'] ?? 0);
    $catatan      = trim($_POST['catatan'] ?? '');

    if ($nama) {
        $stmt = $koneksi->prepare("INSERT INTO bahan_baku (nama, stok, satuan, stok_minimum, catatan) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sdsss', $nama, $stok, $satuan, $stok_minimum, $catatan);
        $stmt->execute();
        flash('success', 'Bahan baku berhasil ditambahkan.');
    }
    redirect('stok-bahan-baku.php');
}

/* ============================================================
   HANDLE POST — EDIT BAHAN
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_bahan') {
    $id           = (int)($_POST['id'] ?? 0);
    $nama         = trim($_POST['nama'] ?? '');
    $satuan       = trim($_POST['satuan'] ?? 'kg');
    $stok_minimum = (float)str_replace(',', '.', $_POST['stok_minimum'] ?? 0);
    $catatan      = trim($_POST['catatan'] ?? '');

    if ($id && $nama) {
        $stmt = $koneksi->prepare("UPDATE bahan_baku SET nama=?, satuan=?, stok_minimum=?, catatan=? WHERE id=?");
        $stmt->bind_param('ssdsi', $nama, $satuan, $stok_minimum, $catatan, $id);
        $stmt->execute();
        flash('success', 'Bahan baku berhasil diupdate.');
    }
    redirect('stok-bahan-baku.php');
}

/* ============================================================
   HANDLE POST — HAPUS BAHAN
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus_bahan') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $koneksi->prepare("DELETE FROM bahan_baku WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    flash('success', 'Bahan baku dihapus.');
    redirect('stok-bahan-baku.php');
}

/* ============================================================
   HANDLE POST — CATAT TRANSAKSI (masuk/keluar)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'catat_transaksi') {
    $bahan_id   = (int)($_POST['bahan_id'] ?? 0);
    $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
    $jenis      = $_POST['jenis'] ?? 'masuk';
    $jumlah     = (float)str_replace(',', '.', $_POST['jumlah'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($bahan_id && $jumlah > 0 && in_array($jenis, ['masuk', 'keluar'])) {
        $koneksi->begin_transaction();
        try {
            // Insert riwayat transaksi
            $stmt = $koneksi->prepare("INSERT INTO bahan_baku_transaksi (bahan_id, tanggal, jenis, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('issds', $bahan_id, $tanggal, $jenis, $jumlah, $keterangan);
            $stmt->execute();

            // Update stok di tabel master
            if ($jenis === 'masuk') {
                $upd = $koneksi->prepare("UPDATE bahan_baku SET stok = stok + ? WHERE id = ?");
            } else {
                $upd = $koneksi->prepare("UPDATE bahan_baku SET stok = GREATEST(0, stok - ?) WHERE id = ?");
            }
            $upd->bind_param('di', $jumlah, $bahan_id);
            $upd->execute();

            $koneksi->commit();
            flash('success', 'Transaksi berhasil dicatat. Stok sudah diupdate.');
        } catch (Exception $e) {
            $koneksi->rollback();
            flash('success', 'Gagal mencatat transaksi: ' . $e->getMessage());
        }
    }
    redirect('stok-bahan-baku.php');
}

/* ============================================================
   HANDLE POST — HAPUS TRANSAKSI (otomatis koreksi stok)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus_transaksi') {
    $id = (int)($_POST['id'] ?? 0);
    $tr = $koneksi->query("SELECT * FROM bahan_baku_transaksi WHERE id = $id")->fetch_assoc();
    if ($tr) {
        $koneksi->begin_transaction();
        try {
            // Balik efek stok
            if ($tr['jenis'] === 'masuk') {
                $upd = $koneksi->prepare("UPDATE bahan_baku SET stok = GREATEST(0, stok - ?) WHERE id = ?");
            } else {
                $upd = $koneksi->prepare("UPDATE bahan_baku SET stok = stok + ? WHERE id = ?");
            }
            $upd->bind_param('di', $tr['jumlah'], $tr['bahan_id']);
            $upd->execute();

            $koneksi->query("DELETE FROM bahan_baku_transaksi WHERE id = $id");
            $koneksi->commit();
            flash('success', 'Transaksi dihapus, stok sudah dikoreksi.');
        } catch (Exception $e) {
            $koneksi->rollback();
            flash('success', 'Gagal menghapus: ' . $e->getMessage());
        }
    }
    redirect('stok-bahan-baku.php');
}

/* ============================================================
   AMBIL DATA
============================================================ */
$tab = $_GET['tab'] ?? 'daftar';

// Daftar bahan baku
$daftarBahan = $koneksi->query("SELECT * FROM bahan_baku ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

// Statistik
$totalBahan   = count($daftarBahan);
$bahanAman    = 0;
$bahanHampir  = 0;
$bahanHabis   = 0;
foreach ($daftarBahan as $b) {
    if ($b['stok'] <= 0) $bahanHabis++;
    elseif ($b['stok'] <= $b['stok_minimum']) $bahanHampir++;
    else $bahanAman++;
}

// Riwayat transaksi (50 terbaru)
$riwayat = $koneksi->query("
    SELECT t.*, b.nama AS nama_bahan, b.satuan
    FROM bahan_baku_transaksi t
    LEFT JOIN bahan_baku b ON t.bahan_id = b.id
    ORDER BY t.tanggal DESC, t.id DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Stok Bahan Baku';
$pageCrumb = ['Dashboard' => 'index.php', 'Stok Bahan Baku' => 'stok-bahan-baku.php'];
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/sidebar.php';

/* Helper status */
function statusBahan($b) {
    if ($b['stok'] <= 0) return ['Habis', 'badge-danger', 'habis'];
    if ($b['stok'] <= $b['stok_minimum']) return ['Hampir Habis', 'badge-warning', 'hampir'];
    return ['Aman', 'badge-success', 'aman'];
}
?>

<style>
/* ============ STYLE STOK BAHAN BAKU ============ */
.sbb-tabs{display:flex;gap:4px;margin-bottom:20px;background:#fff;padding:5px;border-radius:12px;width:fit-content;border:1px solid #e6e2dc}
.sbb-tab{padding:9px 22px;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;color:#8a6a4a;border:none;background:transparent;cursor:pointer;transition:.2s;text-decoration:none;display:inline-block}
.sbb-tab.active{background:#fff7e3;color:#7a1a2e;font-weight:700;box-shadow:0 2px 6px rgba(212,168,67,.15)}

.sbb-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:22px}
.sbb-stat-card{background:#fff;border-radius:14px;padding:18px;border:1px solid #f0ebe3;transition:.25s}
.sbb-stat-card:hover{box-shadow:0 6px 22px rgba(122,26,46,.08);transform:translateY(-2px)}
.sbb-stat-head{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.sbb-stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px}
.sbb-stat-icon.blue{background:#3498db}
.sbb-stat-icon.green{background:#27ae60}
.sbb-stat-icon.orange{background:#e67e22}
.sbb-stat-icon.red{background:#e74c3c}
.sbb-stat-label{font-size:12px;font-weight:600;color:#555}
.sbb-stat-value{font-size:22px;font-weight:800;color:#2c0a0e;letter-spacing:-.5px}

.sbb-card{background:#fff;border-radius:14px;border:1px solid #f0ebe3;padding:20px;margin-bottom:20px}
.sbb-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px}
.sbb-card-title{display:flex;align-items:center;gap:10px;font-size:15px;font-weight:700;color:#2c0a0e}
.sbb-card-title i{color:#d4a843;font-size:16px}

.sbb-btn{padding:10px 20px;border-radius:10px;border:none;font-family:'Poppins',sans-serif;font-size:12.5px;font-weight:700;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:7px;text-decoration:none}
.sbb-btn-primary{background:#7a1a2e;color:#fff}
.sbb-btn-primary:hover{background:#5c1020}
.sbb-btn-green{background:#27ae60;color:#fff}
.sbb-btn-green:hover{background:#1e8a4b}
.sbb-btn-red{background:#e74c3c;color:#fff}
.sbb-btn-red:hover{background:#c0392b}
.sbb-btn-ghost{background:#f5efe4;color:#7a5a3a}
.sbb-btn-ghost:hover{background:#e8dcc4}
.sbb-btn-sm{padding:6px 12px;font-size:11px}

.sbb-table-wrap{overflow-x:auto}
.sbb-table{width:100%;border-collapse:collapse;font-size:13px}
.sbb-table th{background:#faf7f2;text-align:left;padding:11px 14px;font-weight:600;color:#8a6a4a;font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;border-bottom:1px solid #f0ebe3}
.sbb-table td{padding:12px 14px;border-bottom:1px solid #f5efe4;color:#444;vertical-align:middle}
.sbb-table tr:last-child td{border-bottom:none}
.sbb-table tr:hover{background:#fdfbf7}

.badge-status{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;font-size:10.5px;font-weight:700}
.badge-aman{background:#e8f8ef;color:#1e8a4b}
.badge-hampir{background:#fff5e0;color:#c9671c}
.badge-habis{background:#fdecec;color:#c0392b}

.badge-jenis-trx{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:10.5px;font-weight:700}
.badge-jenis-trx.masuk{background:#e8f8ef;color:#1e8a4b}
.badge-jenis-trx.keluar{background:#fdecec;color:#c0392b}

.sbb-progress{background:#f0ebe3;height:6px;border-radius:10px;overflow:hidden;margin-top:4px;width:100%;max-width:120px}
.sbb-progress > div{height:100%;border-radius:10px;transition:.4s}

/* Modal */
.sbb-modal-overlay{position:fixed;inset:0;background:rgba(30,20,10,.5);z-index:900;display:none;align-items:center;justify-content:center;padding:20px}
.sbb-modal-overlay.show{display:flex}
.sbb-modal-box{background:#fff;border-radius:16px;max-width:520px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.sbb-modal-head{padding:20px 24px;border-bottom:1px solid #f0ebe3;display:flex;align-items:center;justify-content:space-between}
.sbb-modal-head h3{font-size:17px;font-weight:800;color:#2c0a0e;display:flex;align-items:center;gap:10px;margin:0}
.sbb-modal-head h3 i{color:#d4a843}
.sbb-modal-close{background:#f5efe4;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;color:#8a6a4a;font-size:14px}
.sbb-modal-close:hover{background:#e8dcc4}
.sbb-modal-body{padding:22px 24px}
.sbb-form-group{margin-bottom:16px}
.sbb-form-group label{display:block;font-size:12.5px;font-weight:600;color:#555;margin-bottom:6px}
.sbb-form-group input,.sbb-form-group select,.sbb-form-group textarea{width:100%;padding:11px 14px;border:1.5px solid #e6e2dc;border-radius:10px;font-family:'Poppins',sans-serif;font-size:13px;color:#333;outline:none;transition:.2s;background:#fdfbf7;box-sizing:border-box}
.sbb-form-group input:focus,.sbb-form-group select:focus,.sbb-form-group textarea:focus{border-color:#d4a843;background:#fff}
.sbb-form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.sbb-modal-foot{padding:16px 24px;border-top:1px solid #f0ebe3;display:flex;justify-content:flex-end;gap:10px}

@media (max-width:768px){
    .sbb-form-row{grid-template-columns:1fr}
    .sbb-tabs{width:100%;overflow-x:auto}
}
</style>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="admin-breadcrumb">Admin / Stok Bahan Baku</div>
            <h1 class="admin-page-title">Stok Bahan Baku</h1>
            <p style="font-size:12px;color:#999;margin-top:4px;">Kelola stok bahan baku, catat bahan masuk dan keluar.</p>
        </div>
        <button type="button" class="sbb-btn sbb-btn-primary" onclick="bukaModalBahan()">
            <i class="fas fa-plus"></i> Tambah Bahan
        </button>
    </div>

    <div class="admin-content">

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
        <?php endif; ?>

        <!-- STAT CARDS -->
        <div class="sbb-stat-grid">
            <div class="sbb-stat-card">
                <div class="sbb-stat-head">
                    <div class="sbb-stat-icon blue"><i class="fas fa-boxes"></i></div>
                    <div class="sbb-stat-label">Total Bahan</div>
                </div>
                <div class="sbb-stat-value"><?= $totalBahan ?></div>
            </div>
            <div class="sbb-stat-card">
                <div class="sbb-stat-head">
                    <div class="sbb-stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="sbb-stat-label">Stok Aman</div>
                </div>
                <div class="sbb-stat-value"><?= $bahanAman ?></div>
            </div>
            <div class="sbb-stat-card">
                <div class="sbb-stat-head">
                    <div class="sbb-stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="sbb-stat-label">Hampir Habis</div>
                </div>
                <div class="sbb-stat-value"><?= $bahanHampir ?></div>
            </div>
            <div class="sbb-stat-card">
                <div class="sbb-stat-head">
                    <div class="sbb-stat-icon red"><i class="fas fa-times-circle"></i></div>
                    <div class="sbb-stat-label">Habis</div>
                </div>
                <div class="sbb-stat-value"><?= $bahanHabis ?></div>
            </div>
        </div>

        <!-- TABS -->
        <div class="sbb-tabs">
            <a href="?tab=daftar" class="sbb-tab <?= $tab === 'daftar' ? 'active' : '' ?>">Daftar Stok</a>
            <a href="?tab=riwayat" class="sbb-tab <?= $tab === 'riwayat' ? 'active' : '' ?>">Riwayat Transaksi</a>
        </div>

        <?php if ($tab === 'daftar'): ?>
        <!-- ============== DAFTAR STOK ============== -->
        <div class="sbb-card">
            <div class="sbb-card-header">
                <div class="sbb-card-title"><i class="fas fa-boxes"></i> Daftar Bahan Baku</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="sbb-btn sbb-btn-green sbb-btn-sm" onclick="bukaModalTransaksi('masuk')">
                        <i class="fas fa-arrow-down"></i> Catat Masuk
                    </button>
                    <button type="button" class="sbb-btn sbb-btn-red sbb-btn-sm" onclick="bukaModalTransaksi('keluar')">
                        <i class="fas fa-arrow-up"></i> Catat Keluar
                    </button>
                </div>
            </div>

            <?php if (empty($daftarBahan)): ?>
                <div style="text-align:center;padding:50px 20px;color:#999;">
                    <i class="fas fa-box-open" style="font-size:48px;color:#d4a843;opacity:.5;margin-bottom:12px;"></i>
                    <p style="font-size:13px;">Belum ada bahan baku. Klik <b>Tambah Bahan</b> untuk mulai.</p>
                </div>
            <?php else: ?>
                <div class="sbb-table-wrap">
                    <table class="sbb-table">
                        <thead>
                            <tr>
                                <th>Bahan Baku</th>
                                <th>Stok Saat Ini</th>
                                <th>Stok Minimum</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftarBahan as $b):
                                list($statusLabel, $statusClass, $statusKey) = statusBahan($b);
                                $persenStok = $b['stok_minimum'] > 0 ? min(100, ($b['stok'] / ($b['stok_minimum'] * 2)) * 100) : 100;
                                $warnaBar = $statusKey === 'aman' ? '#27ae60' : ($statusKey === 'hampir' ? '#e67e22' : '#e74c3c');
                            ?>
                                <tr>
                                    <td>
                                        <b style="color:#2c0a0e;"><?= e($b['nama']) ?></b>
                                        <?php if ($b['catatan']): ?>
                                            <div style="font-size:11px;color:#999;margin-top:2px;"><?= e($b['catatan']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <b style="font-size:14px;color:<?= $warnaBar ?>;">
                                            <?= number_format($b['stok'], 2, ',', '.') ?> <?= e($b['satuan']) ?>
                                        </b>
                                        <div class="sbb-progress"><div style="width:<?= $persenStok ?>%;background:<?= $warnaBar ?>;"></div></div>
                                    </td>
                                    <td style="color:#888;"><?= number_format($b['stok_minimum'], 2, ',', '.') ?> <?= e($b['satuan']) ?></td>
                                    <td><span class="badge-status <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    <td>
                                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <button type="button" class="sbb-btn sbb-btn-ghost sbb-btn-sm" onclick='editBahan(<?= json_encode($b) ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="post" onsubmit="return confirm('Hapus bahan <?= e($b['nama']) ?>? Semua riwayat transaksi bahan ini juga akan terhapus.')" style="display:inline;">
                                                <input type="hidden" name="action" value="hapus_bahan">
                                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                                <button type="submit" class="sbb-btn sbb-btn-red sbb-btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- ============== RIWAYAT TRANSAKSI ============== -->
        <div class="sbb-card">
            <div class="sbb-card-header">
                <div class="sbb-card-title"><i class="fas fa-history"></i> Riwayat Transaksi Bahan</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="sbb-btn sbb-btn-green sbb-btn-sm" onclick="bukaModalTransaksi('masuk')">
                        <i class="fas fa-arrow-down"></i> Catat Masuk
                    </button>
                    <button type="button" class="sbb-btn sbb-btn-red sbb-btn-sm" onclick="bukaModalTransaksi('keluar')">
                        <i class="fas fa-arrow-up"></i> Catat Keluar
                    </button>
                </div>
            </div>

            <?php if (empty($riwayat)): ?>
                <div style="text-align:center;padding:50px 20px;color:#999;">
                    <i class="fas fa-history" style="font-size:48px;color:#d4a843;opacity:.5;margin-bottom:12px;"></i>
                    <p style="font-size:13px;">Belum ada transaksi.</p>
                </div>
            <?php else: ?>
                <div class="sbb-table-wrap">
                    <table class="sbb-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Bahan</th>
                                <th>Jenis</th>
                                <th>Jumlah</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $r): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
                                    <td><b style="color:#2c0a0e;"><?= e($r['nama_bahan'] ?? '-') ?></b></td>
                                    <td>
                                        <span class="badge-jenis-trx <?= $r['jenis'] === 'masuk' ? 'masuk' : 'keluar' ?>">
                                            <i class="fas fa-arrow-<?= $r['jenis'] === 'masuk' ? 'down' : 'up' ?>"></i>
                                            <?= $r['jenis'] === 'masuk' ? 'Masuk' : 'Keluar' ?>
                                        </span>
                                    </td>
                                    <td style="font-weight:700;color:<?= $r['jenis']==='masuk' ? '#27ae60' : '#e74c3c' ?>;">
                                        <?= $r['jenis'] === 'masuk' ? '+' : '−' ?><?= number_format($r['jumlah'], 2, ',', '.') ?> <?= e($r['satuan'] ?? '') ?>
                                    </td>
                                    <td style="color:#888;font-size:12px;"><?= e($r['keterangan'] ?: '-') ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Hapus transaksi ini? Stok bahan akan dikoreksi otomatis.')" style="display:inline;">
                                            <input type="hidden" name="action" value="hapus_transaksi">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="sbb-btn sbb-btn-red sbb-btn-sm"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- ============ MODAL TAMBAH/EDIT BAHAN ============ -->
<div class="sbb-modal-overlay" id="modalBahan">
    <div class="sbb-modal-box">
        <form method="post">
            <input type="hidden" name="action" id="formBahanAction" value="tambah_bahan">
            <input type="hidden" name="id" id="formBahanId" value="">

            <div class="sbb-modal-head">
                <h3><i class="fas fa-box"></i> <span id="modalBahanTitle">Tambah Bahan Baku</span></h3>
                <button type="button" class="sbb-modal-close" onclick="tutupModalBahan()"><i class="fas fa-times"></i></button>
            </div>

            <div class="sbb-modal-body">
                <div class="sbb-form-group">
                    <label>Nama Bahan <span style="color:#e74c3c;">*</span></label>
                    <input type="text" name="nama" id="formBahanNama" placeholder="Contoh: Ayam" required>
                </div>

                <div class="sbb-form-row">
                    <div class="sbb-form-group" id="wrapStokAwal">
                        <label>Stok Awal</label>
                        <input type="text" name="stok" id="formBahanStok" placeholder="0" value="0">
                    </div>
                    <div class="sbb-form-group">
                        <label>Satuan <span style="color:#e74c3c;">*</span></label>
                        <select name="satuan" id="formBahanSatuan" required>
                            <option value="kg">kg</option>
                            <option value="gram">gram</option>
                            <option value="liter">liter</option>
                            <option value="ml">ml</option>
                            <option value="pack">pack</option>
                            <option value="pcs">pcs</option>
                            <option value="box">box</option>
                        </select>
                    </div>
                </div>

                <div class="sbb-form-group">
                    <label>Stok Minimum (batas peringatan)</label>
                    <input type="text" name="stok_minimum" id="formBahanMin" placeholder="Contoh: 3" value="0">
                    <div style="font-size:11px;color:#999;margin-top:4px;">Kalau stok ≤ angka ini, status jadi "Hampir Habis"</div>
                </div>

                <div class="sbb-form-group">
                    <label>Catatan (opsional)</label>
                    <textarea name="catatan" id="formBahanCatatan" rows="2" placeholder="Catatan tambahan..."></textarea>
                </div>
            </div>

            <div class="sbb-modal-foot">
                <button type="button" class="sbb-btn sbb-btn-ghost" onclick="tutupModalBahan()">Batal</button>
                <button type="submit" class="sbb-btn sbb-btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ MODAL CATAT TRANSAKSI ============ -->
<div class="sbb-modal-overlay" id="modalTransaksiBahan">
    <div class="sbb-modal-box">
        <form method="post">
            <input type="hidden" name="action" value="catat_transaksi">
            <input type="hidden" name="jenis" id="formTrxJenis" value="masuk">

            <div class="sbb-modal-head">
                <h3><i class="fas fa-exchange-alt"></i> <span id="modalTrxTitle">Catat Bahan Masuk</span></h3>
                <button type="button" class="sbb-modal-close" onclick="tutupModalTrx()"><i class="fas fa-times"></i></button>
            </div>

            <div class="sbb-modal-body">
                <div class="sbb-form-group">
                    <label>Pilih Bahan <span style="color:#e74c3c;">*</span></label>
                    <select name="bahan_id" required>
                        <option value="">— Pilih Bahan —</option>
                        <?php foreach ($daftarBahan as $b): ?>
                            <option value="<?= (int)$b['id'] ?>">
                                <?= e($b['nama']) ?> (stok: <?= number_format($b['stok'], 2, ',', '.') ?> <?= e($b['satuan']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sbb-form-row">
                    <div class="sbb-form-group">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="sbb-form-group">
                        <label>Jumlah <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="jumlah" placeholder="Contoh: 2.5" required>
                    </div>
                </div>

                <div class="sbb-form-group">
                    <label>Keterangan (opsional)</label>
                    <input type="text" name="keterangan" placeholder="Contoh: Beli dari supplier">
                </div>
            </div>

            <div class="sbb-modal-foot">
                <button type="button" class="sbb-btn sbb-btn-ghost" onclick="tutupModalTrx()">Batal</button>
                <button type="submit" class="sbb-btn sbb-btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ============ MODAL BAHAN ============ */
function bukaModalBahan() {
    document.getElementById('formBahanAction').value = 'tambah_bahan';
    document.getElementById('formBahanId').value = '';
    document.getElementById('formBahanNama').value = '';
    document.getElementById('formBahanStok').value = '0';
    document.getElementById('formBahanSatuan').value = 'kg';
    document.getElementById('formBahanMin').value = '0';
    document.getElementById('formBahanCatatan').value = '';
    document.getElementById('modalBahanTitle').textContent = 'Tambah Bahan Baku';
    document.getElementById('wrapStokAwal').style.display = '';
    document.getElementById('modalBahan').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editBahan(data) {
    document.getElementById('formBahanAction').value = 'edit_bahan';
    document.getElementById('formBahanId').value = data.id;
    document.getElementById('formBahanNama').value = data.nama;
    document.getElementById('formBahanSatuan').value = data.satuan;
    document.getElementById('formBahanMin').value = data.stok_minimum;
    document.getElementById('formBahanCatatan').value = data.catatan || '';
    document.getElementById('modalBahanTitle').textContent = 'Edit Bahan Baku';
    document.getElementById('wrapStokAwal').style.display = 'none';
    document.getElementById('modalBahan').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function tutupModalBahan() {
    document.getElementById('modalBahan').classList.remove('show');
    document.body.style.overflow = '';
}

/* ============ MODAL TRANSAKSI ============ */
function bukaModalTransaksi(jenis) {
    document.getElementById('formTrxJenis').value = jenis;
    document.getElementById('modalTrxTitle').textContent = jenis === 'masuk' ? 'Catat Bahan Masuk' : 'Catat Bahan Keluar';
    document.getElementById('modalTransaksiBahan').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function tutupModalTrx() {
    document.getElementById('modalTransaksiBahan').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('modalBahan').addEventListener('click', function(e){ if (e.target === this) tutupModalBahan(); });
document.getElementById('modalTransaksiBahan').addEventListener('click', function(e){ if (e.target === this) tutupModalTrx(); });
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') { tutupModalBahan(); tutupModalTrx(); }
});
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>