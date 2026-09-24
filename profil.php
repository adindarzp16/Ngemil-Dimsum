<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) redirect('admin/index.php');

$userId = $_SESSION['user_id'];

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
    'alamat'       => $user['alamat']       ?? '',
];

// ============================================================
// SUBMIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['nama_lengkap'] = trim($_POST['nama_lengkap'] ?? '');
    $form['nickname']     = trim($_POST['nickname']     ?? '');
    $form['email']        = trim($_POST['email']        ?? '');
    $form['no_telp']      = trim($_POST['no_telp']      ?? '');
    $form['alamat']       = trim($_POST['alamat']       ?? '');
    $newPass              = $_POST['new_password']      ?? '';
    $confirmPass          = $_POST['confirm_password']  ?? '';
    $croppedBase64        = $_POST['cropped_image']     ?? '';
    $fotoPath             = $user['foto_profil'];
    $MAX_SIZE             = 10 * 1024 * 1024; // 10 MB

    // Validasi dasar
    if ($form['nama_lengkap'] === '') $errors[] = 'Nama lengkap wajib diisi.';
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    // Cek email duplikat
    if ($form['email'] !== '' && empty($errors)) {
        $stmt = $koneksi->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $form['email'], $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors[] = 'Email sudah dipakai akun lain.';
    }

    // Password baru
    if ($newPass !== '' || $confirmPass !== '') {
        if (strlen($newPass) < 6) $errors[] = 'Password baru minimal 6 karakter.';
        elseif ($newPass !== $confirmPass) $errors[] = 'Konfirmasi password tidak cocok.';
    }

    // ============================================
    // PRIORITAS 1: Hasil crop (base64 dari Croppie)
    // ============================================
    if (!empty($croppedBase64)) {
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $croppedBase64, $m)) {
            $ext  = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            $data = base64_decode($m[2]);

            if ($data === false) {
                $errors[] = 'Data gambar tidak valid.';
            } elseif (strlen($data) > $MAX_SIZE) {
                $errors[] = 'Ukuran gambar hasil crop terlalu besar.';
            } elseif (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                $errors[] = 'Format gambar hasil crop tidak didukung.';
            } else {
                $fileName = 'avatar-' . $userId . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                $uploadDir = __DIR__ . '/images/avatars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                if (file_put_contents($uploadDir . $fileName, $data) !== false) {
                    // hapus foto lama
                    if (!empty($user['foto_profil']) && file_exists(__DIR__ . '/' . $user['foto_profil'])) {
                        @unlink(__DIR__ . '/' . $user['foto_profil']);
                    }
                    $fotoPath = 'images/avatars/' . $fileName;
                } else {
                    $errors[] = 'Gagal menyimpan foto hasil crop.';
                }
            }
        } else {
            $errors[] = 'Format base64 tidak valid.';
        }
    }
    // ============================================
    // PRIORITAS 2: Upload file langsung (tanpa crop, fallback)
    // ============================================
    elseif (!empty($_FILES['foto_profil']['name'])) {
        $file    = $_FILES['foto_profil'];
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Gagal upload foto (kode ' . $file['error'] . ').';
        } elseif ($file['size'] > $MAX_SIZE) {
            $errors[] = 'Ukuran foto maksimal 10MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowed[$mime])) {
                $errors[] = 'Format foto harus JPG, PNG, atau WEBP.';
            } else {
                $ext      = $allowed[$mime];
                $fileName = 'avatar-' . $userId . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                $uploadDir = __DIR__ . '/images/avatars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                    if (!empty($user['foto_profil']) && file_exists(__DIR__ . '/' . $user['foto_profil'])) {
                        @unlink(__DIR__ . '/' . $user['foto_profil']);
                    }
                    $fotoPath = 'images/avatars/' . $fileName;
                } else {
                    $errors[] = 'Gagal menyimpan foto ke server.';
                }
            }
        }
    }

    // ============================================
    // SIMPAN KE DB
    // ============================================
    if (empty($errors)) {
        if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $koneksi->prepare("
                UPDATE users SET nama_lengkap=?, nickname=?, email=?, no_telp=?, alamat=?, foto_profil=?, password=?
                WHERE id=?
            ");
            $stmt->bind_param('sssssssi', $form['nama_lengkap'], $form['nickname'], $form['email'], $form['no_telp'], $form['alamat'], $fotoPath, $hash, $userId);
        } else {
            $stmt = $koneksi->prepare("
                UPDATE users SET nama_lengkap=?, nickname=?, email=?, no_telp=?, alamat=?, foto_profil=?
                WHERE id=?
            ");
            $stmt->bind_param('ssssssi', $form['nama_lengkap'], $form['nickname'], $form['email'], $form['no_telp'], $form['alamat'], $fotoPath, $userId);
        }

        if ($stmt->execute()) {
            $_SESSION['nama']        = $form['nama_lengkap'];
            $_SESSION['foto_profil'] = $fotoPath;

            $stmt2 = $koneksi->prepare("SELECT * FROM users WHERE id = ?");
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();
            $user = $stmt2->get_result()->fetch_assoc();

            $success = '✅ Profil berhasil diperbarui!';
        } else {
            $errors[] = 'Gagal menyimpan profil.';
        }
    }
}

