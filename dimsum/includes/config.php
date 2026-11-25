<?php
session_start();

$DB_HOST = 'sql106.infinityfree.com';
$DB_USER = 'if0_43520102';
$DB_PASS = 'ngemildimsum';
$DB_NAME = 'if0_43520102_ngemil_dimsum';

$koneksi = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($koneksi->connect_error) {
    die('Koneksi DB gagal: ' . $koneksi->connect_error);
}
$koneksi->set_charset('utf8mb4');

define('BASE_URL', '/ngemil-dimsum'); // sesuaikan folder kamu
define('ONGKIR_DEFAULT', 5000);