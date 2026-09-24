<?php
/* ============================================================
   NGEMIL DIMSUM — Form Tambah / Edit Produk (Admin)
   Fitur: Multi-gambar dengan crop, varian pcs optional
   ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];

// Data default
$produk = [
    'nama'         => '',
    'sku'          => '',
    'category_id'  => '',
    'deskripsi'    => '',
    'harga'        => '',
    'harga_diskon' => '',
    'stok'         => 0,
    'has_variant'  => 0,
    'gambar'       => '',
    'is_active'    => 1,
];
$existingImages = [];

// Kalau edit → load data
if ($isEdit) {
    $stmt = $koneksi->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    if (!$found) {
        flash('success', 'Produk tidak ditemukan.');
        redirect('products.php');
    }
    $produk = $found;

    // Ambil gambar dari product_images
    $stmt = $koneksi->prepare("SELECT id, gambar, urutan FROM product_images WHERE product_id = ? ORDER BY urutan ASC, id ASC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $existingImages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Auto-migrate kalau belum ada di product_images tapi products.gambar ada
    if (empty($existingImages) && !empty($produk['gambar'])) {
        $stmt = $koneksi->prepare("INSERT INTO product_images (product_id, gambar, urutan) VALUES (?, ?, 0)");
        $stmt->bind_param('is', $id, $produk['gambar']);
        $stmt->execute();
        $existingImages = [['id' => $koneksi->insert_id, 'gambar' => $produk['gambar'], 'urutan' => 0]];
    }
}

// Kategori
$categories = $koneksi->query("SELECT id, nama FROM categories ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

// ============================================================
// SUBMIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama         = trim($_POST['nama'] ?? '');
    $sku          = trim($_POST['sku'] ?? '');
    $category_id  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $deskripsi    = trim($_POST['deskripsi'] ?? '');
    $harga        = (int)($_POST['harga'] ?? 0);
    $harga_diskon = ($_POST['harga_diskon'] ?? '') !== '' ? (int)$_POST['harga_diskon'] : null;
    $stok         = (int)($_POST['stok'] ?? 0);
    $has_variant  = isset($_POST['has_variant']) ? 1 : 0;
    $is_active    = isset($_POST['is_active']) ? 1 : 0;
    $imagesJson   = $_POST['images_json'] ?? '[]';
    $imagesData   = json_decode($imagesJson, true);
    if (!is_array($imagesData)) $imagesData = [];

    // Validasi
    if ($nama === '')  $errors[] = 'Nama produk wajib diisi.';
    if ($sku === '')   $errors[] = 'SKU wajib diisi.';
    if ($harga <= 0)   $errors[] = 'Harga harus lebih dari 0.';
    if ($harga_diskon !== null && $harga_diskon >= $harga) $errors[] = 'Harga diskon harus lebih kecil dari harga normal.';

    // Cek SKU duplikat
    if (empty($errors)) {
        if ($isEdit) {
            $stmt = $koneksi->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
            $stmt->bind_param('si', $sku, $id);
        } else {
            $stmt = $koneksi->prepare("SELECT id FROM products WHERE sku = ?");
            $stmt->bind_param('s', $sku);
        }
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors[] = "SKU \"$sku\" sudah dipakai produk lain.";
    }

    // Simpan/update produk
    if (empty($errors)) {
        if ($isEdit) {
            $stmt = $koneksi->prepare("
                UPDATE products
                SET category_id=?, nama=?, sku=?, deskripsi=?, harga=?, harga_diskon=?, stok=?, has_variant=?, is_active=?
                WHERE id=?
            ");
            $stmt->bind_param('isssiiiisi',
                $category_id, $nama, $sku, $deskripsi, $harga, $harga_diskon, $stok, $has_variant, $is_active, $id
            );
        } else {
            $stmt = $koneksi->prepare("
                INSERT INTO products (category_id, nama, sku, deskripsi, harga, harga_diskon, stok, has_variant, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('isssiiiis',
                $category_id, $nama, $sku, $deskripsi, $harga, $harga_diskon, $stok, $has_variant, $is_active
            );
        }

        if ($stmt->execute()) {
            if (!$isEdit) {
                $id = $koneksi->insert_id;
                $isEdit = true;
            }

            // ============ PROSES GAMBAR ============
            // Ambil gambar existing dari DB
            $existingInDb = [];
            $stmtG = $koneksi->prepare("SELECT id, gambar FROM product_images WHERE product_id = ?");
            $stmtG->bind_param('i', $id);
            $stmtG->execute();
            $resG = $stmtG->get_result();
            while ($r = $resG->fetch_assoc()) $existingInDb[$r['id']] = $r['gambar'];

            $keptIds  = [];   // id => urutan baru
            $newPaths = [];   // ['path' => ..., 'urutan' => ...]
            $urutan   = 0;

            foreach ($imagesData as $img) {
                if (isset($img['id']) && isset($existingInDb[(int)$img['id']])) {
                    $keptIds[(int)$img['id']] = $urutan++;
                } elseif (!empty($img['base64'])) {
                    if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $img['base64'], $m)) {
                        $ext  = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                        $data = base64_decode($m[2]);
                        if ($data !== false && in_array($ext, ['jpg','jpeg','png','webp'])) {
                            $fileName  = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $uploadDir = __DIR__ . '/../images/products/';
                            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                            if (file_put_contents($uploadDir . $fileName, $data) !== false) {
                                $newPaths[] = ['path' => 'images/products/' . $fileName, 'urutan' => $urutan++];
                            }
                        }
                    }
                }
            }

            // Hapus gambar yang tidak dipertahankan (dari disk + DB)
            foreach ($existingInDb as $eid => $epath) {
                if (!isset($keptIds[$eid])) {
                    if ($epath && file_exists(__DIR__ . '/../' . $epath)) @unlink(__DIR__ . '/../' . $epath);
                    $del = $koneksi->prepare("DELETE FROM product_images WHERE id = ?");
                    $del->bind_param('i', $eid);
                    $del->execute();
                }
            }

            // Update urutan existing
            foreach ($keptIds as $eid => $u) {
                $upd = $koneksi->prepare("UPDATE product_images SET urutan = ? WHERE id = ?");
                $upd->bind_param('ii', $u, $eid);
                $upd->execute();
            }

            // Insert gambar baru
            foreach ($newPaths as $np) {
                $ins = $koneksi->prepare("INSERT INTO product_images (product_id, gambar, urutan) VALUES (?, ?, ?)");
                $ins->bind_param('isi', $id, $np['path'], $np['urutan']);
                $ins->execute();
            }

            // Update products.gambar = gambar utama (urutan terkecil)
            $stmtM = $koneksi->prepare("SELECT gambar FROM product_images WHERE product_id = ? ORDER BY urutan ASC, id ASC LIMIT 1");
            $stmtM->bind_param('i', $id);
            $stmtM->execute();
            $mainRow = $stmtM->get_result()->fetch_assoc();
            $mainPath = $mainRow ? $mainRow['gambar'] : '';
            $updM = $koneksi->prepare("UPDATE products SET gambar = ? WHERE id = ?");
            $updM->bind_param('si', $mainPath, $id);
            $updM->execute();

            flash('success', ($_POST['is_edit'] ?? 0) ? "Produk \"$nama\" berhasil diperbarui." : "Produk \"$nama\" berhasil ditambahkan.");
            redirect('products.php');
        } else {
            $errors[] = 'Gagal menyimpan produk: ' . $koneksi->error;
        }
    }
}

$pageTitle = $isEdit ? 'Edit Produk' : 'Tambah Produk';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/sidebar.php';
?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="admin-breadcrumb">
                <a href="index.php">Dashboard</a> / <a href="products.php">Produk</a> / <?= $isEdit ? 'Edit' : 'Tambah' ?>
            </div>
            <h1 class="admin-page-title"><?= $isEdit ? 'Edit Produk' : 'Tambah Produk Baru' ?></h1>
        </div>
        <a href="products.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="admin-content">

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <b>Ada kesalahan:</b>
                    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" id="produkForm">
            <input type="hidden" name="is_edit" value="<?= $isEdit ? 1 : 0 ?>">
            <input type="hidden" name="images_json" id="imagesJson" value="[]">

            <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;">

                <!-- ============== KIRI ============== -->
                <div>

                    <!-- INFO PRODUK -->
                    <div class="card" style="margin-bottom:20px;">
                        <div class="card-title"><i class="fas fa-info-circle"></i> Informasi Produk</div>
                        <div class="card-subtitle">Detail dasar produk yang akan tampil di katalog</div>

                        <div class="form-group">
                            <label class="form-label">Nama Produk <span class="req">*</span></label>
                            <input type="text" name="nama" class="form-input" value="<?= e($_POST['nama'] ?? $produk['nama']) ?>" required placeholder="Contoh: Dimsum Ayam Premium">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">SKU <span class="req">*</span></label>
                                <input type="text" name="sku" class="form-input" value="<?= e($_POST['sku'] ?? $produk['sku']) ?>" required placeholder="DS-AYM-01" style="font-family:monospace;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kategori <span style="color:#999;font-weight:400;">(opsional)</span></label>
                                <select name="category_id" class="form-input">
                                    <option value="">— Tanpa Kategori —</option>
                                    <?php $selCat = $_POST['category_id'] ?? $produk['category_id']; ?>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $selCat == $c['id'] ? 'selected' : '' ?>><?= e($c['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" class="form-input" placeholder="Deskripsi produk..."><?= e($_POST['deskripsi'] ?? $produk['deskripsi']) ?></textarea>
                        </div>
                    </div>

                    <!-- HARGA & STOK -->
                    <div class="card">
                        <div class="card-title"><i class="fas fa-tags"></i> Harga & Stok</div>
                        <div class="card-subtitle">Kosongkan harga diskon jika tidak ada promo</div>

                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">
                                    Harga Normal <span class="req">*</span>
                                    <span id="hargaLabelBase" style="display:none;color:#7a1a2e;font-size:11px;">(untuk 3 pcs)</span>
                                </label>
                                <div class="input-group">
                                    <span class="prefix">Rp</span>
                                    <input type="number" name="harga" id="hargaInput" min="0" value="<?= e($_POST['harga'] ?? $produk['harga']) ?>" required placeholder="18000">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Harga Diskon</label>
                                <div class="input-group">
                                    <span class="prefix">Rp</span>
                                    <input type="number" name="harga_diskon" min="0" value="<?= e($_POST['harga_diskon'] ?? $produk['harga_diskon']) ?>" placeholder="(opsional)">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stok</label>
                                <input type="number" name="stok" min="0" class="form-input" value="<?= e($_POST['stok'] ?? $produk['stok']) ?>" placeholder="0">
                            </div>
                        </div>

                        <!-- VARIAN PCS -->
                        <div style="margin-top:20px;padding:16px;background:#fdf6ee;border-radius:12px;border:1px solid #f0e2cb;">
                            <label style="display:flex;align-items:center;gap:12px;cursor:pointer;margin-bottom:8px;">
                                <input type="checkbox" name="has_variant" id="hasVariant" value="1"
                                       <?= ($_POST['has_variant'] ?? $produk['has_variant']) ? 'checked' : '' ?>
                                       style="width:18px;height:18px;accent-color:#7a1a2e;">
                                <span style="font-size:13px;font-weight:700;color:#2c0a0e;">
                                    <i class="fas fa-cubes" style="color:#d4a843;"></i>
                                    Produk ini punya varian pcs (3 / 6 / 8 pcs)
                                </span>
                            </label>
                            <div style="font-size:11px;color:#888;margin-bottom:12px;padding-left:30px;">
                                Centang untuk dimsum yang dijual per pcs. Jangan centang untuk minuman, paket, atau produk satuan.
                            </div>

                            <div id="variantPreview" style="display:none;padding-left:30px;">
                                <div style="font-size:11px;font-weight:700;color:#7a1a2e;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;">
                                    📊 Preview Harga Per Varian
                                </div>
                                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                                    <div style="background:#fff;padding:10px;border-radius:8px;border:1px solid #e0d8d0;text-align:center;">
                                        <div style="font-size:10px;color:#999;">3 pcs (base)</div>
                                        <div style="font-size:14px;font-weight:800;color:#7a1a2e;" id="previewHarga3">Rp 0</div>
                                    </div>
                                    <div style="background:#fff;padding:10px;border-radius:8px;border:1px solid #e0d8d0;text-align:center;">
                                        <div style="font-size:10px;color:#999;">6 pcs (×2)</div>
                                        <div style="font-size:14px;font-weight:800;color:#7a1a2e;" id="previewHarga6">Rp 0</div>
                                    </div>
                                    <div style="background:#fff;padding:10px;border-radius:8px;border:1px solid #e0d8d0;text-align:center;">
                                        <div style="font-size:10px;color:#999;">8 pcs (×2.67)</div>
                                        <div style="font-size:14px;font-weight:800;color:#7a1a2e;" id="previewHarga8">Rp 0</div>
                                    </div>
                                </div>
                                <div style="font-size:11px;color:#888;margin-top:8px;font-style:italic;">
                                    <i class="fas fa-info-circle"></i> Isi harga untuk <b>3 pcs</b> di atas, sisanya otomatis dihitung.
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============== KANAN ============== -->
                <div>

                    <!-- GAMBAR PRODUK (MULTI) -->
                    <div class="card" style="margin-bottom:20px;">
                        <div class="card-title"><i class="fas fa-images"></i> Foto Produk</div>
                        <div class="card-subtitle">Bisa upload beberapa foto. Foto pertama = gambar utama.</div>

                        <!-- Preview grid -->
                        <div id="imageList" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;"></div>

                        <!-- Tombol tambah -->
                        <label for="fileInput" class="upload-btn">
                            <i class="fas fa-plus-circle"></i> Tambah Foto
                        </label>
                        <input type="file" id="fileInput" accept="image/jpeg,image/png,image/webp" style="display:none;">

                        <div class="form-help" style="text-align:center;margin-top:10px;">
                            Format JPG / PNG / WEBP • Maks 8 foto • Bisa crop sebelum upload
                        </div>
                    </div>

                    <!-- STATUS -->
                    <div class="card" style="margin-bottom:20px;">
                        <div class="card-title" style="font-size:13px;"><i class="fas fa-toggle-on"></i> Status Produk</div>
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 0;">
                            <input type="checkbox" name="is_active" value="1" <?= ($_POST['is_active'] ?? $produk['is_active']) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#d4a843;">
                            <span style="font-size:13px;font-weight:600;color:#333;">Aktifkan produk</span>
                        </label>
                        <div class="form-help">Produk nonaktif tidak tampil di katalog pelanggan.</div>
                    </div>

                    <!-- SUBMIT -->
                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-save"></i>
                        <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Produk' ?>
                    </button>

                </div>
            </div>
        </form>
    </div>
</main>

<!-- CROP MODAL -->
<div class="crop-modal" id="cropModal">
    <div class="crop-modal-content">
        <div class="crop-modal-header">
            <h3><i class="fas fa-crop-alt"></i> Atur Foto Produk</h3>
            <button type="button" onclick="closeCropModal()">&times;</button>
        </div>
        <div id="cropContainer"></div>
        <div class="crop-tips">
            <i class="fas fa-info-circle"></i> Geser & zoom untuk atur posisi. Rasio 4:3 (landscape).
        </div>
        <div class="crop-modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeCropModal()">
                <i class="fas fa-times"></i> Batal
            </button>
            <button type="button" class="btn btn-gold" onclick="saveCrop()">
                <i class="fas fa-check"></i> Gunakan Foto
            </button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

<script>
/* ============================================================
   STATE GAMBAR
============================================================ */
var images = <?= json_encode(array_map(function($img){
    return ['id' => (int)$img['id'], 'path' => $img['gambar']];
}, $existingImages)) ?>;

