<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $koneksi->prepare("SELECT nama_produk, qty, harga, subtotal FROM order_items WHERE order_id = ?");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($items);