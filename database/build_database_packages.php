<?php

$databaseDir = __DIR__;
$migrationFiles = glob($databaseDir . DIRECTORY_SEPARATOR . '[0-9][0-9][0-9]_*.sql');

if ($migrationFiles === false || $migrationFiles === []) {
    fwrite(STDERR, "Migration SQL tidak ditemukan.\n");
    exit(1);
}

sort($migrationFiles, SORT_NATURAL);

$baseNotice = <<<'SQL'
-- APLIKASI SURVEI SKM/IPAK
-- Dibuat otomatis dari migration bernomor di folder database.
-- Jangan mengubah paket hasil build secara langsung; ubah migration sumbernya.
--
-- PENTING:
-- 1. Paket ini tidak menghapus tabel maupun data SKM lama.
-- 2. Database tujuan harus sudah memiliki tabel aplikasi SKM lama,
--    khususnya skm_data_skm, skm_cms_user, dan tabel referensi SKM.
-- 3. ipak_admin_roles sengaja tidak memakai FOREIGN KEY ke skm_cms_user.
--    Hal ini menjaga kompatibilitas saat tipe/engine tabel pengguna server lama berbeda.

SET NAMES utf8;
SET time_zone = '+07:00';

SQL;

$packages = [
    'VERSI_1_UPDATE_DATABASE_EXISTING.sql' =>
        "-- VERSI 1 (DIREKOMENDASIKAN): UPDATE DATABASE SERVER YANG SUDAH BERJALAN\n"
        . "-- Aman dijalankan ulang. Struktur dan data SKM lama dipertahankan.\n\n",
    'VERSI_2_INSTALL_MODUL_LENGKAP.sql' =>
        "-- VERSI 2: INSTALASI LENGKAP MODUL SURVEI PADA DATABASE SKM LAMA\n"
        . "-- Gunakan untuk server SKM yang belum pernah dipasangi modul survei fleksibel.\n\n",
];

$migrationSql = '';
foreach ($migrationFiles as $migrationFile) {
    $name = basename($migrationFile);
    $contents = file_get_contents($migrationFile);
    if ($contents === false) {
        fwrite(STDERR, "Gagal membaca {$name}.\n");
        exit(1);
    }

    $migrationSql .= "\n-- ============================================================\n";
    $migrationSql .= "-- MIGRATION: {$name}\n";
    $migrationSql .= "-- ============================================================\n\n";
    $migrationSql .= rtrim($contents) . "\n";
}

foreach ($packages as $filename => $packageHeader) {
    $target = $databaseDir . DIRECTORY_SEPARATOR . $filename;
    $sql = $packageHeader . $baseNotice . $migrationSql;

    if (file_put_contents($target, $sql) === false) {
        fwrite(STDERR, "Gagal menulis {$filename}.\n");
        exit(1);
    }

    echo $filename . ' (' . strlen($sql) . " bytes)\n";
}
