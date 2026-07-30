-- Mendukung wizard pembuatan form tiga langkah:
-- 1) input awal wajib, 2) identitas fleksibel, 3) pertanyaan.

SET @ipak_field_group_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_form_fields'
      AND column_name = 'field_group'
);
SET @ipak_field_group_sql = IF(
    @ipak_field_group_exists = 0,
    'ALTER TABLE ipak_form_fields ADD COLUMN field_group ENUM(''access'',''identity'') NOT NULL DEFAULT ''identity'' AFTER field_key',
    'SELECT 1'
);
PREPARE ipak_field_group_statement FROM @ipak_field_group_sql;
EXECUTE ipak_field_group_statement;
DEALLOCATE PREPARE ipak_field_group_statement;

SET @ipak_field_type_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_form_fields'
      AND column_name = 'field_type'
);
SET @ipak_field_type_sql = IF(
    @ipak_field_type_exists = 0,
    'ALTER TABLE ipak_form_fields ADD COLUMN field_type VARCHAR(20) NOT NULL DEFAULT ''text'' AFTER field_group',
    'SELECT 1'
);
PREPARE ipak_field_type_statement FROM @ipak_field_type_sql;
EXECUTE ipak_field_type_statement;
DEALLOCATE PREPARE ipak_field_type_statement;

SET @ipak_field_options_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_form_fields'
      AND column_name = 'field_options'
);
SET @ipak_field_options_sql = IF(
    @ipak_field_options_exists = 0,
    'ALTER TABLE ipak_form_fields ADD COLUMN field_options TEXT NULL AFTER field_type',
    'SELECT 1'
);
PREPARE ipak_field_options_statement FROM @ipak_field_options_sql;
EXECUTE ipak_field_options_statement;
DEALLOCATE PREPARE ipak_field_options_statement;

SET @ipak_field_system_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_form_fields'
      AND column_name = 'is_system'
);
SET @ipak_field_system_sql = IF(
    @ipak_field_system_exists = 0,
    'ALTER TABLE ipak_form_fields ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order',
    'SELECT 1'
);
PREPARE ipak_field_system_statement FROM @ipak_field_system_sql;
EXECUTE ipak_field_system_statement;
DEALLOCATE PREPARE ipak_field_system_statement;

UPDATE ipak_form_fields
SET field_group = CASE
        WHEN field_key IN ('email', 'phone', 'identity_number') THEN 'access'
        ELSE 'identity'
    END,
    field_type = CASE
        WHEN field_key = 'email' THEN 'email'
        WHEN field_key = 'phone' THEN 'tel'
        WHEN field_key = 'age' THEN 'number'
        WHEN field_key IN ('gender', 'education', 'job', 'service') THEN 'select'
        ELSE 'text'
    END,
    is_system = 1
WHERE is_system = 1;

INSERT IGNORE INTO ipak_form_fields
    (form_id, field_key, field_group, field_type, field_options, field_label, field_mode, help_text, sort_order, is_system)
SELECT
    id,
    'address',
    'identity',
    'textarea',
    NULL,
    'Alamat',
    'hidden',
    'Isi alamat sesuai kebutuhan survei.',
    45,
    1
FROM ipak_forms;

CREATE TABLE IF NOT EXISTS ipak_response_fields (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    skm_data_id INT(10) NOT NULL,
    field_key VARCHAR(30) NOT NULL,
    field_label_snapshot VARCHAR(100) NOT NULL,
    field_group ENUM('access', 'identity') NOT NULL DEFAULT 'identity',
    field_value TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_response_field (skm_data_id, field_key),
<<<<<<< HEAD
    KEY idx_ipak_response_field_key (field_key),
    CONSTRAINT fk_ipak_response_field_submission
        FOREIGN KEY (skm_data_id) REFERENCES skm_data_skm (kode)
        ON UPDATE CASCADE ON DELETE CASCADE
=======
    KEY idx_ipak_response_field_key (field_key)
>>>>>>> 563b877ee5432943018f22402774054db6dabfa4
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
