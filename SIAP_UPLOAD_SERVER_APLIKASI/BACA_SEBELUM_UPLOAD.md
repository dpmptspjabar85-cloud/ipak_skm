# Patch CSS untuk server

Salin isi folder ini ke root aplikasi `ipak_skm` di server dengan tetap
mempertahankan struktur direktorinya.

Berkas utama yang diperbaiki:

`application/config/config.php`

`application/models/Ipaksurvey_model.php`

Folder asset yang harus tersedia:

`assets/ipak/`

## Pencarian pada pilihan (select option)

Fitur pencarian pada field bertipe `select` (Pendidikan, Pekerjaan, Sektor,
dan field KBLI di langkah identitas) gagal di server karena
`assets/ipak/js/survey.js` pada paket upload ini masih versi lama tanpa blok
`makeSearchable`, dan `assets/ipak/css/app.css` belum memuat aturan
`.searchable-select`.

Berkas yang wajib ikut terupload:

`assets/ipak/js/survey.js`

`assets/ipak/css/app.css`

## Pencarian KBLI dilakukan di sisi server

Langkah identitas memuat pilihan KBLI. Sebelumnya seluruh tabel `ipak_kbli`
(dimiliki ribuan baris) dirender ke setiap halaman survei, sehingga dropdown
menjadi sangat berat danpora tidak bisa dicari di server.

Sekarang hanya 200 pilihan pertama yang dirender. Sisanya diambil lewat
pencarian ke endpoint baru berikut:

`GET survey/kbli_options?field=<field>&q=<kata>&selected=<nilai>&form=<kode_form>`

Endpoint memakai konfigurasi kolom yang tersimpan pada field tersebut, sehingga
label yang tampil sama dengan sebelumnya. Endpoint menolak field yang
`source`-nya bukan `ipak_kbli` atau yang disembunyikan pengelola.

Agar fitur ini aktif, berkas berikut harus terupload:

`application/controllers/Survey.php`

`application/views/public/_respondent_fields.php`

`application/models/Ipaksurvey_model.php`

`application/config/ipak.php`

## Asset tidak lagi tersimpan di cache browser

`application/views/public/survey.php` kini memuat asset lewat helper
`ipak_asset()`, yang menambahkan query `?v=<filemtime>` pada URL CSS dan JS.
Tujuannya agar browser dan reverse proxy tidak lagi menyajikan `survey.js`
versi lama setelah upload. Agar helper aktif, dua berkas berikut juga harus
terupload:

`application/helpers/ipak_asset_helper.php`

`application/config/autoload.php`

Bila `application/config/autoload.php` di server pernah diubah manual, gabungkan
hanya baris pada `$autoload['helper']`:

`'url', 'form', 'security', 'ipak_asset'`

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
