-- VERSI 1 (DIREKOMENDASIKAN): UPDATE DATABASE SERVER YANG SUDAH BERJALAN
-- Aman dijalankan ulang. Struktur dan data SKM lama dipertahankan.

-- APLIKASI SURVEI SKM/IPAK
-- Dibuat otomatis dari migration bernomor di folder database.
-- Jangan mengubah paket hasil build secara langsung; ubah migration sumbernya.
--
-- PENTING:
-- 1. Paket ini tidak menghapus tabel maupun data SKM lama.
-- 2. Database tujuan harus sudah memiliki tabel aplikasi SKM lama,
--    khususnya skm_data_skm, skm_cms_user, dan tabel referensi SKM.
-- 3. ipak_admin_roles sengaja tidak memakai FOREIGN KEY ke skm_cms_user.
--    Hal ini menjaga kompatibilitas saat tipe/engine tabel pengguna server lama berbeda.

SET NAMES utf8;
SET time_zone = '+07:00';

-- ============================================================
-- MIGRATION: 001_ipak_normalized_schema.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS ipak_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_code VARCHAR(20) NOT NULL,
    question_text TEXT NOT NULL,
    measurement_name VARCHAR(100) NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    weight DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_question_code (question_code),
    KEY idx_ipak_question_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS ipak_answer_options (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id INT UNSIGNED NOT NULL,
    option_code VARCHAR(20) NOT NULL,
    option_label VARCHAR(255) NOT NULL,
    option_value DECIMAL(10,2) NOT NULL,
    normalized_score DECIMAL(6,2) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_question_option_code (question_id, option_code),
    KEY idx_ipak_answer_question_order (question_id, is_active, sort_order),
    CONSTRAINT fk_ipak_answer_question
        FOREIGN KEY (question_id) REFERENCES ipak_questions (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

CREATE TABLE IF NOT EXISTS ipak_admin_roles (
    user_id INT(10) NOT NULL,
    role_name VARCHAR(30) NOT NULL DEFAULT 'admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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

-- ============================================================
-- MIGRATION: 002_backfill_ipak_response_answers.sql
-- ============================================================

INSERT IGNORE INTO ipak_response_answers (
    skm_data_id,
    resi,
    question_id,
    answer_option_id,
    answer_value,
    normalized_score,
    question_text_snapshot,
    option_label_snapshot,
    measurement_snapshot,
    category_snapshot,
    created_at
)
SELECT
    s.kode,
    s.resi,
    q.id,
    ao.id,
    ao.option_value,
    ao.normalized_score,
    q.question_text,
    ao.option_label,
    q.measurement_name,
    q.category_name,
    COALESCE(s.tgl_buat, CONCAT(s.tgl_pengisian, ' 00:00:00'))
FROM skm_data_skm s
JOIN (
    SELECT 1 AS position_no
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
) positions
JOIN ipak_questions q
    ON q.sort_order = positions.position_no
JOIN ipak_answer_options ao
    ON ao.question_id = q.id
    AND ao.option_value = CAST(
        SUBSTRING_INDEX(
            SUBSTRING_INDEX(s.data_skm_nilai, ',', positions.position_no),
            ',',
            -1
        ) AS DECIMAL(10,2)
    )
WHERE s.data_skm_id LIKE 'IPAK:%'
  AND positions.position_no <= 1 + LENGTH(s.data_skm_nilai) - LENGTH(REPLACE(s.data_skm_nilai, ',', ''));

-- ============================================================
-- MIGRATION: 003_flexible_survey_engine.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS ipak_surveys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_code VARCHAR(30) NOT NULL,
    survey_name VARCHAR(150) NOT NULL,
    index_label VARCHAR(60) NOT NULL,
    description TEXT NULL,
    color VARCHAR(10) NOT NULL DEFAULT '#3049d8',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_survey_code (survey_code),
    KEY idx_ipak_survey_active (is_active, survey_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS ipak_survey_questions (
    survey_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    weight_override DECIMAL(8,2) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (survey_id, question_id),
    KEY idx_ipak_survey_question_order (survey_id, sort_order),
    KEY idx_ipak_question_surveys (question_id, survey_id),
    CONSTRAINT fk_ipak_sq_survey
        FOREIGN KEY (survey_id) REFERENCES ipak_surveys (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ipak_sq_question
        FOREIGN KEY (question_id) REFERENCES ipak_questions (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS ipak_survey_score_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_id INT UNSIGNED NOT NULL,
    category_label VARCHAR(80) NOT NULL,
    minimum_score DECIMAL(6,2) NOT NULL,
    maximum_score DECIMAL(6,2) NOT NULL,
    color VARCHAR(10) NOT NULL DEFAULT '#64748b',
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_survey_category_range (survey_id, minimum_score, maximum_score),
    CONSTRAINT fk_ipak_category_survey
        FOREIGN KEY (survey_id) REFERENCES ipak_surveys (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS ipak_forms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_code VARCHAR(30) NOT NULL,
    form_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_form_code (form_code),
    KEY idx_ipak_form_default (is_active, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS ipak_form_surveys (
    form_id INT UNSIGNED NOT NULL,
    survey_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    section_label VARCHAR(150) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (form_id, survey_id),
    KEY idx_ipak_form_survey_order (form_id, sort_order),
    CONSTRAINT fk_ipak_fs_form
        FOREIGN KEY (form_id) REFERENCES ipak_forms (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ipak_fs_survey
        FOREIGN KEY (survey_id) REFERENCES ipak_surveys (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS ipak_submission_surveys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    skm_data_id INT(10) NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    survey_id INT UNSIGNED NOT NULL,
    score DECIMAL(6,2) NOT NULL,
    category_label VARCHAR(80) NOT NULL,
    answer_count INT NOT NULL DEFAULT 0,
    weight_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_submission_survey (skm_data_id, survey_id),
    KEY idx_ipak_result_survey_score (survey_id, score),
    KEY idx_ipak_result_form (form_id),
    CONSTRAINT fk_ipak_result_skm
        FOREIGN KEY (skm_data_id) REFERENCES skm_data_skm (kode)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ipak_result_form
        FOREIGN KEY (form_id) REFERENCES ipak_forms (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ipak_result_survey
        FOREIGN KEY (survey_id) REFERENCES ipak_surveys (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS ipak_submission_survey_answers (
    survey_result_id BIGINT UNSIGNED NOT NULL,
    response_answer_id BIGINT UNSIGNED NOT NULL,
    applied_weight DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (survey_result_id, response_answer_id),
    KEY idx_ipak_result_answer (response_answer_id),
    CONSTRAINT fk_ipak_result_answer_result
        FOREIGN KEY (survey_result_id) REFERENCES ipak_submission_surveys (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ipak_result_answer_detail
        FOREIGN KEY (response_answer_id) REFERENCES ipak_response_answers (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO ipak_surveys
    (survey_code, survey_name, index_label, description, color, is_active)
VALUES
    ('IPAK', 'Survei Persepsi Anti Korupsi', 'Nilai IPAK', 'Mengukur persepsi integritas, transparansi, dan pencegahan korupsi dalam pelayanan.', '#3049d8', 1),
    ('SKM', 'Survei Kepuasan Masyarakat', 'Nilai SKM', 'Mengukur kepuasan masyarakat atas mutu dan hasil pelayanan.', '#12a594', 1)
ON DUPLICATE KEY UPDATE
    survey_name = VALUES(survey_name),
    index_label = VALUES(index_label),
    description = VALUES(description),
    color = VALUES(color);

INSERT INTO ipak_questions
    (question_code, question_text, measurement_name, category_name, weight, sort_order, is_active)
SELECT
    CONCAT('SKM-', LPAD(q.kode_quis, 2, '0')),
    q.judul_quis,
    q.judul_quis,
    'Kepuasan Masyarakat',
    1.00,
    q.kode_quis,
    1
FROM skm_quisioner q
INNER JOIN skm_grup_quis g
    ON FIND_IN_SET(q.kode_quis, REPLACE(g.data_quis, ' ', '')) > 0
WHERE g.status_quis = 2
ON DUPLICATE KEY UPDATE
    question_text = VALUES(question_text),
    measurement_name = VALUES(measurement_name),
    category_name = VALUES(category_name);

INSERT INTO ipak_answer_options
    (question_id, option_code, option_label, option_value, normalized_score, sort_order, is_active)
SELECT
    iq.id,
    CONCAT('SKM-', p.bobot),
    p.pertanyaan,
    p.bobot,
    LEAST(100, GREATEST(0, p.bobot * 25)),
    5 - p.bobot,
    1
FROM skm_pertanyaan p
INNER JOIN ipak_questions iq
    ON iq.question_code = CONCAT('SKM-', LPAD(p.kode_quis, 2, '0'))
ON DUPLICATE KEY UPDATE
    option_label = VALUES(option_label),
    option_value = VALUES(option_value),
    normalized_score = VALUES(normalized_score),
    sort_order = VALUES(sort_order);

INSERT INTO ipak_survey_questions
    (survey_id, question_id, sort_order, weight_override, is_required)
SELECT s.id, q.id, q.sort_order, NULL, 1
FROM ipak_surveys s
INNER JOIN ipak_questions q ON q.question_code LIKE 'IPAK-%'
WHERE s.survey_code = 'IPAK'
ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order),
    is_required = VALUES(is_required);

INSERT INTO ipak_survey_questions
    (survey_id, question_id, sort_order, weight_override, is_required)
SELECT s.id, q.id, q.sort_order, NULL, 1
FROM ipak_surveys s
INNER JOIN ipak_questions q ON q.question_code LIKE 'SKM-%'
WHERE s.survey_code = 'SKM'
ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order),
    is_required = VALUES(is_required);

INSERT INTO ipak_survey_score_categories
    (survey_id, category_label, minimum_score, maximum_score, color, sort_order)
SELECT s.id, c.category_label, c.minimum_score, c.maximum_score, c.color, c.sort_order
FROM ipak_surveys s
CROSS JOIN (
    SELECT 'Sangat Baik' AS category_label, 88.31 AS minimum_score, 100.00 AS maximum_score, '#0f9f6e' AS color, 1 AS sort_order
    UNION ALL SELECT 'Baik', 76.61, 88.30, '#3049d8', 2
    UNION ALL SELECT 'Kurang Baik', 65.00, 76.60, '#e59b2f', 3
    UNION ALL SELECT 'Tidak Baik', 0.00, 64.99, '#e35757', 4
) c
WHERE 1 = 1
ON DUPLICATE KEY UPDATE
    category_label = VALUES(category_label),
    color = VALUES(color),
    sort_order = VALUES(sort_order);

INSERT INTO ipak_forms
    (form_code, form_name, description, is_default, is_active)
VALUES
    ('GABUNGAN-SKM-IPAK', 'Survei Pelayanan Terpadu', 'Satu pengalaman pengisian untuk Survei Kepuasan Masyarakat dan Survei Persepsi Anti Korupsi.', 1, 1),
    ('IPAK', 'Form IPAK', 'Form khusus Survei Persepsi Anti Korupsi.', 0, 1),
    ('SKM', 'Form SKM', 'Form khusus Survei Kepuasan Masyarakat.', 0, 1)
ON DUPLICATE KEY UPDATE
    form_name = VALUES(form_name),
    description = VALUES(description),
    is_active = VALUES(is_active);

UPDATE ipak_forms
SET is_default = CASE WHEN form_code = 'GABUNGAN-SKM-IPAK' THEN 1 ELSE 0 END;

INSERT INTO ipak_form_surveys (form_id, survey_id, sort_order, section_label)
SELECT f.id, s.id,
    CASE WHEN s.survey_code = 'SKM' THEN 1 ELSE 2 END,
    s.survey_name
FROM ipak_forms f
INNER JOIN ipak_surveys s ON s.survey_code IN ('SKM', 'IPAK')
WHERE f.form_code = 'GABUNGAN-SKM-IPAK'
ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order),
    section_label = VALUES(section_label);

INSERT INTO ipak_form_surveys (form_id, survey_id, sort_order, section_label)
SELECT f.id, s.id, 1, s.survey_name
FROM ipak_forms f
INNER JOIN ipak_surveys s ON s.survey_code = f.form_code
WHERE f.form_code IN ('IPAK', 'SKM')
ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order),
    section_label = VALUES(section_label);

INSERT INTO ipak_submission_surveys
    (skm_data_id, form_id, survey_id, score, category_label, answer_count, weight_total, created_at)
SELECT
    d.kode,
    f.id,
    s.id,
    d.rata,
    CASE
        WHEN d.rata >= 88.31 THEN 'Sangat Baik'
        WHEN d.rata >= 76.61 THEN 'Baik'
        WHEN d.rata >= 65.00 THEN 'Kurang Baik'
        ELSE 'Tidak Baik'
    END,
    (SELECT COUNT(*) FROM ipak_response_answers ra WHERE ra.skm_data_id = d.kode),
    (SELECT COALESCE(SUM(q.weight), 0)
       FROM ipak_response_answers ra
       INNER JOIN ipak_questions q ON q.id = ra.question_id
      WHERE ra.skm_data_id = d.kode),
    COALESCE(d.tgl_buat, CONCAT(d.tgl_pengisian, ' 00:00:00'))
FROM skm_data_skm d
INNER JOIN ipak_forms f ON f.form_code = 'IPAK'
INNER JOIN ipak_surveys s ON s.survey_code = 'IPAK'
WHERE d.data_skm_id LIKE 'IPAK:%'
ON DUPLICATE KEY UPDATE
    score = VALUES(score),
    category_label = VALUES(category_label),
    answer_count = VALUES(answer_count),
    weight_total = VALUES(weight_total);

INSERT IGNORE INTO ipak_submission_survey_answers
    (survey_result_id, response_answer_id, applied_weight)
SELECT r.id, ra.id, COALESCE(sq.weight_override, q.weight)
FROM ipak_submission_surveys r
INNER JOIN ipak_surveys s ON s.id = r.survey_id AND s.survey_code = 'IPAK'
INNER JOIN ipak_response_answers ra ON ra.skm_data_id = r.skm_data_id
INNER JOIN ipak_survey_questions sq
    ON sq.survey_id = r.survey_id AND sq.question_id = ra.question_id
INNER JOIN ipak_questions q ON q.id = ra.question_id;

-- ============================================================
-- MIGRATION: 004_unique_question_per_survey.sql
-- ============================================================

-- Kebijakan terbaru: satu pertanyaan boleh digunakan oleh beberapa survei.
--
-- Primary key (survey_id, question_id) pada ipak_survey_questions tetap
-- mencegah pertanyaan yang sama dimasukkan dua kali ke survei yang sama.
--
-- Blok ini juga aman untuk database yang pernah menjalankan migration lama.

SET @ipak_shared_question_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_survey_questions'
      AND index_name = 'uq_ipak_question_single_survey'
);

SET @ipak_shared_question_sql = IF(
    @ipak_shared_question_index_exists > 0,
    'ALTER TABLE ipak_survey_questions DROP INDEX uq_ipak_question_single_survey',
    'SELECT 1'
);

PREPARE ipak_shared_question_statement FROM @ipak_shared_question_sql;
EXECUTE ipak_shared_question_statement;
DEALLOCATE PREPARE ipak_shared_question_statement;

-- ============================================================
-- MIGRATION: 005_standalone_survey_shortcuts.sql
-- ============================================================

-- Membuat form mandiri untuk survei lama yang belum mempunyai form sendiri.
-- Form ini menjadi target shortcut "Isi Survei" pada backoffice.

INSERT INTO ipak_forms
    (form_code, form_name, description, is_default, is_active)
SELECT
    CONCAT('MANDIRI-', LEFT(s.survey_code, 10), '-', s.id),
    CONCAT('Form ', s.survey_name),
    CONCAT('Form mandiri untuk ', s.survey_name, '.'),
    0,
    s.is_active
FROM ipak_surveys s
WHERE NOT EXISTS (
    SELECT 1
    FROM ipak_form_surveys own_fs
    WHERE own_fs.survey_id = s.id
      AND (
          SELECT COUNT(*)
          FROM ipak_form_surveys all_fs
          WHERE all_fs.form_id = own_fs.form_id
      ) = 1
)
AND NOT EXISTS (
    SELECT 1
    FROM ipak_forms existing_form
    WHERE existing_form.form_code = CONCAT('MANDIRI-', LEFT(s.survey_code, 10), '-', s.id)
);

INSERT IGNORE INTO ipak_form_surveys
    (form_id, survey_id, sort_order, section_label)
SELECT
    f.id,
    s.id,
    1,
    s.survey_name
FROM ipak_surveys s
INNER JOIN ipak_forms f
    ON f.form_code = CONCAT('MANDIRI-', LEFT(s.survey_code, 10), '-', s.id)
WHERE NOT EXISTS (
    SELECT 1
    FROM ipak_form_surveys own_fs
    WHERE own_fs.survey_id = s.id
      AND (
          SELECT COUNT(*)
          FROM ipak_form_surveys all_fs
          WHERE all_fs.form_id = own_fs.form_id
      ) = 1
);

-- ============================================================
-- MIGRATION: 006_flexible_respondent_fields_and_skm_lock.sql
-- ============================================================

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

-- ============================================================
-- MIGRATION: 007_three_step_form_builder.sql
-- ============================================================

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
    KEY idx_ipak_response_field_key (field_key),
    CONSTRAINT fk_ipak_response_field_submission
        FOREIGN KEY (skm_data_id) REFERENCES skm_data_skm (kode)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================================
-- MIGRATION: 008_default_regional_units.sql
-- ============================================================

-- Data bawaan perangkat daerah. Tidak mengubah struktur tabel yang sudah ada.
UPDATE trunitkerja
SET n_unitkerja = 'DINAS KOMUNIKASI DAN INFORMATIKA PROVINSI JAWA BARAT'
WHERE n_unitkerja IN (
    'DINAS KOMUNIKASI DAN INFORMASI PROVINSI JAWA BARAT',
    'DINAS KOMUNIKASI DAN INFORMATIKA'
);

INSERT INTO trunitkerja (n_unitkerja, nm_cap)
SELECT
    'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT',
    ''
WHERE NOT EXISTS (
    SELECT 1
    FROM trunitkerja
    WHERE n_unitkerja = 'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT'
);

INSERT INTO trunitkerja (n_unitkerja, nm_cap)
SELECT
    'DINAS KOMUNIKASI DAN INFORMATIKA PROVINSI JAWA BARAT',
    ''
WHERE NOT EXISTS (
    SELECT 1
    FROM trunitkerja
    WHERE n_unitkerja = 'DINAS KOMUNIKASI DAN INFORMATIKA PROVINSI JAWA BARAT'
);

-- ============================================================
-- MIGRATION: 009_regular_survey_sector_and_unit.sql
-- ============================================================

-- Menyamakan sumber sektor dan perangkat daerah untuk survei biasa.
-- Tidak mengubah struktur tabel.

UPDATE ipak_form_fields
SET
    field_label = 'Sektor',
    help_text = 'Pilih sektor layanan yang sedang dinilai.'
WHERE field_key = 'service'
  AND field_label = 'Jenis layanan';

UPDATE skm_data_skm
SET
    keterangan = JSON_SET(
        IF(JSON_VALID(keterangan), keterangan, '{}'),
        '$.unit_id',
        COALESCE(
            (
                SELECT id
                FROM trunitkerja
                WHERE n_unitkerja = 'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT'
                LIMIT 1
            ),
            1
        ),
        '$.unit_name',
        'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT',
        '$.sector_source',
        'legacy_service',
        '$.legacy_service_name',
        CASE sektor
            WHEN 1 THEN 'Pelayanan Perizinan'
            WHEN 2 THEN 'Pelayanan Pengawasan'
            WHEN 3 THEN 'Pelayanan Pembinaan'
            WHEN 4 THEN 'Pelayanan Penyelesaian Permasalahan'
            WHEN 5 THEN 'Pelayanan Lainnya'
            ELSE NULL
        END
    ),
    sektor = 0
WHERE jenis_ijin = 0
  AND data_skm_id LIKE 'FLEX:%'
  AND JSON_EXTRACT(
      IF(JSON_VALID(keterangan), keterangan, '{}'),
      '$.sector_source'
  ) IS NULL;

-- ============================================================
-- MIGRATION: 010_add_survey_type.sql
-- ============================================================

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

-- ============================================================
-- MIGRATION: 011_add_unique_survey_codes.sql
-- ============================================================

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

-- ============================================================
-- MIGRATION: 012_protect_legacy_skm.sql
-- ============================================================

-- Perlindungan SKM lama dan pemisahan hasil formulir gabungan.
-- Kolom baru memiliki nilai default yang aman untuk aplikasi SKM lama:
-- aplikasi lama tetap menulis flag_skm=1 dan dianggap SKM legacy.

ALTER TABLE ipak_surveys
    ADD COLUMN IF NOT EXISTS storage_profile VARCHAR(20) NOT NULL DEFAULT 'FLEX' AFTER kode_unik,
    ADD COLUMN IF NOT EXISTS survey_version VARCHAR(30) NOT NULL DEFAULT '1' AFTER storage_profile,
    ADD COLUMN IF NOT EXISTS legacy_question_limit SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER survey_version;

UPDATE ipak_surveys
SET storage_profile = 'LEGACY_SKM',
    survey_version = 'SKM-LEGACY-10',
    legacy_question_limit = 10
WHERE UPPER(survey_code) = 'SKM';

UPDATE ipak_surveys
SET storage_profile = 'FLEX',
    legacy_question_limit = 0
WHERE UPPER(survey_code) <> 'SKM'
  AND storage_profile <> 'FLEX';

ALTER TABLE skm_data_skm
    ADD COLUMN IF NOT EXISTS kode_pengisian CHAR(36) NULL AFTER kode_survei_unik,
    ADD COLUMN IF NOT EXISTS versi_survei VARCHAR(30) NULL AFTER kode_pengisian,
    ADD COLUMN IF NOT EXISTS is_legacy_skm TINYINT(1) NOT NULL DEFAULT 1 AFTER versi_survei;

CREATE INDEX IF NOT EXISTS idx_skm_kode_pengisian
    ON skm_data_skm (kode_pengisian);

CREATE INDEX IF NOT EXISTS idx_skm_legacy_dashboard
    ON skm_data_skm (flag_skm, tgl_pengisian);

-- Respons fleksibel yang pernah dibuat oleh aplikasi gabungan tidak boleh
-- ikut dihitung oleh grafik aplikasi SKM lama.
UPDATE skm_data_skm
SET flag_skm = 0,
    is_legacy_skm = 0,
    versi_survei = COALESCE(NULLIF(versi_survei, ''), '1'),
    kode_pengisian = COALESCE(NULLIF(kode_pengisian, ''), LOWER(UUID()))
WHERE jenis_survei = 'SURVEY';

-- Semua data lama tetap diperlakukan sebagai SKM legacy. Nilai dan jawaban
-- historisnya tidak ditulis ulang oleh migrasi ini.
UPDATE skm_data_skm
SET is_legacy_skm = 1,
    versi_survei = COALESCE(NULLIF(versi_survei, ''), 'SKM-LEGACY-10')
WHERE flag_skm = 1
  AND jenis_survei = 'SKM';

-- ============================================================
-- MIGRATION: 013_api_builder.sql
-- ============================================================

-- API Builder untuk membagikan data survei secara aman ke aplikasi lain.
-- Migrasi ini hanya menambah tabel baru dan tidak mengubah tabel SKM lama.

CREATE TABLE IF NOT EXISTS ipak_api_clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_name VARCHAR(120) NOT NULL,
    client_code VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    api_key_prefix VARCHAR(20) NOT NULL,
    api_key_hash CHAR(64) NOT NULL,
    survey_scope ENUM('all', 'selected') NOT NULL DEFAULT 'selected',
    allowed_survey_ids TEXT NOT NULL,
    allowed_resources TEXT NOT NULL,
    allowed_dimensions TEXT NOT NULL,
    allowed_detail_fields TEXT NOT NULL,
    max_page_size SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    rate_limit_per_minute SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    allowed_ip_addresses TEXT NULL,
    allowed_origin VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_api_client_code (client_code),
    UNIQUE KEY uq_ipak_api_key_hash (api_key_hash),
    KEY idx_ipak_api_client_active (is_active, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ipak_api_access_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    api_client_id INT UNSIGNED NOT NULL,
    resource_name VARCHAR(30) NOT NULL,
    survey_id INT UNSIGNED NULL,
    request_method VARCHAR(10) NOT NULL DEFAULT 'GET',
    request_ip VARCHAR(45) NULL,
    query_string TEXT NULL,
    status_code SMALLINT UNSIGNED NOT NULL,
    response_count INT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ipak_api_log_client_time (api_client_id, requested_at),
    KEY idx_ipak_api_log_status_time (status_code, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MIGRATION: 014_separate_flexible_survey_responses.sql
-- ============================================================

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

-- ============================================================
-- MIGRATION: 015_remove_legacy_foreign_key_dependencies.sql
-- ============================================================

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

-- ============================================================
-- MIGRATION: 016_allow_shared_questions_across_surveys.sql
-- ============================================================

-- Membuka penggunaan ulang pertanyaan pada beberapa survei untuk server
-- existing yang sebelumnya memasang unique index per question_id.

SET @ipak_shared_question_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_survey_questions'
      AND index_name = 'uq_ipak_question_single_survey'
);

SET @ipak_shared_question_sql = IF(
    @ipak_shared_question_index_exists > 0,
    'ALTER TABLE ipak_survey_questions DROP INDEX uq_ipak_question_single_survey',
    'SELECT 1'
);

PREPARE ipak_shared_question_statement FROM @ipak_shared_question_sql;
EXECUTE ipak_shared_question_statement;
DEALLOCATE PREPARE ipak_shared_question_statement;

-- ============================================================
-- MIGRATION: 017_form_public_visibility.sql
-- ============================================================

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
