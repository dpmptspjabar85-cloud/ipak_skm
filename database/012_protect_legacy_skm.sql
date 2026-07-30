-- Perlindungan SKM lama dan pemisahan hasil formulir gabungan.
-- Kolom baru memiliki nilai default yang aman untuk aplikasi SKM lama:
-- aplikasi lama tetap menulis flag_skm=1 dan dianggap SKM legacy.

ALTER TABLE ipak_surveys
    ADD COLUMN IF NOT EXISTS storage_profile VARCHAR(20) NOT NULL DEFAULT 'FLEX' AFTER kode_unik,
    ADD COLUMN IF NOT EXISTS survey_version VARCHAR(30) NOT NULL DEFAULT '1' AFTER storage_profile,
    ADD COLUMN IF NOT EXISTS legacy_question_limit SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER survey_version;

UPDATE ipak_surveys
SET storage_profile = 'LEGACY_SKM',
    survey_version = 'SKM-LEGACY-10',
    legacy_question_limit = 10
WHERE UPPER(survey_code) = 'SKM';

UPDATE ipak_surveys
SET storage_profile = 'FLEX',
    legacy_question_limit = 0
WHERE UPPER(survey_code) <> 'SKM'
  AND storage_profile <> 'FLEX';

ALTER TABLE skm_data_skm
    ADD COLUMN IF NOT EXISTS kode_pengisian CHAR(36) NULL AFTER kode_survei_unik,
    ADD COLUMN IF NOT EXISTS versi_survei VARCHAR(30) NULL AFTER kode_pengisian,
    ADD COLUMN IF NOT EXISTS is_legacy_skm TINYINT(1) NOT NULL DEFAULT 1 AFTER versi_survei;

CREATE INDEX IF NOT EXISTS idx_skm_kode_pengisian
    ON skm_data_skm (kode_pengisian);

CREATE INDEX IF NOT EXISTS idx_skm_legacy_dashboard
    ON skm_data_skm (flag_skm, tgl_pengisian);

-- Respons fleksibel yang pernah dibuat oleh aplikasi gabungan tidak boleh
-- ikut dihitung oleh grafik aplikasi SKM lama.
UPDATE skm_data_skm
SET flag_skm = 0,
    is_legacy_skm = 0,
    versi_survei = COALESCE(NULLIF(versi_survei, ''), '1'),
    kode_pengisian = COALESCE(NULLIF(kode_pengisian, ''), LOWER(UUID()))
WHERE jenis_survei = 'SURVEY';

-- Semua data lama tetap diperlakukan sebagai SKM legacy. Nilai dan jawaban
-- historisnya tidak ditulis ulang oleh migrasi ini.
UPDATE skm_data_skm
SET is_legacy_skm = 1,
    versi_survei = COALESCE(NULLIF(versi_survei, ''), 'SKM-LEGACY-10')
WHERE flag_skm = 1
  AND jenis_survei = 'SKM';
