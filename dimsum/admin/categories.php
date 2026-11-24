<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$errors = [];

// ============================================================
// HANDLE POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH
    if ($action === 'add') {
        $nama = trim($_POST['nama'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if ($nama === '') $errors[] = 'Nama kategori wajib diisi.';
        if ($slug === '') $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $nama));
        $slug = strtolower(preg_replace('/[^a-z0-9\-]+/', '', $slug));

        if (empty($errors)) {
            $stmt = $koneksi->prepare("SELECT id FROM categories WHERE slug = ?");
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Slug \"$slug\" sudah dipakai.";
            } else {
                $stmt = $koneksi->prepare("INSERT INTO categories (nama, slug) VALUES (?, ?)");
                $stmt->bind_param('ss', $nama, $slug);
                $stmt->execute();
                flash('success', "Kategori \"$nama\" berhasil ditambahkan.");
                redirect('categories.php');
            }
        }
    }

    // EDIT
    elseif ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if ($nama === '') $errors[] = 'Nama kategori wajib diisi.';
        if ($slug === '') $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $nama));

        if (empty($errors) && $id > 0) {
            $stmt = $koneksi->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
            $stmt->bind_param('si', $slug, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Slug \"$slug\" sudah dipakai.";
            } else {
                $stmt = $koneksi->prepare("UPDATE categories SET nama = ?, slug = ? WHERE id = ?");
                $stmt->bind_param('ssi', $nama, $slug, $id);
                $stmt->execute();
                flash('success', "Kategori \"$nama\" berhasil diperbarui.");
                redirect('categories.php');
            }
        }
    }

    // HAPUS
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $koneksi->prepare("SELECT nama FROM categories WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $cat = $stmt->get_result()->fetch_assoc();
            if ($cat) {
                // Set produk yang pakai kategori ini jadi NULL
                $upd = $koneksi->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?");
                $upd->bind_param('i', $id);
                $upd->execute();

                $del = $koneksi->prepare("DELETE FROM categories WHERE id = ?");
                $del->bind_param('i', $id);
                $del->execute();

                flash('success', "Kategori \"{$cat['nama']}\" berhasil dihapus.");
            }
        }
        redirect('categories.php');
    }
}

// Ambil data
$categories = $koneksi->query("
    SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS total_produk
    FROM categories c ORDER BY c.id ASC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Kelola Kategori';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/sidebar.php';
?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="admin-breadcrumb"><a href="index.php">Dashboard</a> / Kategori</div>
            <h1 class="admin-page-title">Kelola Kategori</h1>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Tambah Kategori
        </button>
    </div>

    <div class="admin-content">

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><b>Ada kesalahan:</b><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <div class="table-header">
                <div>
                    <div class="table-title">Daftar Kategori</div>
                    <div style="font-size:11px;color:#999;margin-top:2px;"><?= count($categories) ?> kategori terdaftar</div>
                </div>
            </div>

            <?php if (empty($categories)): ?>
                <div class="empty">
                    <i class="fas fa-tags"></i>
                    <h3>Belum ada kategori</h3>
                    <p>Mulai tambahkan kategori produkmu.</p>
                    <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Tambah Kategori</button>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Nama Kategori</th>
                            <th>Slug</th>
                            <th style="text-align:center;">Produk</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td><span style="font-family:monospace;color:#999;">#<?= $c['id'] ?></span></td>
                                <td><b style="color:#2c0a0e;"><?= e($c['nama']) ?></b></td>
                                <td><code style="background:#f0f0f3;padding:3px 8px;border-radius:5px;font-size:12px;color:#666;"><?= e($c['slug']) ?></code></td>
                                <td style="text-align:center;">
                                    <span class="badge badge-gold"><?= (int)$c['total_produk'] ?> produk</span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn btn-outline btn-icon" title="Edit"
                                                onclick="openEditModal(<?= $c['id'] ?>, '<?= e(addslashes($c['nama'])) ?>', '<?= e(addslashes($c['slug'])) ?>')">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="btn btn-danger btn-icon" title="Hapus"
                                                onclick="confirmDelete(<?= $c['id'] ?>, '<?= e(addslashes($c['nama'])) ?>')">
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

<!-- MODAL FORM -->
<div class="modal-backdrop" id="formModal">
    <div class="modal-box" style="text-align:left;max-width:460px;">
        <h3 id="modalTitle" style="text-align:left;margin-bottom:20px;">Tambah Kategori</h3>
        <form method="post">
            <input type="hidden" name="action" id="modalAction" value="add">
            <input type="hidden" name="id" id="modalId" value="">

            <div class="form-group">
                <label class="form-label">Nama Kategori <span class="req">*</span></label>
                <input type="text" name="nama" id="modalNama" class="form-input" required placeholder="Contoh: Dimsum Ayam">
            </div>

            <div class="form-group">
                <label class="form-label">Slug</label>
                <input type="text" name="slug" id="modalSlug" class="form-input" placeholder="dimsum-ayam" style="font-family:monospace;">
                <div class="form-help">Kosongkan untuk auto-generate dari nama.</div>
            </div>

            <div class="actions" style="display:flex;gap:10px;margin-top:20px;">
                <button type="button" class="btn btn-outline" style="flex:1;justify-content:center;" onclick="closeFormModal()">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal-box">
        <div class="icon-warn"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Hapus Kategori?</h3>
        <p>Yakin mau hapus <b id="delName"></b>?<br>Produk yang pakai kategori ini akan jadi "tanpa kategori".</p>
        <div class="actions">
            <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Batal</button>
            <form method="post" style="flex:1;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delId">
                <button type="submit" class="btn btn-danger" style="width:100%;">
                    <i class="fas fa-trash"></i> Ya, Hapus
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Kategori';
    document.getElementById('modalAction').value = 'add';
    document.getElementById('modalId').value = '';
    document.getElementById('modalNama').value = '';
    document.getElementById('modalSlug').value = '';
    document.getElementById('formModal').classList.add('show');
}
function openEditModal(id, nama, slug) {
    document.getElementById('modalTitle').textContent = 'Edit Kategori';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalId').value = id;
    document.getElementById('modalNama').value = nama;
    document.getElementById('modalSlug').value = slug;
    document.getElementById('formModal').classList.add('show');
}
function closeFormModal() {
    document.getElementById('formModal').classList.remove('show');
}
function confirmDelete(id, nama) {
    document.getElementById('delId').value = id;
    document.getElementById('delName').textContent = nama;
    document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
}
document.querySelectorAll('.modal-backdrop').forEach(function(m){
    m.addEventListener('click', function(e){
        if (e.target === this) this.classList.remove('show');
    });
});
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>