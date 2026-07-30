-- Pengaturan apakah form aktif ditampilkan pada dashboard dan katalog publik.
-- Default 1 menjaga semua form lama tetap tampil setelah migration dijalankan.

SET @ipak_form_public_visibility_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_forms'
      AND column_name = 'is_public_listed'
);

SET @ipak_form_public_visibility_sql = IF(
    @ipak_form_public_visibility_exists = 0,
    'ALTER TABLE ipak_forms ADD COLUMN is_public_listed TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active',
    'SELECT 1'
);

PREPARE ipak_form_public_visibility_statement FROM @ipak_form_public_visibility_sql;
EXECUTE ipak_form_public_visibility_statement;
DEALLOCATE PREPARE ipak_form_public_visibility_statement;
