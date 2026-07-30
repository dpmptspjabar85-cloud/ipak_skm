-- Membedakan data SKM lama dengan respons survei fleksibel biasa.
ALTER TABLE skm_data_skm
    ADD COLUMN IF NOT EXISTS jenis_survei ENUM('SKM', 'SURVEY')
    NOT NULL DEFAULT 'SKM'
    AFTER flag_skm;

-- Data aplikasi sebelumnya tetap SKM. Hanya respons fleksibel tanpa SKM
-- yang diklasifikasikan sebagai SURVEY.
UPDATE skm_data_skm d
SET d.jenis_survei = 'SURVEY'
WHERE d.data_skm_id LIKE 'FLEX:%'
  AND NOT EXISTS (
      SELECT 1
      FROM ipak_submission_surveys r
      INNER JOIN ipak_surveys s ON s.id = r.survey_id
      WHERE r.skm_data_id = d.kode
        AND UPPER(s.survey_code) = 'SKM'
  );

UPDATE skm_data_skm d
SET d.jenis_survei = 'SKM'
WHERE d.data_skm_id NOT LIKE 'FLEX:%'
   OR EXISTS (
      SELECT 1
      FROM ipak_submission_surveys r
      INNER JOIN ipak_surveys s ON s.id = r.survey_id
      WHERE r.skm_data_id = d.kode
        AND UPPER(s.survey_code) = 'SKM'
   );
