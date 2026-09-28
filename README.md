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
SKM legacy tetap ditulis ke `skm_data_skm` agar kompatibel dengan laporan lama.
Respons survei fleksibel non-SKM ditulis ke `ipak_survey_responses`. Jawaban
detail disimpan di `ipak_response_answers`, dan `ipak_submission_surveys` serta
`ipak_submission_survey_answers` mengaitkan nilai dan jawaban ke survei yang
dinilai. Snapshot teks pertanyaan, opsi, pengukuran, kategori, dan field
responden menjaga konteks respons saat konfigurasi berubah. Pertanyaan dapat
dipakai pada lebih dari satu survei; bobot dan pemetaan jawaban ke hasil survei
disimpan per-survei.

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
- menu pengelolaan dipisahkan menjadi Survei, Form, dan Shortcut. Form memiliki
  tab terpisah untuk Form Utama dan Form Gabungan;
- detail respons dan ekspor CSV memuat nilai terpisah untuk setiap survei.
- API Builder per aplikasi peminta dengan keluaran ringkasan, grafik, detail respons,
  dan struktur pertanyaan yang dapat dibatasi per survei.
- dokumentasi PDF otomatis per akses API dengan kop DPMPTSP, panduan Postman,
  cURL, PHP, JavaScript, PowerShell, kondisi HTTP, keamanan, dan cap keluaran sistem.
- pengelolaan survei menyediakan tautan langsung untuk mengubah identitas dan
  susunan survei, isi pertanyaan dan opsi, serta nama form dan input responden.
- wizard survei mendukung pilihan label dengan value angka yang dapat diatur,
  misalnya `SD = 1`, untuk pendidikan, pekerjaan, sektor, dan dropdown tambahan.
  Field statistik bawaan tetap memakai kode kategori yang tersedia; dropdown
  tambahan dapat memakai value angka bebas atau teks label sebagai value.
- katalog KBLI memiliki menu admin sendiri untuk tambah, ubah, hapus, pencarian,
  dan impor CSV dengan pilihan mempertahankan atau mengganti isi katalog.

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

## Peta aplikasi

Aplikasi menggunakan CodeIgniter 3 dengan `index.php` sebagai front controller.
Permintaan lokal diarahkan oleh `router.php`, yang menyerahkan file statis
langsung ke server PHP dan meneruskan route aplikasi ke CodeIgniter.

- `application/config/routes.php` memetakan URL publik, backoffice, dan API.
- `application/controllers/Survey.php` mengelola dashboard publik, katalog
  form, pemeriksaan resi, validasi pengiriman, dan halaman hasil.
- `application/controllers/Admin.php` mengelola login, laporan, respons,
  pertanyaan, survei, form, unit layanan, pengguna, sinkronisasi database,
  dan API Builder. Operasi pengelolaan tertentu dibatasi untuk superadmin.
- `application/controllers/Api.php` memvalidasi permintaan endpoint API dan
  membentuk keluaran ringkasan, grafik, pertanyaan, atau detail.
- `application/models/Ipaksurvey_model.php` adalah pemilik utama definisi
  survei/form, pencarian resi, perhitungan nilai, penyimpanan respons, dan
  kueri laporan.
- `application/models/Survey_api_model.php` mengelola klien API, autentikasi
  kunci, pembatasan permintaan, serta log akses.
- `application/views/public/` berisi tampilan dashboard dan form publik;
  `application/views/admin/` berisi tampilan backoffice.
- `application/config/ipak.php` berisi label dan konfigurasi default IPAK.
  Pertanyaan, survei, form, kategori nilai, dan opsi yang dibuat pengguna
  disimpan di database, bukan hanya di konfigurasi ini.
- `assets/` berisi CSS, JavaScript, gambar, font, dan pustaka sisi browser.
- `SIAP_UPLOAD_SERVER_APLIKASI/` adalah paket salinan untuk upload server.
  Saat kode utama berubah, periksa apakah file yang sama di paket upload juga
  perlu diperbarui sebelum paket tersebut dipakai untuk deployment.

