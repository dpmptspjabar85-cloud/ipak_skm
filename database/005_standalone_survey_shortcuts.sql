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
