<?php
/* ============================================================
   NGEMIL DIMSUM — Halaman Login / Register / Forgot
   - Login pakai Email ATAU No. HP
   - Register auto-deteksi (ada @ = email, tanpa @ = no HP)
   - Session + redirect sesuai role
   ============================================================ */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Kalau sudah login, langsung lempar sesuai role
if (isLogin()) {
    redirect(isAdmin() ? 'admin/index.php' : 'dashboard.php');
}

// Generate CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors        = [];
$success       = '';
$activeScreen  = 'login-customer';   // default screen
$oldIdentifier = '';
$oldNama       = '';

// ============================================================
// HANDLE: LOGIN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrf       = $_POST['csrf'] ?? '';

    $oldIdentifier = $identifier;

    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $errors[] = 'Sesi tidak valid. Silakan muat ulang halaman.';
    } elseif ($identifier === '' || $password === '') {
        $errors[] = 'Email/No. HP dan password wajib diisi.';
    } else {
        $stmt = $koneksi->prepare("SELECT * FROM users WHERE email = ? OR no_telp = ? LIMIT 1");
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $errors[] = 'Akun tidak ditemukan. Periksa email / no. HP kamu.';
        } elseif (!password_verify($password, $user['password'])) {
            $errors[] = 'Password salah. Coba lagi.';
        } else {
            // === Login sukses ===
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama']     = $user['nama_lengkap'];
            $_SESSION['role']     = $user['role'];

            // rotate CSRF
            $_SESSION['csrf'] = bin2hex(random_bytes(32));

            redirect($user['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
        }
    }
    $activeScreen = 'login-customer';
}

// ============================================================
// HANDLE: REGISTER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $nama       = trim($_POST['nama'] ?? '');
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrf       = $_POST['csrf'] ?? '';

    $oldNama       = $nama;
    $oldIdentifier = $identifier;

    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $errors[] = 'Sesi tidak valid. Silakan muat ulang halaman.';
    } elseif ($nama === '' || $identifier === '' || $password === '') {
        $errors[] = 'Semua kolom wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $identifier)
              && !preg_match('/^[0-9+\-\s]{8,20}$/', $identifier)) {
        $errors[] = 'Format email atau no. HP tidak valid.';
    } else {
        $isEmail  = strpos($identifier, '@') !== false;
        $username = $isEmail ? explode('@', $identifier)[0] : $identifier;

        // Pastikan username unik (kalau bentrok, tambah angka random)
        $baseUsername = $username;
        $suffix = 1;
        while (true) {
            $stmt = $koneksi->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) break;
            $username = $baseUsername . $suffix++;
            if ($suffix > 99) { $username = $baseUsername . '_' . time(); break; }
        }

        // Cek duplikat email/no HP
        if ($isEmail) {
            $stmt = $koneksi->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $identifier);
        } else {
            $stmt = $koneksi->prepare("SELECT id FROM users WHERE no_telp = ? LIMIT 1");
            $stmt->bind_param('s', $identifier);
        }
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = ($isEmail ? 'Email' : 'No. HP') . ' sudah terdaftar. Silakan login.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            if ($isEmail) {
                $stmt = $koneksi->prepare("
                    INSERT INTO users (username, password, nama_lengkap, email, role)
                    VALUES (?, ?, ?, ?, 'pelanggan')
                ");
                $stmt->bind_param('ssss', $username, $hash, $nama, $identifier);
            } else {
                $stmt = $koneksi->prepare("
                    INSERT INTO users (username, password, nama_lengkap, no_telp, role)
                    VALUES (?, ?, ?, ?, 'pelanggan')
                ");
                $stmt->bind_param('ssss', $username, $hash, $nama, $identifier);
            }

            if ($stmt->execute()) {
                $success      = '🎉 Akun berhasil dibuat! Silakan login dengan akun baru.';
                $activeScreen = 'login-customer';
                $oldNama      = '';
                $oldIdentifier = '';
            } else {
                $errors[] = 'Gagal membuat akun. Coba lagi nanti.';
            }
        }
    }

    if (empty($success)) $activeScreen = 'register';
}

// ============================================================
// HANDLE: FORGOT PASSWORD (placeholder — butuh SMTP untuk real)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'forgot') {
    $success      = '📧 Fitur reset password sedang dikembangkan. Untuk sementara, hubungi admin via WhatsApp.';
    $activeScreen = 'forgot-password';
}

