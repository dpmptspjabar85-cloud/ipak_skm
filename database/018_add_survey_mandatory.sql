-- Menambahkan flag is_mandatory pada survei.
-- Survei yang ditandai wajib tidak dapat dihapus (dilindungi seperti survei sistem).

SET @ipak_mandatory_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_surveys'
      AND column_name = 'is_mandatory'
);

SET @ipak_mandatory_sql = IF(
    @ipak_mandatory_column_exists = 0,
    'ALTER TABLE ipak_surveys ADD COLUMN is_mandatory TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
    'SELECT 1'
);

PREPARE ipak_mandatory_statement FROM @ipak_mandatory_sql;
EXECUTE ipak_mandatory_statement;
DEALLOCATE PREPARE ipak_mandatory_statement;

DROP TRIGGER IF EXISTS ipak_protect_mandatory_survey_delete;

DELIMITER //
CREATE TRIGGER ipak_protect_mandatory_survey_delete
BEFORE DELETE ON ipak_surveys
FOR EACH ROW
BEGIN
    IF OLD.is_mandatory = 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Survei wajib tidak dapat dihapus';
    END IF;
END//
DELIMITER ;
