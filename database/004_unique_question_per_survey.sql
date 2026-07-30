-- Satu pertanyaan hanya boleh dimiliki oleh satu survei.
-- Form gabungan tetap dapat memuat beberapa survei, tetapi kumpulan
-- pertanyaan antar-survei tidak boleh saling tumpang tindih.

SET @ipak_unique_question_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'ipak_survey_questions'
      AND index_name = 'uq_ipak_question_single_survey'
);

SET @ipak_unique_question_sql = IF(
    @ipak_unique_question_index_exists = 0,
    'ALTER TABLE ipak_survey_questions ADD UNIQUE KEY uq_ipak_question_single_survey (question_id)',
    'SELECT 1'
);

PREPARE ipak_unique_question_statement FROM @ipak_unique_question_sql;
EXECUTE ipak_unique_question_statement;
DEALLOCATE PREPARE ipak_unique_question_statement;
