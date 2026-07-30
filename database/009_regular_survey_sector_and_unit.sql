-- Menyamakan sumber sektor dan perangkat daerah untuk survei biasa.
-- Tidak mengubah struktur tabel.

UPDATE ipak_form_fields
SET
    field_label = 'Sektor',
    help_text = 'Pilih sektor layanan yang sedang dinilai.'
WHERE field_key = 'service'
  AND field_label = 'Jenis layanan';

UPDATE skm_data_skm
SET
    keterangan = JSON_SET(
        IF(JSON_VALID(keterangan), keterangan, '{}'),
        '$.unit_id',
        COALESCE(
            (
                SELECT id
                FROM trunitkerja
                WHERE n_unitkerja = 'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT'
                LIMIT 1
            ),
            1
        ),
        '$.unit_name',
        'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT',
        '$.sector_source',
        'legacy_service',
        '$.legacy_service_name',
        CASE sektor
            WHEN 1 THEN 'Pelayanan Perizinan'
            WHEN 2 THEN 'Pelayanan Pengawasan'
            WHEN 3 THEN 'Pelayanan Pembinaan'
            WHEN 4 THEN 'Pelayanan Penyelesaian Permasalahan'
            WHEN 5 THEN 'Pelayanan Lainnya'
            ELSE NULL
        END
    ),
    sektor = 0
WHERE jenis_ijin = 0
  AND data_skm_id LIKE 'FLEX:%'
  AND JSON_EXTRACT(
      IF(JSON_VALID(keterangan), keterangan, '{}'),
      '$.sector_source'
  ) IS NULL;
