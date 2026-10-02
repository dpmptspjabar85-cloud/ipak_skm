<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['ipak_title'] = 'Survei Persepsi Anti Korupsi';
$config['ipak_agency'] = 'DPMPTSP Provinsi Jawa Barat';
$config['ipak_version'] = '2026';

$config['ipak_questions'] = [
    1 => 'Pelayanan yang dilakukan sudah sesuai dengan prosedur dan aturan yang berlaku di lingkungan DPMPTSP Jawa Barat.',
    2 => 'Petugas tidak pernah memanfaatkan jabatannya untuk memengaruhi proses atau hasil pelayanan.',
    3 => 'Tidak terdapat indikasi penyalahgunaan jabatan dalam proses pelayanan di lingkungan DPMPTSP Jawa Barat.',
    4 => 'Informasi mengenai biaya pelayanan disampaikan secara jelas dan mudah diakses oleh masyarakat.',
    5 => 'Pengguna layanan tidak pernah diminta membayar biaya tambahan di luar ketentuan resmi.',
    6 => 'Tidak terdapat pemberian hadiah atau imbalan kepada petugas untuk mempercepat pelayanan.',
    7 => 'Setiap transaksi pelayanan selalu disertai dengan bukti pembayaran resmi.',
    8 => 'Tidak terdapat praktik percaloan dari petugas, dinas teknis, atau pihak lain yang menjanjikan kemudahan layanan dengan imbalan tertentu.',
    9 => 'Tidak terdapat laporan atau indikasi tindakan curang dalam penyelenggaraan pelayanan publik.',
    10 => 'Tidak terdapat transaksi rahasia atau tidak tercatat yang terjadi di DPMPTSP Jawa Barat.',
];

$config['ipak_answer_labels'] = [
    1 => 'Sangat Tidak Setuju',
    2 => 'Tidak Setuju',
    3 => 'Setuju',
    4 => 'Sangat Setuju',
];

$config['ipak_education'] = [
    1 => 'SD',
    2 => 'SMP',
    3 => 'SMA/SMK',
    4 => 'Diploma',
    5 => 'Sarjana',
    6 => 'Pascasarjana',
];

$config['ipak_jobs'] = [
    1 => 'Aparatur Sipil Negara (ASN)',
    2 => 'Pegawai Swasta',
    3 => 'Wiraswasta',
    4 => 'TNI/POLRI',
    5 => 'Lainnya',
];

$config['ipak_services'] = [
    1 => 'Pelayanan Perizinan',
    2 => 'Pelayanan Pengawasan',
    3 => 'Pelayanan Pembinaan',
    4 => 'Pelayanan Penyelesaian Permasalahan',
    5 => 'Pelayanan Lainnya',
];

/*
| Jumlah pilihan KBLI yang dirender awal pada field select.
| Daftar lengkap katalog KBLI terlalu besar untuk dikirim di setiap halaman
| survei, sehingga sisanya diambil melalui pencarian sisi server
| (Survey::kbli_options).
*/
$config['ipak_kbli_initial_limit'] = 200;
$config['ipak_kbli_search_limit'] = 50;

$config['ipak_score_categories'] = [
    ['min' => 88.31, 'label' => 'Sangat Baik', 'color' => '#0f766e'],
    ['min' => 76.61, 'label' => 'Baik', 'color' => '#2563eb'],
    ['min' => 65.00, 'label' => 'Cukup', 'color' => '#d97706'],
    ['min' => 0, 'label' => 'Perlu Perbaikan', 'color' => '#dc2626'],
];

/*
| Endpoint maintenance sync-database.php
|
| Endpoint ini TIDAK memakai login backoffice dan tidak meminta ID maupun
| password. Pembatasnya adalah asal jaringan.
|
| ipak_maintenance_restrict_to_internal
|   true  = sinkronisasi hanya jalan bila REMOTE_ADDR berada di jaringan
|           internal (loopback, 10/8, 172.16/12, 192.168/16, 169.254/16).
|           Nilai ini yang dipakai di server.
|   false = abaikan pembatasan IP. HANYA untuk mesin development lokal,
|           jangan dipakai di server yang bisa dijangkau internet.
|
| ipak_maintenance_blocked_ips
|   Daftar IP yang secara khusus dilarang, dipisah koma. Dipakai bila
|   ada jaringan internal yang tidak boleh menjalankan sinkronisasi.
|   Kosong berarti tidak ada pengecualian.
|
| Peringatan: sync_database() menjalankan DDL terhadap database, yaitu
| CREATE TABLE, ALTER TABLE, dan ADD COLUMN. Jangan pernah memasang
| endpoint ini di server publik tanpa pembatas jaringan lain.
*/
$config['ipak_maintenance_restrict_to_internal'] = true;
$config['ipak_maintenance_blocked_ips'] = '';