## Alur respons

1. Halaman publik memuat definisi form, survei, pertanyaan, opsi, dan field
   responden dari database.
2. Form yang mensyaratkan resi memeriksa nomor tersebut terhadap data
   permohonan lama, memastikan izin sudah terbit/selesai, lalu mencegah
   pengiriman kedua untuk resi yang sama.
3. Server memvalidasi persetujuan, field responden, dan bahwa setiap jawaban
   termasuk opsi yang dibolehkan oleh definisi form. Jangan mengandalkan
   validasi browser saja.
4. Model menghitung skor berbobot per survei. Satu kiriman ke form gabungan
   memiliki kode kelompok yang sama, tetapi menyimpan hasil terpisah untuk
   tiap survei. Penulisan dilakukan sebagai transaksi database.
5. Respons SKM legacy mengikuti bentuk tabel SKM lama; respons fleksibel lain
   disimpan terpisah. Jawaban dan snapshot konfigurasi ditautkan ke respons
   masing-masing.

Perubahan pada proses ini perlu menjaga kesesuaian antara validasi controller,
perhitungan dan penyimpanan model, struktur tabel, serta dashboard, detail,
ekspor, dan API yang membaca data tersebut.

## Konfigurasi penting

- Koneksi aktif berada di `application/config/database.php`: MySQLi ke
  database `backoffice` pada `localhost:3306`, dengan konfigurasi lokal bawaan
  `root` tanpa password. Sesuaikan kredensial di lingkungan masing-masing;
  jangan memasukkan kredensial produksi ke source control.
- `index.php` memuat file `.env` di root dan menetapkan `CI_ENV`; tetapi nilai
  koneksi `IPAK_DB_*` pada `database.php` saat ini masih berupa contoh komentar,
  bukan konfigurasi aktif. Pastikan sumber koneksi sebelum mengandalkan `.env`.
- `application/config/config.php` mengaktifkan CSRF dan menyimpan session ke
  file di `application/cache`. Pastikan folder tersebut dapat ditulis oleh
  proses PHP dan tidak dapat diakses sebagai file publik pada server.
- `application/config/migration.php` menonaktifkan migrasi bawaan CodeIgniter.
  Skema aplikasi dikirim sebagai SQL bernomor di `database/`; jangan
  mengasumsikan deploy aplikasi akan menjalankan migrasi otomatis.
- Server lokal dapat dijalankan dengan `start-local.bat` atau
  `start-local-php56.bat` di `http://127.0.0.1:8001`. Skrip PHP 5.6 memakai
  `C:\xampp\php\php.exe` dan `C:\xampp\php\php.ini`; sesuaikan jalur bila
  instalasi PHP berada di lokasi lain. PHP 5.6 sudah usang dan hanya layak
  digunakan untuk kompatibilitas aplikasi lama, bukan layanan internet baru.

## Panduan update

### Perubahan database

1. Buat migration SQL baru dengan nomor berikutnya di `database/`; jangan
   mengubah migration lama yang mungkin sudah pernah dijalankan pada server.
2. Pastikan migration aman untuk data lama, mempertahankan tabel SKM warisan,
   dan kompatibel dengan versi MySQL server tujuan. Struktur tabel lama dapat
   berbeda antar-server; foreign key terhadap tabel warisan perlu perhatian
   khusus.
3. Bangun ulang paket SQL dari sumber migration dengan menjalankan
   `php database/build_database_packages.php`. Jangan mengedit langsung
   `VERSI_1_UPDATE_DATABASE_EXISTING.sql` atau
   `VERSI_2_INSTALL_MODUL_LENGKAP.sql`; keduanya adalah hasil build.
4. Backup database sebelum import dan uji pada salinan staging. Untuk database
   SKM yang sudah berjalan, ikuti `database/PETUNJUK_DUA_VERSI_DATABASE.md`
   serta `database/DEPLOYMENT_SAFE_SKM.md`. Versi 2 memasang modul lengkap pada
   database SKM yang tabel lamanya sudah tersedia; bukan database kosong.
