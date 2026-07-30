# Urutan Deploy Aman untuk SKM Lama

Tujuan deploy ini adalah mempertahankan data dan grafik SKM lama, sambil
memungkinkan aplikasi survei fleksibel menyimpan survei baru pada tabel yang
sama.

## Sebelum deploy

1. Buat backup database `backoffice`.
2. Catat jumlah dan nilai rata-rata SKM lama:

   ```sql
   SELECT COUNT(*) AS jumlah_skm, ROUND(AVG(rata), 4) AS rata_skm
   FROM skm_data_skm
   WHERE flag_skm = 1;
   ```

## Urutan deploy

1. Terapkan migrasi `010_add_survey_type.sql`.
2. Terapkan migrasi `011_add_unique_survey_codes.sql`.
3. Terapkan migrasi `012_protect_legacy_skm.sql`.
4. Terapkan migrasi `013_api_builder.sql`. Migrasi ini hanya membuat tabel
   konfigurasi dan log API; tabel SKM lama tidak diubah.
5. Deploy perubahan aplikasi SKM lama pada:
   - `application/models/Skm_model.php`
   - `application/models/Survei_model.php`
6. Deploy aplikasi survei fleksibel `ipak_skm`.

Perubahan aplikasi SKM lama harus terpasang sebelum survei fleksibel dibuka
untuk umum. Penyaring `flag_skm = 1` mencegah respons survei baru masuk ke
grafik SKM lama.

## Pemeriksaan setelah deploy

```sql
SELECT COUNT(*) AS jumlah_skm, ROUND(AVG(rata), 4) AS rata_skm
FROM skm_data_skm
WHERE flag_skm = 1;

SELECT flag_skm, jenis_survei, COUNT(*) AS jumlah
FROM skm_data_skm
GROUP BY flag_skm, jenis_survei;

SELECT survey_code, storage_profile, survey_version, legacy_question_limit
FROM ipak_surveys
ORDER BY id;
```

Jumlah dan rata-rata SKM lama harus sama dengan hasil sebelum deploy. Profil
survei `SKM` harus bernilai `LEGACY_SKM`, versi `SKM-LEGACY-10`, dan batas
pertanyaan `10`.

## Perilaku setelah deploy

- Aplikasi SKM lama tetap menghasilkan satu baris `flag_skm = 1`.
- Survei baru menghasilkan baris `flag_skm = 0`.
- Form gabungan menghasilkan satu baris untuk setiap survei.
- Semua baris dari satu form gabungan memiliki `kode_pengisian` yang sama.
- Baris SKM menyimpan maksimal 10 nilai dan menambah `0` bila pertanyaannya
  kurang dari 10.
- Baris survei baru menyimpan seluruh pertanyaannya tanpa batas SKM legacy.