var fileInput    = document.getElementById('fileInput');
var imageListEl  = document.getElementById('imageList');
var imagesJsonEl = document.getElementById('imagesJson');
var croppieInstance = null;

/* ============================================================
   RENDER GRID GAMBAR
============================================================ */
function renderImages() {
    var html = '';

    if (images.length === 0) {
        html = '<div style="grid-column:1/-1;text-align:center;padding:24px 12px;background:#f9f9f9;border:2px dashed #ddd;border-radius:10px;color:#999;font-size:12px;">'
             + '<i class="fas fa-image" style="font-size:26px;display:block;margin-bottom:6px;opacity:.4;"></i>'
             + 'Belum ada foto'
             + '</div>';
    } else {
        images.forEach(function(img, idx) {
            var src = img.id ? ('../' + img.path + '?v=' + Date.now()) : img.base64;
            html += '<div style="position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:#f0f0f3;border:1px solid #e5e5e8;">';
            html += '<img src="' + src + '" style="width:100%;height:100%;object-fit:cover;display:block;">';
            html += '<button type="button" onclick="removeImage(' + idx + ')" style="position:absolute;top:5px;right:5px;width:26px;height:26px;border-radius:50%;background:#e74c3c;color:#fff;border:2px solid #fff;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;line-height:1;">×</button>';
            if (idx === 0) {
                html += '<span style="position:absolute;bottom:5px;left:5px;background:#d4a843;color:#2c0a0e;font-size:9px;font-weight:800;padding:2px 8px;border-radius:10px;letter-spacing:.5px;">UTAMA</span>';
            }
            html += '</div>';
        });
    }

    imageListEl.innerHTML = html;
    imagesJsonEl.value = JSON.stringify(images);
}

