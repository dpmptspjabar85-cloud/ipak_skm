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
