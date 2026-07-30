# Database siap dimasukkan ke server

## Server sudah memiliki aplikasi dan data SKM

Impor:

`01_UPDATE_DATABASE_EXISTING.sql`

Ini adalah pilihan yang direkomendasikan untuk server staging dan produksi.
Data SKM lama tidak dihapus.

## Server memiliki database SKM lama, tetapi modul survei belum pernah dipasang

Impor:

`02_INSTALL_MODUL_LENGKAP.sql`

Database tujuan tetap harus mempunyai tabel inti aplikasi SKM lama.

## Urutan deployment

1. Backup database server.
2. Pilih database SKM yang benar pada phpMyAdmin.
3. Impor salah satu berkas SQL sesuai kondisi server.
4. Jangan mengimpor kedua berkas secara bersamaan.
5. Pastikan proses berakhir tanpa pesan error.

Paket sudah dibuat tanpa foreign key dari tabel baru `ipak_*` menuju tabel lama
`skm_*`, sehingga perbedaan engine dan tipe kolom database lama tidak
menggagalkan proses impor.

## Jika tabel pengguna tidak tersedia

Impor `03_CREATE_SKM_CMS_USER.sql` bila server menampilkan error bahwa tabel
`skm_cms_user` tidak ditemukan. File tersebut membuat tabel pengguna dan akun
superadmin awal tanpa menghapus pengguna yang sudah tersedia.

## Mengizinkan pertanyaan dipakai beberapa survei

Untuk server yang sudah memasang versi sebelumnya, impor
`04_ALLOW_SHARED_QUESTIONS.sql`. Migration ini hanya melepas unique index lama
pada `question_id`. Data survei dan pertanyaan yang sudah tersedia tidak dihapus.

## Menampilkan atau menyembunyikan form di front office

Untuk server yang hanya membutuhkan patch fitur terbaru, impor
`05_FORM_PUBLIC_VISIBILITY.sql`. Semua form lama tetap ditampilkan secara default.
Pengaturan ini hanya menyembunyikan kartu form dari dashboard dan katalog publik;
form aktif tetap dapat dibuka melalui URL shortcut resminya.
