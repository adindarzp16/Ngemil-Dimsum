<?php
/* ============================================================
   NGEMIL DIMSUM — Profil Admin
   ============================================================ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$userId = $_SESSION['user_id'];

// Ambil data user
$stmt = $koneksi->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$errors  = [];
$success = '';
$form = [
    'nama_lengkap' => $user['nama_lengkap'],
    'nickname'     => $user['nickname']     ?? '',
    'email'        => $user['email']        ?? '',
    'no_telp'      => $user['no_telp']      ?? '',
];

$MAX_SIZE = 10 * 1024 * 1024; // 10 MB

// ============================================================
// SUBMIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['nama_lengkap'] = trim($_POST['nama_lengkap'] ?? '');
    $form['nickname']     = trim($_POST['nickname']     ?? '');
    $form['email']        = trim($_POST['email']        ?? '');
    $form['no_telp']      = trim($_POST['no_telp']      ?? '');
    $newPass              = $_POST['new_password']      ?? '';
    $confirmPass          = $_POST['confirm_password']  ?? '';
    $croppedBase64        = $_POST['cropped_image']     ?? '';
    $fotoPath             = $user['foto_profil'];

    // Validasi
    if ($form['nama_lengkap'] === '') $errors[] = 'Nama lengkap wajib diisi.';
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if ($form['email'] !== '' && empty($errors)) {
        $stmt = $koneksi->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $form['email'], $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors[] = 'Email sudah dipakai akun lain.';
    }

    if ($newPass !== '' || $confirmPass !== '') {
        if (strlen($newPass) < 6) $errors[] = 'Password minimal 6 karakter.';
        elseif ($newPass !== $confirmPass) $errors[] = 'Konfirmasi password tidak cocok.';
    }

    // Simpan foto hasil crop
    if (!empty($croppedBase64)) {
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $croppedBase64, $m)) {
            $ext  = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            $data = base64_decode($m[2]);

            if ($data === false) {
                $errors[] = 'Data gambar tidak valid.';
            } elseif (strlen($data) > $MAX_SIZE) {
                $errors[] = 'Ukuran gambar terlalu besar.';
            } elseif (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                $errors[] = 'Format gambar tidak didukung.';
            } else {
                $fileName  = 'avatar-' . $userId . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                $uploadDir = __DIR__ . '/../images/avatars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                if (file_put_contents($uploadDir . $fileName, $data) !== false) {
                    if (!empty($user['foto_profil']) && file_exists(__DIR__ . '/../' . $user['foto_profil'])) {
                        @unlink(__DIR__ . '/../' . $user['foto_profil']);
                    }
                    $fotoPath = 'images/avatars/' . $fileName;
                } else {
                    $errors[] = 'Gagal menyimpan foto.';
                }
            }
        }
    }

    // Simpan ke DB
    if (empty($errors)) {
        if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $koneksi->prepare("
                UPDATE users SET nama_lengkap=?, nickname=?, email=?, no_telp=?, foto_profil=?, password=?
                WHERE id=?
            ");
            $stmt->bind_param('ssssssi', $form['nama_lengkap'], $form['nickname'], $form['email'], $form['no_telp'], $fotoPath, $hash, $userId);
        } else {
            $stmt = $koneksi->prepare("
                UPDATE users SET nama_lengkap=?, nickname=?, email=?, no_telp=?, foto_profil=?
                WHERE id=?
            ");
            $stmt->bind_param('sssssi', $form['nama_lengkap'], $form['nickname'], $form['email'], $form['no_telp'], $fotoPath, $userId);
        }

        if ($stmt->execute()) {
            $_SESSION['nama']        = $form['nama_lengkap'];
            $_SESSION['foto_profil'] = $fotoPath;

            $stmt2 = $koneksi->prepare("SELECT * FROM users WHERE id = ?");
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();
            $user = $stmt2->get_result()->fetch_assoc();

            $success = '✅ Profil admin berhasil diperbarui!';
        } else {
            $errors[] = 'Gagal menyimpan profil.';
        }
    }
}

$pageTitle = 'Profil Admin';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/sidebar.php';
?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="admin-breadcrumb">Admin / Profil</div>
            <h1 class="admin-page-title">Profil Admin</h1>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><b>Ada kesalahan:</b><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="cropped_image" id="croppedImage">

            <div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;">

                <!-- KIRI: FOTO PROFIL -->
                <div>
                    <div class="card">
                        <div class="card-title"><i class="fas fa-camera"></i> Foto Profil</div>
                        <div class="card-subtitle">Klik ikon kamera untuk ganti foto</div>

                        <div style="text-align:center;padding:15px 0;">
                            <div id="avatarPreview" style="width:140px;height:140px;border-radius:50%;background:#d4a843;color:#2c0a0e;display:flex;align-items:center;justify-content:center;font-size:48px;font-weight:800;margin:0 auto 15px;border:4px solid #2c0a0e;overflow:hidden;position:relative;box-shadow:0 6px 20px rgba(44,10,14,.2);">
                                <?php if ($user['foto_profil'] && file_exists(__DIR__.'/../'.$user['foto_profil'])): ?>
                                    <img src="../<?= e($user['foto_profil']) ?>?v=<?= time() ?>" id="avatarImg" style="width:100%;height:100%;object-fit:cover;display:block;">
                                    <span id="avatarInitials" style="display:none;"><?= strtoupper(substr($user['nama_lengkap'],0,2)) ?></span>
                                <?php else: ?>
                                    <img src="" id="avatarImg" style="width:100%;height:100%;object-fit:cover;display:none;">
                                    <span id="avatarInitials"><?= strtoupper(substr($user['nama_lengkap'],0,2)) ?></span>
                                <?php endif; ?>

                                <label for="fileInput" style="position:absolute;bottom:5px;right:5px;width:36px;height:36px;background:#d4a843;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#2c0a0e;cursor:pointer;border:3px solid #fff;font-size:13px;transition:.2s;">
                                    <i class="fas fa-camera"></i>
                                </label>
                            </div>
                            <input type="file" id="fileInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
                            <div style="font-size:11px;color:#999;">JPG / PNG / WEBP • Maks 10MB</div>
                        </div>
                    </div>

                    <div class="card" style="margin-top:20px;">
                        <div class="card-title"><i class="fas fa-key"></i> Ganti Password</div>
                        <div class="card-subtitle">Biarkan kosong jika tidak ganti</div>

                        <div class="form-group">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="new_password" class="form-input" placeholder="Minimal 6 karakter">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Konfirmasi Password</label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Ulangi password baru">
                        </div>
                    </div>
                </div>

                <!-- KANAN: DATA -->
                <div>
                    <div class="card">
                        <div class="card-title"><i class="fas fa-user-edit"></i> Data Admin</div>
                        <div class="card-subtitle">Informasi akun administrator</div>

                        <div class="form-group">
                            <label class="form-label">Nama Lengkap <span class="req">*</span></label>
                            <input type="text" name="nama_lengkap" class="form-input" value="<?= e($form['nama_lengkap']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nickname</label>
                            <input type="text" name="nickname" class="form-input" value="<?= e($form['nickname']) ?>" placeholder="Nama tampilan" maxlength="50">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-input" value="<?= e($form['email']) ?>" placeholder="email@contoh.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label">No. HP / WhatsApp</label>
                                <input type="text" name="no_telp" class="form-input" value="<?= e($form['no_telp']) ?>" placeholder="0812xxxxxxxx">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="margin-top:10px;">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<!-- CROP MODAL -->
<div class="crop-modal" id="cropModal">
    <div class="crop-modal-content">
        <div class="crop-modal-header">
            <h3><i class="fas fa-crop-alt"></i> Atur Foto Profil</h3>
            <button type="button" onclick="closeCropModal()">&times;</button>
        </div>
        <div id="cropContainer"></div>
        <div class="crop-tips"><i class="fas fa-info-circle"></i> Geser & zoom untuk atur posisi. Hasil akan dipotong bulat.</div>
        <div class="crop-modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeCropModal()"><i class="fas fa-times"></i> Batal</button>
            <button type="button" class="btn btn-gold" onclick="saveCrop()"><i class="fas fa-check"></i> Gunakan Foto</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

<script>
const MAX_SIZE = 10 * 1024 * 1024;
const fileInput = document.getElementById('fileInput');
let croppieInstance = null;

fileInput.addEventListener('change', function(){
    const file = this.files[0];
    if (!file) return;

    if (file.size > MAX_SIZE) {
        alert('Ukuran foto maksimal 10MB. File kamu: ' + (file.size / 1024 / 1024).toFixed(2) + 'MB');
        this.value = ''; return;
    }

    const allowed = ['image/jpeg','image/png','image/webp'];
    if (!allowed.includes(file.type)) {
        alert('Format harus JPG, PNG, atau WEBP.');
        this.value = ''; return;
    }

    const reader = new FileReader();
    reader.onload = e => openCropModal(e.target.result);
    reader.readAsDataURL(file);
});

function openCropModal(imageSrc){
    const modal = document.getElementById('cropModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    setTimeout(() => {
        const container = document.getElementById('cropContainer');
        container.innerHTML = '';

        if (croppieInstance) { try { croppieInstance.destroy(); } catch(e){} croppieInstance = null; }

        croppieInstance = new Croppie(container, {
            viewport:  { width: 240, height: 240, type: 'circle' },
            boundary:  { width: 300, height: 300 },
            showZoomer: true,
            enableOrientation: true,
            enableExif: true,
            enforceBoundary: true,
            mouseWheelZoom: true,
        });

        croppieInstance.bind({ url: imageSrc, orientation: 1 });
    }, 120);
}

function saveCrop(){
    if (!croppieInstance) return;

    croppieInstance.result({
        type: 'base64',
        size: { width: 500, height: 500 },
        format: 'jpeg',
        quality: 0.92
    }).then(function(base64){
        document.getElementById('croppedImage').value = base64;

        const img = document.getElementById('avatarImg');
        const initials = document.getElementById('avatarInitials');
        img.src = base64;
        img.style.display = 'block';
        if (initials) initials.style.display = 'none';

        closeCropModal();
    });
}

function closeCropModal(){
    document.getElementById('cropModal').classList.remove('show');
    document.body.style.overflow = '';
    setTimeout(() => {
        if (croppieInstance) { try { croppieInstance.destroy(); } catch(e){} croppieInstance = null; }
        document.getElementById('cropContainer').innerHTML = '';
    }, 300);
    document.getElementById('fileInput').value = '';
}
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>