// Helper untuk menandai screen active
function screenClass($id, $active) {
    return $id === $active ? 'screen active' : 'screen';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — Ngemil Dimsum</title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --maroon-900:#4a0e18;--maroon-800:#6b1420;--maroon-700:#7f1b28;
  --cream:#f7ecd3;--cream-2:#fdf7e9;--gold:#f2a93b;--gold-dark:#d98d1f;
  --ink:#2c1810;--ink-soft:#6b5648;--line:#e8d9b8;--white:#fff;
  --shadow:0 10px 24px rgba(74,14,24,.12);
}
*{box-sizing:border-box}
body{margin:0;font-family:'Inter',sans-serif;background:var(--cream);color:var(--ink);-webkit-font-smoothing:antialiased;overflow-x:hidden}
h1,h2,h3,h4,.brand,.btn{font-family:'Baloo 2',sans-serif}

@keyframes floating{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
@keyframes pulseLogo{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
@keyframes spin{to{transform:rotate(360deg)}}

#splash-screen{
  position:fixed;top:0;left:0;width:100%;height:100vh;
  background:linear-gradient(160deg,var(--maroon-800),var(--maroon-900));
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  z-index:999;transition:opacity .6s ease, visibility .6s ease;
}
#splash-screen.fade-out{opacity:0;visibility:hidden}
.splash-logo{
  width:110px;height:110px;border-radius:50%;background:var(--cream);
  border:4px solid var(--gold);display:flex;align-items:center;justify-content:center;
  margin-bottom:16px;box-shadow:0 8px 20px rgba(0,0,0,.3);overflow:hidden;
  animation:pulseLogo 1.5s ease-in-out infinite;
}
.splash-logo img{width:100%;height:100%;object-fit:cover;}
#splash-screen h2{color:var(--gold);font-size:28px;margin:0 0 6px;}
#splash-screen p{color:#e9d3ba;font-size:14px;margin:0;}

.main-container{position:relative;width:100%;min-height:100vh;overflow:hidden;}
.screen{
  position:absolute;top:0;left:0;width:100%;min-height:100vh;
  transition:transform .45s cubic-bezier(0.4, 0, 0.2, 1), opacity .45s ease;
  opacity:0;pointer-events:none;
}
.screen.active{opacity:1;pointer-events:auto;transform:translateX(0);position:relative;}
.screen.slide-left-exit{transform:translateX(-60px);opacity:0;}
.screen.slide-left-enter{transform:translateX(60px);opacity:0;}
.screen.slide-right-exit{transform:translateX(60px);opacity:0;}
.screen.slide-right-enter{transform:translateX(-60px);opacity:0;}

.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;background:radial-gradient(circle at 15% 20%,rgba(242,169,59,.1),transparent 40%),var(--cream)}
.login-card{width:100%;max-width:860px;min-height:560px;display:grid;grid-template-columns:1fr 1fr;background:var(--cream-2);border-radius:22px;overflow:hidden;box-shadow:var(--shadow);border:1px solid var(--line);position:relative;}
@media(max-width:680px){.login-card{grid-template-columns:1fr;min-height:auto}}

