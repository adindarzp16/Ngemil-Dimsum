-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2026 at 03:31 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ngemil_dimsum`
--

-- --------------------------------------------------------

--
-- Table structure for table `bahan_baku`
--

CREATE TABLE `bahan_baku` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `stok` decimal(10,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(20) NOT NULL DEFAULT 'kg',
  `stok_minimum` decimal(10,2) NOT NULL DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bahan_baku`
--

INSERT INTO `bahan_baku` (`id`, `nama`, `stok`, `satuan`, `stok_minimum`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 'Ayam', 2.00, 'kg', 3.00, NULL, '2026-10-14 13:13:23', '2026-10-14 13:13:23'),
(2, 'Udang', 1.50, 'kg', 3.00, NULL, '2026-10-14 13:13:23', '2026-10-14 13:13:23'),
(3, 'Kulit Dimsum', 3.00, 'pack', 2.00, NULL, '2026-10-14 13:13:23', '2026-10-14 13:13:23'),
(4, 'Sayuran', 5.00, 'kg', 2.00, NULL, '2026-10-14 13:13:23', '2026-10-14 13:13:23'),
(5, 'Tepung Tapioka', 4.00, 'kg', 2.00, NULL, '2026-10-14 13:13:23', '2026-10-14 13:13:23'),
(6, 'Tepung Terigu', 6.00, 'kg', 3.00, NULL, '2026-10-14 13:13:23', '2026-10-14 13:13:23');

-- --------------------------------------------------------

--
-- Table structure for table `bahan_baku_transaksi`
--

CREATE TABLE `bahan_baku_transaksi` (
  `id` int(11) NOT NULL,
  `bahan_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jenis` enum('masuk','keluar') NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `gambar` varchar(255) NOT NULL,
  `judul` varchar(150) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `promo_id` int(11) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `gambar`, `judul`, `deskripsi`, `link`, `promo_id`, `urutan`, `is_active`, `created_at`) VALUES
(6, 'asse/banner1.jpeg', 'Promo Spesial', 'Diskon 20% untuk semua menu', NULL, NULL, 1, 1, '2026-10-08 10:22:27'),
(7, 'asse/banner2.jpeg', 'Paket Hemat', 'Beli 2 gratis 1 dimsum pilihan', NULL, NULL, 2, 1, '2026-10-08 10:22:27'),
(8, 'asse/banner3.jpeg', 'New Menu', 'Coba varian terbaru kami', NULL, NULL, 3, 1, '2026-10-08 10:22:27'),
(9, 'asse/banner4.jpeg', 'Free Delivery', 'Gratis ongkir min. 50rb', NULL, NULL, 4, 1, '2026-10-08 10:22:27'),
(10, 'asse/banner5.jpeg', 'Member Day', 'Diskon khusus member setiap Jumat', NULL, NULL, 5, 1, '2026-10-08 10:22:27');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `nama`, `slug`) VALUES
(1, 'Dimsum Ayam', 'ayam'),
(2, 'Dimsum Udang', 'udang'),
(3, 'Dimsum Sayur', 'sayur'),
(4, 'Paket Hemat', 'paket'),
(5, 'Minuman', 'minuman');

-- --------------------------------------------------------

--
-- Table structure for table `keuangan_transaksi`
--

