-- Memisahkan respons survei fleksibel dari tabel historis SKM.
-- Tabel skm_data_skm tidak diubah oleh migrasi ini.
-- Data SURVEY lama disalin, bukan dihapus, agar rollback tetap aman.

CREATE TABLE IF NOT EXISTS ipak_survey_responses (
    kode BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    legacy_skm_data_id INT(10) NULL,
    legacy_submission_survey_id BIGINT UNSIGNED NULL,
    migration_key VARCHAR(50) NULL,
    nib VARCHAR(20) NULL,
    resi VARCHAR(20) NULL,
    permohonan_id INT(10) NULL,
    nama_responden VARCHAR(100) NULL,
    status_responden INT(2) NOT NULL DEFAULT 1,
    responden VARCHAR(100) NULL,
    mobile VARCHAR(25) NULL,
    gender INT(3) NULL,
    usia INT(3) NULL,
    pekerjaan_id INT(4) NULL,
    pendidikan_id INT(4) NULL,
    sektor INT(4) NULL,
    jenis_ijin INT(4) NULL,
    tgl_pengisian DATE NULL,
    data_skm_id TEXT NULL,
    data_skm_nilai TEXT NULL,
    total DOUBLE NULL,
    rata DOUBLE NULL,
    saran TEXT NULL,
    keterangan TEXT NULL,
    tgl_buat DATETIME NULL,
    flag_skm INT(1) NOT NULL DEFAULT 0,
    jenis_survei ENUM('SURVEY') NOT NULL DEFAULT 'SURVEY',
    kode_survei_unik CHAR(36) NULL,
    kode_pengisian CHAR(36) NULL,
    versi_survei VARCHAR(30) NULL,
    is_legacy_skm TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (kode),
    UNIQUE KEY uq_ipak_survey_response_migration (migration_key),
    KEY idx_ipak_survey_response_legacy (legacy_skm_data_id),
    KEY idx_ipak_survey_response_reference (resi),
    KEY idx_ipak_survey_response_group (kode_pengisian),
    KEY idx_ipak_survey_response_date (tgl_pengisian),
    KEY idx_ipak_survey_response_unique_code (kode_survei_unik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

ALTER TABLE ipak_survey_responses
    ADD COLUMN IF NOT EXISTS legacy_submission_survey_id BIGINT UNSIGNED NULL AFTER legacy_skm_data_id,
    ADD COLUMN IF NOT EXISTS migration_key VARCHAR(50) NULL AFTER legacy_submission_survey_id,
    DROP INDEX IF EXISTS uq_ipak_survey_response_legacy,
    DROP INDEX IF EXISTS uq_ipak_survey_response_reference;

CREATE UNIQUE INDEX IF NOT EXISTS uq_ipak_survey_response_migration
    ON ipak_survey_responses (migration_key);

CREATE INDEX IF NOT EXISTS idx_ipak_survey_response_legacy
    ON ipak_survey_responses (legacy_skm_data_id);

CREATE INDEX IF NOT EXISTS idx_ipak_survey_response_reference
    ON ipak_survey_responses (resi);

ALTER TABLE ipak_response_answers
    MODIFY COLUMN skm_data_id INT(10) NULL,
    ADD COLUMN IF NOT EXISTS flex_response_id BIGINT UNSIGNED NULL AFTER skm_data_id;

ALTER TABLE ipak_response_fields
    MODIFY COLUMN skm_data_id INT(10) NULL,
    ADD COLUMN IF NOT EXISTS flex_response_id BIGINT UNSIGNED NULL AFTER skm_data_id;

ALTER TABLE ipak_submission_surveys
    MODIFY COLUMN skm_data_id INT(10) NULL,
    ADD COLUMN IF NOT EXISTS flex_response_id BIGINT UNSIGNED NULL AFTER skm_data_id;

CREATE UNIQUE INDEX IF NOT EXISTS uq_ipak_flex_response_question
    ON ipak_response_answers (flex_response_id, question_id);

CREATE INDEX IF NOT EXISTS idx_ipak_answer_flex_response
    ON ipak_response_answers (flex_response_id);

CREATE UNIQUE INDEX IF NOT EXISTS uq_ipak_flex_response_field
    ON ipak_response_fields (flex_response_id, field_key);

CREATE INDEX IF NOT EXISTS idx_ipak_field_flex_response
    ON ipak_response_fields (flex_response_id);

CREATE UNIQUE INDEX IF NOT EXISTS uq_ipak_flex_submission_survey
    ON ipak_submission_surveys (flex_response_id, survey_id);

CREATE INDEX IF NOT EXISTS idx_ipak_result_flex_response
    ON ipak_submission_surveys (flex_response_id);

SET @ipak_answer_flex_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'ipak_response_answers'
      AND constraint_name = 'fk_ipak_answer_flex_response'
);
SET @ipak_answer_flex_fk_sql = IF(
    @ipak_answer_flex_fk_exists = 0,
    'ALTER TABLE ipak_response_answers ADD CONSTRAINT fk_ipak_answer_flex_response FOREIGN KEY (flex_response_id) REFERENCES ipak_survey_responses (kode) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE ipak_answer_flex_fk_statement FROM @ipak_answer_flex_fk_sql;
EXECUTE ipak_answer_flex_fk_statement;
DEALLOCATE PREPARE ipak_answer_flex_fk_statement;

SET @ipak_field_flex_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'ipak_response_fields'
      AND constraint_name = 'fk_ipak_field_flex_response'
);
SET @ipak_field_flex_fk_sql = IF(
    @ipak_field_flex_fk_exists = 0,
    'ALTER TABLE ipak_response_fields ADD CONSTRAINT fk_ipak_field_flex_response FOREIGN KEY (flex_response_id) REFERENCES ipak_survey_responses (kode) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE ipak_field_flex_fk_statement FROM @ipak_field_flex_fk_sql;
EXECUTE ipak_field_flex_fk_statement;
DEALLOCATE PREPARE ipak_field_flex_fk_statement;

SET @ipak_result_flex_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'ipak_submission_surveys'
      AND constraint_name = 'fk_ipak_result_flex_response'
);
SET @ipak_result_flex_fk_sql = IF(
    @ipak_result_flex_fk_exists = 0,
    'ALTER TABLE ipak_submission_surveys ADD CONSTRAINT fk_ipak_result_flex_response FOREIGN KEY (flex_response_id) REFERENCES ipak_survey_responses (kode) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE ipak_result_flex_fk_statement FROM @ipak_result_flex_fk_sql;
EXECUTE ipak_result_flex_fk_statement;
DEALLOCATE PREPARE ipak_result_flex_fk_statement;

UPDATE ipak_survey_responses r
INNER JOIN ipak_submission_surveys sr
    ON sr.flex_response_id = r.kode
SET r.legacy_submission_survey_id = sr.id,
    r.migration_key = CONCAT('RESULT-', sr.id)
WHERE r.legacy_skm_data_id IS NOT NULL;

INSERT INTO ipak_survey_responses (
    legacy_skm_data_id,
    legacy_submission_survey_id,
    migration_key,
    nib,
    resi,
    permohonan_id,
    nama_responden,
    status_responden,
    responden,
    mobile,
    gender,
    usia,
    pekerjaan_id,
    pendidikan_id,
    sektor,
    jenis_ijin,
    tgl_pengisian,
    data_skm_id,
    data_skm_nilai,
    total,
    rata,
    saran,
    keterangan,
    tgl_buat,
    flag_skm,
    jenis_survei,
    kode_survei_unik,
    kode_pengisian,
    versi_survei,
    is_legacy_skm
)
SELECT
    d.kode,
    sr.id,
    CONCAT('RESULT-', sr.id),
    d.nib,
    d.resi,
    d.permohonan_id,
    d.nama_responden,
    d.status_responden,
    d.responden,
    d.mobile,
    d.gender,
    d.usia,
    d.pekerjaan_id,
    d.pendidikan_id,
    d.sektor,
    d.jenis_ijin,
    d.tgl_pengisian,
    d.data_skm_id,
    d.data_skm_nilai,
    d.total,
    sr.score,
    d.saran,
    d.keterangan,
    d.tgl_buat,
    0,
    'SURVEY',
    COALESCE(
        NULLIF(CONVERT(sr.kode_survei_unik USING utf8), _utf8''),
        CONVERT(d.kode_survei_unik USING utf8)
    ),
    COALESCE(
        NULLIF(CONVERT(d.kode_pengisian USING utf8), _utf8''),
        LOWER(CONCAT(
            SUBSTRING(MD5(CONCAT('legacy-', d.kode)), 1, 8), '-',
            SUBSTRING(MD5(CONCAT('legacy-', d.kode)), 9, 4), '-4',
            SUBSTRING(MD5(CONCAT('legacy-', d.kode)), 14, 3), '-a',
            SUBSTRING(MD5(CONCAT('legacy-', d.kode)), 18, 3), '-',
            SUBSTRING(MD5(CONCAT('legacy-', d.kode)), 21, 12)
        ))
    ),
    COALESCE(
        NULLIF(CONVERT(d.versi_survei USING utf8), _utf8''),
        NULLIF(CONVERT(s.survey_version USING utf8), _utf8''),
        _utf8'1'
    ),
    0
FROM ipak_submission_surveys sr
INNER JOIN ipak_surveys s
    ON s.id = sr.survey_id
   AND s.storage_profile <> 'LEGACY_SKM'
INNER JOIN skm_data_skm d
    ON d.kode = sr.skm_data_id
WHERE sr.flex_response_id IS NULL
ON DUPLICATE KEY UPDATE
    resi = VALUES(resi),
    legacy_skm_data_id = VALUES(legacy_skm_data_id),
    legacy_submission_survey_id = VALUES(legacy_submission_survey_id),
    kode_pengisian = VALUES(kode_pengisian),
    kode_survei_unik = VALUES(kode_survei_unik),
    versi_survei = VALUES(versi_survei);

UPDATE ipak_submission_surveys sr
INNER JOIN ipak_survey_responses r
    ON r.legacy_submission_survey_id = sr.id
SET sr.flex_response_id = r.kode
WHERE sr.flex_response_id IS NULL;

INSERT INTO ipak_survey_responses (
    legacy_skm_data_id,
    migration_key,
    nib,
    resi,
    permohonan_id,
    nama_responden,
    status_responden,
    responden,
    mobile,
    gender,
    usia,
    pekerjaan_id,
    pendidikan_id,
    sektor,
    jenis_ijin,
    tgl_pengisian,
    data_skm_id,
    data_skm_nilai,
    total,
    rata,
    saran,
    keterangan,
    tgl_buat,
    flag_skm,
    jenis_survei,
    kode_survei_unik,
    kode_pengisian,
    versi_survei,
    is_legacy_skm
)
SELECT
    d.kode,
    CONCAT('ORPHAN-', d.kode),
    d.nib,
    d.resi,
    d.permohonan_id,
    d.nama_responden,
    d.status_responden,
    d.responden,
    d.mobile,
    d.gender,
    d.usia,
    d.pekerjaan_id,
    d.pendidikan_id,
    d.sektor,
    d.jenis_ijin,
    d.tgl_pengisian,
    d.data_skm_id,
    d.data_skm_nilai,
    d.total,
    d.rata,
    d.saran,
    d.keterangan,
    d.tgl_buat,
    0,
    'SURVEY',
    d.kode_survei_unik,
    d.kode_pengisian,
    COALESCE(NULLIF(CONVERT(d.versi_survei USING utf8), _utf8''), _utf8'1'),
    0
FROM skm_data_skm d
WHERE d.flag_skm = 0
  AND d.jenis_survei = 'SURVEY'
  AND NOT EXISTS (
      SELECT 1
      FROM ipak_submission_surveys sr
      INNER JOIN ipak_surveys s ON s.id = sr.survey_id
      WHERE sr.skm_data_id = d.kode
        AND s.storage_profile <> 'LEGACY_SKM'
  )
ON DUPLICATE KEY UPDATE
    resi = VALUES(resi),
    kode_pengisian = VALUES(kode_pengisian),
    kode_survei_unik = VALUES(kode_survei_unik),
    versi_survei = VALUES(versi_survei);

UPDATE ipak_response_answers a
INNER JOIN ipak_submission_survey_answers rsa
    ON rsa.response_answer_id = a.id
INNER JOIN ipak_submission_surveys sr
    ON sr.id = rsa.survey_result_id
SET a.flex_response_id = sr.flex_response_id
WHERE sr.flex_response_id IS NOT NULL
  AND a.flex_response_id IS NULL;

INSERT IGNORE INTO ipak_response_fields (
    skm_data_id,
    flex_response_id,
    field_key,
    field_label_snapshot,
    field_group,
    field_value,
    created_at
)
SELECT
    NULL,
    r.kode,
    f.field_key,
    f.field_label_snapshot,
    f.field_group,
    f.field_value,
    f.created_at
FROM ipak_survey_responses r
INNER JOIN ipak_response_fields f
    ON f.skm_data_id = r.legacy_skm_data_id
WHERE r.legacy_skm_data_id IS NOT NULL;

UPDATE ipak_submission_surveys s
INNER JOIN ipak_survey_responses r
    ON r.legacy_submission_survey_id = s.id
SET s.flex_response_id = r.kode
WHERE s.flex_response_id IS NULL;

CREATE OR REPLACE VIEW ipak_all_responses AS
SELECT
    'SKM' AS response_source,
    d.kode,
    d.nib,
    d.resi,
    d.permohonan_id,
    d.nama_responden,
    d.status_responden,
    d.responden,
    d.mobile,
    d.gender,
    d.usia,
    d.pekerjaan_id,
    d.pendidikan_id,
    d.sektor,
    d.jenis_ijin,
    d.tgl_pengisian,
    d.data_skm_id,
    d.data_skm_nilai,
    d.total,
    d.rata,
    d.saran,
    d.keterangan,
    d.tgl_buat,
    d.flag_skm,
    d.jenis_survei,
    d.kode_survei_unik,
    d.kode_pengisian,
    d.versi_survei,
    d.is_legacy_skm
FROM skm_data_skm d
WHERE d.flag_skm = 1

UNION ALL

SELECT
    'SURVEY' AS response_source,
    r.kode,
    r.nib,
    r.resi,
    r.permohonan_id,
    r.nama_responden,
    r.status_responden,
    r.responden,
    r.mobile,
    r.gender,
    r.usia,
    r.pekerjaan_id,
    r.pendidikan_id,
    r.sektor,
    r.jenis_ijin,
    r.tgl_pengisian,
    r.data_skm_id,
    r.data_skm_nilai,
    r.total,
    r.rata,
    r.saran,
    r.keterangan,
    r.tgl_buat,
    r.flag_skm,
    r.jenis_survei,
    r.kode_survei_unik,
    r.kode_pengisian,
    r.versi_survei,
    r.is_legacy_skm
FROM ipak_survey_responses r;
