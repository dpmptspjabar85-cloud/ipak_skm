-- Pengaturan data responden per form dan perlindungan survei sistem SKM.
-- Tabel respons utama tidak diubah; konfigurasi baru disimpan terpisah.

SET @ipak_survey_lock_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_surveys'
      AND column_name = 'is_system_locked'
);

SET @ipak_survey_lock_sql = IF(
    @ipak_survey_lock_column_exists = 0,
    'ALTER TABLE ipak_surveys ADD COLUMN is_system_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
    'SELECT 1'
);

PREPARE ipak_survey_lock_statement FROM @ipak_survey_lock_sql;
EXECUTE ipak_survey_lock_statement;
DEALLOCATE PREPARE ipak_survey_lock_statement;

UPDATE ipak_surveys
SET is_active = 1,
    is_system_locked = 1
WHERE UPPER(survey_code) = 'SKM';

CREATE TABLE IF NOT EXISTS ipak_form_fields (
    form_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(30) NOT NULL,
    field_label VARCHAR(100) NOT NULL,
    field_mode ENUM('hidden', 'optional', 'required') NOT NULL DEFAULT 'hidden',
    help_text VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (form_id, field_key),
    KEY idx_ipak_form_field_mode (field_key, field_mode),
    CONSTRAINT fk_ipak_form_field_form
        FOREIGN KEY (form_id) REFERENCES ipak_forms (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT IGNORE INTO ipak_form_fields
    (form_id, field_key, field_label, field_mode, help_text, sort_order)
SELECT
    f.id,
    fields.field_key,
    fields.field_label,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM ipak_form_surveys skm_fs
            INNER JOIN ipak_surveys skm_s ON skm_s.id = skm_fs.survey_id
            WHERE skm_fs.form_id = f.id
              AND UPPER(skm_s.survey_code) = 'SKM'
        ) THEN
            CASE
                WHEN fields.field_key IN ('name', 'email', 'phone', 'age', 'gender', 'education', 'job') THEN 'required'
                WHEN fields.field_key = 'identity_number' THEN 'optional'
                ELSE 'hidden'
            END
        WHEN EXISTS (
            SELECT 1
            FROM ipak_form_surveys nib_fs
            INNER JOIN ipak_surveys nib_s ON nib_s.id = nib_fs.survey_id
            WHERE nib_fs.form_id = f.id
              AND UPPER(nib_s.survey_code) = 'NIB'
        ) THEN
            CASE
                WHEN fields.field_key = 'identity_number' THEN 'required'
                ELSE 'hidden'
            END
        ELSE
            CASE
                WHEN fields.field_key = 'email' THEN 'required'
                ELSE 'hidden'
            END
    END,
    fields.help_text,
    fields.sort_order
FROM ipak_forms f
CROSS JOIN (
    SELECT 'name' AS field_key, 'Nama lengkap / nama perusahaan' AS field_label, 'Isi nama responden atau nama perusahaan.' AS help_text, 10 AS sort_order
    UNION ALL SELECT 'email', 'Email', 'Gunakan alamat email yang aktif, misalnya nama@email.com.', 20
    UNION ALL SELECT 'phone', 'Nomor telepon', 'Gunakan nomor telepon aktif, misalnya 081234567890.', 30
    UNION ALL SELECT 'identity_number', 'Nomor identitas / nomor induk', 'Dapat digunakan untuk NIK, NIB, nomor siswa, atau nomor identitas lain.', 40
    UNION ALL SELECT 'age', 'Usia', 'Isi usia saat ini dalam angka.', 50
    UNION ALL SELECT 'gender', 'Jenis kelamin', 'Pilih satu pilihan untuk pengelompokan statistik.', 60
    UNION ALL SELECT 'education', 'Pendidikan terakhir', 'Pilih jenjang pendidikan terakhir yang telah diselesaikan.', 70
    UNION ALL SELECT 'job', 'Pekerjaan', 'Pilih pekerjaan utama responden.', 80
    UNION ALL SELECT 'service', 'Jenis layanan', 'Pilih layanan yang sedang dinilai.', 90
) fields;

DROP TRIGGER IF EXISTS ipak_protect_locked_survey_update;
DROP TRIGGER IF EXISTS ipak_protect_locked_survey_delete;

DELIMITER //
CREATE TRIGGER ipak_protect_locked_survey_update
BEFORE UPDATE ON ipak_surveys
FOR EACH ROW
BEGIN
    IF OLD.is_system_locked = 1 THEN
        SET NEW.survey_code = 'SKM';
        SET NEW.is_active = 1;
        SET NEW.is_system_locked = 1;
    END IF;
END//

CREATE TRIGGER ipak_protect_locked_survey_delete
BEFORE DELETE ON ipak_surveys
FOR EACH ROW
BEGIN
    IF OLD.is_system_locked = 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Survei SKM adalah survei sistem dan tidak dapat dihapus';
    END IF;
END//
DELIMITER ;
