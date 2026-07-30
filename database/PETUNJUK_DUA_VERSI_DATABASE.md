# Paket database staging

Tersedia dua berkas SQL mandiri yang dapat diimpor melalui phpMyAdmin.

## Versi 1 — update database existing

Gunakan `VERSI_1_UPDATE_DATABASE_EXISTING.sql` untuk database server yang sudah
menyimpan data SKM. Paket ini menjadi pilihan utama untuk staging dan produksi.
Migration disusun agar dapat dijalankan ulang dan tidak berisi `DROP TABLE`,
`TRUNCATE`, atau penghapusan data SKM lama.

Sebelum mengimpor:

1. Buat backup database server.
2. Pilih database SKM yang benar di phpMyAdmin.
3. Impor berkas Versi 1.
4. Pastikan impor berakhir tanpa pesan error.

## Versi 2 — instalasi modul lengkap

Gunakan `VERSI_2_INSTALL_MODUL_LENGKAP.sql` bila database SKM lama sudah tersedia,
tetapi seluruh modul survei fleksibel belum pernah dipasang. Paket ini membuat
struktur, konfigurasi awal, survei bawaan, form, API builder, serta pemisahan
respons survei baru dari `skm_data_skm`.

Versi 2 bukan dump database SKM kosong. Tabel inti aplikasi SKM lama tetap harus
sudah tersedia karena aplikasi sengaja menggunakan database lama tanpa
menggantinya.

## Penyebab error `errno: 150`

Server staging mempunyai definisi `skm_cms_user.id` yang tidak identik dengan
`ipak_admin_roles.user_id`, atau tabel pengguna lama tidak menggunakan InnoDB.
MySQL mensyaratkan engine dan tipe kolom foreign key kompatibel secara persis.

Paket baru tidak lagi membuat foreign key dari tabel `ipak_*` ke tabel warisan
`skm_*`. Ini berlaku untuk pengguna, jawaban, hasil survei, dan field responden.
Kolom ID serta index tetap tersedia dan tetap dipakai aplikasi untuk
menghubungkan data.

Foreign key antar-tabel baru `ipak_*` tetap dipertahankan karena seluruh tipe
kolom dan engine tabel tersebut dikendalikan oleh modul survei. Dengan demikian,
integritas data modul baru tetap terjaga tanpa bergantung pada variasi struktur
database SKM lama di setiap server.
