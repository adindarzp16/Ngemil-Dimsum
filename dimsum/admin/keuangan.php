<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$tglMulai = $_GET['dari'] ?? date('Y-m-01');
$tglAkhir = $_GET['sampai'] ?? date('Y-m-t');

/* TAMBAH TRANSAKSI */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tambah_transaksi') {
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $jenis = $_POST['jenis'] ?? 'pemasukan';
    $jumlah = (int)str_replace(['.', ',', ' '], '', $_POST['jumlah'] ?? 0);
    $metode = $_POST['metode'] ?? 'cash';
    $catatan = trim($_POST['catatan'] ?? '');

    if ($keterangan && $jumlah > 0 && in_array($jenis, ['pemasukan', 'pengeluaran'], true)) {
        $stmt = $koneksi->prepare("INSERT INTO keuangan_transaksi (tanggal,keterangan,jenis,jumlah,metode,catatan) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('sssiss', $tanggal, $keterangan, $jenis, $jumlah, $metode, $catatan);
        $stmt->execute();
        flash('success', 'Transaksi berhasil ditambahkan.');
    }
    redirect('keuangan.php');
}

/* HAPUS TRANSAKSI */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus_transaksi') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $koneksi->prepare("DELETE FROM keuangan_transaksi WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    flash('success', 'Transaksi dihapus.');
    redirect('keuangan.php');
}

/* RINGKASAN PERIODE */
$q = $koneksi->prepare("
    SELECT
    COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN jumlah ELSE 0 END),0) AS total_masuk,
    COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN jumlah ELSE 0 END),0) AS total_keluar
    FROM keuangan_transaksi WHERE tanggal BETWEEN ? AND ?
");
$q->bind_param('ss', $tglMulai, $tglAkhir);
$q->execute();
$ring = $q->get_result()->fetch_assoc();

$totalMasuk = (int)$ring['total_masuk'];
$totalKeluar = (int)$ring['total_keluar'];
$labaBersih = $totalMasuk - $totalKeluar;

/* SALDO KAS */
$kas = $koneksi->query("
    SELECT
    COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN jumlah ELSE 0 END),0) -
    COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN jumlah ELSE 0 END),0) AS saldo
    FROM keuangan_transaksi
")->fetch_assoc();
$saldoKas = (int)$kas['saldo'];

/* PERBANDINGAN BULAN LALU */
$bulanLaluAwal = date('Y-m-01', strtotime($tglMulai . ' -1 month'));
$bulanLaluAkhir = date('Y-m-t', strtotime($tglAkhir . ' -1 month'));

$q2 = $koneksi->prepare("
    SELECT
    COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN jumlah ELSE 0 END),0) AS masuk_lalu,
    COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN jumlah ELSE 0 END),0) AS keluar_lalu
    FROM keuangan_transaksi WHERE tanggal BETWEEN ? AND ?
");
$q2->bind_param('ss', $bulanLaluAwal, $bulanLaluAkhir);
$q2->execute();
$lalu = $q2->get_result()->fetch_assoc();

$masukLalu = (int)$lalu['masuk_lalu'];
$keluarLalu = (int)$lalu['keluar_lalu'];
$labaLalu = $masukLalu - $keluarLalu;

$pctMasuk = $masukLalu != 0 ? round((($totalMasuk - $masukLalu) / abs($masukLalu)) * 100) : ($totalMasuk > 0 ? 100 : 0);
$pctKeluar = $keluarLalu != 0 ? round((($totalKeluar - $keluarLalu) / abs($keluarLalu)) * 100) : ($totalKeluar > 0 ? 100 : 0);
$pctLaba = $labaLalu != 0 ? round((($labaBersih - $labaLalu) / abs($labaLalu)) * 100) : 0;
$persenProfit = $totalMasuk > 0 ? round(($labaBersih / $totalMasuk) * 100) : 0;

/* GRAFIK KEUANGAN */
$periodeGrafik = $_GET['grafik_periode'] ?? '7';
if (!in_array($periodeGrafik, ['7', '30', '12'], true)) $periodeGrafik = '7';

$hariIni = date('Y-m-d');
$grafik = [];