function removeImage(idx) {
    if (!confirm('Hapus foto ini?')) return;
    images.splice(idx, 1);
    renderImages();
}

function addImage(base64) {
    if (images.length >= 8) {
        alert('Maksimal 8 foto per produk.');
        return;
    }
    images.push({ base64: base64 });
    renderImages();
}

renderImages();

/* ============================================================
   UPLOAD + CROP
============================================================ */
fileInput.addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;

    var allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (allowed.indexOf(file.type) === -1) {
        alert('Format harus JPG, PNG, atau WEBP.');
        this.value = '';
        return;
    }

    var reader = new FileReader();
    reader.onload = function(e) {
        openCropModal(e.target.result);
    };
    reader.readAsDataURL(file);
});

function openCropModal(src) {
    var modal = document.getElementById('cropModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    setTimeout(function() {
        var container = document.getElementById('cropContainer');
        container.innerHTML = '';

        if (croppieInstance) {
            try { croppieInstance.destroy(); } catch(e) {}
            croppieInstance = null;
        }

        croppieInstance = new Croppie(container, {
            viewport:  { width: 400, height: 300, type: 'square' },
            boundary:  { width: 480, height: 360 },
            showZoomer: true,
            enableOrientation: true,
            enableExif: true,
            enforceBoundary: true,
            mouseWheelZoom: true
        });

        croppieInstance.bind({
            url: src,
            orientation: 1
        });
    }, 120);
}