CREATE TABLE `keuangan_transaksi` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jenis` enum('pemasukan','pengeluaran') NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 0,
  `metode` enum('cash','transfer','qris','lainnya') DEFAULT 'cash',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `keuangan_transaksi`
--

INSERT INTO `keuangan_transaksi` (`id`, `tanggal`, `keterangan`, `jenis`, `jumlah`, `metode`, `catatan`, `created_at`) VALUES
(1, '2026-10-14', 'Penjualan Paket Mix', 'pemasukan', 320000, 'cash', NULL, '2026-10-14 12:49:12'),
(2, '2026-10-14', 'Pembelian Ayam', 'pengeluaran', 750000, 'transfer', NULL, '2026-10-14 12:49:12'),
(3, '2026-10-13', 'Penjualan Reguler', 'pemasukan', 285000, 'cash', NULL, '2026-10-14 12:49:12'),
(4, '2026-10-13', 'Pembelian Sayuran', 'pengeluaran', 180000, 'transfer', NULL, '2026-10-14 12:49:12'),
(5, '2026-10-12', 'Penjualan Paket Hemat', 'pemasukan', 420000, 'cash', NULL, '2026-10-14 12:49:12'),
(6, '2026-10-12', 'Pembelian Tepung', 'pengeluaran', 250000, 'cash', NULL, '2026-10-14 12:49:12'),
(7, '2026-10-11', 'Penjualan Reguler', 'pemasukan', 510000, 'transfer', NULL, '2026-10-14 12:49:12'),
(8, '2026-10-10', 'Pembelian Bumbu', 'pengeluaran', 320000, 'cash', NULL, '2026-10-14 12:49:12'),
(9, '2026-10-09', 'Penjualan Paket Mix', 'pemasukan', 620000, 'cash', NULL, '2026-10-14 12:49:12'),
(10, '2026-10-08', 'Pembelian Ayam', 'pengeluaran', 480000, 'transfer', NULL, '2026-10-14 12:49:12');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `kode_order` varchar(20) NOT NULL,
  `subtotal` int(11) NOT NULL,
  `ongkir` int(11) DEFAULT 5000,
  `grand_total` int(11) NOT NULL,
  `status` enum('pending','diproses','dikirim','selesai','dibatalkan') DEFAULT 'pending',
  `metode_bayar` varchar(20) DEFAULT 'cod',
  `metode_detail` varchar(50) DEFAULT NULL,
  `alamat_kirim` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `kode_order`, `subtotal`, `ongkir`, `grand_total`, `status`, `metode_bayar`, `metode_detail`, `alamat_kirim`, `catatan`, `created_at`) VALUES
(3, 1, 'ORD-260921-1305DE', 2686667, 5000, 2691667, 'dibatalkan', 'cod', NULL, 'hjhjhj\nPenerima: jordi | HP: 08736374663 | Bayar: TRANSFER', 'hhhkhk', '2026-09-21 08:26:30'),
(7, 1, 'ORD-260923-D38A05', 1000000, 5000, 1005000, 'pending', 'transfer', 'qris', 'hhhh\nPenerima: jordi | HP: 08736374663', '', '2026-09-23 13:30:17');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `nama_produk` varchar(100) DEFAULT NULL,
  `harga` int(11) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `subtotal` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `nama_produk`, `harga`, `qty`, `subtotal`) VALUES
(4, 3, 5, 'ayam', 20000, 1, 20000),
(5, 3, 4, 'ayang (8 pcs)', 2666667, 1, 2666667),
(10, 7, 6, 'miaw', 1000000, 1, 1000000);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `sku` varchar(30) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` int(11) NOT NULL,
  `harga_diskon` int(11) DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `stok` int(11) DEFAULT 0,
  `has_variant` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `nama`, `sku`, `deskripsi`, `harga`, `harga_diskon`, `gambar`, `stok`, `has_variant`, `is_active`, `created_at`) VALUES
(6, NULL, 'miaw', '1', 'hah', 1000000, NULL, 'images/products/20260923-152731-f7f4781e.jpg', 19, 0, 1, '2026-09-23 13:27:30');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `gambar` varchar(255) NOT NULL,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `gambar`, `urutan`, `created_at`) VALUES
(9, 6, 'images/products/20260923-152731-f7f4781e.jpg', 0, '2026-09-23 13:27:31');

-- --------------------------------------------------------

--
-- Table structure for table `promos`
--

