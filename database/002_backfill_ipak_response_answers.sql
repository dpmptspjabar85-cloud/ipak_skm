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
