-- Upgrade safety (MySQL 5.6 compatible): tabel mungkin sudah ada dari versi lama.
-- Gunakan DEFAULT NULL agar kompatibel dengan semua konfigurasi MySQL tanpa error.
-- Tambah kolom created_at/updated_at hanya jika belum ada. Jika ada tapi dengan default lama, ganti.
SET @col := (SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_questions' AND COLUMN_NAME = 'created_at');
SET @has_col := IF(@col IS NOT NULL, 1, 0);
SET @col_default := (SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_questions' AND COLUMN_NAME = 'created_at');
SET @col_extra := (SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_questions' AND COLUMN_NAME = 'created_at');
SET @needs_fix := IF(@has_col = 1 AND (@col_default != 'NULL' OR @col_extra NOT LIKE '%on update%'), 1, 0);
SET @sql := IF(@has_col = 0, 'ALTER TABLE ipak_questions ADD COLUMN created_at DATETIME NULL DEFAULT NULL AFTER is_active, ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at', IF(@needs_fix = 1, 'ALTER TABLE ipak_questions MODIFY COLUMN created_at DATETIME NULL DEFAULT NULL, MODIFY COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'SELECT "ipak_questions already has correct created_at/updated_at"'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS ipak_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_code VARCHAR(20) NOT NULL,
    question_text TEXT NOT NULL,
    measurement_name VARCHAR(100) NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    weight DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL DEFAULT NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_question_code (question_code),
    KEY idx_ipak_question_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Upgrade safety (MySQL 5.6 compatible):
-- Tambah kolom created_at/updated_at hanya jika belum ada. Jika ada tapi dengan default lama, ganti.
SET @col := (SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_answer_options' AND COLUMN_NAME = 'created_at');
SET @has_col := IF(@col IS NOT NULL, 1, 0);
SET @col_default := (SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_answer_options' AND COLUMN_NAME = 'created_at');
SET @col_extra := (SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_answer_options' AND COLUMN_NAME = 'created_at');
SET @needs_fix := IF(@has_col = 1 AND (@col_default != 'CURRENT_TIMESTAMP' OR @col_extra NOT LIKE '%on update%'), 1, 0);
SET @sql := IF(@has_col = 0, 'ALTER TABLE ipak_answer_options ADD COLUMN created_at DATETIME NULL DEFAULT NULL AFTER is_active, ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at', IF(@needs_fix = 1, 'ALTER TABLE ipak_answer_options MODIFY COLUMN created_at DATETIME NULL DEFAULT NULL, MODIFY COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'SELECT "ipak_answer_options already has correct created_at/updated_at"'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS ipak_answer_options (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id INT UNSIGNED NOT NULL,
    option_code VARCHAR(20) NOT NULL,
    option_label VARCHAR(255) NOT NULL,
    option_value DECIMAL(10,2) NOT NULL,
    normalized_score DECIMAL(6,2) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL DEFAULT NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_question_option_code (question_id, option_code),
    KEY idx_ipak_answer_question_order (question_id, is_active, sort_order),
    CONSTRAINT fk_ipak_answer_question
        FOREIGN KEY (question_id) REFERENCES ipak_questions (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Upgrade safety (MySQL 5.6 compatible):
SET @col := (SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_response_answers' AND COLUMN_NAME = 'created_at');
SET @has_col := IF(@col IS NOT NULL, 1, 0);
SET @col_default := (SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_response_answers' AND COLUMN_NAME = 'created_at');
SET @needs_fix := IF(@has_col = 1 AND @col_default != 'CURRENT_TIMESTAMP', 1, 0);
SET @sql := IF(@has_col = 0, 'ALTER TABLE ipak_response_answers ADD COLUMN created_at DATETIME NULL DEFAULT NULL AFTER category_snapshot', IF(@needs_fix = 1, 'ALTER TABLE ipak_response_answers MODIFY COLUMN created_at DATETIME NULL DEFAULT NULL', 'SELECT "ipak_response_answers already has correct created_at"'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS ipak_response_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    skm_data_id INT(10) NOT NULL,
    resi VARCHAR(20) NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer_option_id INT UNSIGNED NOT NULL,
    answer_value DECIMAL(10,2) NOT NULL,
    normalized_score DECIMAL(6,2) NOT NULL,
    question_text_snapshot TEXT NOT NULL,
    option_label_snapshot VARCHAR(255) NOT NULL,
    measurement_snapshot VARCHAR(100) NOT NULL,
    category_snapshot VARCHAR(100) NOT NULL,
    created_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_response_question (skm_data_id, question_id),
    KEY idx_ipak_response_resi (resi),
    KEY idx_ipak_response_question (question_id),
    KEY idx_ipak_response_option (answer_option_id),
    CONSTRAINT fk_ipak_response_skm
        FOREIGN KEY (skm_data_id) REFERENCES skm_data_skm (kode)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ipak_response_question
        FOREIGN KEY (question_id) REFERENCES ipak_questions (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ipak_response_option
        FOREIGN KEY (answer_option_id) REFERENCES ipak_answer_options (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Upgrade safety (MySQL 5.6 compatible):
SET @col := (SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_admin_roles' AND COLUMN_NAME = 'created_at');
SET @has_col := IF(@col IS NOT NULL, 1, 0);
SET @col_default := (SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ipak_admin_roles' AND COLUMN_NAME = 'created_at');
SET @needs_fix := IF(@has_col = 1 AND @col_default != 'CURRENT_TIMESTAMP', 1, 0);
SET @sql := IF(@has_col = 0, 'ALTER TABLE ipak_admin_roles ADD COLUMN created_at DATETIME NULL DEFAULT NULL AFTER role_name, ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at', IF(@needs_fix = 1, 'ALTER TABLE ipak_admin_roles MODIFY COLUMN created_at DATETIME NULL DEFAULT NULL, MODIFY COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'SELECT "ipak_admin_roles already has correct created_at/updated_at"'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS ipak_admin_roles (
    user_id INT(10) NOT NULL,
    role_name VARCHAR(30) NOT NULL DEFAULT 'admin',
    created_at DATETIME NULL DEFAULT NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY idx_ipak_admin_role (role_name),
    CONSTRAINT fk_ipak_admin_role_user
        FOREIGN KEY (user_id) REFERENCES skm_cms_user (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO ipak_questions
    (question_code, question_text, measurement_name, category_name, weight, sort_order, is_active)
VALUES
    ('IPAK-01', 'Pelayanan yang dilakukan sudah sesuai dengan prosedur dan aturan yang berlaku di lingkungan DPMPTSP Jawa Barat.', 'Kepatuhan Prosedur', 'Prosedur Pelayanan', 1.00, 1, 1),
    ('IPAK-02', 'Petugas tidak pernah memanfaatkan jabatannya untuk memengaruhi proses atau hasil pelayanan.', 'Integritas Petugas', 'Penyalahgunaan Jabatan', 1.00, 2, 1),
    ('IPAK-03', 'Tidak terdapat indikasi penyalahgunaan jabatan dalam proses pelayanan di lingkungan DPMPTSP Jawa Barat.', 'Akuntabilitas Jabatan', 'Pencegahan Penyalahgunaan', 1.00, 3, 1),
    ('IPAK-04', 'Informasi mengenai biaya pelayanan disampaikan secara jelas dan mudah diakses oleh masyarakat.', 'Transparansi Biaya', 'Keterbukaan Informasi', 1.00, 4, 1),
    ('IPAK-05', 'Pengguna layanan tidak pernah diminta membayar biaya tambahan di luar ketentuan resmi.', 'Kewajaran Biaya', 'Pungutan Liar', 1.00, 5, 1),
    ('IPAK-06', 'Tidak terdapat pemberian hadiah atau imbalan kepada petugas untuk mempercepat pelayanan.', 'Integritas Pelayanan', 'Gratifikasi', 1.00, 6, 1),
    ('IPAK-07', 'Setiap transaksi pelayanan selalu disertai dengan bukti pembayaran resmi.', 'Akuntabilitas Transaksi', 'Bukti Pembayaran', 1.00, 7, 1),
    ('IPAK-08', 'Tidak terdapat praktik percaloan dari petugas, dinas teknis, atau pihak lain yang menjanjikan kemudahan layanan dengan imbalan tertentu.', 'Kepastian Layanan', 'Percaloan', 1.00, 8, 1),
    ('IPAK-09', 'Tidak terdapat laporan atau indikasi tindakan curang dalam penyelenggaraan pelayanan publik.', 'Pengendalian Kecurangan', 'Tindakan Curang', 1.00, 9, 1),
    ('IPAK-10', 'Tidak terdapat transaksi rahasia atau tidak tercatat yang terjadi di DPMPTSP Jawa Barat.', 'Transparansi Transaksi', 'Transaksi Rahasia', 1.00, 10, 1)
ON DUPLICATE KEY UPDATE
    question_text = VALUES(question_text),
    measurement_name = VALUES(measurement_name),
    category_name = VALUES(category_name),
    sort_order = VALUES(sort_order);

INSERT INTO ipak_answer_options
    (question_id, option_code, option_label, option_value, normalized_score, sort_order, is_active)
SELECT
    q.id,
    o.option_code,
    o.option_label,
    o.option_value,
    o.normalized_score,
    o.sort_order,
    1
FROM ipak_questions q
CROSS JOIN (
    SELECT 'STS' AS option_code, 'Sangat Tidak Setuju' AS option_label, 1.00 AS option_value, 25.00 AS normalized_score, 1 AS sort_order
    UNION ALL SELECT 'TS', 'Tidak Setuju', 2.00, 50.00, 2
    UNION ALL SELECT 'S', 'Setuju', 3.00, 75.00, 3
    UNION ALL SELECT 'SS', 'Sangat Setuju', 4.00, 100.00, 4
) o
WHERE q.question_code LIKE 'IPAK-%'
ON DUPLICATE KEY UPDATE
    option_label = VALUES(option_label),
    option_value = VALUES(option_value),
    normalized_score = VALUES(normalized_score),
    sort_order = VALUES(sort_order);