if ($periodeGrafik === '12') {
    $awalGrafik = date('Y-m-01', strtotime('-11 months', strtotime(date('Y-m-01'))));

    $gr = $koneksi->prepare("
        SELECT DATE_FORMAT(tanggal,'%Y-%m') AS periode,
        COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN jumlah ELSE 0 END),0) AS masuk,
        COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN jumlah ELSE 0 END),0) AS keluar
        FROM keuangan_transaksi WHERE tanggal BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(tanggal,'%Y-%m') ORDER BY periode ASC
    ");
    $gr->bind_param('ss', $awalGrafik, $hariIni);
    $gr->execute();
    $hasilGrafik = $gr->get_result()->fetch_all(MYSQLI_ASSOC);
    $dataPeriode = [];

    foreach ($hasilGrafik as $row) $dataPeriode[$row['periode']] = $row;

    for ($i = 11; $i >= 0; $i--) {
        $bulan = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))));
        $grafik[] = [
            'label' => date('M Y', strtotime($bulan . '-01')),
            'masuk' => (int)($dataPeriode[$bulan]['masuk'] ?? 0),
            'keluar' => (int)($dataPeriode[$bulan]['keluar'] ?? 0)
        ];
    }
} else {
    $jumlahHari = (int)$periodeGrafik;
    $awalGrafik = date('Y-m-d', strtotime('-' . ($jumlahHari - 1) . ' days', strtotime($hariIni)));

    $gr = $koneksi->prepare("
        SELECT tanggal,
        COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN jumlah ELSE 0 END),0) AS masuk,
        COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN jumlah ELSE 0 END),0) AS keluar
        FROM keuangan_transaksi WHERE tanggal BETWEEN ? AND ?
        GROUP BY tanggal ORDER BY tanggal ASC
    ");
    $gr->bind_param('ss', $awalGrafik, $hariIni);
    $gr->execute();
    $hasilGrafik = $gr->get_result()->fetch_all(MYSQLI_ASSOC);
    $dataTanggal = [];

    foreach ($hasilGrafik as $row) $dataTanggal[$row['tanggal']] = $row;

    for ($i = $jumlahHari - 1; $i >= 0; $i--) {
        $tgl = date('Y-m-d', strtotime("-$i days", strtotime($hariIni)));
        $grafik[] = [
            'label' => date('d M', strtotime($tgl)),
            'masuk' => (int)($dataTanggal[$tgl]['masuk'] ?? 0),
            'keluar' => (int)($dataTanggal[$tgl]['keluar'] ?? 0)
        ];
    }
}

$grafikTotalMasuk = array_sum(array_column($grafik, 'masuk'));
$grafikTotalKeluar = array_sum(array_column($grafik, 'keluar'));
$grafikJudulPeriode = ['7' => '7 Hari Terakhir', '30' => '30 Hari Terakhir', '12' => '12 Bulan Terakhir'][$periodeGrafik];

/* AJAX GRAFIK: KIRIM DATA TANPA RELOAD HALAMAN */
if (($_GET['ajax_grafik'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'periode' => $periodeGrafik,
        'judul' => $grafikJudulPeriode,
        'labels' => array_column($grafik, 'label'),
        'masuk' => array_column($grafik, 'masuk'),
        'keluar' => array_column($grafik, 'keluar'),
        'totalMasuk' => $grafikTotalMasuk,
        'totalKeluar' => $grafikTotalKeluar
    ]);
    exit;
}

