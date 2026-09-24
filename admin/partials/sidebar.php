<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$menuUtama = [
    ['index.php',      'Dashboard', 'fa-chart-line'],
    ['products.php',   'Produk',    'fa-box'],
    ['categories.php', 'Kategori',  'fa-tags'],
    ['orders.php',     'Pesanan',   'fa-receipt'],
    ['users.php',      'Pelanggan', 'fa-users'],
];

// Path logo — ganti sesuai kebutuhan
$logoAdmin = '../images/logo-admin.png';   // dari folder admin/ ke images/
$logoAdminFallback = '../asse/lo2.jpeg';         // fallback kalau logo utama gak ada
?>

<aside class="admin-sidebar">
    <div class="admin-logo">
        <?php if (file_exists(__DIR__ . '/../../images/logo-admin.png')): ?>
            <img src="<?= $logoAdmin ?>" alt="Logo" class="admin-logo-icon" style="object-fit:cover;">
        <?php elseif (file_exists(__DIR__ . '/../../asse/lo2.jpeg')): ?>
            <img src="<?= $logoAdminFallback ?>" alt="Logo" class="admin-logo-icon" style="object-fit:cover;">
        <?php else: ?>
            <div class="admin-logo-icon"></div>
        <?php endif; ?>

        <div class="admin-logo-text">
            Ngemil Dimsum
            <small>ADMIN PANEL</small>
        </div>
    </div>

    <nav class="admin-nav">
        <div class="admin-nav-section">Menu Utama</div>
        <?php foreach ($menuUtama as $m):
            $active = $currentPage === $m[0];
        ?>
            <a href="<?= $m[0] ?>" class="<?= $active ? 'active' : '' ?>">
                <i class="fas <?= $m[2] ?>"></i>
                <span><?= $m[1] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="admin-user-box">
        <a href="profil.php" class="admin-user-row" style="text-decoration:none;cursor:pointer;transition:.2s;border-radius:8px;padding:6px;margin:-6px;">
            <div class="admin-user-avatar">
                <?php if (!empty($adminFoto) && file_exists(__DIR__.'/../../'.$adminFoto)): ?>
                    <img src="../<?= htmlspecialchars($adminFoto) ?>?v=<?= time() ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars($adminInisial) ?>
                <?php endif; ?>
            </div>
            <div class="admin-user-info">
                <div class="admin-user-name"><?= htmlspecialchars($adminNama) ?></div>
                <div class="admin-user-role">Administrator</div>
            </div>
        </a>
        <a href="../logout.php" class="admin-logout" style="margin-top:10px;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>