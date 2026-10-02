<?php
/**
 * Endpoint maintenance untuk menjalankan sinkronisasi struktur database.
 *
 * File ini adalah pintu masuk mandiri. Tidak memakai login backoffice dan
 * tidak meminta ID maupun password. Pembatasnya adalah asal jaringan:
 * sinkronisasi hanya berjalan bila request datang dari jaringan internal.
 * Tetap memakai function sync_database() yang sudah ada di model, jadi
 * logika sinkronisasi tidak diduplikasi.
 *
 * Alur:
 *   buka sync-database.php -> dari jaringan internal, tombol Sync Database
 *   POST                   -> divalidasi CSRF -> Ipaksurvey_model::sync_database()
 *   tampilkan hasil        -> tabel, field baru, field lama, error/warning
 *
 * Keamanan:
 *   - hanya POST yang menjalankan sinkronisasi, GET hanya menampilkan tombol;
 *   - hanya REMOTE_ADDR jaringan internal yang diizinkan. Alamat IP privat
 *     tidak dapat diusulkan dari internet publik, jadi ini batas yang nyata;
 *   - token CSRF tetap diwajibkan untuk setiap POST, supaya halaman web lain
 *     tidak bisa memicu sinkronisasi lewat browser milik orang lain yang
 *     kebetulan berada di jaringan internal;
 *   - daftar IP khusus yang diblokir diatur lewat ipak_maintenance_blocked_ips.
 *
 * PERINGATAN: sync_database() menjalankan DDL terhadap database, yaitu
 * CREATE TABLE, ALTER TABLE, dan ADD COLUMN. Jangan pasang endpoint ini di
 * server yang bisa dijangkau publik tanpa pembatas jaringan lain.
 *
 * @see application/controllers/Maintenance.php
 * @see application/config/ipak.php
 * @see Ipaksurvey_model::sync_database()
 */

$ipakRoot = __DIR__;

// Jangan pernah bocorkan isi direktori kalau web server salah konfigurasi.
if (is_dir($ipakRoot) && !is_file($ipakRoot . DIRECTORY_SEPARATOR . 'index.php')) {
    header('HTTP/1.1 403 Forbidden.', true, 403);
    exit('Akses ditolak.');
}

// Method lain selain GET dan POST ditolak outright, supaya tidak ada cara
// memancing sinkronisasi lewat HEAD, OPTIONS, atau method buatan.
$ipakMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
if ($ipakMethod !== 'GET' && $ipakMethod !== 'POST') {
    header('Allow: GET, POST', true, 405);
    header('HTTP/1.1 405 Method Not Allowed.', true, 405);
    exit('Method tidak diizinkan.');
}

// Penanda bahwa request datang lewat pintu masuk ini, bukan lewat index.php
// secara langsung. Dipakai sebagai jejak diagnostik.
if (!isset($GLOBALS['IPAK_MAINTENANCE_ENTRY'])) {
    $GLOBALS['IPAK_MAINTENANCE_ENTRY'] = true;
}

// Berkas ini adalah satu-satunya front controller untuk endpoint ini, jadi
// penentuan rute ditulis tanpa syarat. Sebagian konfigurasi web server sudah
// mengisi PATH_INFO sebelum skrip berjalan, sehingga penulisan bersyarat membuat
// rute ikut terpengaruh dan endpoint tidak ditemukan.
$_SERVER['PATH_INFO'] = '/maintenance/sync_database';
$_SERVER['REQUEST_URI'] = '/index.php/maintenance/sync_database';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $ipakRoot . DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['PHP_SELF'] = '/index.php/maintenance/sync_database';

require_once $ipakRoot . DIRECTORY_SEPARATOR . 'index.php';
