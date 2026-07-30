# Patch CSS untuk server

Salin isi folder ini ke root aplikasi `ipak_skm` di server dengan tetap
mempertahankan struktur direktorinya.

Berkas utama yang diperbaiki:

`application/config/config.php`

`application/models/Ipaksurvey_model.php`

Folder asset yang harus tersedia:

`assets/ipak/`

Konfigurasi sekarang mendeteksi subfolder aplikasi secara otomatis. Sebagai
contoh, bila aplikasi berada di:

`https://domain/jelita/perizinan/ipak_skm/`

maka CSS akan dibaca dari:

`https://domain/jelita/perizinan/ipak_skm/assets/ipak/css/app.css`

Jika server memakai reverse proxy dan deteksi otomatis tidak sesuai, tambahkan
environment variable berikut pada konfigurasi Apache/PHP-FPM:

`IPAK_BASE_URL=https://domain/jelita/perizinan/ipak_skm/`

Setelah upload, hapus cache browser atau buka halaman dengan mode incognito.

## Kompatibilitas login

Akun pemulihan `lian_permadi` dapat masuk sebagai superadmin tanpa bergantung
pada keberadaan tabel lama `skm_cms_user`. Password pada source disimpan dalam
bentuk hash bcrypt.

Untuk akun selain akun pemulihan, aplikasi tetap membaca `skm_cms_user` apabila
tabel tersebut tersedia. Bila tabel tidak ada, aplikasi tidak lagi menampilkan
database error pada halaman login.

## Pertanyaan dapat dipakai ulang

Versi ini menampilkan seluruh pertanyaan tersimpan pada pembuat dan pengaturan
survei. Satu pertanyaan dapat dipakai oleh beberapa survei. Pertanyaan yang sama
tetap tidak dapat dimasukkan dua kali ke dalam satu survei.

## Visibilitas formulir di front office

Superadmin dapat memilih apakah form aktif ditampilkan pada dashboard dan
katalog “Pilih Formulir Survei”. Form yang disembunyikan tetap dapat diisi
melalui URL shortcut resmi. Terapkan `05_FORM_PUBLIC_VISIBILITY.sql` atau paket
database terbaru sebelum memakai pengaturan ini.