$namaUser  = $user['nama_lengkap'];
$userFoto  = $user['foto_profil'];
$initials  = strtoupper(substr($namaUser, 0, 2));
$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil — Ngemil Dimsum</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Croppie: crop foto -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>
<link rel="stylesheet" href="assets/customer.css">
<style>
.profil-layout{display:grid;grid-template-columns:1fr 340px;gap:25px;align-items:start}
.avatar-section{text-align:center;padding:25px 20px}
.avatar-preview{width:130px;height:130px;border-radius:50%;object-fit:cover;background:#7a1a2e;display:flex;align-items:center;justify-content:center;color:#fff;font-size:46px;font-weight:600;margin:0 auto 15px;border:4px solid #d4a843;overflow:hidden;position:relative;box-shadow:0 5px 20px rgba(122,26,46,.2)}
.avatar-preview img{width:100%;height:100%;object-fit:cover}
.avatar-edit{position:absolute;bottom:5px;right:5px;width:34px;height:34px;background:#d4a843;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;cursor:pointer;border:3px solid #fff;font-size:13px;transition:.2s}
.avatar-edit:hover{background:#7a1a2e;transform:scale(1.1)}
.section-title{font-size:15px;font-weight:700;color:#333;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f0ebe6;display:flex;align-items:center;gap:8px}
.section-title i{color:#7a1a2e}
.info-box{padding:12px 14px;background:#f8f4f0;border-radius:10px;font-size:12px;color:#666;line-height:1.6;margin-top:15px}
.info-box b{color:#7a1a2e}
@media (max-width:900px){.profil-layout{grid-template-columns:1fr}}

/* ========== CROP MODAL ========== */
.crop-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:15px;backdrop-filter:blur(4px)}
.crop-modal.show{display:flex}
.crop-modal-content{background:#fff;border-radius:20px;max-width:520px;width:100%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35);animation:modalIn .3s ease}
@keyframes modalIn{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
.crop-modal-header{padding:16px 20px;border-bottom:1px solid #f0ebe6;display:flex;justify-content:space-between;align-items:center}
.crop-modal-header h3{font-size:15px;font-weight:700;color:#333;display:flex;align-items:center;gap:8px}
.crop-modal-header h3 i{color:#7a1a2e}
.crop-modal-header button{background:none;border:none;font-size:26px;cursor:pointer;color:#999;line-height:1;transition:.2s}
.crop-modal-header button:hover{color:#7a1a2e}
#cropContainer{height:360px;background:#2a2a2a;display:flex;align-items:center;justify-content:center;overflow:hidden}
.crop-tips{padding:10px 20px;background:#f8f4f0;font-size:11px;color:#666;text-align:center;border-top:1px solid #f0ebe6}
.crop-tips i{color:#d4a843;margin-right:4px}
.crop-modal-footer{padding:14px 20px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid #f0ebe6;background:#fff}
.crop-modal-footer .btn{flex:1;justify-content:center}
@media (max-width:500px){#cropContainer{height:280px}}
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
        <li><a href="orders.php"><i class="fas fa-clipboard-list"></i> <span>Pesanan Saya</span></a></li>
        <li><a href="profil.php" class="active"><i class="fas fa-user"></i> <span>Profil</span></a></li>
    </ul>
</aside>

<div class="main-content">
    <div class="topbar">
        <form class="search-box" method="get" action="dashboard.php"><i class="fas fa-search"></i><input type="text" name="q" placeholder="Cari menu favoritmu..."></form>
        <div class="topbar-right">
            <a href="cart.php" class="topbar-icon"><i class="fas fa-shopping-cart"></i><?php if ($cartCount>0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?></a>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php if ($userFoto && file_exists(__DIR__.'/'.$userFoto)): ?>
                        <img src="<?= e($userFoto) ?>?v=<?= time() ?>" alt="Avatar">
                    <?php else: ?>
                        <?= e($initials) ?>
                    <?php endif; ?>
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
        <h1 class="page-title">Profil Saya</h1>
        <div class="breadcrumb"><a href="dashboard.php">Beranda</a> / Profil</div>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><b>Ada kesalahan:</b><ul style="margin:5px 0 0 18px;"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="profilForm">
            <div class="profil-layout">
                <div>
                    <div class="card" style="margin-bottom:20px;">
                        <div class="section-title"><i class="fas fa-camera"></i> Foto Profil</div>
                        <div class="avatar-section">
                            <div class="avatar-preview" id="avatarPreview">
                                <?php if ($userFoto && file_exists(__DIR__.'/'.$userFoto)): ?>
                                    <img src="<?= e($userFoto) ?>?v=<?= time() ?>" id="avatarImg" alt="Avatar">
                                    <span id="avatarInitials" style="display:none;"><?= e($initials) ?></span>
                                <?php else: ?>
                                    <img src="" id="avatarImg" style="display:none;">
                                    <span id="avatarInitials"><?= e($initials) ?></span>
                                <?php endif; ?>
                                <label for="fotoInput" class="avatar-edit" title="Ganti foto"><i class="fas fa-camera"></i></label>
                            </div>
                            <input type="file" name="foto_profil" id="fotoInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
                            <!-- Hasil crop dikirim ke sini -->
                            <input type="hidden" name="cropped_image" id="croppedImage">
                            <p style="font-size:12px;color:#999;">Klik ikon kamera untuk ganti foto</p>
                            <p style="font-size:11px;color:#bbb;margin-top:3px;">JPG, PNG, atau WEBP • Maks 10MB</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="section-title"><i class="fas fa-key"></i> Ganti Password (Opsional)</div>
                        <div class="form-group">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="new_password" class="form-input" placeholder="Biarkan kosong jika tidak ganti">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Ulangi password baru">
                        </div>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <div class="section-title"><i class="fas fa-user-edit"></i> Data Diri</div>

                        <div class="form-group">
                            <label class="form-label">Nama Lengkap <span class="req">*</span></label>
                            <input type="text" name="nama_lengkap" class="form-input" value="<?= e($form['nama_lengkap']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nickname (nama tampilan)</label>
                            <input type="text" name="nickname" class="form-input" value="<?= e($form['nickname']) ?>" placeholder="Contoh: Azril Si Pecinta Dimsum" maxlength="50">
                            <div class="form-help">Nama ini akan tampil saat kamu berkomentar & review produk.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" value="<?= e($form['email']) ?>" placeholder="email@contoh.com">
                        </div>

                        <div class="form-group">
                            <label class="form-label">No. HP / WhatsApp</label>
                            <input type="text" name="no_telp" class="form-input" value="<?= e($form['no_telp']) ?>" placeholder="0812xxxxxxxx">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-input" placeholder="Alamat lengkap untuk pengiriman"><?= e($form['alamat']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top:8px;">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>

                        <div class="info-box">
                            <b>💡 Tips:</b> Isi <b>Nickname</b> dan upload <b>foto profil</b> biar pas kamu kasih ulasan di produk, orang lain lihat foto & nama kamu (seperti di Shopee).
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- =========================
     CROP MODAL
========================= -->
<div class="crop-modal" id="cropModal">
    <div class="crop-modal-content">
        <div class="crop-modal-header">
            <h3><i class="fas fa-crop-alt"></i> Atur Foto Profil</h3>
            <button type="button" onclick="closeCropModal()">&times;</button>
        </div>
        <div id="cropContainer"></div>
        <div class="crop-tips">
            <i class="fas fa-info-circle"></i> Geser & zoom untuk atur posisi. Hasil akan dipotong bulat.
        </div>
        <div class="crop-modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeCropModal()">
                <i class="fas fa-times"></i> Batal
            </button>
            <button type="button" class="btn btn-primary" onclick="saveCrop()">
                <i class="fas fa-check"></i> Gunakan Foto
            </button>
        </div>
    </div>
</div>

<div class="wave-decoration">
    <svg viewBox="0 0 1440 60" preserveAspectRatio="none" style="width:100%;height:100%;">
        <path d="M0,30 Q360,0 720,30 T1440,30 L1440,60 L0,60 Z" fill="#7a1a2e"/>
        <path d="M0,40 Q360,10 720,40 T1440,40 L1440,60 L0,60 Z" fill="#d4a843" opacity="0.6"/>
    </svg>
</div>

<script>
const MAX_SIZE = 10 * 1024 * 1024; // 10MB
const fotoInput = document.getElementById('fotoInput');
let croppieInstance = null;
let originalFileName = '';

fotoInput.addEventListener('change', function(){
    const file = this.files[0];
    if (!file) return;

    // Validasi ukuran 10MB
    if (file.size > MAX_SIZE) {
        alert('Ukuran foto maksimal 10MB. File kamu: ' + (file.size / 1024 / 1024).toFixed(2) + 'MB');
        this.value = '';
        return;
    }

    // Validasi tipe
    const allowed = ['image/jpeg','image/png','image/webp'];
    if (!allowed.includes(file.type)) {
        alert('Format foto harus JPG, PNG, atau WEBP.');
        this.value = '';
        return;
    }

    originalFileName = file.name;

    const reader = new FileReader();
    reader.onload = function(e){
        openCropModal(e.target.result);
    };
    reader.readAsDataURL(file);
});

function openCropModal(imageSrc){
    const modal = document.getElementById('cropModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    setTimeout(() => {
        const container = document.getElementById('cropContainer');
        container.innerHTML = '';

        // Destroy instance sebelumnya
        if (croppieInstance) {
            try { croppieInstance.destroy(); } catch(e) {}
            croppieInstance = null;
        }

        croppieInstance = new Croppie(container, {
            viewport: { width: 240, height: 240, type: 'circle' },
            boundary: { width: 300, height: 300 },
            showZoomer: true,
            enableOrientation: true,
            enableExif: true,
            enforceBoundary: true,
            mouseWheelZoom: true,
        });

        croppieInstance.bind({
            url: imageSrc,
            orientation: 1,
        });
    }, 120);
}

function saveCrop(){
    if (!croppieInstance) return;

    // Ambil hasil crop sebagai base64 (jpg, size 500x500)
    croppieInstance.result({
        type: 'base64',
        size: { width: 500, height: 500 },
        format: 'jpeg',
        quality: 0.92,
        circle: false
    }).then(function(base64){
        // Simpan ke hidden input
        document.getElementById('croppedImage').value = base64;

        // Update preview di halaman
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
        if (croppieInstance) {
            try { croppieInstance.destroy(); } catch(e) {}
            croppieInstance = null;
        }
        document.getElementById('cropContainer').innerHTML = '';
    }, 300);

    // Reset file input supaya bisa pilih file yang sama dua kali
    document.getElementById('fotoInput').value = '';
}
</script>
</body>
</html>