5. Setelah import, periksa jumlah respons SKM lama, akses admin, form publik,
   pengiriman uji, laporan, ekspor, dan endpoint API yang terdampak.

Migration `database/019_create_kbli_catalog.sql` membuat katalog KBLI terpisah
di `ipak_kbli`. Katalog ini tidak mengubah tabel sektor lama `trsektor` atau
nilai sektor yang telah tersimpan pada respons SKM. Menu KBLI juga menjalankan
sinkronisasi idempoten saat dibuka: tabel, kolom, dan index yang belum tersedia
ditambahkan, sedangkan struktur yang sudah ada dibiarkan utuh. Sinkronisasi
database admin umum turut memeriksa tabel KBLI tersebut.

Sinkronisasi tidak mewajibkan foreign key dari `ipak_response_answers.option_id`
ke `ipak_answer_options.id`; jawaban menyimpan snapshot label/nilai dan data
historis dapat memakai `option_id = 0`. Foreign key lain yang memiliki orphan
tetap dilaporkan dengan jumlah serta contoh nilainya; sinkronisasi tidak
menghapus atau menebak data historis.

### Perubahan fitur

- Untuk perubahan URL, perbarui `application/config/routes.php` dan periksa
  perilaku langsung serta route yang memakai method HTTP tertentu.
- Untuk perubahan aturan form atau skor, periksa validasi
  `Survey::submit()`, metode perhitungan dan `create_response()` pada model,
  lalu periksa hasil di laporan dan API.
- Untuk perubahan tabel respons, pastikan kedua profil penyimpanan (SKM legacy
  dan survei fleksibel) tetap benar. Jangan menganggap semua respons berada di
  satu tabel.
- Untuk perubahan akses, periksa guard login dan superadmin di controller.
  API memiliki autentikasi dan pembatasan tersendiri di `Api.php` serta
  `Survey_api_model.php`.
- Untuk perubahan tampilan, periksa desktop dan ponsel serta alur publik dan
  admin yang bersangkutan.
- Jika aplikasi utama dan `SIAP_UPLOAD_SERVER_APLIKASI/` sama-sama digunakan,
  sinkronkan file yang memang menjadi bagian dari update dan verifikasi bahwa
  paket upload tidak tertinggal.

### Mengedit survei

Pada `Survei, Form & Shortcut`, setiap survei mempunyai tautan ke tiga bagian:
identitas/susunan survei, teks pertanyaan beserta opsi jawaban, dan nama form
beserta input responden. Pengaturan input yang tersedia meliputi tampil atau
sembunyi, wajib atau opsional, label, dan petunjuk. Pertanyaan dapat dipilih
atau dilepas dari survei tanpa membuat ulang pertanyaan.

Pertanyaan dapat dipakai bersama oleh beberapa survei. Mengubah teks, opsi,
skor, atau pengukuran pertanyaan akan mengubah definisi untuk semua survei yang
memakainya; respons lama tetap mempunyai snapshot teks dan label saat dikirim.
Saat menyimpan survei, tautan pertanyaan yang tidak berubah mempertahankan
urutan, bobot khusus, dan status wajibnya.

### Pemeriksaan dan batas test

Root berisi beberapa `test_*.php` untuk debug, login, dan sinkronisasi database.
File-file tersebut bukan test suite regresi otomatis; sebagian mengirim
permintaan ke server lokal, menggunakan cookie, atau memuat data uji. Tinjau
isi dan targetnya sebelum menjalankan, dan jangan arahkan ke produksi.

Setiap update minimal perlu diperiksa dengan lint PHP pada file yang berubah,
permintaan HTTP lokal untuk route terkait, dan skenario data yang terdampak.
Untuk alur submit, uji validasi gagal dan berhasil, serta pastikan respons
terlihat benar di detail, ringkasan, ekspor, dan API bila relevan. Belum ada
test suite otomatis aplikasi yang terkonfigurasi di repository ini.
