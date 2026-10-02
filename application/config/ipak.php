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
| Kredensial endpoint maintenance sync-database.php
|
| Nilai diambil dari .env, tidak pernah ditulis langsung di sini. Bila .env
| belum diisi, endpoint menolak berjalan dan menampilkan pesan konfigurasi,
| bukan memakai kredensial bawaan apa pun.
|
|   IPAK_MAINT_ID         = id maintenance
|   IPAK_MAINT_PASSWORD   = password maintenance
|   IPAK_MAINT_ALLOWED_IPS= daftar IP dipisah koma, kosong = semua IP
*/
$config['ipak_maintenance_id'] = getenv('IPAK_MAINT_ID') !== false
    ? getenv('IPAK_MAINT_ID')
    : '';
$config['ipak_maintenance_password'] = getenv('IPAK_MAINT_PASSWORD') !== false
    ? getenv('IPAK_MAINT_PASSWORD')
    : '';
$config['ipak_maintenance_allowed_ips'] = getenv('IPAK_MAINT_ALLOWED_IPS') !== false
    ? getenv('IPAK_MAINT_ALLOWED_IPS')
    : '';
$config['ipak_maintenance_max_attempts'] = 5;
$config['ipak_maintenance_lockout_minutes'] = 15;