function saveCrop() {
    if (!croppieInstance) return;

    croppieInstance.result({
        type: 'base64',
        size: { width: 800, height: 600 },
        format: 'jpeg',
        quality: 0.92
    }).then(function(base64) {
        addImage(base64);
        closeCropModal();
    });
}

function closeCropModal() {
    document.getElementById('cropModal').classList.remove('show');
    document.body.style.overflow = '';

    setTimeout(function() {
        if (croppieInstance) {
            try { croppieInstance.destroy(); } catch(e) {}
            croppieInstance = null;
        }
        document.getElementById('cropContainer').innerHTML = '';
    }, 300);

    fileInput.value = '';
}

/* ============================================================
   VARIAN PCS — PREVIEW HARGA
============================================================ */
var hasVariantEl     = document.getElementById('hasVariant');
var variantPreviewEl = document.getElementById('variantPreview');
var hargaInputEl     = document.getElementById('hargaInput');
var hargaLabelEl     = document.getElementById('hargaLabelBase');

function updateVariantPreview() {
    var base = parseInt(hargaInputEl.value) || 0;
    var perPcs = base / 3;
    document.getElementById('previewHarga3').textContent = 'Rp ' + base.toLocaleString('id-ID');
    document.getElementById('previewHarga6').textContent = 'Rp ' + Math.round(perPcs * 6).toLocaleString('id-ID');
    document.getElementById('previewHarga8').textContent = 'Rp ' + Math.round(perPcs * 8).toLocaleString('id-ID');
}

function toggleVariantUI() {
    if (hasVariantEl.checked) {
        variantPreviewEl.style.display = 'block';
        if (hargaLabelEl) hargaLabelEl.style.display = 'inline';
        updateVariantPreview();
    } else {
        variantPreviewEl.style.display = 'none';
        if (hargaLabelEl) hargaLabelEl.style.display = 'none';
    }
}

if (hasVariantEl) {
    hasVariantEl.addEventListener('change', toggleVariantUI);
    if (hargaInputEl) hargaInputEl.addEventListener('input', updateVariantPreview);
    toggleVariantUI();
}
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>