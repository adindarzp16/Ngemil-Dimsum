<?php
function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header("Location: $url"); exit; }
function isLogin() { return isset($_SESSION['user_id']); }
function isAdmin() { return isLogin() && $_SESSION['role'] === 'admin'; }
function requireLogin() { if (!isLogin()) redirect(BASE_URL.'/login.php'); }
function requireAdmin() { if (!isAdmin()) redirect(BASE_URL.'/login.php'); }
function flash($key, $msg = null) {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return; }
    $v = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $v;
}