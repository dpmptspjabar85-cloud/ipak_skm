-- Kode unik permanen untuk membedakan setiap survei.
-- survey_code tetap menjadi kode singkat yang mudah dibaca,
-- sedangkan kode_unik (UUID) menjadi identitas teknis yang tidak boleh sama.

ALTER TABLE ipak_surveys
    ADD COLUMN IF NOT EXISTS kode_unik CHAR(36) NULL AFTER survey_code;

UPDATE ipak_surveys
SET kode_unik = LOWER(UUID())
WHERE kode_unik IS NULL OR kode_unik = '';

ALTER TABLE ipak_surveys
    MODIFY kode_unik CHAR(36) NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_ipak_survey_kode_unik
    ON ipak_surveys (kode_unik);

-- Simpan salinan UUID survei pada hasil agar riwayat tetap jelas
-- walaupun nama atau kode singkat survei berubah.
ALTER TABLE ipak_submission_surveys
    ADD COLUMN IF NOT EXISTS kode_survei_unik CHAR(36) NULL AFTER survey_id;

UPDATE ipak_submission_surveys AS hasil
INNER JOIN ipak_surveys AS survei ON survei.id = hasil.survey_id
SET hasil.kode_survei_unik = survei.kode_unik
WHERE hasil.kode_survei_unik IS NULL OR hasil.kode_survei_unik = '';

ALTER TABLE ipak_submission_surveys
    MODIFY kode_survei_unik CHAR(36) NOT NULL;

CREATE INDEX IF NOT EXISTS idx_ipak_result_kode_survei_unik
    ON ipak_submission_surveys (kode_survei_unik);

-- Satu formulir dapat berisi beberapa survei. Karena itu kolom ini
-- menyimpan daftar UUID survei yang terisi, dipisahkan dengan koma.
ALTER TABLE skm_data_skm
    ADD COLUMN IF NOT EXISTS kode_survei_unik VARCHAR(1000) NULL AFTER jenis_survei;

-- Data lama dari aplikasi sebelumnya selalu dipetakan ke UUID survei SKM.
UPDATE skm_data_skm AS data_skm
SET data_skm.kode_survei_unik = (
    SELECT survei.kode_unik
    FROM ipak_surveys AS survei
    WHERE UPPER(survei.survey_code) = 'SKM'
    LIMIT 1
)
WHERE data_skm.data_skm_id NOT LIKE 'FLEX:%'
  AND (data_skm.kode_survei_unik IS NULL OR data_skm.kode_survei_unik = '');

-- Data formulir fleksibel mengikuti UUID setiap survei yang benar-benar terisi.
UPDATE skm_data_skm AS data_skm
SET data_skm.kode_survei_unik = (
    SELECT GROUP_CONCAT(hasil.kode_survei_unik ORDER BY hasil.survey_id SEPARATOR ',')
    FROM ipak_submission_surveys AS hasil
    WHERE hasil.skm_data_id = data_skm.kode
)
WHERE data_skm.data_skm_id LIKE 'FLEX:%'
  AND (data_skm.kode_survei_unik IS NULL OR data_skm.kode_survei_unik = '');
