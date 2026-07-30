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

