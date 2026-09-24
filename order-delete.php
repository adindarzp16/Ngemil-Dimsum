<?php
/* ============================================================
   NGEMIL DIMSUM — Hapus Pesanan (Pelanggan)
   Hanya bisa hapus kalau status = dibatalkan
   ============================================================ */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) redirect('admin/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id'])) {
    redirect('orders.php');
}

$orderId = (int)$_POST['order_id'];
$userId  = $_SESSION['user_id'];

// Cek order milik user & status
$stmt = $koneksi->prepare("
    SELECT id, kode_order, status FROM orders
    WHERE id = ? AND user_id = ? LIMIT 1
");
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    flash('success', '❌ Pesanan tidak ditemukan.');
    redirect('orders.php');
}

if (!in_array($order['status'], ['dibatalkan', 'selesai'])) {
    flash('success', '⚠️ Hanya pesanan yang sudah dibatalkan atau selesai yang bisa dihapus.');
    redirect('orders.php');
}

// Hapus (order_items akan otomatis terhapus via ON DELETE CASCADE)
$stmt = $koneksi->prepare("DELETE FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $orderId, $userId);

if ($stmt->execute()) {
    flash('success', '✅ Pesanan #' . $order['kode_order'] . ' berhasil dihapus.');
} else {
    flash('success', '❌ Gagal menghapus pesanan.');
}

redirect('orders.php');