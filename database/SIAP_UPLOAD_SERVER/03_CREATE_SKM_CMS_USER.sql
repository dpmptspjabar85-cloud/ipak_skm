-- ============================================================
-- TABEL PENGGUNA BACKOFFICE SKM
-- Aman dijalankan pada database existing.
-- Tidak menghapus tabel atau pengguna yang sudah tersedia.
-- ============================================================

SET NAMES utf8;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS skm_cms_user (
    id INT(10) NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(200) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_skm_cms_user_username (username),
    KEY idx_skm_cms_user_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Password akun di bawah adalah hash bcrypt untuk password yang telah
-- ditentukan pada aplikasi. INSERT IGNORE menjaga akun lama agar tidak ditimpa.
INSERT IGNORE INTO skm_cms_user
    (username, password, nama, is_active, created_at)
VALUES
    (
        'lian_permadi',
        '$2y$10$6I8UmTAJ/T5cEKkqYg1cxO0pKLA8nmOE5GWo8GRJSxL5F.51YzEOO',
        'Lian Permadi',
        1,
        CURRENT_TIMESTAMP
    );

-- Hubungkan sebagai superadmin apabila tabel role modul sudah tersedia.
-- Statement dinamis membuat file ini tetap bisa digunakan sebelum atau
-- sesudah instalasi modul survei.
SET @ipak_role_table_exists = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_admin_roles'
);

SET @ipak_superadmin_user_id = (
    SELECT id
    FROM skm_cms_user
    WHERE username = 'lian_permadi'
    LIMIT 1
);

SET @ipak_seed_role_sql = IF(
    @ipak_role_table_exists > 0 AND @ipak_superadmin_user_id IS NOT NULL,
    CONCAT(
        'INSERT INTO ipak_admin_roles (user_id, role_name) VALUES (',
        @ipak_superadmin_user_id,
        ', ''superadmin'') ON DUPLICATE KEY UPDATE role_name = ''superadmin'''
    ),
    'SELECT 1'
);

PREPARE ipak_seed_role_statement FROM @ipak_seed_role_sql;
EXECUTE ipak_seed_role_statement;
DEALLOCATE PREPARE ipak_seed_role_statement;

