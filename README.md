# Survei Pelayanan Terpadu DPMPTSP Jawa Barat

Aplikasi survei fleksibel dengan alur dan gaya SKM. Satu form publik dapat
memuat beberapa survei, tetapi nilai dan pengelolaan datanya tetap dipisahkan
per survei. Konfigurasi awal menggabungkan SKM dan IPAK dalam satu form.
Aplikasi berjalan berdampingan dengan aplikasi `skm` dan `survey_ipak` lama.

## Menjalankan aplikasi

Jalankan `start-local.bat`, lalu buka:

- Dashboard publik: `http://127.0.0.1:8001/`
- Formulir survei: `http://127.0.0.1:8001/survey`
- Backoffice: `http://127.0.0.1:8001/admin/login`

Formulir publik dibuka menggunakan nomor resi izin yang sudah terbit:

- `http://127.0.0.1:8001/survey?resi=NOMOR_RESI`
- kompatibel dengan parameter `?nomor_resi=...` dan `?CODE=...`;
- parameter tersebut juga dapat dikirim ke halaman utama dan akan diarahkan
  otomatis ke formulir survei.

Nomor resi diperiksa pada `tmpermohonan`. Nama pemohon/perusahaan, kontak, NIB,
jenis izin, dan sektor diambil dari data permohonan lama. Satu nomor resi hanya
dapat mengirim satu respons survei terpadu.

Backoffice menggunakan akun pada `backoffice.skm_cms_user`. Hak akses aplikasi
disimpan pada `backoffice.ipak_admin_roles`; password tetap disimpan sebagai
hash dan tidak pernah ditanam di source code.

## Penyimpanan database

Aplikasi tetap memakai tabel lama:

- `backoffice.skm_data_skm` untuk header respons agar laporan lama tetap kompatibel;
- `backoffice.skm_cms_user` untuk login backoffice.

Tabel normalisasi pertanyaan dan akses:

- `ipak_questions` untuk pertanyaan, pengukuran, kategori, bobot, dan status;
- `ipak_answer_options` untuk opsi serta nilai setiap pertanyaan;
- `ipak_response_answers` untuk jawaban terperinci dan snapshot konfigurasi;
- `ipak_admin_roles` untuk peran `admin` dan `superadmin`.

Tabel mesin multi-survei:

- `ipak_surveys` untuk definisi SKM, IPAK, atau survei baru;
- `ipak_survey_questions` untuk susunan dan bobot pertanyaan per survei;
- `ipak_survey_score_categories` untuk kategori hasil setiap survei;
- `ipak_forms` untuk definisi form publik;
- `ipak_form_surveys` untuk menggabungkan beberapa survei ke satu form;
- `ipak_submission_surveys` untuk nilai hasil yang terpisah per survei;
- `ipak_submission_survey_answers` untuk relasi jawaban terhadap hasil survei.
- `ipak_api_clients` untuk konfigurasi endpoint, cakupan data, dan hash kunci API;
- `ipak_api_access_logs` untuk pencatatan penggunaan serta pembatasan permintaan API.

SQL pembentukan dan data awal tersedia pada folder `database`. Respons utama
tetap ditulis ke `skm_data_skm` untuk kompatibilitas laporan lama. Jawaban
ditulis satu kali ke `ipak_response_answers`. Setiap pertanyaan hanya dapat
dimiliki satu survei; form gabungan menyatukan beberapa kumpulan pertanyaan
yang tidak tumpang tindih.

## Rumus nilai

Setiap opsi jawaban mempunyai `normalized_score` dan setiap pertanyaan mempunyai
`weight`. Nilai masing-masing survei dihitung dengan rumus:

`jumlah(normalized_score × weight) ÷ jumlah(weight)`

Nilai gabungan adalah rata-rata hasil survei yang terdapat pada form. Data awal
menggunakan skor 25, 50, 75, dan 100. Superadmin dapat membuat opsi, skor,
pengukuran, kategori, dan bobot yang berbeda untuk setiap pertanyaan.

## Fitur

- wizard survei responsif;
- halaman utama berupa dashboard statistik publik;
- validasi browser dan server;
- perlindungan CSRF;
- halaman keberhasilan dengan nomor referensi;
- login memakai akun admin lama;
- input respons oleh operator dengan field yang sama;
- dashboard nilai dan grafik statistik bulanan bergaya SKM;
- pilihan grafik keseluruhan, jenis kelamin, usia, pendidikan, pekerjaan,
  dan sektor layanan;
- filter tahun dan perangkat daerah seperti aplikasi SKM asli;
- filter ukuran grafik: Nilai Gabungan, Nilai SKM, atau Nilai IPAK;
- kompatibel dengan parameter lama `thnskm`, `stsd`, dan `token`;
- distribusi jawaban dan nilai per indikator;
- unduh grafik sebagai gambar, PDF, CSV, atau XLS;
- daftar, filter, detail respons;
- ekspor CSV yang dapat dibuka di Excel;
- pengelolaan pertanyaan, kategori, pengukuran, bobot, dan opsi oleh superadmin.
- pengelolaan survei serta pemilihan pertanyaan di dalamnya;
- pengelolaan form gabungan dengan satu atau beberapa survei;
- form mandiri dan shortcut `Isi Survei` dibuat otomatis untuk setiap survei baru;
- detail respons dan ekspor CSV memuat nilai terpisah untuk setiap survei.
- API Builder per aplikasi peminta dengan keluaran ringkasan, grafik, detail respons,
  dan struktur pertanyaan yang dapat dibatasi per survei.
- dokumentasi PDF otomatis per akses API dengan kop DPMPTSP, panduan Postman,
  cURL, PHP, JavaScript, PowerShell, kondisi HTTP, keamanan, dan cap keluaran sistem.

## API Builder

Superadmin dapat membuka menu `API Builder`, membuat peminta API, lalu memilih:

- semua survei aktif atau survei tertentu;
- keluaran `summary`, `chart`, `details`, dan `questions`;
- dimensi grafik dan kolom detail yang diizinkan;
- batas data per halaman, batas permintaan per menit, IP, CORS, dan masa berlaku.

Endpoint berbentuk:

`GET /api/v1/survey-data/{kode_endpoint}?resource=summary&survey=SKM&year=2026`

Kunci dikirim melalui header `Authorization: Bearer {kunci}` atau
`X-API-Key: {kunci}`. Kunci lengkap hanya tampil saat dibuat atau dibuat ulang;
database hanya menyimpan hash. Migrasi `database/013_api_builder.sql` hanya
menambah tabel API dan tidak mengubah tabel SKM lama.

Setelah akses API berhasil dibuat, tombol `Dokumentasi PDF` tersedia pada kartu
peminta dan panel kredensial. PDF dibuat dari konfigurasi terbaru, tidak memuat
kunci lengkap, serta menandai capnya sebagai keluaran sistem dan bukan tanda
tangan elektronik tersertifikasi.