CREATE TABLE `promos` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `persen_diskon` int(11) NOT NULL DEFAULT 10,
  `max_diskon` int(11) NOT NULL DEFAULT 0,
  `min_belanja` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promos`
--

INSERT INTO `promos` (`id`, `nama`, `deskripsi`, `persen_diskon`, `max_diskon`, `min_belanja`, `is_active`, `created_at`) VALUES
(1, 'Promo Spesial 15%', 'Diskon 15% semua produk! Maksimal potongan Rp 20.000.', 15, 20000, 0, 1, '2026-10-08 10:03:49'),
(2, 'Voucher Hemat 5%', 'Diskon 5% untuk semua menu', 5, 10000, 0, 1, '2026-10-08 10:33:13'),
(3, 'Voucher Spesial 10%', 'Diskon 10% semua menu', 10, 25000, 25000, 1, '2026-10-08 10:33:13'),
(4, 'Voucher Premium 15%', 'Diskon 15% untuk pembelian min 50rb', 15, 50000, 50000, 1, '2026-10-08 10:33:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `role` enum('admin','pelanggan') DEFAULT 'pelanggan',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `nickname`, `foto_profil`, `email`, `no_telp`, `alamat`, `role`, `created_at`) VALUES
(1, 'smkwb2', '$2y$10$y37GEa/N9ZROX.ct0ZaQ9e/.skuHBVkDm/5YXtw06u7HX04hJ.p9G', 'jordi', '', 'images/avatars/avatar-1-1790081032-de4608.jpg', 'smkwb2@gmail.com', '', '', 'pelanggan', '2026-09-19 09:17:26'),
(7, '2', '$2y$10$8ZiiJV9xwPXnGeE/nr8ERuhSEgWkjXq8A7ksfXJAZf.5lAYnAH33u', 'sayoi', '', 'images/avatars/avatar-7-1791984325-949677.jpg', '2@gmail.com', '', NULL, 'admin', '2026-10-14 12:45:31');

-- --------------------------------------------------------

--
-- Table structure for table `user_promos`
--

CREATE TABLE `user_promos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `promo_id` int(11) NOT NULL,
  `claimed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_promo_claims`
--

CREATE TABLE `user_promo_claims` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `promo_id` int(11) NOT NULL,
  `status` enum('claimed','in_cart','used') NOT NULL DEFAULT 'claimed',
  `used_at` timestamp NULL DEFAULT NULL,
  `cart_session` varchar(64) DEFAULT NULL,
  `claimed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bahan_baku`
--
ALTER TABLE `bahan_baku`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bahan_baku_transaksi`
--
ALTER TABLE `bahan_baku_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bahan` (`bahan_id`),
  ADD KEY `idx_tanggal` (`tanggal`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `promo_id` (`promo_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `keuangan_transaksi`
--
ALTER TABLE `keuangan_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tanggal` (`tanggal`),
  ADD KEY `idx_jenis` (`jenis`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_order` (`kode_order`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`,`urutan`);

--
-- Indexes for table `promos`
--
ALTER TABLE `promos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_promos`
--
ALTER TABLE `user_promos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_promo` (`user_id`,`promo_id`),
  ADD KEY `promo_id` (`promo_id`);

--
-- Indexes for table `user_promo_claims`
--
ALTER TABLE `user_promo_claims`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_promo` (`user_id`,`promo_id`),
  ADD KEY `promo_id` (`promo_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bahan_baku`
--
ALTER TABLE `bahan_baku`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `bahan_baku_transaksi`
--
ALTER TABLE `bahan_baku_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `keuangan_transaksi`
--
ALTER TABLE `keuangan_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `promos`
--
ALTER TABLE `promos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_promos`
--
ALTER TABLE `user_promos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_promo_claims`
--
ALTER TABLE `user_promo_claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bahan_baku_transaksi`
--
ALTER TABLE `bahan_baku_transaksi`
  ADD CONSTRAINT `bahan_baku_transaksi_ibfk_1` FOREIGN KEY (`bahan_id`) REFERENCES `bahan_baku` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `banners`
--
ALTER TABLE `banners`
  ADD CONSTRAINT `banners_ibfk_1` FOREIGN KEY (`promo_id`) REFERENCES `promos` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_promos`
--
ALTER TABLE `user_promos`
  ADD CONSTRAINT `user_promos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_promos_ibfk_2` FOREIGN KEY (`promo_id`) REFERENCES `promos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_promo_claims`
--
ALTER TABLE `user_promo_claims`
  ADD CONSTRAINT `user_promo_claims_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_promo_claims_ibfk_2` FOREIGN KEY (`promo_id`) REFERENCES `promos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
