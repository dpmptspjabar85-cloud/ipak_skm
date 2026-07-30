-- Kompatibilitas database SKM lama.
--
-- Tabel warisan dapat mempunyai engine, signed/unsigned, atau ukuran kolom yang
-- berbeda antar-server. Aplikasi tetap menghubungkan data melalui kolom ID,
-- tetapi tidak memaksa foreign key ke tabel warisan tersebut.
--
-- Foreign key antar-tabel ipak_* tetap digunakan.

SET @ipak_drop_legacy_fk_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.referential_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'ipak_admin_roles'
          AND constraint_name = 'fk_ipak_admin_role_user'
    ),
    'ALTER TABLE ipak_admin_roles DROP FOREIGN KEY fk_ipak_admin_role_user',
    'SELECT 1'
);
PREPARE ipak_drop_legacy_fk_statement FROM @ipak_drop_legacy_fk_sql;
EXECUTE ipak_drop_legacy_fk_statement;
DEALLOCATE PREPARE ipak_drop_legacy_fk_statement;

SET @ipak_drop_legacy_fk_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.referential_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'ipak_response_answers'
          AND constraint_name = 'fk_ipak_response_skm'
    ),
    'ALTER TABLE ipak_response_answers DROP FOREIGN KEY fk_ipak_response_skm',
    'SELECT 1'
);
PREPARE ipak_drop_legacy_fk_statement FROM @ipak_drop_legacy_fk_sql;
EXECUTE ipak_drop_legacy_fk_statement;
DEALLOCATE PREPARE ipak_drop_legacy_fk_statement;

SET @ipak_drop_legacy_fk_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.referential_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'ipak_submission_surveys'
          AND constraint_name = 'fk_ipak_result_skm'
    ),
    'ALTER TABLE ipak_submission_surveys DROP FOREIGN KEY fk_ipak_result_skm',
    'SELECT 1'
);
PREPARE ipak_drop_legacy_fk_statement FROM @ipak_drop_legacy_fk_sql;
EXECUTE ipak_drop_legacy_fk_statement;
DEALLOCATE PREPARE ipak_drop_legacy_fk_statement;

SET @ipak_drop_legacy_fk_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.referential_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'ipak_response_fields'
          AND constraint_name = 'fk_ipak_response_field_submission'
    ),
    'ALTER TABLE ipak_response_fields DROP FOREIGN KEY fk_ipak_response_field_submission',
    'SELECT 1'
);
PREPARE ipak_drop_legacy_fk_statement FROM @ipak_drop_legacy_fk_sql;
EXECUTE ipak_drop_legacy_fk_statement;
DEALLOCATE PREPARE ipak_drop_legacy_fk_statement;