/* TRANSAKSI TERBARU */
$transaksi = $koneksi->query("SELECT * FROM keuangan_transaksi ORDER BY tanggal DESC,id DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Keuangan';
$pageCrumb = ['Dashboard' => 'index.php', 'Keuangan' => 'keuangan.php'];
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/sidebar.php';
?>

<style>
.page-header-flex{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:15px}
.date-filter{display:flex;align-items:center;gap:8px;background:#fff;padding:9px 16px;border-radius:10px;border:1px solid #e6e2dc;font-size:13px;color:#555;box-shadow:0 1px 3px rgba(0,0,0,.03)}
.date-filter input{border:none;outline:none;font-family:'Poppins',sans-serif;font-size:12.5px;color:#7a1a2e;font-weight:600;cursor:pointer;background:transparent}
.tabs-row{display:flex;gap:4px;margin-bottom:20px;background:#fff;padding:5px;border-radius:12px;width:fit-content;border:1px solid #e6e2dc;flex-wrap:wrap}
.tab-btn{padding:9px 22px;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;color:#8a6a4a;border:none;background:transparent;cursor:pointer;transition:.2s}
.tab-btn.active{background:#fff7e3;color:#7a1a2e;font-weight:700;box-shadow:0 2px 6px rgba(212,168,67,.15)}
.keu-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;margin-bottom:22px}
.keu-stat-card{background:#fff;border-radius:14px;padding:20px;border:1px solid #f0ebe3;transition:.25s}
.keu-stat-card:hover{box-shadow:0 6px 22px rgba(122,26,46,.08);transform:translateY(-2px)}
.keu-stat-head{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.keu-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px}
.keu-stat-icon.green{background:#27ae60}.keu-stat-icon.red{background:#e74c3c}.keu-stat-icon.amber{background:#f39c12}.keu-stat-icon.purple{background:#9b59b6}
.keu-stat-label{font-size:12.5px;font-weight:600;color:#555;line-height:1.3}
.keu-stat-value{font-size:24px;font-weight:800;color:#2c0a0e;margin-bottom:6px;letter-spacing:-.5px}
.keu-stat-trend{font-size:11.5px;font-weight:600;display:inline-flex;align-items:center;gap:5px}
.keu-stat-trend.up{color:#27ae60}.keu-stat-trend.down{color:#e74c3c}
.keu-stat-sub{font-size:11px;color:#a89a86;margin-top:4px}
.keu-two-col{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px}
.keu-card{background:#fff;border-radius:14px;border:1px solid #f0ebe3;padding:20px;min-width:0}
.keu-card-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
.keu-card-title{display:flex;align-items:center;gap:10px;font-size:15px;font-weight:700;color:#2c0a0e}
.keu-card-title i{color:#d4a843;font-size:16px}
.keu-card-action{font-size:12px;color:#7a1a2e;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:5px}
.keu-card-action:hover{color:#d4a843}
.chart-legend{display:flex;gap:18px;font-size:11.5px;color:#666;margin-bottom:14px;flex-wrap:wrap}
.chart-legend span{display:flex;align-items:center;gap:6px}
.chart-legend .dot{width:10px;height:10px;border-radius:50%}
.dot-green{background:#27ae60}.dot-red{background:#e76b6b}
.chart-summary{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0 18px}
.chart-summary-item{padding:13px 15px;border-radius:11px;background:#fdfbf7;border:1px solid #f0ebe3;min-width:0}
.chart-summary-label{font-size:11px;color:#8a7a6a;margin-bottom:5px;font-weight:600}
.chart-summary-value{font-size:17px;font-weight:800;letter-spacing:-.3px;overflow-wrap:anywhere}
.chart-summary-value.income{color:#219653}.chart-summary-value.expense{color:#dc5555}
.chart-period-select{padding:8px 12px;border:1px solid #e6e2dc;border-radius:9px;background:#fdfbf7;color:#7a1a2e;font-family:'Poppins',sans-serif;font-size:11.5px;font-weight:600;outline:none;cursor:pointer;transition:.2s}
.chart-period-select:focus{border-color:#d4a843;box-shadow:0 0 0 3px rgba(212,168,67,.1)}
.quick-list{display:flex;flex-direction:column;gap:10px}
.quick-btn{display:flex;align-items:center;gap:14px;padding:14px;border-radius:12px;text-decoration:none;transition:.2s;border:1.5px solid transparent;cursor:pointer;background:none;font-family:'Poppins',sans-serif;text-align:left;width:100%}
.quick-btn:hover{transform:translateX(3px)}
.quick-btn.green{background:#eef9f2;border-color:#d1f0dd}.quick-btn.red{background:#fef2f2;border-color:#fbdcdc}.quick-btn.amber{background:#fffbe9;border-color:#f7edc7}
.quick-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0}
.quick-btn.green .quick-icon{background:#27ae60}.quick-btn.red .quick-icon{background:#e74c3c}.quick-btn.amber .quick-icon{background:#f39c12}
.quick-info{flex:1}.quick-info h4{font-size:13px;font-weight:700;color:#2c0a0e;margin-bottom:1px}.quick-info p{font-size:11px;color:#8a7a6a;margin:0}
.quick-arrow{color:#bbb;font-size:14px}
.keu-table-wrap{overflow-x:auto}.keu-table{width:100%;border-collapse:collapse;font-size:12.5px}
.keu-table th{background:#faf7f2;text-align:left;padding:11px 14px;font-weight:600;color:#8a6a4a;font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;border-bottom:1px solid #f0ebe3}
.keu-table td{padding:12px 14px;border-bottom:1px solid #f5efe4;color:#444}
.keu-table tr:last-child td{border-bottom:none}.keu-table tr:hover{background:#fdfbf7}
.keu-badge-jenis{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:10.5px;font-weight:700}
.keu-badge-jenis.masuk{background:#e8f8ef;color:#1e8a4b}.keu-badge-jenis.keluar{background:#fdecec;color:#c0392b}
.keu-badge-metode{padding:3px 9px;border-radius:6px;font-size:10.5px;background:#f5efe4;color:#7a5a3a;font-weight:600;text-transform:capitalize}
.ringkasan-item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px dashed #f0ebe3;font-size:13px;gap:10px}
.ringkasan-item:last-of-type{border-bottom:none}.ringkasan-item span:first-child{color:#666}
.ringkasan-item strong{color:#2c0a0e;font-weight:700}
.ringkasan-laba{background:linear-gradient(135deg,#fff7e3,#fdf0d0);border-radius:12px;padding:16px;margin-top:12px;display:flex;justify-content:space-between;align-items:center}
.ringkasan-laba .lbl{font-size:13px;font-weight:700;color:#7a5a10}.ringkasan-laba .val{font-size:19px;font-weight:800;color:#7a5a10}
.ringkasan-laba small{display:block;font-size:10.5px;color:#a07d10;margin-top:2px;text-align:right}
.keu-progress-bar{background:#f0ebe3;height:8px;border-radius:10px;overflow:hidden;margin-top:10px}
.keu-progress-bar>div{background:linear-gradient(90deg,#f39c12,#d4a843);height:100%;border-radius:10px;transition:.4s}
.profit-label{display:flex;justify-content:space-between;font-size:11.5px;color:#8a6a4a;font-weight:600;margin-top:10px}
.keu-modal-overlay{position:fixed;inset:0;background:rgba(30,20,10,.5);z-index:900;display:none;align-items:center;justify-content:center;padding:20px}
.keu-modal-overlay.show{display:flex}
.keu-modal-box{background:#fff;border-radius:16px;max-width:520px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.keu-modal-head{padding:20px 24px;border-bottom:1px solid #f0ebe3;display:flex;align-items:center;justify-content:space-between}
.keu-modal-head h3{font-size:17px;font-weight:800;color:#2c0a0e;display:flex;align-items:center;gap:10px;margin:0}
.keu-modal-head h3 i{color:#d4a843}
.keu-modal-close{background:#f5efe4;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;color:#8a6a4a;font-size:14px}
.keu-modal-close:hover{background:#e8dcc4}.keu-modal-body{padding:22px 24px}
.keu-form-group{margin-bottom:16px}
.keu-form-group label{display:block;font-size:12.5px;font-weight:600;color:#555;margin-bottom:6px}
.keu-form-group input,.keu-form-group select,.keu-form-group textarea{width:100%;padding:11px 14px;border:1.5px solid #e6e2dc;border-radius:10px;font-family:'Poppins',sans-serif;font-size:13px;color:#333;outline:none;transition:.2s;background:#fdfbf7;box-sizing:border-box}
.keu-form-group input:focus,.keu-form-group select:focus,.keu-form-group textarea:focus{border-color:#d4a843;background:#fff}
.keu-form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.keu-modal-foot{padding:16px 24px;border-top:1px solid #f0ebe3;display:flex;justify-content:flex-end;gap:10px}
.keu-btn{padding:11px 22px;border-radius:10px;border:none;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:.2s}
.keu-btn-primary{background:#7a1a2e;color:#fff}.keu-btn-primary:hover{background:#5c1020}
.keu-btn-ghost{background:#f5efe4;color:#7a5a3a}.keu-btn-ghost:hover{background:#e8dcc4}
@media(max-width:900px){.keu-two-col{grid-template-columns:1fr}}
@media(max-width:500px){
.chart-summary{gap:8px}.chart-summary-item{padding:11px}.chart-summary-value{font-size:14px}
.keu-card{padding:15px}.keu-card-header{flex-wrap:wrap}.keu-stat-grid{grid-template-columns:1fr}
.date-filter{flex-wrap:wrap}.keu-form-row{grid-template-columns:1fr;gap:0}
.tabs-row{width:100%}.tab-btn{padding:8px 12px;font-size:11px}
}
</style>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="admin-breadcrumb">Admin / Keuangan</div>
            <h1 class="admin-page-title">Keuangan</h1>
            <p style="font-size:12px;color:#999;margin-top:4px;">Kelola pemasukan, pengeluaran, dan pantau kondisi keuangan bisnismu.</p>
        </div>
        <form method="get" class="date-filter">
            <input type="hidden" name="grafik_periode" value="<?= e($periodeGrafik) ?>">
            <i class="far fa-calendar-alt" style="color:#d4a843;"></i>
            <input type="date" name="dari" value="<?= e($tglMulai) ?>" onchange="this.form.submit()">
            <span style="color:#bbb;">—</span>
            <input type="date" name="sampai" value="<?= e($tglAkhir) ?>" onchange="this.form.submit()">
        </form>
    </div>

    <div class="admin-content">
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
        <?php endif; ?>

        <div class="tabs-row">
            <button type="button" class="tab-btn active" onclick="scrollToSection('ringkasan')">Ringkasan</button>
            <button type="button" class="tab-btn" onclick="bukaModalKeu('pemasukan')">Pemasukan</button>
            <button type="button" class="tab-btn" onclick="bukaModalKeu('pengeluaran')">Pengeluaran</button>
            <button type="button" class="tab-btn" onclick="scrollToSection('laba-rugi')">Laporan Laba Rugi</button>
        </div>

        <!-- STATISTIK -->
        <div class="keu-stat-grid" id="ringkasan">
            <div class="keu-stat-card">
                <div class="keu-stat-head"><div class="keu-stat-icon green"><i class="fas fa-arrow-down"></i></div><div class="keu-stat-label">Total Pemasukan</div></div>
                <div class="keu-stat-value"><?= rupiah($totalMasuk) ?></div>
                <div class="keu-stat-trend <?= $pctMasuk >= 0 ? 'up' : 'down' ?>"><i class="fas fa-arrow-<?= $pctMasuk >= 0 ? 'up' : 'down' ?>"></i> <?= abs($pctMasuk) ?>%</div>
                <div class="keu-stat-sub">Dibanding periode sebelumnya</div>
            </div>
            <div class="keu-stat-card">
                <div class="keu-stat-head"><div class="keu-stat-icon red"><i class="fas fa-arrow-up"></i></div><div class="keu-stat-label">Total Pengeluaran</div></div>
                <div class="keu-stat-value"><?= rupiah($totalKeluar) ?></div>
                <div class="keu-stat-trend <?= $pctKeluar <= 0 ? 'up' : 'down' ?>"><i class="fas fa-arrow-<?= $pctKeluar >= 0 ? 'up' : 'down' ?>"></i> <?= abs($pctKeluar) ?>%</div>
                <div class="keu-stat-sub">Dibanding periode sebelumnya</div>
            </div>
            <div class="keu-stat-card">
                <div class="keu-stat-head"><div class="keu-stat-icon amber"><i class="fas fa-chart-line"></i></div><div class="keu-stat-label">Laba Bersih</div></div>
                <div class="keu-stat-value"><?= rupiah($labaBersih) ?></div>
                <div class="keu-stat-trend <?= $pctLaba >= 0 ? 'up' : 'down' ?>"><i class="fas fa-arrow-<?= $pctLaba >= 0 ? 'up' : 'down' ?>"></i> <?= abs($pctLaba) ?>%</div>
                <div class="keu-stat-sub">Dibanding periode sebelumnya</div>
            </div>
            <div class="keu-stat-card">
                <div class="keu-stat-head"><div class="keu-stat-icon purple"><i class="fas fa-wallet"></i></div><div class="keu-stat-label">Saldo Kas</div></div>
                <div class="keu-stat-value"><?= rupiah($saldoKas) ?></div>
                <div class="keu-stat-sub" style="margin-top:10px;">Total saldo saat ini</div>
            </div>
        </div>

        <!-- GRAFIK DAN AKSI CEPAT -->
        <div class="keu-two-col">
            <div class="keu-card">
                <div class="keu-card-header">
                    <div class="keu-card-title"><i class="fas fa-chart-bar"></i> Grafik Keuangan</div>
                    <select id="pilihPeriodeGrafik" class="chart-period-select" aria-label="Pilih periode grafik">
                        <option value="7" <?= $periodeGrafik === '7' ? 'selected' : '' ?>>7 Hari</option>
                        <option value="30" <?= $periodeGrafik === '30' ? 'selected' : '' ?>>30 Hari</option>
                        <option value="12" <?= $periodeGrafik === '12' ? 'selected' : '' ?>>12 Bulan</option>
                    </select>
                </div>

                <div id="judulPeriodeGrafik" style="font-size:11px;color:#999;margin-top:-10px;margin-bottom:15px;">
                    Perkembangan pemasukan dan pengeluaran · <?= e($grafikJudulPeriode) ?>
                </div>

                <div class="chart-legend">
                    <span><span class="dot dot-green"></span> Pemasukan</span>
                    <span><span class="dot dot-red"></span> Pengeluaran</span>
                </div>

                <div class="chart-summary">
                    <div class="chart-summary-item">
                        <div class="chart-summary-label"><i class="fas fa-arrow-down" style="color:#27ae60;"></i> Total Pemasukan</div>
                        <div class="chart-summary-value income" id="grafikTotalMasuk"><?= rupiah($grafikTotalMasuk) ?></div>
                    </div>
                    <div class="chart-summary-item">
                        <div class="chart-summary-label"><i class="fas fa-arrow-up" style="color:#e74c3c;"></i> Total Pengeluaran</div>
                        <div class="chart-summary-value expense" id="grafikTotalKeluar"><?= rupiah($grafikTotalKeluar) ?></div>
                    </div>
                </div>

                <div style="position:relative;height:250px;width:100%;">
                    <canvas id="chartKeuangan"></canvas>
                </div>
                <div id="statusGrafik" style="font-size:11px;color:#999;text-align:center;margin-top:8px;display:none;"></div>
            </div>

            <div class="keu-card">
                <div class="keu-card-header"><div class="keu-card-title"><i class="fas fa-bolt"></i> Aksi Cepat</div></div>
                <div class="quick-list">
                    <button type="button" class="quick-btn green" onclick="bukaModalKeu('pemasukan')">
                        <div class="quick-icon"><i class="fas fa-plus"></i></div>
                        <div class="quick-info"><h4>Tambah Pemasukan</h4><p>Catat transaksi pemasukan</p></div>
                        <i class="fas fa-chevron-right quick-arrow"></i>
                    </button>
                    <button type="button" class="quick-btn red" onclick="bukaModalKeu('pengeluaran')">
                        <div class="quick-icon"><i class="fas fa-minus"></i></div>
                        <div class="quick-info"><h4>Tambah Pengeluaran</h4><p>Catat transaksi pengeluaran</p></div>
                        <i class="fas fa-chevron-right quick-arrow"></i>
                    </button>
                    <a href="#laba-rugi" class="quick-btn amber">
                        <div class="quick-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="quick-info"><h4>Laporan Laba Rugi</h4><p>Lihat ringkasan laba rugi</p></div>
                        <i class="fas fa-chevron-right quick-arrow"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- TRANSAKSI DAN LABA RUGI -->
        <div class="keu-two-col">
            <div class="keu-card">
                <div class="keu-card-header">
                    <div class="keu-card-title"><i class="fas fa-receipt"></i> Transaksi Terbaru</div>
                    <a href="#" class="keu-card-action">Lihat Semua <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="keu-table-wrap">
                    <table class="keu-table">
                        <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Jenis</th><th>Jumlah</th><th>Metode</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php if (empty($transaksi)): ?>
                                <tr><td colspan="6" style="text-align:center;padding:30px;color:#999;">Belum ada transaksi.</td></tr>
                            <?php else: ?>
                                <?php foreach ($transaksi as $t): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($t['tanggal'])) ?></td>
                                        <td><?= e($t['keterangan']) ?></td>
                                        <td><span class="keu-badge-jenis <?= $t['jenis'] === 'pemasukan' ? 'masuk' : 'keluar' ?>"><?= $t['jenis'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?></span></td>
                                        <td style="font-weight:600;color:<?= $t['jenis'] === 'pemasukan' ? '#27ae60' : '#e74c3c' ?>;"><?= rupiah($t['jumlah']) ?></td>
                                        <td><span class="keu-badge-metode"><?= e($t['metode']) ?></span></td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Hapus transaksi ini?')" style="display:inline;">
                                                <input type="hidden" name="action" value="hapus_transaksi">
                                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                                <button type="submit" style="background:none;border:none;cursor:pointer;color:#d4a843;font-size:11px;font-weight:700;font-family:'Poppins',sans-serif;">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="keu-card" id="laba-rugi">
                <div class="keu-card-header"><div class="keu-card-title"><i class="fas fa-chart-pie"></i> Ringkasan Laba Rugi</div></div>
                <div class="ringkasan-item">
                    <span><i class="fas fa-circle" style="color:#27ae60;font-size:8px;margin-right:6px;"></i> Total Pendapatan</span>
                    <strong><?= rupiah($totalMasuk) ?></strong>
                </div>
                <div class="ringkasan-item">
                    <span><i class="fas fa-circle" style="color:#e74c3c;font-size:8px;margin-right:6px;"></i> Total Beban</span>
                    <strong><?= rupiah($totalKeluar) ?></strong>
                </div>
                <div class="ringkasan-laba">
                    <div class="lbl">Laba Bersih</div>
                    <div style="text-align:right;">
                        <div class="val"><?= rupiah($labaBersih) ?></div>
                        <small><i class="fas fa-arrow-<?= $pctLaba >= 0 ? 'up' : 'down' ?>"></i> <?= abs($pctLaba) ?>% dari bulan lalu</small>
                    </div>
                </div>
                <div class="profit-label"><span>Persentase Profit</span><span><?= $persenProfit ?>%</span></div>
                <div class="keu-progress-bar"><div style="width:<?= max(0, min(100, $persenProfit)) ?>%"></div></div>
            </div>
        </div>
    </div>
</main>

<!-- MODAL TRANSAKSI -->
<div class="keu-modal-overlay" id="modalKeuangan">
    <div class="keu-modal-box">
        <form method="post">
            <input type="hidden" name="action" value="tambah_transaksi">
            <input type="hidden" name="jenis" id="inputJenisKeu" value="pemasukan">
            <div class="keu-modal-head">
                <h3><i class="fas fa-plus-circle"></i> <span id="modalTitleKeu">Tambah Pemasukan</span></h3>
                <button type="button" class="keu-modal-close" onclick="tutupModalKeu()"><i class="fas fa-times"></i></button>
            </div>
            <div class="keu-modal-body">
                <div class="keu-form-row">
                    <div class="keu-form-group">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="keu-form-group">
                        <label>Metode</label>
                        <select name="metode">
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="keu-form-group">
                    <label>Keterangan</label>
                    <input type="text" name="keterangan" placeholder="Contoh: Penjualan Paket Mix" required>
                </div>
                <div class="keu-form-group">
                    <label>Jumlah (Rp)</label>
                    <input type="number" name="jumlah" placeholder="Contoh: 320000" min="1" required>
                </div>
                <div class="keu-form-group">
                    <label>Catatan (opsional)</label>
                    <textarea name="catatan" rows="2" placeholder="Catatan tambahan..."></textarea>
                </div>
            </div>
            <div class="keu-modal-foot">
                <button type="button" class="keu-btn keu-btn-ghost" onclick="tutupModalKeu()">Batal</button>
                <button type="submit" class="keu-btn keu-btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* MODAL */
function bukaModalKeu(jenis) {
    document.getElementById('inputJenisKeu').value = jenis;
    document.getElementById('modalTitleKeu').textContent = jenis === 'pemasukan' ? 'Tambah Pemasukan' : 'Tambah Pengeluaran';
    document.getElementById('modalKeuangan').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function tutupModalKeu() {
    document.getElementById('modalKeuangan').classList.remove('show');
    document.body.style.overflow = '';
}
document.getElementById('modalKeuangan').addEventListener('click', function(e) {
    if (e.target === this) tutupModalKeu();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') tutupModalKeu();
});

/* SCROLL SECTION */
function scrollToSection(id) {
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
}

/* FORMAT RUPIAH */
function formatRupiah(value) {
    return new Intl.NumberFormat('id-ID', {
        style:'currency', currency:'IDR', minimumFractionDigits:0, maximumFractionDigits:0
    }).format(value || 0);
}

/* CHART */
let chartKeuangan = null;
const labelsAwal = <?= json_encode(array_column($grafik, 'label'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const masukAwal = <?= json_encode(array_column($grafik, 'masuk')) ?>;
const keluarAwal = <?= json_encode(array_column($grafik, 'keluar')) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('chartKeuangan');
    if (!canvas || typeof Chart === 'undefined') return;

    chartKeuangan = new Chart(canvas.getContext('2d'), {
        type:'bar',
        data:{
            labels:labelsAwal,
            datasets:[
                {
                    label:'Pemasukan',
                    data:masukAwal,
                    backgroundColor:'#27ae60',
                    hoverBackgroundColor:'#219653',
                    borderRadius:8,
                    borderSkipped:false,
                    barPercentage:.72,
                    categoryPercentage:.65,
                    maxBarThickness:34
                },
                {
                    label:'Pengeluaran',
                    data:keluarAwal,
                    backgroundColor:'#e76b6b',
                    hoverBackgroundColor:'#d94b4b',
                    borderRadius:8,
                    borderSkipped:false,
                    barPercentage:.72,
                    categoryPercentage:.65,
                    maxBarThickness:34
                }
            ]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            animation:{duration:700,easing:'easeOutQuart'},
            interaction:{mode:'index',intersect:false},
            plugins:{
                legend:{display:false},
                tooltip:{
                    backgroundColor:'#2c0a0e',
                    titleColor:'#fff',
                    bodyColor:'#fff',
                    padding:13,
                    cornerRadius:10,
                    displayColors:true,
                    titleFont:{family:'Poppins',size:12,weight:'600'},
                    bodyFont:{family:'Poppins',size:12},
                    callbacks:{
                        label:context => ' ' + context.dataset.label + ': ' + formatRupiah(context.parsed.y)
                    }
                }
            },
            scales:{
                y:{
                    beginAtZero:true,
                    ticks:{
                        color:'#a89a86',
                        font:{family:'Poppins',size:10},
                        callback:function(value) {
                            if (value >= 1000000000) return (value / 1000000000) + ' M';
                            if (value >= 1000000) return (value / 1000000) + ' jt';
                            if (value >= 1000) return (value / 1000) + ' rb';
                            return value;
                        }
                    },
                    grid:{color:'#f0ebe3',borderDash:[5,5],drawBorder:false}
                },
                x:{
                    ticks:{
                        color:'#a89a86',
                        font:{family:'Poppins',size:10},
                        maxRotation:0,
                        autoSkip:true,
                        maxTicksLimit:10
                    },
                    grid:{display:false}
                }
            }
        }
    });
});

/* UBAH PERIODE GRAFIK TANPA RELOAD HALAMAN */
document.getElementById('pilihPeriodeGrafik').addEventListener('change', async function() {
    const select = this;
    const periode = select.value;
    const status = document.getElementById('statusGrafik');
    const pilihanSebelumnya = select.dataset.previous || '<?= e($periodeGrafik) ?>';

    select.disabled = true;
    status.style.display = 'block';
    status.textContent = 'Memperbarui grafik...';

    try {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax_grafik', '1');
        url.searchParams.set('grafik_periode', periode);

        const response = await fetch(url.toString(), {
            method:'GET',
            headers:{'X-Requested-With':'XMLHttpRequest'}
        });

        if (!response.ok) throw new Error('Gagal mengambil data grafik.');

        const data = await response.json();
        if (!data.success) throw new Error('Data grafik tidak tersedia.');

        if (!chartKeuangan) throw new Error('Grafik belum siap.');

        chartKeuangan.data.labels = data.labels;
        chartKeuangan.data.datasets[0].data = data.masuk;
        chartKeuangan.data.datasets[1].data = data.keluar;
        chartKeuangan.update();

        document.getElementById('grafikTotalMasuk').textContent = formatRupiah(data.totalMasuk);
        document.getElementById('grafikTotalKeluar').textContent = formatRupiah(data.totalKeluar);
        document.getElementById('judulPeriodeGrafik').textContent = 'Perkembangan pemasukan dan pengeluaran · ' + data.judul;

        const urlBersih = new URL(window.location.href);
        urlBersih.searchParams.delete('ajax_grafik');
        urlBersih.searchParams.set('grafik_periode', periode);
        window.history.replaceState({}, '', urlBersih.toString());

        select.dataset.previous = periode;
        status.style.display = 'none';
    } catch (error) {
        console.error(error);
        select.value = pilihanSebelumnya;
        status.textContent = 'Gagal memperbarui grafik. Silakan coba lagi.';
    } finally {
        select.disabled = false;
    }
});

document.getElementById('pilihPeriodeGrafik').dataset.previous = '<?= e($periodeGrafik) ?>';
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>