<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    redirect('products.php');
}

$id = (int)$_POST['id'];

$stmt = $koneksi->prepare("SELECT nama, gambar FROM products WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();

if (!$produk) {
    flash('success', 'Produk tidak ditemukan.');
    redirect('products.php');
}

// Hapus file fisik
if ($produk['gambar'] && file_exists(__DIR__ . '/../' . $produk['gambar'])) {
    @unlink(__DIR__ . '/../' . $produk['gambar']);
}

// Hapus dari DB
$stmt = $koneksi->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

flash('success', "Produk \"{$produk['nama']}\" berhasil dihapus.");
redirect('products.php');