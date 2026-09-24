<?php
if (!isset($pageTitle)) $pageTitle = 'Admin';
if (!isset($_SESSION))  session_start();

$adminNama    = $_SESSION['nama'] ?? 'Admin';
$adminInisial = strtoupper(substr($adminNama, 0, 2));

// Query DB — biar foto selalu fresh
$adminFoto = null;
if (isset($_SESSION['user_id']) && isset($koneksi)) {
    $stmtF = $koneksi->prepare("SELECT foto_profil FROM users WHERE id = ?");
    $stmtF->bind_param('i', $_SESSION['user_id']);
    $stmtF->execute();
    $rowF = $stmtF->get_result()->fetch_assoc();
    $adminFoto = $rowF['foto_profil'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — Ngemil Dimsum Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<div class="admin-layout">