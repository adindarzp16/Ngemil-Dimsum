<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function login($username, $password) {
    global $koneksi;
    $stmt = $koneksi->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama']     = $user['nama_lengkap'];
        $_SESSION['role']     = $user['role'];
        return true;
    }
    return false;
}

function register($username, $password, $nama, $email = '', $telp = '', $alamat = '') {
    global $koneksi;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $koneksi->prepare("INSERT INTO users (username,password,nama_lengkap,email,no_telp,alamat) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('ssssss', $username, $hash, $nama, $email, $telp, $alamat);
    return $stmt->execute();
}

function logout() { session_destroy(); }