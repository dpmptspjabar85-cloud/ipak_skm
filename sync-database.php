<?php
/**
 * Endpoint maintenance untuk menjalankan sinkronisasi struktur database.
 *
 * File ini adalah pintu masuk mandiri. Autentikasinya sendiri dan tidak
 * memakai login backoffice, namun tetap memakai function sync_database() yang
 * sudah ada di model, jadi logika sinkronisasi tidak diduplikasi.
 *
 * Alur:
 *   buka sync-database.php -> form ID + Password
 *   POST kredensial        -> divalidasi -> Ipaksurvey_model::sync_database()
 *   tampilkan hasil        -> tabel, field baru, field lama, error/warning
 *
 * Keamanan:
 *   - hanya POST yang menjalankan sinkronisasi, GET hanya menampilkan form;
 *   - kredensial dibaca dari .env, tidak ada password di dalam source;
 *   - perbandingan memakai hash_equals agar tahan tebak-tebakan waktu;
 *   - kunci sementara setelah beberapa kali gagal;
 *   - token CSRF untuk setiap POST;
 *   - daftar IP boleh dibatasi lewat IPAK_MAINT_ALLOWED_IPS.
 *
 * @see application/controllers/Maintenance.php
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