.login-hero{position:relative;background:linear-gradient(160deg,var(--maroon-800),var(--maroon-900));padding:40px 32px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;overflow:hidden}
.login-hero .cloud-pos{position:absolute;top:20px;left:24px;animation:floating 4s ease-in-out infinite}
.login-hero .wave{position:absolute;left:0;right:0;bottom:0;height:70px;background:var(--gold);opacity:.9;border-radius:50% 50% 0 0/100% 100% 0 0;transform:scale(1.6)}
.login-hero .wave.two{background:#c73a2f;opacity:.7;height:46px;transform:scale(1.9) translateY(6px)}
.steamer{width:96px;height:96px;border-radius:50%;background:var(--cream);border:4px solid var(--gold);display:flex;align-items:center;justify-content:center;margin-bottom:14px;box-shadow:0 6px 14px rgba(0,0,0,.25);position:relative;z-index:2;overflow:hidden;padding:0}
.steamer img{width:100%;height:100%;object-fit:cover;}
.login-hero h2{color:var(--gold);font-size:26px;margin:0 0 4px;position:relative;z-index:2}
.login-hero .tagline{color:#f5e6cf;font-size:20px;font-weight:700;margin:2px 0 6px;position:relative;z-index:2}
.login-hero p{color:#e9d3ba;font-size:13px;margin:0;max-width:220px;position:relative;z-index:2}

.login-form{padding:38px 34px 32px 34px;display:flex;flex-direction:column;justify-content:center;position:relative;}

.login-form h3{margin:4px 0 4px;font-size:22px}
.login-form .sub{color:var(--ink-soft);font-size:13px;margin:0 0 14px}
.role-toggle{display:flex;background:var(--line);border-radius:10px;padding:4px;margin-bottom:14px}
.role-toggle button{flex:1;border:none;background:transparent;padding:9px 0;border-radius:8px;font-family:'Inter',sans-serif;font-weight:600;font-size:13px;color:var(--ink-soft);cursor:pointer;transition:.2s}
.role-toggle button.active{background:var(--maroon-800);color:#fbe9d0;box-shadow:0 3px 8px rgba(107,20,32,.35)}
.field-label{font-size:12px;font-weight:600;color:var(--ink-soft);margin:0 0 4px}
.input-group{display:flex;align-items:center;gap:8px;border:1.5px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:10px;background:var(--cream);transition:.2s}
.input-group:focus-within{border-color:var(--gold);box-shadow:0 0 0 3px rgba(242,169,59,.15)}
.input-group input{border:none;outline:none;background:transparent;font-family:'Inter',sans-serif;font-size:13px;width:100%;color:var(--ink)}
.input-group svg{opacity:.6;flex-shrink:0}

.link-back-top{
  position:absolute;top:12px;left:20px;background:transparent;border:none;
  display:flex;align-items:center;gap:6px;font-family:'Inter',sans-serif;
  font-size:12px;font-weight:600;color:var(--maroon-800);text-decoration:none;
  cursor:pointer;padding:4px 8px;border-radius:6px;transition:background .2s;z-index:5;
}
.link-back-top:hover{background:var(--line);}

.forgot-link-wrap{text-align:right;margin-bottom:14px;margin-top:-4px}
.forgot-link-wrap a{font-size:12px;color:var(--maroon-800);font-weight:600;text-decoration:none;cursor:pointer}
.forgot-link-wrap a:hover{text-decoration:underline}

.strength-bar{display:flex;gap:4px;margin-bottom:10px;height:4px}
.strength-bar div{flex:1;background:var(--line);border-radius:2px;transition:.3s}

.btn-primary{width:100%;border:none;background:linear-gradient(180deg,var(--maroon-700),var(--maroon-900));color:#fbe9d0;font-family:'Baloo 2',sans-serif;font-weight:700;font-size:15px;padding:11px 0;border-radius:10px;cursor:pointer;transition:.15s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(107,20,32,.35)}
.spinner{width:18px;height:18px;border:2px solid #fbe9d0;border-top-color:transparent;border-radius:50%;animation:spin .8s linear infinite;display:none}

/* ================= ALERT BOX ================= */
.alert{
  padding:10px 12px;border-radius:10px;font-size:12.5px;
  margin-bottom:12px;line-height:1.5;border:1px solid;
  font-family:'Inter',sans-serif;
}
.alert-error{background:#fdecec;color:#b02a2a;border-color:#f5bcbc}
.alert-success{background:#e9f7eb;color:#1e6a2a;border-color:#bce0c2}
.alert ul{margin:0;padding-left:16px}
.alert li{margin-bottom:2px}

.toast-top{
  position:fixed;top:25px;left:50%;transform:translateX(-50%) translateY(-40px);
  background:linear-gradient(135deg, var(--maroon-800), var(--maroon-900));
  color:#fbe9d0;padding:14px 26px;border-radius:30px;
  box-shadow:0 10px 30px rgba(74,14,24,.3);z-index:1000;
  display:flex;align-items:center;gap:10px;font-weight:600;font-size:14px;
  border:1px solid var(--gold);opacity:0;pointer-events:none;
  transition:all .4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.toast-top.show{opacity:1;transform:translateX(-50%) translateY(0);pointer-events:auto;}

footer.attr{text-align:center;font-size:11px;color:var(--ink-soft);padding:14px;position:absolute;bottom:0;width:100%}
</style>
</head>
<body>

<!-- SPLASH SCREEN -->
<div id="splash-screen">
  <div class="splash-logo"><img src="asse/lo1.png" alt="Logo"></div>
  <h2>Ngemil Dimsum</h2>
  <p>Menyiapkan yang hangat untukmu...</p>
</div>

<!-- TOAST -->
<div class="toast-top" id="toastTop">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
  <span id="toastMsg">Berhasil!</span>
</div>

<div class="main-container">

  <!-- ============================================================
       SCREEN 1: LOGIN
  ============================================================ -->
  <section class="<?= screenClass('login-customer', $activeScreen) ?>" id="login-customer">
  <div class="login-wrap">
  <div class="login-card">
    <div class="login-hero">
      <div class="cloud-pos"><svg width="32" height="20" viewBox="0 0 24 16" fill="#fff"><path d="M19.36 6.04C18.87 2.62 15.9 0 12.3 0c-2.8 0-5.2 1.6-6.3 4C3.8 4.4 2 6.4 2 8.8c0 2.4 1.9 4.2 4.3 4.2h13c2.2 0 4-1.8 4-4s-1.8-4-3.94-2.76z"/></svg></div>
      <div class="steamer"><img src="asse/lo1.png" alt="Logo"></div>
      <h2>Ngemil<br>Dimsum</h2>
      <div class="tagline">Selamat Datang!</div>
      <p>Masuk untuk mulai memesan Dimsum favoritmu.</p>
      <div class="wave"></div><div class="wave two"></div>
    </div>
    <div class="login-form">
      <a href="index.php" class="link-back-top">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Kembali
      </a>

      <h3>Masuk ke Akun</h3>
      <p class="sub">cepat login, banyak dimsum yang enak menunggu mu</p>

      <div class="role-toggle">
        <button type="button" class="active">login</button>
        <button type="button" onclick="switchScreen('register', 'left')">buat akun</button>
      </div>

      <?php if ($activeScreen === 'login-customer' && $errors): ?>
        <div class="alert alert-error">
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
      <?php if ($activeScreen === 'login-customer' && $success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" id="formLogin">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <p class="field-label">Email / No. Handphone</p>
        <div class="input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <input id="cEmail" name="identifier" type="text" placeholder="Masukkan Email atau No. HP" value="<?= e($oldIdentifier) ?>" required>
        </div>

        <p class="field-label">Password</p>
        <div class="input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input id="cPass" name="password" type="password" placeholder="Masukkan Password" required>
          <svg id="cEye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="cursor:pointer" onclick="togglePass('cPass','cEye')"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>

        <div class="forgot-link-wrap">
          <a onclick="switchScreen('forgot-password', 'left')">Lupa Password?</a>
        </div>

        <button type="submit" class="btn-primary" id="btnCLogin">
          <span>Masuk →</span>
          <div class="spinner" id="spinC"></div>
        </button>
      </form>
    </div>
  </div>
  </div>
  </section>

  <!-- ============================================================
       SCREEN 2: REGISTER
  ============================================================ -->
  <section class="<?= screenClass('register', $activeScreen) ?>" id="register">
  <div class="login-wrap">
  <div class="login-card">
    <div class="login-hero">
      <div class="cloud-pos"><svg width="32" height="20" viewBox="0 0 24 16" fill="#fff"><path d="M19.36 6.04C18.87 2.62 15.9 0 12.3 0c-2.8 0-5.2 1.6-6.3 4C3.8 4.4 2 6.4 2 8.8c0 2.4 1.9 4.2 4.3 4.2h13c2.2 0 4-1.8 4-4s-1.8-4-3.94-2.76z"/></svg></div>
      <div class="steamer"><img src="asse/lo1.png" alt="Logo"></div>
      <h2>Ngemil<br>Dimsum</h2>
      <div class="tagline">Buat Akun Baru</div>
      <p>Daftarkan akun untuk mulai memesan.</p>
      <div class="wave"></div><div class="wave two"></div>
    </div>
    <div class="login-form">
      <a href="index.php" class="link-back-top">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Kembali
      </a>

      <h3>Registrasi Akun</h3>
      <p class="sub">tolong isi pendaftaran nya dulu</p>

      <div class="role-toggle">
        <button type="button" onclick="switchScreen('login-customer', 'right')">login</button>
        <button type="button" class="active">buat akun</button>
      </div>

      <?php if ($activeScreen === 'register' && $errors): ?>
        <div class="alert alert-error">
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" action="login.php" id="formRegister">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <p class="field-label">Nama Lengkap</p>
        <div class="input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input id="rName" name="nama" type="text" placeholder="Masukkan Nama Lengkap" value="<?= e($oldNama) ?>" required>
        </div>

        <p class="field-label">Email / No. Handphone</p>
        <div class="input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <input id="rEmail" name="identifier" type="text" placeholder="contoh@email.com atau 0812xxxx" value="<?= e($oldIdentifier) ?>" required>
        </div>

        <p class="field-label">Password Baru</p>
        <div class="input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input id="rPass" name="password" type="password" placeholder="Minimal 6 karakter" oninput="checkStrength(this.value)" required>
          <svg id="rEye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="cursor:pointer" onclick="togglePass('rPass','rEye')"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <div class="strength-bar" id="strengthBar">
          <div id="s1"></div><div id="s2"></div><div id="s3"></div>
        </div>

        <button type="submit" class="btn-primary" id="btnRReg">
          <span>Daftar Akun →</span>
          <div class="spinner" id="spinR"></div>
        </button>
      </form>
    </div>
  </div>
  </div>
  </section>

  <!-- ============================================================
       SCREEN 3: FORGOT PASSWORD
  ============================================================ -->
  <section class="<?= screenClass('forgot-password', $activeScreen) ?>" id="forgot-password">
  <div class="login-wrap">
  <div class="login-card">
    <div class="login-hero">
      <div class="cloud-pos"><svg width="32" height="20" viewBox="0 0 24 16" fill="#fff"><path d="M19.36 6.04C18.87 2.62 15.9 0 12.3 0c-2.8 0-5.2 1.6-6.3 4C3.8 4.4 2 6.4 2 8.8c0 2.4 1.9 4.2 4.3 4.2h13c2.2 0 4-1.8 4-4s-1.8-4-3.94-2.76z"/></svg></div>
      <div class="steamer"><img src="asse/lo1.png" alt="Logo"></div>
      <h2>Ngemil<br>Dimsum</h2>
      <div class="tagline">Pulihkan Akun</div>
      <p>Jangan khawatir, kami akan bantu kembalikan akunmu.</p>
      <div class="wave"></div><div class="wave two"></div>
    </div>
    <div class="login-form">
      <a href="index.php" class="link-back-top">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Kembali
      </a>

      <h3>Reset Password</h3>
      <p class="sub">Masukkan email terdaftar untuk menerima link pemulihan</p>

      <?php if ($activeScreen === 'forgot-password' && $success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php">
        <input type="hidden" name="action" value="forgot">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <p class="field-label">Email Terdaftar</p>
        <div class="input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <input id="fEmail" name="identifier" type="email" placeholder="Masukkan Email Anda">
        </div>

        <button type="submit" class="btn-primary" id="btnForgot">
          <span>Kirim Instruksi →</span>
          <div class="spinner" id="spinF"></div>
        </button>
      </form>
    </div>
  </div>
  </div>
  </section>

</div>

<footer class="attr"></footer>

<script>
window.addEventListener('load', () => {
  setTimeout(() => {
    document.getElementById('splash-screen').classList.add('fade-out');
  }, 1200);
});

function switchScreen(targetId, direction){
  const current = document.querySelector('.screen.active');
  const target = document.getElementById(targetId);
  if(!current || current === target) return;

  if(direction === 'left'){
    current.classList.add('slide-left-exit');
    target.classList.add('slide-left-enter');
  } else {
    current.classList.add('slide-right-exit');
    target.classList.add('slide-right-enter');
  }
  current.classList.remove('active');

  setTimeout(() => {
    current.classList.remove('slide-left-exit', 'slide-right-exit');
    target.classList.remove('slide-left-enter', 'slide-right-enter');
    target.classList.add('active');
    window.scrollTo({top:0,behavior:'smooth'});
  }, 300);
}

function togglePass(inputId, svgId){
  const i = document.getElementById(inputId), s = document.getElementById(svgId);
  if(i.type === 'password'){
    i.type = 'text';
    s.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    i.type = 'password';
    s.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}

function checkStrength(val){
  const s1 = document.getElementById('s1'),
        s2 = document.getElementById('s2'),
        s3 = document.getElementById('s3');
  s1.style.background = val.length > 0 ? '#f2a93b' : 'var(--line)';
  s2.style.background = val.length > 5 ? '#f2a93b' : 'var(--line)';
  s3.style.background = val.length > 8 ? '#247324' : 'var(--line)';
}

// Spinner saat submit
['formLogin', 'formRegister'].forEach(id => {
  const form = document.getElementById(id);
  if (!form) return;
  form.addEventListener('submit', function() {
    const btn = form.querySelector('.btn-primary');
    const spinner = btn.querySelector('.spinner');
    const span = btn.querySelector('span');
    if (spinner) {
      spinner.style.display = 'block';
      span.style.display = 'none';
      btn.style.pointerEvents = 'none';
    }
  });
});

// Kalau ada success message, tampilkan toast
<?php if ($success && $activeScreen === 'login-customer'): ?>
window.addEventListener('load', () => {
  setTimeout(() => {
    const toast = document.getElementById('toastTop');
    document.getElementById('toastMsg').textContent = <?= json_encode($success) ?>;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }, 1400);
});
<?php endif; ?>
</script>
</body>
</html>