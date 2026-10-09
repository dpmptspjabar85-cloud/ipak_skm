<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ipaksurvey_model extends CI_Model
{
    /**
     * Jawaban yang tertimpa saat jawaban dirakit per question_id.
     *
     * @var array
     */
    public $export_overwritten_answers = array();

    /**
     * Jawaban yang dibuang karena pertanyaannya tidak terdaftar pada survei.
     *
     * @var array
     */
    public $export_unregistered_answers = array();
    private $table = 'skm_data_skm';
    private $flexResponseTable = 'ipak_survey_responses';
    private $allResponsesView = 'ipak_all_responses';
    private $formPublicVisibilitySupported = null;

    /**
     * Masa berlaku cache pemeriksaan struktur tabel (detik).
     *
     * Pemeriksaan ini hanya concerned dengan skema, bukan data, sehingga
     * достаточно disimpan singkat. Setelah migration dijalankan, cache akan
     * kedaluwarsa sendiri tanpa perlu penghapusan manual.
     */
    const SCHEMA_CACHE_TTL = 3600;

    /**
     * Cache pemeriksaan struktur tabel dengan basis berkas.
     *
     * Query ke INFORMATION_SCHEMA pada MySQL/MariaDB melakukan pemindaian
     * metadata seluruh server sehingga sangat lambat pada hosting bersama.
     * Karena create dan edit form memanggil sinkronisasi skema setiap kali,
     * proses tersebut membuat halaman terasa menggantung. Cache berkas
     * menghindari query yang sama pada request berikutnya.
     *
     * @param string $key
     * @return mixed|null
     */
    private function schema_cache_get($key)
    {
        $path = $this->schema_cache_path($key);
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return null;
        }
        $expiresAt = isset($payload['expires']) ? (int) $payload['expires'] : 0;
        if ($expiresAt > 0 && $expiresAt < time()) {
            return null;
        }
        return $payload['value'];
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    private function schema_cache_set($key, $value)
    {
        $path = $this->schema_cache_path($key);
        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            return;
        }
        $payload = json_encode([
            'created' => time(),
            'expires' => time() + self::SCHEMA_CACHE_TTL,
            'value' => $value,
        ]);
        if ($payload === false) {
            return;
        }
        $temporary = $path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temporary, $payload) === false) {
            return;
        }
        @rename($temporary, $path);
    }

    /**
     * @param string $key
     * @return string
     */
    private function schema_cache_path($key)
    {
        $directory = defined('APPPATH')
            ? APPPATH . 'cache' . DIRECTORY_SEPARATOR
            : '';
        return $directory . 'ipak_schema_' . preg_replace('/[^a-z0-9_]/', '', strtolower($key)) . '.json';
    }

    private function supports_form_public_visibility()
    {
        if ($this->formPublicVisibilitySupported === null) {
            $this->formPublicVisibilitySupported = $this->db->field_exists(
                'is_public_listed',
                'ipak_forms'
            );
        }
        return $this->formPublicVisibilitySupported;
    }

    private function kbli_schema_definition()
    {
        return [
            'columns' => [
                'id' => ['type' => 'INT UNSIGNED', 'auto_increment' => true, 'nullable' => false, 'primary' => true],
                'kode_gabungan' => ['type' => 'VARCHAR(32)', 'nullable' => false],
                'kategori' => ['type' => 'VARCHAR(32)', 'nullable' => false, 'default' => ''],
                'kode' => ['type' => 'VARCHAR(32)', 'nullable' => false, 'default' => ''],
                'sektor_bps' => ['type' => 'VARCHAR(255)', 'nullable' => false, 'default' => ''],
                'judul' => ['type' => 'VARCHAR(255)', 'nullable' => false, 'default' => ''],
                'deskripsi' => ['type' => 'TEXT', 'nullable' => false],
                'digit' => ['type' => 'TINYINT UNSIGNED', 'nullable' => false, 'default' => '0'],
                'hirarki' => ['type' => 'VARCHAR(40)', 'nullable' => false, 'default' => ''],
                'created_at' => ['type' => 'DATETIME', 'nullable' => true, 'default' => null],
                'updated_at' => ['type' => 'DATETIME', 'nullable' => true, 'default' => null],
            ],
            'indexes' => [
                'PRIMARY' => ['columns' => ['id'], 'type' => 'PRIMARY'],
                'uq_ipak_kbli_kode_gabungan' => ['columns' => ['kode_gabungan'], 'type' => 'UNIQUE'],
                'idx_ipak_kbli_kategori' => ['columns' => ['kategori'], 'type' => 'INDEX'],
                'idx_ipak_kbli_kode' => ['columns' => ['kode'], 'type' => 'INDEX'],
                'idx_ipak_kbli_digit_hirarki' => ['columns' => ['digit', 'hirarki'], 'type' => 'INDEX'],
            ],
            'foreign_keys' => [],
            'engine' => 'InnoDB',
            'charset' => 'utf8',
            'collate' => 'utf8_general_ci',
        ];
    }

    public function sync_kbli_schema()
    {
        $tableName = 'ipak_kbli';
        $tableDef = $this->kbli_schema_definition();
        $results = [
            'tables_created' => [], 'tables_existed' => [],
            'columns_added' => [], 'columns_existed' => [],
            'indexes_created' => [], 'indexes_existed' => [],
            'errors' => [],
        ];

        // Pemeriksaan struktur hanya perlu dijalankan ulang setelah cache habis
        // masa berlakunya. Tanpa ini setiap buka halaman create/edit form
        // menjalankan dua query INFORMATION_SCHEMA yang lambat.
        $cacheKey = 'kbli_schema_' . $this->db->database;
        $cached = $this->schema_cache_get($cacheKey);
        if (is_array($cached) && empty($cached['errors'])) {
            return $cached;
        }

        if (!$this->db->table_exists($tableName)) {
            try {
                $this->db->query($this->build_create_table_sql($tableName, $tableDef));
                $results['tables_created'][] = $tableName;
            } catch (Exception $e) {
                $results['errors'][] = 'Tidak dapat membuat tabel ' . $tableName . ': ' . $e->getMessage();
                return $results;
            }
        } else {
            $results['tables_existed'][] = $tableName;
        }

        $existingColumns = $this->get_table_columns($tableName);
        foreach ($tableDef['columns'] as $columnName => $columnDef) {
            if (isset($existingColumns[$columnName])) {
                $results['columns_existed'][] = $tableName . '.' . $columnName;
                continue;
            }
            $columnSql = '`' . $columnName . '` ' . $columnDef['type'];
            $columnSql .= empty($columnDef['nullable']) ? ' NOT NULL' : ' NULL';
            if (isset($columnDef['default'])) {
                $default = $columnDef['default'];
                if (strpos($default, 'CURRENT_TIMESTAMP') === false && preg_match('/^(INT|TINYINT|DECIMAL|FLOAT)/i', $columnDef['type'])) {
                    $columnSql .= ' DEFAULT ' . $default;
                } elseif (strpos($default, 'CURRENT_TIMESTAMP') !== false) {
                    $columnSql .= ' DEFAULT ' . $default;
                } else {
                    $columnSql .= " DEFAULT '" . $this->db->escape_str($default) . "'";
                }
            }
            if (!empty($columnDef['auto_increment'])) $columnSql .= ' AUTO_INCREMENT';
            if (!empty($columnDef['primary'])) $columnSql .= ' PRIMARY KEY';
            try {
                $this->db->query('ALTER TABLE `' . $tableName . '` ADD COLUMN ' . $columnSql);
                $results['columns_added'][] = $tableName . '.' . $columnName;
            } catch (Exception $e) {
                $results['errors'][] = 'Tidak dapat menambahkan kolom ' . $tableName . '.' . $columnName . ': ' . $e->getMessage();
            }
        }

        $existingIndexes = $this->get_table_indexes($tableName);
        foreach ($tableDef['indexes'] as $indexName => $indexDef) {
            $found = false;
            foreach ($existingIndexes as $existingIndex) {
                if ($existingIndex['Key_name'] === $indexName) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $results['indexes_existed'][] = $tableName . '.' . $indexName;
                continue;
            }
            try {
                if ($indexDef['type'] === 'PRIMARY') {
                    $this->db->query('ALTER TABLE `' . $tableName . '` ADD PRIMARY KEY (`' . implode('`, `', $indexDef['columns']) . '`)');
                } else {
                    $this->db->query($this->build_create_index_sql($tableName, $indexName, $indexDef));
                }
                $results['indexes_created'][] = $tableName . '.' . $indexName;
            } catch (Exception $e) {
                $results['errors'][] = 'Tidak dapat membuat index ' . $tableName . '.' . $indexName . ': ' . $e->getMessage();
            }
        }
        if (empty($results['errors'])) {
            $this->schema_cache_set($cacheKey, $results);
        }
        return $results;
    }

    public function get_kbli_rows($search = '', $limit = 50, $offset = 0)
    {
        if ($search !== '') {
            $this->db->group_start()
                ->like('kode_gabungan', $search)
                ->or_like('kategori', $search)
                ->or_like('kode', $search)
                ->or_like('sektor_bps', $search)
                ->or_like('judul', $search)
                ->or_like('deskripsi', $search)
                ->or_like('hirarki', $search)
                ->group_end();
        }
        return $this->db
            ->order_by('kode_gabungan', 'ASC')
            ->limit(max(1, (int) $limit), max(0, (int) $offset))
            ->get('ipak_kbli')
            ->result_array();
    }

    public function count_kbli_rows($search = '')
    {
        if ($search !== '') {
            $this->db->group_start()
                ->like('kode_gabungan', $search)
                ->or_like('kategori', $search)
                ->or_like('kode', $search)
                ->or_like('sektor_bps', $search)
                ->or_like('judul', $search)
                ->or_like('deskripsi', $search)
                ->or_like('hirarki', $search)
                ->group_end();
        }
        return (int) $this->db->count_all_results('ipak_kbli');
    }

    public function get_kbli_by_id($id)
    {
        return $this->db
            ->where('id', (int) $id)
            ->limit(1)
            ->get('ipak_kbli')
            ->row_array();
    }

    public function kbli_display_columns()
    {
        return [
            'kode_gabungan' => 'Kode Gabungan',
            'kategori' => 'Kategori',
            'kode' => 'Kode',
            'sektor_bps' => 'Sektor BPS',
            'judul' => 'Judul',
            'deskripsi' => 'Deskripsi',
            'digit' => 'Digit',
            'hirarki' => 'Hirarki',
        ];
    }

    public function kbli_option_label(array $row, array $configuration)
    {
        $availableColumns = $this->kbli_display_columns();
        $codeField = isset($configuration['code_field']) && in_array($configuration['code_field'], ['kode', 'kode_gabungan'], true)
            ? $configuration['code_field']
            : 'kode_gabungan';
        $selectedColumns = isset($configuration['display_columns']) && is_array($configuration['display_columns'])
            ? $configuration['display_columns']
            : [];
        $selectedColumns = array_values(array_intersect(array_keys($availableColumns), $selectedColumns));
        $columns = array_values(array_unique(array_merge([$codeField], $selectedColumns)));
        $parts = [];
        foreach ($columns as $column) {
            if (!isset($row[$column]) || trim((string) $row[$column]) === '') {
                continue;
            }
            $parts[] = $availableColumns[$column] . ': ' . trim((string) $row[$column]);
        }
        return implode(' · ', $parts);
    }

    public function get_kbli_field_options(array $configuration, $limit = 0, $selectedValue = '')
    {
        if (!$this->db->table_exists('ipak_kbli')) {
            return [];
        }
        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = (int) $this->config->item('ipak_kbli_initial_limit');
        }
        $limit = $limit > 0 ? $limit : 200;
        $selectedId = trim((string) $selectedValue);
        $selectedId = preg_match('/^\d+$/', $selectedId) ? $selectedId : '';

        $options = [];
        if ($selectedId !== '') {
            $selected = $this->db
                ->where('id', $selectedId)
                ->limit(1)
                ->get('ipak_kbli')
                ->row_array();
            if (!empty($selected)) {
                $options[$selectedId] = $this->kbli_option_label($selected, $configuration);
            }
        }

        $rows = $this->db
            ->order_by('kode_gabungan', 'ASC')
            ->limit($limit)
            ->get('ipak_kbli')
            ->result_array();
        foreach ($rows as $row) {
            $options[(string) $row['id']] = $this->kbli_option_label($row, $configuration);
        }
        return $options;
    }

    /**
     * Server-side search for KBLI-backed select fields.
     *
     * The full ipak_kbli table is far too large to render into every survey
     * page, so the select is populated lazily through this lookup instead.
     */
    public function search_kbli_field_options(array $configuration, $search = '', $limit = 50, $selectedValue = '')
    {
        $limit = max(1, min(100, (int) $limit));
        $search = trim((string) $search);
        $selectedId = trim((string) $selectedValue);
        $selectedId = preg_match('/^\d+$/', $selectedId) ? $selectedId : '';

        $options = [];
        if ($selectedId !== '' && $search !== '') {
            $selected = $this->db
                ->where('id', $selectedId)
                ->limit(1)
                ->get('ipak_kbli')
                ->row_array();
            if (!empty($selected)) {
                $options[$selectedId] = $this->kbli_option_label($selected, $configuration);
            }
        }

        $rows = $this->get_kbli_rows($search, $limit, 0);
        foreach ($rows as $row) {
            $options[(string) $row['id']] = $this->kbli_option_label($row, $configuration);
        }

        return [
            'options' => $options,
            'total' => (int) $this->count_kbli_rows($search),
        ];
    }

    public function kbli_code_exists($code, $excludeId = 0)
    {
        $this->db->where('kode_gabungan', trim((string) $code));
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results('ipak_kbli') > 0;
    }

    public function save_kbli(array $row)
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        unset($row['id']);
        $row['updated_at'] = date('Y-m-d H:i:s');
        if ($id > 0) {
            return $this->db->where('id', $id)->update('ipak_kbli', $row) ? $id : false;
        }
        $row['created_at'] = $row['updated_at'];
        return $this->db->insert('ipak_kbli', $row) ? (int) $this->db->insert_id() : false;
    }

    public function delete_kbli($id)
    {
        return $this->db->where('id', (int) $id)->delete('ipak_kbli');
    }

    public function import_kbli(array $rows, $replaceExisting)
    {
        if (!$rows) {
            return false;
        }
        $this->db->trans_begin();
        if ($replaceExisting) {
            $this->db->empty_table('ipak_kbli');
        }
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $row['updated_at'] = $now;
            $existing = $this->db
                ->select('id')
                ->where('kode_gabungan', $row['kode_gabungan'])
                ->limit(1)
                ->get('ipak_kbli')
                ->row_array();
            if ($existing) {
                $this->db->where('id', (int) $existing['id'])->update('ipak_kbli', $row);
            } else {
                $row['created_at'] = $now;
                $this->db->insert('ipak_kbli', $row);
            }
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return false;
            }
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return true;
    }

    public function create_response(array $input)
    {
        $defaults = [
            'resi' => '',
            'nib' => '',
            'name' => '',
            'email' => '',
            'phone' => '',
            'gender' => 0,
            'age' => 0,
            'job' => 0,
            'education' => 0,
            'service' => 0,
            'job_other' => '',
            'service_other' => '',
            'suggestion' => '',
        ];
        $input = array_merge($defaults, $input);
        foreach (['resi', 'nib', 'name', 'email', 'phone', 'job_other', 'service_other', 'suggestion'] as $stringKey) {
            $input[$stringKey] = trim((string) $input[$stringKey]);
        }
        $answerDetails = isset($input['answer_details']) && is_array($input['answer_details'])
            ? $input['answer_details']
            : [];
        $form = isset($input['form_definition']) && is_array($input['form_definition'])
            ? $input['form_definition']
            : $this->get_form_definition('IPAK');
        $surveyResults = $this->calculate_survey_results($form, $answerDetails);
        if (!$surveyResults) {
            return false;
        }

        $resultScores = [];
        foreach ($surveyResults as $surveyResult) {
            $resultScores[] = (float) $surveyResult['score'];
        }
        $score = $resultScores ? array_sum($resultScores) / count($resultScores) : 0;
        $formCode = !empty($form['form_code']) ? $form['form_code'] : 'IPAK';
        $formSurveyCodes = [];
        if (!empty($form['surveys'])) {
            foreach ($form['surveys'] as $survey) {
                $formSurveyCodes[] = $survey['survey_code'];
            }
        }
        $submissionGroupCode = $this->new_submission_group_uuid();
        $visibleReference = '';
        $rowIds = [];
        $responseKeys = [];
        $publicResults = [];

        $this->db->trans_start();
        $responseFields = isset($input['response_fields']) && is_array($input['response_fields'])
            ? $input['response_fields']
            : [];

        foreach ($surveyResults as $surveyResult) {
            $surveyId = (int) $surveyResult['survey_id'];
            $survey = isset($form['surveys'][$surveyId]) ? $form['surveys'][$surveyId] : [];
            $isLegacySkm = $this->is_legacy_skm_survey_definition($survey);
            $surveyVersion = !empty($survey['survey_version'])
                ? $survey['survey_version']
                : ($isLegacySkm ? 'SKM-LEGACY-10' : '1');
            $scopedAnswers = isset($surveyResult['_answer_details'])
                ? $surveyResult['_answer_details']
                : [];
            $questionIds = [];
            $storedValues = [];
            $normalizedTotal = 0.0;
            foreach ($scopedAnswers as $detail) {
                $questionIds[] = (int) $detail['question_id'];
                $storedValues[] = (float) $detail['option_value'];
                $normalizedTotal += (float) $detail['normalized_score'];
            }
            if ($isLegacySkm) {
                $storedValues = array_slice($storedValues, 0, 10);
                while (count($storedValues) < 10) {
                    $storedValues[] = 0;
                }
            }

            $rowReference = ($isLegacySkm && $input['resi'] !== '')
                ? $input['resi']
                : $this->new_reference();
            if ($visibleReference === '' || $isLegacySkm) {
                $visibleReference = $rowReference;
            }

            $metadata = [
                'app' => 'SURVEY-FLEX',
                'version' => $this->config->item('ipak_version'),
                'email' => $input['email'],
                'job_other' => $input['job_other'],
                'service_other' => $input['service_other'],
                'consent' => true,
                'permit_status' => isset($input['permit_status']) ? $input['permit_status'] : '',
                'permit_name' => isset($input['permit_name']) ? $input['permit_name'] : '',
                'sector_name' => isset($input['sector_name']) ? $input['sector_name'] : '',
                'sector_source' => isset($input['sector_source']) ? $input['sector_source'] : '',
                'unit_id' => isset($input['unit_id']) ? (int) $input['unit_id'] : 0,
                'unit_name' => isset($input['unit_name']) ? $input['unit_name'] : '',
                'form_code' => $formCode,
                'form_survey_codes' => $formSurveyCodes,
                'survey_codes' => [$surveyResult['survey_code']],
                'survey_unique_codes' => [$surveyResult['survey_unique_code']],
                'submission_group_code' => $submissionGroupCode,
                'survey_version' => $surveyVersion,
                'storage_profile' => $isLegacySkm ? 'LEGACY_SKM' : 'FLEX',
            ];
            $data = [
                'nib' => $input['nib'] ?: null,
                'resi' => $rowReference,
                'permohonan_id' => isset($input['permohonan_id']) ? (int) $input['permohonan_id'] : null,
                'nama_responden' => $input['name'],
                'status_responden' => 1,
                'responden' => $input['email'],
                'mobile' => $input['phone'],
                'gender' => (int) $input['gender'],
                'usia' => (int) $input['age'],
                'pekerjaan_id' => (int) $input['job'],
                'pendidikan_id' => (int) $input['education'],
                'sektor' => (int) $input['service'],
                'jenis_ijin' => isset($input['permit_type_id']) ? (int) $input['permit_type_id'] : 0,
                'tgl_pengisian' => date('Y-m-d'),
                'data_skm_id' => $isLegacySkm
                    ? implode(',', array_slice($questionIds, 0, 10))
                    : 'FLEX:' . $surveyResult['survey_code'] . ':' . implode(',', $questionIds),
                'data_skm_nilai' => implode(',', $storedValues),
                'total' => round($normalizedTotal, 2),
                'rata' => round((float) $surveyResult['score'], 2),
                'saran' => $input['suggestion'],
                'keterangan' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
                'tgl_buat' => date('Y-m-d H:i:s'),
                'flag_skm' => $isLegacySkm ? 1 : 0,
                'jenis_survei' => $isLegacySkm ? 'SKM' : 'SURVEY',
                'kode_survei_unik' => $surveyResult['survey_unique_code'],
                'kode_pengisian' => $submissionGroupCode,
                'versi_survei' => $surveyVersion,
                'is_legacy_skm' => $isLegacySkm ? 1 : 0,
            ];
            $responseSource = $isLegacySkm ? 'SKM' : 'SURVEY';
            $responseTable = $isLegacySkm ? $this->table : $this->flexResponseTable;
            if (!$isLegacySkm) {
                $data['legacy_skm_data_id'] = null;
            }
            $this->db->insert($responseTable, $data);
            $id = (int) $this->db->insert_id();
            $rowIds[] = $id;
            $responseKeys[] = $this->response_key($id, $responseSource);

            if ($responseFields) {
                $fieldRows = [];
                foreach ($responseFields as $field) {
                    if (empty($field['field_key'])) {
                        continue;
                    }
                    $fieldRows[] = [
                        'skm_data_id' => $isLegacySkm ? $id : null,
                        'flex_response_id' => $isLegacySkm ? null : $id,
                        'field_key' => substr(trim((string) $field['field_key']), 0, 30),
                        'field_label_snapshot' => substr(trim((string) $field['field_label']), 0, 100),
                        'field_group' => isset($field['field_group']) && $field['field_group'] === 'access'
                            ? 'access'
                            : 'identity',
                        'field_value' => isset($field['field_value']) ? trim((string) $field['field_value']) : '',
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                }
                if ($fieldRows) {
                    $this->db->insert_batch('ipak_response_fields', $fieldRows);
                }
            }

            if ($scopedAnswers) {
                $detailRows = [];
                foreach ($scopedAnswers as $detail) {
                    $detailRows[] = [
                        'skm_data_id' => $isLegacySkm ? $id : null,
                        'flex_response_id' => $isLegacySkm ? null : $id,
                        'resi' => $rowReference,
                        'question_id' => (int) $detail['question_id'],
                        'answer_option_id' => (int) $detail['answer_option_id'],
                        'answer_value' => (float) $detail['option_value'],
                        'normalized_score' => (float) $detail['normalized_score'],
                        'question_text_snapshot' => $detail['question_text'],
                        'option_label_snapshot' => $detail['option_label'],
                        'measurement_snapshot' => $detail['measurement_name'],
                        'category_snapshot' => $detail['category_name'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                }
                $this->db->insert_batch('ipak_response_answers', $detailRows);
            }

            $answerRows = $this->db
                ->select('id,question_id')
                ->where($isLegacySkm ? 'skm_data_id' : 'flex_response_id', $id)
                ->get('ipak_response_answers')
                ->result_array();
            $answerIds = [];
            foreach ($answerRows as $answerRow) {
                $answerIds[(int) $answerRow['question_id']] = (int) $answerRow['id'];
            }

            $this->db->insert('ipak_submission_surveys', [
                'skm_data_id' => $isLegacySkm ? $id : null,
                'flex_response_id' => $isLegacySkm ? null : $id,
                'form_id' => (int) $form['id'],
                'survey_id' => $surveyId,
                'kode_survei_unik' => $surveyResult['survey_unique_code'],
                'score' => round((float) $surveyResult['score'], 2),
                'category_label' => $surveyResult['category_label'],
                'answer_count' => (int) $surveyResult['answer_count'],
                'weight_total' => (float) $surveyResult['weight_total'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $surveyResultId = (int) $this->db->insert_id();
            $mappingRows = [];
            foreach ($surveyResult['question_weights'] as $questionId => $weight) {
                if (!isset($answerIds[$questionId])) {
                    continue;
                }
                $mappingRows[] = [
                    'survey_result_id' => $surveyResultId,
                    'response_answer_id' => $answerIds[$questionId],
                    'applied_weight' => (float) $weight,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
            if ($mappingRows) {
                $this->db->insert_batch('ipak_submission_survey_answers', $mappingRows);
            }

            unset($surveyResult['_answer_details']);
            $surveyResult['reference'] = $rowReference;
            $surveyResult['submission_group_code'] = $submissionGroupCode;
            $surveyResult['response_source'] = $responseSource;
            $surveyResult['response_key'] = $this->response_key($id, $responseSource);
            $publicResults[] = $surveyResult;
        }

        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return false;
        }

        return [
            'id' => $rowIds ? $rowIds[0] : 0,
            'row_ids' => $rowIds,
            'response_keys' => $responseKeys,
            'reference' => $visibleReference,
            'submission_group_code' => $submissionGroupCode,
            'score' => round($score, 2),
            'survey_results' => $publicResults,
        ];
    }

    private function calculate_survey_results(array $form, array $answerDetails)
    {
        $detailsByQuestion = [];
        foreach ($answerDetails as $detail) {
            $detailsByQuestion[(int) $detail['question_id']] = $detail;
        }

        $results = [];
        if (empty($form['surveys'])) {
            return $results;
        }
        foreach ($form['surveys'] as $survey) {
            $weightedTotal = 0.0;
            $weightTotal = 0.0;
            $answerCount = 0;
            $questionWeights = [];
            $scopedAnswerDetails = [];
            $isLegacySkm = $this->is_legacy_skm_survey_definition($survey);
            $legacyLimit = !empty($survey['legacy_question_limit'])
                ? max(1, (int) $survey['legacy_question_limit'])
                : 10;
            $questionPosition = 0;
            foreach ($survey['questions'] as $questionId => $question) {
                if ($isLegacySkm && $questionPosition >= $legacyLimit) {
                    break;
                }
                $questionPosition++;
                $questionId = (int) $questionId;
                if (!isset($detailsByQuestion[$questionId])) {
                    continue;
                }
                $weight = max(0.01, (float) $question['survey_weight']);
                $weightedTotal += (float) $detailsByQuestion[$questionId]['normalized_score'] * $weight;
                $weightTotal += $weight;
                $answerCount++;
                $questionWeights[$questionId] = $weight;
                $scopedAnswerDetails[] = $detailsByQuestion[$questionId];
            }
            $surveyScore = $weightTotal > 0 ? $weightedTotal / $weightTotal : 0;
            $category = $this->survey_score_category((int) $survey['id'], $surveyScore);
            $results[] = [
                'survey_id' => (int) $survey['id'],
                'survey_code' => $survey['survey_code'],
                'survey_unique_code' => isset($survey['kode_unik']) ? $survey['kode_unik'] : '',
                'survey_name' => $survey['survey_name'],
                'index_label' => $survey['index_label'],
                'score' => round($surveyScore, 2),
                'category_label' => $category['label'],
                'category_color' => $category['color'],
                'answer_count' => $answerCount,
                'weight_total' => round($weightTotal, 2),
                'question_weights' => $questionWeights,
                '_answer_details' => $scopedAnswerDetails,
            ];
        }
        return $results;
    }

    private function new_reference()
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = 'SRV' . date('ymdHis') . mt_rand(10, 99);
            $legacyExists = $this->db->where('resi', $reference)->count_all_results($this->table);
            $flexExists = $this->db->where('resi', $reference)->count_all_results($this->flexResponseTable);
            if (!$legacyExists && !$flexExists) {
                return $reference;
            }
        }

        return substr('SRV' . date('ymdHis') . mt_rand(100, 999), 0, 20);
    }

    private function is_legacy_skm_survey_definition(array $survey)
    {
        if (!empty($survey['storage_profile'])) {
            return strtoupper((string) $survey['storage_profile']) === 'LEGACY_SKM';
        }
        return isset($survey['survey_code'])
            && strtoupper((string) $survey['survey_code']) === 'SKM';
    }

    private function new_submission_group_uuid()
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $hex = md5(uniqid((string) mt_rand(), true) . microtime(true));
            $uuid = substr($hex, 0, 8) . '-' .
                substr($hex, 8, 4) . '-4' .
                substr($hex, 13, 3) . '-' .
                dechex((hexdec($hex[16]) & 0x3) | 0x8) .
                substr($hex, 17, 3) . '-' .
                substr($hex, 20, 12);
            $legacyExists = $this->db
                ->where('kode_pengisian', $uuid)
                ->count_all_results($this->table);
            $flexExists = $this->db
                ->where('kode_pengisian', $uuid)
                ->count_all_results($this->flexResponseTable);
            if (!$legacyExists && !$flexExists) {
                return $uuid;
            }
        }
        return strtolower(sprintf(
            '%08x-%04x-4%03x-%04x-%012x',
            mt_rand(),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff),
            mt_rand(0x8000, 0xbfff),
            mt_rand()
        ));
    }

    private function response_key($id, $source)
    {
        return strtoupper((string) $source) === 'SURVEY'
            ? 'SURVEY-' . (int) $id
            : (string) (int) $id;
    }

    private function parse_response_key($key, $source = '')
    {
        $source = strtoupper(trim((string) $source));
        $key = trim((string) $key);
        if (preg_match('/^SURVEY-(\d+)$/i', $key, $matches)) {
            return ['id' => (int) $matches[1], 'source' => 'SURVEY'];
        }
        return [
            'id' => max(0, (int) $key),
            'source' => $source === 'SURVEY' ? 'SURVEY' : 'SKM',
        ];
    }

    private function attach_response_identity(array $row, $source = '')
    {
        if (!$row) {
            return $row;
        }
        $source = strtoupper(trim((string) ($source !== '' ? $source : (isset($row['response_source']) ? $row['response_source'] : 'SKM'))));
        $source = $source === 'SURVEY' ? 'SURVEY' : 'SKM';
        $row['response_source'] = $source;
        $row['response_key'] = $this->response_key((int) $row['kode'], $source);
        return $row;
    }

    private function new_survey_uuid()
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $hex = md5(uniqid((string) mt_rand(), true) . microtime(true));
            $uuid = substr($hex, 0, 8) . '-' .
                substr($hex, 8, 4) . '-4' .
                substr($hex, 13, 3) . '-' .
                dechex((hexdec($hex[16]) & 0x3) | 0x8) .
                substr($hex, 17, 3) . '-' .
                substr($hex, 20, 12);
            if (!$this->db->where('kode_unik', $uuid)->count_all_results('ipak_surveys')) {
                return $uuid;
            }
        }
        return strtolower(sprintf(
            '%08x-%04x-4%03x-%04x-%012x',
            mt_rand(),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff),
            mt_rand(0x8000, 0xbfff),
            mt_rand()
        ));
    }

    public function find_permit_by_resi($resi)
    {
        $resi = trim((string) $resi);
        if ($resi === '') {
            return null;
        }

        $row = $this->db
            ->select(
                'p.id AS permohonan_id,p.pendaftaran_id AS resi,p.trsektor_id,' .
                'p.status_berkas,p.d_selesai_proses,p.c_izin_selesai,p.siap_serah,p.tgl_siap_serah,' .
                'pp.id AS pemohon_portal_id,pp.namaPemohon,pp.namaPerusahaan,pp.telpPemohon,' .
                'pp.telpPerusahaan,pp.emailPerusahaan,pp.nib,pp.izin AS permit_type_id,' .
                'pi.n_perizinan AS permit_name,s.n_sektor AS sector_name,' .
                'pi.dinas_pengelola AS unit_id,u.n_unitkerja AS unit_name',
                false
            )
            ->from('tmpermohonan p')
            ->join('tmpemohon_portal pp', 'pp.id = p.id_pemohon_portal', 'left')
            ->join('trperizinan pi', 'pi.id = pp.izin', 'left')
            ->join('trsektor s', 's.id = p.trsektor_id', 'left')
            ->join('trunitkerja u', 'u.id = pi.dinas_pengelola', 'left')
            ->where('p.pendaftaran_id', $resi)
            ->order_by('p.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row) {
            return null;
        }

        if (empty($row['trsektor_id']) && !empty($row['permit_type_id'])) {
            $sector = $this->db
                ->select('ts.id AS trsektor_id,ts.n_sektor AS sector_name')
                ->from('trperizinan_trsektor pts')
                ->join('trsektor ts', 'ts.id = pts.trsektor_id', 'left')
                ->where('pts.trperizinan_id', (int) $row['permit_type_id'])
                ->limit(1)
                ->get()
                ->row_array();
            if ($sector) {
                $row['trsektor_id'] = $sector['trsektor_id'];
                $row['sector_name'] = $sector['sector_name'];
            }
        }

        $status = strtolower(trim((string) $row['status_berkas']));
        $isRejected = strpos($status, 'tolak') !== false
            || strpos($status, 'cabut') !== false
            || strpos($status, 'batal') !== false;
        $hasIssuedStatus = strpos($status, 'disetujui') !== false
            || strpos($status, 'terbit') !== false
            || strpos($status, 'selesai') !== false;
        $hasCompletionDate = !empty($row['d_selesai_proses'])
            && $row['d_selesai_proses'] !== '0000-00-00';
        $hasHandoverDate = !empty($row['tgl_siap_serah'])
            && $row['tgl_siap_serah'] !== '0000-00-00';

        $row['is_issued'] = !$isRejected && (
            $hasIssuedStatus
            || (int) $row['c_izin_selesai'] === 1
            || $hasCompletionDate
            || $hasHandoverDate
        );

        return $row;
    }

    public function has_ipak_response($resi)
    {
        $this->db
            ->where('resi', trim((string) $resi))
            ->where('flag_skm', 1)
            ->group_start()
            ->where('rata >', 0)
            ->or_where("data_skm_nilai REGEXP '[1-9]'", null, false)
            ->group_end();
        return $this->db->count_all_results($this->table) > 0;
    }

    public function get_admin_by_username($username)
    {
        return $this->db
            ->select('u.*,COALESCE(r.role_name, "admin") AS role_name', false)
            ->from('skm_cms_user u')
            ->join('ipak_admin_roles r', 'r.user_id = u.id', 'left')
            ->where('u.username', $username)
            ->where('u.is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
    }

    public function touch_admin_login($id)
    {
        return $this->db
            ->where('id', (int) $id)
            ->update('skm_cms_user', ['last_login' => date('Y-m-d H:i:s')]);
    }

    public function get_admin_users()
    {
        return $this->db
            ->select('u.*,COALESCE(r.role_name, "admin") AS role_name,r.is_system', false)
            ->from('skm_cms_user u')
            ->join('ipak_admin_roles r', 'r.user_id = u.id', 'left')
            ->order_by('u.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function get_admin_by_id($id)
    {
        return $this->db
            ->select('u.*,COALESCE(r.role_name, "admin") AS role_name,r.is_system', false)
            ->from('skm_cms_user u')
            ->join('ipak_admin_roles r', 'r.user_id = u.id', 'left')
            ->where('u.id', (int) $id)
            ->limit(1)
            ->get()
            ->row_array();
    }

    public function save_admin($data)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $roleName = isset($data['role_name']) ? $data['role_name'] : null;
        unset($data['id'], $data['role_name']);
        if (isset($data['password']) && $data['password'] !== '') {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['password']);
        }
        if ($id > 0) {
            $this->db->where('id', $id)->update('skm_cms_user', $data);
        } else {
            $this->db->insert('skm_cms_user', $data);
            $id = (int) $this->db->insert_id();
        }
        if ($roleName !== null) {
            $existingRole = $this->db
                ->select('user_id')
                ->from('ipak_admin_roles')
                ->where('user_id', $id)
                ->limit(1)
                ->get()
                ->row_array();
            $roleData = [
                'user_id' => $id,
                'role_name' => $roleName,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($existingRole) {
                $this->db->where('user_id', $id)->update('ipak_admin_roles', $roleData);
            } else {
                $roleData['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('ipak_admin_roles', $roleData);
            }
        }
        return $id;
    }

    public function change_admin_password($id, $newPassword)
    {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        return $this->db
            ->where('id', (int) $id)
            ->update('skm_cms_user', ['password' => $hashed]);
    }

    public function delete_admin($id)
    {
        return $this->db
            ->where('id', (int) $id)
            ->delete('skm_cms_user');
    }

    public function admin_exists($id)
    {
        return $this->db
            ->where('id', (int) $id)
            ->count_all_results('skm_cms_user') > 0;
    }

    public function admin_username_exists($username, $excludeId = 0)
    {
        $this->db->where('username', $username);
        if ($excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results('skm_cms_user') > 0;
    }

    public function get_admin_roles()
    {
        return [
            'superadmin' => 'Super Admin',
            'admin' => 'Administrator',
        ];
    }

    public function get_surveys($activeOnly = false)
    {
        $this->db
            ->select(
                's.*,COUNT(DISTINCT sq.question_id) AS question_count,' .
                'COUNT(DISTINCT sr.id) AS response_count',
                false
            )
            ->from('ipak_surveys s')
            ->join('ipak_survey_questions sq', 'sq.survey_id = s.id', 'left')
            ->join('ipak_submission_surveys sr', 'sr.survey_id = s.id', 'left');
        if ($activeOnly) {
            $this->db->where('s.is_active', 1);
        }
        $rows = $this->db
            ->group_by('s.id')
            ->order_by('s.survey_name', 'ASC')
            ->get()
            ->result_array();
        $legacyResponseCount = (int) $this->db
            ->where('flag_skm', 1)
            ->count_all_results($this->table);
        $result = [];
        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['is_active'] = (int) $row['is_active'];
            $row['is_system_locked'] = isset($row['is_system_locked']) ? (int) $row['is_system_locked'] : 0;
            $row['is_mandatory'] = isset($row['is_mandatory']) ? (int) $row['is_mandatory'] : 0;
            $row['question_count'] = (int) $row['question_count'];
            $row['response_count'] = (int) $row['response_count'];
            if ($this->is_legacy_skm_survey_definition($row)) {
                $row['response_count'] = $legacyResponseCount;
            }
            $result[$row['id']] = $row;
        }
        return $result;
    }

    public function legacy_skm_survey_id()
    {
        $row = $this->db
            ->select('id')
            ->where('storage_profile', 'LEGACY_SKM')
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get('ipak_surveys')
            ->row_array();
        if (!$row) {
            $row = $this->db
                ->select('id')
                ->where('survey_code', 'SKM')
                ->limit(1)
                ->get('ipak_surveys')
                ->row_array();
        }
        return $row ? (int) $row['id'] : 0;
    }

    public function is_legacy_skm_survey($surveyId)
    {
        $row = $this->db
            ->select('survey_code,storage_profile')
            ->where('id', (int) $surveyId)
            ->limit(1)
            ->get('ipak_surveys')
            ->row_array();
        return $row ? $this->is_legacy_skm_survey_definition($row) : false;
    }

    public function get_survey_by_id($surveyId)
    {
        $survey = $this->db
            ->where('id', (int) $surveyId)
            ->limit(1)
            ->get('ipak_surveys')
            ->row_array();
        if (!$survey) {
            return false;
        }
        $survey['id'] = (int) $survey['id'];
        $survey['is_active'] = isset($survey['is_active']) ? (int) $survey['is_active'] : 0;
        $survey['is_system_locked'] = isset($survey['is_system_locked']) ? (int) $survey['is_system_locked'] : 0;
        $survey['is_mandatory'] = isset($survey['is_mandatory']) ? (int) $survey['is_mandatory'] : 0;
        return $survey;
    }

    public function get_forms($activeOnly = false)
    {
        $this->db
            ->select('f.*,COUNT(DISTINCT fs.survey_id) AS survey_count', false)
            ->from('ipak_forms f')
            ->join('ipak_form_surveys fs', 'fs.form_id = f.id', 'left');
        if ($activeOnly) {
            $this->db->where('f.is_active', 1);
        }
        $rows = $this->db
            ->group_by('f.id')
            ->order_by('f.is_default', 'DESC')
            ->order_by('f.form_name', 'ASC')
            ->get()
            ->result_array();
        foreach ($rows as $index => $row) {
            $rows[$index]['id'] = (int) $row['id'];
            $rows[$index]['is_default'] = (int) $row['is_default'];
            $rows[$index]['is_active'] = (int) $row['is_active'];
            $rows[$index]['is_public_listed'] = isset($row['is_public_listed'])
                ? (int) $row['is_public_listed']
                : 1;
            $rows[$index]['survey_count'] = (int) $row['survey_count'];
        }
        return $rows;
    }

    public function get_form_by_id($formId)
    {
        $form = $this->db
            ->select('f.*,COUNT(DISTINCT fs.survey_id) AS survey_count', false)
            ->from('ipak_forms f')
            ->join('ipak_form_surveys fs', 'fs.form_id = f.id', 'left')
            ->where('f.id', (int) $formId)
            ->group_by('f.id')
            ->limit(1)
            ->get()
            ->row_array();
        if (!$form) {
            return false;
        }
        $form['id'] = (int) $form['id'];
        $form['is_default'] = (int) $form['is_default'];
        $form['is_active'] = (int) $form['is_active'];
        $form['is_public_listed'] = isset($form['is_public_listed'])
            ? (int) $form['is_public_listed']
            : 1;
        $form['survey_count'] = (int) $form['survey_count'];
        $form['survey_ids'] = $this->get_form_survey_ids((int) $form['id']);
        return $form;
    }

    public function get_public_forms($search = '', $type = 'all', $limit = 9, $offset = 0)
    {
        $this->build_public_forms_query($search, $type);
        $rows = $this->db
            ->order_by('f.is_default', 'DESC')
            ->order_by('f.form_name', 'ASC')
            ->limit(max(1, (int) $limit), max(0, (int) $offset))
            ->get()
            ->result_array();

        foreach ($rows as $index => $row) {
            $surveyNames = array_values(array_filter(array_map('trim', explode('||', (string) $row['survey_names']))));
            $rows[$index]['id'] = (int) $row['id'];
            $rows[$index]['is_default'] = (int) $row['is_default'];
            $rows[$index]['survey_count'] = (int) $row['survey_count'];
            $rows[$index]['question_count'] = (int) $row['question_count'];
            $rows[$index]['requires_resi'] = (int) $row['requires_resi'];
            $rows[$index]['survey_names'] = $surveyNames;
            $rows[$index]['accent_color'] = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $row['accent_color'])
                ? $row['accent_color']
                : '#3049d8';
        }
        return $rows;
    }

    public function count_public_forms($search = '', $type = 'all')
    {
        $this->build_public_forms_query($search, $type, true);
        return $this->db->get()->num_rows();
    }

    private function build_public_forms_query($search = '', $type = 'all', $countOnly = false)
    {
        $requiresResiSql = "MAX(CASE WHEN UPPER(s.survey_code) = 'SKM' THEN 1 ELSE 0 END)";
        $surveyCountSql = 'COUNT(DISTINCT s.id)';

        if ($countOnly) {
            $this->db->select('f.id', false);
        } else {
            $this->db->select(
                "f.id,f.form_code,f.form_name,f.description,f.is_default," .
                "{$surveyCountSql} AS survey_count," .
                'COUNT(DISTINCT q.id) AS question_count,' .
                "{$requiresResiSql} AS requires_resi," .
                "GROUP_CONCAT(DISTINCT s.survey_name SEPARATOR '||') AS survey_names," .
                'MAX(s.color) AS accent_color',
                false
            );
        }

        $this->db
            ->from('ipak_forms f')
            ->join('ipak_form_surveys fs', 'fs.form_id = f.id', 'inner')
            ->join('ipak_surveys s', 's.id = fs.survey_id', 'inner')
            ->join('ipak_survey_questions sq', 'sq.survey_id = s.id', 'inner')
            ->join('ipak_questions q', 'q.id = sq.question_id AND q.is_active = 1', 'inner')
            ->where('f.is_active', 1)
            ->where('s.is_active', 1);
        if ($this->supports_form_public_visibility()) {
            $this->db->where('f.is_public_listed', 1);
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $this->db
                ->group_start()
                ->like('f.form_name', $search)
                ->or_like('f.form_code', $search)
                ->or_like('f.description', $search)
                ->or_like('s.survey_name', $search)
                ->group_end();
        }

        $this->db->group_by('f.id');
        if ($type === 'skm') {
            $this->db->having($requiresResiSql . ' = 1', null, false);
        } elseif ($type === 'regular') {
            $this->db->having($requiresResiSql . ' = 0', null, false);
        } elseif ($type === 'combined') {
            $this->db->having($surveyCountSql . ' > 1', null, false);
        }
    }

    public function respondent_field_definitions()
    {
        return [
            'name' => [
                'label' => 'Nama lengkap / nama perusahaan',
                'help_text' => 'Isi nama responden atau nama perusahaan.',
                'sort_order' => 10,
                'field_group' => 'identity',
                'field_type' => 'text',
            ],
            'email' => [
                'label' => 'Email',
                'help_text' => 'Gunakan alamat email yang aktif, misalnya nama@email.com.',
                'sort_order' => 20,
                'field_group' => 'access',
                'field_type' => 'email',
            ],
            'phone' => [
                'label' => 'Nomor telepon',
                'help_text' => 'Gunakan nomor telepon aktif, misalnya 081234567890.',
                'sort_order' => 30,
                'field_group' => 'access',
                'field_type' => 'tel',
            ],
            'identity_number' => [
                'label' => 'Nomor identitas / nomor induk',
                'help_text' => 'Dapat digunakan untuk NIK, NIB, nomor siswa, atau nomor identitas lain.',
                'sort_order' => 40,
                'field_group' => 'access',
                'field_type' => 'text',
            ],
            'address' => [
                'label' => 'Alamat',
                'help_text' => 'Isi alamat sesuai kebutuhan survei.',
                'sort_order' => 45,
                'field_group' => 'identity',
                'field_type' => 'textarea',
            ],
            'age' => [
                'label' => 'Usia',
                'help_text' => 'Isi usia saat ini dalam angka.',
                'sort_order' => 50,
                'field_group' => 'identity',
                'field_type' => 'number',
            ],
            'gender' => [
                'label' => 'Jenis kelamin',
                'help_text' => 'Pilih satu pilihan untuk pengelompokan statistik.',
                'sort_order' => 60,
                'field_group' => 'identity',
                'field_type' => 'select',
            ],
            'education' => [
                'label' => 'Pendidikan terakhir',
                'help_text' => 'Pilih jenjang pendidikan terakhir yang telah diselesaikan.',
                'sort_order' => 70,
                'field_group' => 'identity',
                'field_type' => 'select',
            ],
            'job' => [
                'label' => 'Pekerjaan',
                'help_text' => 'Pilih pekerjaan utama responden.',
                'sort_order' => 80,
                'field_group' => 'identity',
                'field_type' => 'select',
            ],
            'service' => [
                'label' => 'Sektor',
                'help_text' => 'Pilih sektor layanan yang sedang dinilai.',
                'sort_order' => 90,
                'field_group' => 'identity',
                'field_type' => 'select',
            ],
            'kbli' => [
                'label' => 'Layanan KBLI',
                'help_text' => 'Pilih kegiatan usaha berdasarkan katalog KBLI.',
                'sort_order' => 100,
                'field_group' => 'identity',
                'field_type' => 'select',
            ],
        ];
    }

    public function get_form_fields($formId)
    {
        $rows = $this->db
            ->where('form_id', (int) $formId)
            ->order_by('sort_order', 'ASC')
            ->get('ipak_form_fields')
            ->result_array();
        $result = [];
        foreach ($rows as $row) {
            $row['form_id'] = (int) $row['form_id'];
            $row['sort_order'] = (int) $row['sort_order'];
            $row['is_system'] = isset($row['is_system']) ? (int) $row['is_system'] : 1;
            $decodedOptions = !empty($row['field_options'])
                ? json_decode($row['field_options'], true)
                : [];
            $row['options'] = is_array($decodedOptions) ? $decodedOptions : [];
            $result[$row['field_key']] = $row;
        }
        return $result;
    }

    private function respondent_field_mode_rank($mode)
    {
        if ($mode === 'required') {
            return 2;
        }
        if ($mode === 'optional') {
            return 1;
        }
        return 0;
    }

    private function respondent_field_signature(array $field)
    {
        if (!empty($field['is_system'])) {
            return 'system:' . strtolower((string) $field['field_key']);
        }
        $label = strtolower(trim((string) $field['field_label']));
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label);
        $label = trim(preg_replace('/\s+/', ' ', $label));
        $systemAliases = [
            'email' => ['email', 'alamat email'],
            'phone' => ['nomor telepon', 'no telepon', 'nomor hp', 'no hp', 'handphone'],
            'name' => ['nama', 'nama lengkap', 'nama responden'],
            'identity_number' => ['nomor identitas', 'nomor induk', 'nik', 'nib'],
            'address' => ['alamat', 'alamat lengkap', 'alamat domisili'],
            'age' => ['usia', 'umur'],
            'gender' => ['jenis kelamin'],
            'education' => ['pendidikan', 'pendidikan terakhir'],
            'job' => ['pekerjaan'],
            'service' => ['sektor', 'jenis layanan'],
        ];
        foreach ($systemAliases as $systemKey => $aliases) {
            if (in_array($label, $aliases, true)) {
                return 'system:' . $systemKey;
            }
        }
        return 'custom:' . $label;
    }

    private function merge_respondent_field_rows(array $baseFields, array $additionalFields)
    {
        $signatures = [];
        foreach ($baseFields as $fieldKey => $field) {
            $signatures[$this->respondent_field_signature($field)] = $fieldKey;
        }

        foreach ($additionalFields as $fieldKey => $field) {
            $signature = $this->respondent_field_signature($field);
            if (!isset($signatures[$signature])) {
                $targetKey = $fieldKey;
                if (isset($baseFields[$targetKey])) {
                    $targetKey = 'custom_m_' . substr(sha1($signature), 0, 16);
                    $field['field_key'] = $targetKey;
                }
                $baseFields[$targetKey] = $field;
                $signatures[$signature] = $targetKey;
                continue;
            }

            $targetKey = $signatures[$signature];
            $current = $baseFields[$targetKey];
            if (
                $this->respondent_field_mode_rank($field['field_mode'])
                > $this->respondent_field_mode_rank($current['field_mode'])
            ) {
                $current['field_mode'] = $field['field_mode'];
            }
            if (empty($current['help_text']) && !empty($field['help_text'])) {
                $current['help_text'] = $field['help_text'];
            }
            $fieldHasKbliSource = !empty($field['options']['source']) && $field['options']['source'] === 'ipak_kbli';
            $currentHasKbliSource = !empty($current['options']['source']) && $current['options']['source'] === 'ipak_kbli';
            if ($fieldHasKbliSource || $currentHasKbliSource) {
                if ($fieldHasKbliSource && !$currentHasKbliSource) {
                    $current['options'] = $field['options'];
                    $current['field_options'] = json_encode($current['options'], JSON_UNESCAPED_SLASHES);
                } elseif ($fieldHasKbliSource && $currentHasKbliSource) {
                    $current['options']['display_columns'] = array_values(array_unique(array_merge(
                        isset($current['options']['display_columns']) ? $current['options']['display_columns'] : [],
                        isset($field['options']['display_columns']) ? $field['options']['display_columns'] : []
                    )));
                    $current['field_options'] = json_encode($current['options'], JSON_UNESCAPED_SLASHES);
                }
                $baseFields[$targetKey] = $current;
                continue;
            }
            if (!empty($field['options'])) {
                $currentOptions = !empty($current['options']) && is_array($current['options'])
                    ? $current['options']
                    : [];
                $mergedOptions = [];
                $seenOptionValues = [];
                foreach (array_merge($currentOptions, $field['options']) as $option) {
                    $optionValue = is_array($option) && isset($option['value'])
                        ? (string) $option['value']
                        : (string) $option;
                    if (isset($seenOptionValues[$optionValue])) {
                        continue;
                    }
                    $seenOptionValues[$optionValue] = true;
                    $mergedOptions[] = $option;
                }
                $current['options'] = $mergedOptions;
                $current['field_options'] = json_encode($current['options'], JSON_UNESCAPED_SLASHES);
            }
            $baseFields[$targetKey] = $current;
        }
        return $baseFields;
    }

    public function get_effective_form_fields($formId, array $surveyIds = [])
    {
        $formId = (int) $formId;
        $result = $this->get_form_fields($formId);
        if (!$surveyIds) {
            $surveyIds = $this->get_form_survey_ids($formId);
        }
        $surveyIds = array_values(array_filter(array_unique(array_map('intval', $surveyIds))));
        if (count($surveyIds) >= 2) {
            $standaloneForms = $this->get_standalone_forms_by_survey(false);
            foreach ($surveyIds as $surveyId) {
                if (
                    !isset($standaloneForms[$surveyId])
                    || (int) $standaloneForms[$surveyId]['form_id'] === $formId
                ) {
                    continue;
                }
                $sourceFields = $this->get_form_fields((int) $standaloneForms[$surveyId]['form_id']);
                $result = $this->merge_respondent_field_rows($result, $sourceFields);
            }
        }
        foreach ($result as $fieldKey => $field) {
            if (!empty($field['options']['source']) && $field['options']['source'] === 'ipak_kbli') {
                $selected = isset($field['field_value']) ? trim((string) $field['field_value']) : '';
                $result[$fieldKey]['kbli_options'] = $this->get_kbli_field_options(
                    $field['options'],
                    $this->config->item('ipak_kbli_initial_limit'),
                    $selected
                );
            }
        }
        return $result;
    }

    private function form_field_defaults($requiresResi, $containsNib = false)
    {
        $defaults = [];
        foreach ($this->respondent_field_definitions() as $key => $definition) {
            $mode = 'hidden';
            if ($requiresResi) {
                if (in_array($key, ['name', 'email', 'phone', 'age', 'gender', 'education', 'job'], true)) {
                    $mode = 'required';
                } elseif ($key === 'identity_number') {
                    $mode = 'optional';
                }
            } elseif ($containsNib) {
                if ($key === 'identity_number') {
                    $mode = 'required';
                }
            } elseif ($key === 'email') {
                $mode = 'required';
            }
            $defaults[$key] = array_merge($definition, ['field_mode' => $mode]);
        }
        return $defaults;
    }

    private function survey_ids_include_code(array $surveyIds, $surveyCode)
    {
        $surveyIds = array_values(array_filter(array_unique(array_map('intval', $surveyIds))));
        if (!$surveyIds) {
            return false;
        }
        return $this->db
            ->where_in('id', $surveyIds)
            ->where('UPPER(survey_code) =', strtoupper((string) $surveyCode))
            ->count_all_results('ipak_surveys') > 0;
    }

    private function save_form_fields($formId, array $fieldSettings, $requiresResi, $containsNib)
    {
        $formId = (int) $formId;
        $existing = $this->get_form_fields($formId);
        if (!$fieldSettings && $existing) {
            return true;
        }

        $allowedModes = ['hidden', 'optional', 'required'];
        $allowedGroups = ['access', 'identity'];
        $allowedTypes = ['text', 'email', 'tel', 'number', 'textarea', 'select'];
        $defaults = $this->form_field_defaults($requiresResi, $containsNib);
        $keys = array_values(array_unique(array_merge(array_keys($defaults), array_keys($fieldSettings))));
        $customSortOrder = 200;
        foreach ($keys as $key) {
            $isSystem = isset($defaults[$key]);
            if (!$isSystem && !preg_match('/^custom_[a-z0-9_]{1,23}$/', (string) $key)) {
                continue;
            }
            $default = $isSystem
                ? $defaults[$key]
                : [
                    'label' => 'Kolom tambahan',
                    'help_text' => '',
                    'sort_order' => $customSortOrder++,
                    'field_mode' => 'required',
                    'field_group' => 'identity',
                    'field_type' => 'text',
                ];
            $setting = isset($fieldSettings[$key]) && is_array($fieldSettings[$key])
                ? $fieldSettings[$key]
                : [];
            $mode = isset($setting['mode']) ? trim((string) $setting['mode']) : $default['field_mode'];
            if (!in_array($mode, $allowedModes, true)) {
                $mode = $default['field_mode'];
            }
            $label = isset($setting['label']) ? trim((string) $setting['label']) : $default['label'];
            $helpText = isset($setting['help_text']) ? trim((string) $setting['help_text']) : $default['help_text'];
            if ($label === '') {
                $label = $default['label'];
            }
            $fieldGroup = isset($setting['group']) ? trim((string) $setting['group']) : $default['field_group'];
            if (!in_array($fieldGroup, $allowedGroups, true)) {
                $fieldGroup = $default['field_group'];
            }
            $fieldType = isset($setting['type']) ? trim((string) $setting['type']) : $default['field_type'];
            if (!in_array($fieldType, $allowedTypes, true)) {
                $fieldType = $default['field_type'];
            }
            $options = isset($setting['options'])
                ? $setting['options']
                : (isset($existing[$key]['options']) ? $existing[$key]['options'] : []);
            if (is_string($options)) {
                $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $options))));
            }
            if (!is_array($options)) {
                $options = [];
            }
            $row = [
                'form_id' => $formId,
                'field_key' => $key,
                'field_group' => $fieldGroup,
                'field_type' => $fieldType,
                'field_options' => $options ? json_encode(
                    !empty($isSystem) && $key === 'kbli' ? $options : array_values($options),
                    JSON_UNESCAPED_SLASHES
                ) : null,
                'field_label' => substr($label, 0, 100),
                'field_mode' => $mode,
                'help_text' => substr($helpText, 0, 255),
                'sort_order' => isset($setting['sort_order']) ? (int) $setting['sort_order'] : (int) $default['sort_order'],
                'is_system' => $isSystem ? 1 : 0,
            ];
            $this->db->replace('ipak_form_fields', $row);
        }
        return true;
    }

    public function get_standalone_forms_by_survey($activeOnly = false)
    {
        $this->db
            ->select('s.id AS survey_id,s.survey_code,s.survey_name,s.index_label,s.color,s.is_active AS survey_active,f.id AS form_id,f.form_code,f.form_name,f.is_active AS form_active')
            ->from('ipak_form_surveys fs')
            ->join('ipak_forms f', 'f.id = fs.form_id', 'inner')
            ->join('ipak_surveys s', 's.id = fs.survey_id', 'inner')
            ->where('(SELECT COUNT(*) FROM ipak_form_surveys form_members WHERE form_members.form_id = f.id) = 1', null, false);
        if ($activeOnly) {
            $this->db->where('s.is_active', 1)->where('f.is_active', 1);
        }
        $rows = $this->db
            ->order_by('s.survey_name', 'ASC')
            ->order_by('f.id', 'ASC')
            ->get()
            ->result_array();
        $result = [];
        foreach ($rows as $row) {
            $surveyId = (int) $row['survey_id'];
            if (isset($result[$surveyId])) {
                continue;
            }
            $row['survey_id'] = $surveyId;
            $row['form_id'] = (int) $row['form_id'];
            $row['survey_active'] = (int) $row['survey_active'];
            $row['form_active'] = (int) $row['form_active'];
            $result[$surveyId] = $row;
        }
        return $result;
    }

    public function ensure_standalone_form($surveyId)
    {
        $surveyId = (int) $surveyId;
        $standaloneForms = $this->get_standalone_forms_by_survey(false);
        if (isset($standaloneForms[$surveyId])) {
            return $standaloneForms[$surveyId];
        }

        $survey = $this->db
            ->where('id', $surveyId)
            ->limit(1)
            ->get('ipak_surveys')
            ->row_array();
        if (!$survey) {
            return false;
        }

        $preferredCode = strtoupper(trim((string) $survey['survey_code']));
        $codeExists = $this->db
            ->where('form_code', $preferredCode)
            ->count_all_results('ipak_forms') > 0;
        $formCode = $codeExists
            ? 'MANDIRI-' . substr($preferredCode, 0, 10) . '-' . $surveyId
            : $preferredCode;
        $counter = 2;
        while ($this->db->where('form_code', $formCode)->count_all_results('ipak_forms') > 0) {
            $formCode = 'M-' . $surveyId . '-' . $counter;
            $counter++;
        }

        $this->db->trans_start();
        $formData = [
            'form_code' => $formCode,
            'form_name' => 'Form ' . $survey['survey_name'],
            'description' => 'Form mandiri untuk ' . $survey['survey_name'] . '.',
            'is_default' => 0,
            'is_active' => (int) $survey['is_active'] === 1 ? 1 : 0,
        ];
        if ($this->supports_form_public_visibility()) {
            $formData['is_public_listed'] = 1;
        }
        $this->db->insert('ipak_forms', $formData);
        $formId = (int) $this->db->insert_id();
        $this->db->insert('ipak_form_surveys', [
            'form_id' => $formId,
            'survey_id' => $surveyId,
            'sort_order' => 1,
            'section_label' => $survey['survey_name'],
        ]);
        $this->save_form_fields(
            $formId,
            [],
            strtoupper((string) $survey['survey_code']) === 'SKM',
            strtoupper((string) $survey['survey_code']) === 'NIB'
        );
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return false;
        }

        return [
            'survey_id' => $surveyId,
            'survey_code' => $survey['survey_code'],
            'survey_name' => $survey['survey_name'],
            'index_label' => $survey['index_label'],
            'color' => $survey['color'],
            'survey_active' => (int) $survey['is_active'],
            'form_id' => $formId,
            'form_code' => $formCode,
            'form_name' => 'Form ' . $survey['survey_name'],
            'form_active' => (int) $survey['is_active'],
        ];
    }

    public function get_survey_question_ids($surveyId)
    {
        $rows = $this->db
            ->select('question_id')
            ->where('survey_id', (int) $surveyId)
            ->order_by('sort_order', 'ASC')
            ->get('ipak_survey_questions')
            ->result_array();
        return array_map('intval', array_column($rows, 'question_id'));
    }

    public function get_question_assignments()
    {
        $rows = $this->db
            ->select('sq.question_id,s.id AS survey_id,s.survey_code,s.survey_name')
            ->from('ipak_survey_questions sq')
            ->join('ipak_surveys s', 's.id = sq.survey_id', 'inner')
            ->get()
            ->result_array();
        $result = [];
        foreach ($rows as $row) {
            $questionId = (int) $row['question_id'];
            if (!isset($result[$questionId])) {
                $result[$questionId] = [
                    'survey_id' => (int) $row['survey_id'],
                    'survey_code' => $row['survey_code'],
                    'survey_name' => $row['survey_name'],
                    'survey_ids' => [],
                    'survey_names' => [],
                ];
            }
            $result[$questionId]['survey_ids'][] = (int) $row['survey_id'];
            $result[$questionId]['survey_names'][] = $row['survey_name'];
            $result[$questionId]['survey_name'] = implode(', ', $result[$questionId]['survey_names']);
        }
        return $result;
    }

    public function question_assignment_conflicts(array $questionIds, $excludeSurveyId = 0)
    {
        // Pertanyaan boleh digunakan kembali oleh beberapa survei.
        // Method dipertahankan agar pemanggil lama tetap kompatibel.
        return [];
    }

    private function ensure_shared_question_schema()
    {
        $cacheKey = 'shared_question_unique_index';
        $cached = $this->schema_cache_get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $row = $this->db
            ->query(
                "SELECT COUNT(*) AS total
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'ipak_survey_questions'
                   AND index_name = 'uq_ipak_question_single_survey'"
            )
            ->row_array();

        if (empty($row['total'])) {
            $this->schema_cache_set($cacheKey, true);
            return true;
        }

        $dropped = (bool) $this->db->query(
            'ALTER TABLE ipak_survey_questions DROP INDEX uq_ipak_question_single_survey'
        );
        $this->schema_cache_set($cacheKey, $dropped);
        return $dropped;
    }

    public function get_form_survey_ids($formId)
    {
        $rows = $this->db
            ->select('survey_id')
            ->where('form_id', (int) $formId)
            ->order_by('sort_order', 'ASC')
            ->get('ipak_form_surveys')
            ->result_array();
        return array_map('intval', array_column($rows, 'survey_id'));
    }

    public function get_form_definition($formCode = '', $activeOnly = true)
    {
        $formCode = trim((string) $formCode);
        $this->db->from('ipak_forms');
        if ($formCode !== '') {
            $this->db->where('form_code', $formCode);
        } else {
            $this->db->where('is_default', 1);
        }
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }
        $form = $this->db
            ->order_by('is_default', 'DESC')
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();
        if (!$form) {
            return [];
        }

        $this->db
            ->select('s.*,fs.sort_order AS form_sort_order,fs.section_label')
            ->from('ipak_form_surveys fs')
            ->join('ipak_surveys s', 's.id = fs.survey_id', 'inner')
            ->where('fs.form_id', (int) $form['id']);
        if ($activeOnly) {
            $this->db->where('s.is_active', 1);
        }
        $surveyRows = $this->db
            ->order_by('fs.sort_order', 'ASC')
            ->order_by('s.id', 'ASC')
            ->get()
            ->result_array();

        $form['id'] = (int) $form['id'];
        $form['is_default'] = (int) $form['is_default'];
        $form['is_active'] = (int) $form['is_active'];
        $form['is_public_listed'] = isset($form['is_public_listed'])
            ? (int) $form['is_public_listed']
            : 1;
        $form['surveys'] = [];
        $form['questions'] = [];
        $form['requires_resi'] = false;

        foreach ($surveyRows as $survey) {
            $surveyId = (int) $survey['id'];
            $survey['id'] = $surveyId;
            $survey['is_system_locked'] = isset($survey['is_system_locked']) ? (int) $survey['is_system_locked'] : 0;
            $survey['questions'] = $this->get_questions_for_survey($surveyId, $activeOnly);
            $form['surveys'][$surveyId] = $survey;
            if (strtoupper((string) $survey['survey_code']) === 'SKM') {
                $form['requires_resi'] = true;
            }
            foreach ($survey['questions'] as $questionId => $question) {
                if (!isset($form['questions'][$questionId])) {
                    $question['survey_ids'] = [];
                    $question['survey_codes'] = [];
                    $question['survey_names'] = [];
                    $form['questions'][$questionId] = $question;
                }
                $form['questions'][$questionId]['survey_ids'][] = $surveyId;
                $form['questions'][$questionId]['survey_codes'][] = $survey['survey_code'];
                $form['questions'][$questionId]['survey_names'][] = $survey['survey_name'];
            }
        }
        $form['respondent_fields'] = $this->get_effective_form_fields(
            (int) $form['id'],
            array_keys($form['surveys'])
        );
        return $form;
    }

    public function get_questions_for_survey($surveyId, $activeOnly = true)
    {
        $this->db
            ->select(
                'q.*,sq.sort_order AS survey_sort_order,' .
                'COALESCE(sq.weight_override,q.weight) AS survey_weight,sq.is_required',
                false
            )
            ->from('ipak_survey_questions sq')
            ->join('ipak_questions q', 'q.id = sq.question_id', 'inner')
            ->where('sq.survey_id', (int) $surveyId);
        if ($activeOnly) {
            $this->db->where('q.is_active', 1);
        }
        $questions = $this->db
            ->order_by('sq.sort_order', 'ASC')
            ->order_by('q.id', 'ASC')
            ->get()
            ->result_array();
        if (!$questions) {
            return [];
        }

        $result = [];
        $questionIds = [];
        foreach ($questions as $question) {
            $id = (int) $question['id'];
            $question['id'] = $id;
            $question['weight'] = (float) $question['weight'];
            $question['survey_weight'] = (float) $question['survey_weight'];
            $question['is_required'] = (int) $question['is_required'];
            $question['is_active'] = (int) $question['is_active'];
            $question['options'] = [];
            $result[$id] = $question;
            $questionIds[] = $id;
        }

        $this->db->where_in('question_id', $questionIds);
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }
        $options = $this->db
            ->order_by('question_id', 'ASC')
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get('ipak_answer_options')
            ->result_array();
        foreach ($options as $option) {
            $questionId = (int) $option['question_id'];
            if (!isset($result[$questionId])) {
                continue;
            }
            $option['id'] = (int) $option['id'];
            $option['question_id'] = $questionId;
            $option['option_value'] = (float) $option['option_value'];
            $option['normalized_score'] = (float) $option['normalized_score'];
            $option['is_active'] = (int) $option['is_active'];
            $result[$questionId]['options'][] = $option;
        }
        return $result;
    }

    public function survey_score_category($surveyId, $score)
    {
        $row = $this->db
            ->select('category_label,color')
            ->where('survey_id', (int) $surveyId)
            ->where('minimum_score <=', (float) $score)
            ->where('maximum_score >=', (float) $score)
            ->order_by('sort_order', 'ASC')
            ->limit(1)
            ->get('ipak_survey_score_categories')
            ->row_array();
        return $row
            ? ['label' => $row['category_label'], 'color' => $row['color']]
            : ['label' => '-', 'color' => '#64748b'];
    }

    public function get_response_survey_results($responseId, $responseSource = 'SKM')
    {
        $identity = $this->parse_response_key($responseId, $responseSource);
        $responseId = $identity['id'];
        $responseSource = $identity['source'];

        if ($responseSource === 'SURVEY') {
            return $this->db
                ->select('r.*,COALESCE(r.kode_survei_unik,s.kode_unik) AS kode_survei_unik,s.survey_code,s.survey_name,s.index_label,s.color,f.form_code,f.form_name', false)
                ->from('ipak_submission_surveys r')
                ->join('ipak_surveys s', 's.id = r.survey_id', 'inner')
                ->join('ipak_forms f', 'f.id = r.form_id', 'inner')
                ->where('r.flex_response_id', $responseId)
                ->order_by('s.survey_name', 'ASC')
                ->get()
                ->result_array();
        }

        $parent = $this->db
            ->select('kode,flag_skm,rata,versi_survei,kode_survei_unik')
            ->where('kode', $responseId)
            ->limit(1)
            ->get($this->table)
            ->row_array();
        if ($parent && (int) $parent['flag_skm'] === 1) {
            $legacySurveyId = $this->legacy_skm_survey_id();
            $saved = $this->db
                ->select('r.*,COALESCE(r.kode_survei_unik,s.kode_unik) AS kode_survei_unik,s.survey_code,s.survey_name,s.index_label,s.color,f.form_code,f.form_name', false)
                ->from('ipak_submission_surveys r')
                ->join('ipak_surveys s', 's.id = r.survey_id', 'inner')
                ->join('ipak_forms f', 'f.id = r.form_id', 'inner')
                ->where('r.skm_data_id', $responseId)
                ->where('r.survey_id', $legacySurveyId)
                ->limit(1)
                ->get()
                ->row_array();
            if ($saved) {
                return [$saved];
            }
            $survey = $this->db
                ->where('id', $legacySurveyId)
                ->limit(1)
                ->get('ipak_surveys')
                ->row_array();
            if ($survey) {
                $category = $this->survey_score_category($legacySurveyId, (float) $parent['rata']);
                return [[
                    'id' => 0,
                    'skm_data_id' => $responseId,
                    'form_id' => 0,
                    'survey_id' => $legacySurveyId,
                    'kode_survei_unik' => !empty($parent['kode_survei_unik'])
                        ? $parent['kode_survei_unik']
                        : $survey['kode_unik'],
                    'score' => (float) $parent['rata'],
                    'category_label' => $category['label'],
                    'answer_count' => 0,
                    'weight_total' => 0,
                    'survey_code' => $survey['survey_code'],
                    'survey_name' => $survey['survey_name'],
                    'index_label' => $survey['index_label'],
                    'color' => $survey['color'],
                    'form_code' => 'SKM',
                    'form_name' => 'SKM Legacy',
                ]];
            }
        }
        return $this->db
            ->select('r.*,COALESCE(r.kode_survei_unik,s.kode_unik) AS kode_survei_unik,s.survey_code,s.survey_name,s.index_label,s.color,f.form_code,f.form_name', false)
            ->from('ipak_submission_surveys r')
            ->join('ipak_surveys s', 's.id = r.survey_id', 'inner')
            ->join('ipak_forms f', 'f.id = r.form_id', 'inner')
            ->where('r.skm_data_id', $responseId)
            ->order_by('s.survey_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function survey_code_exists($code, $excludeId = 0)
    {
        $this->db->where('survey_code', strtoupper(trim((string) $code)));
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results('ipak_surveys') > 0;
    }

    public function form_code_exists($code, $excludeId = 0)
    {
        $this->db->where('form_code', strtoupper(trim((string) $code)));
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results('ipak_forms') > 0;
    }

    public function delete_survey($surveyId)
    {
        $surveyId = (int) $surveyId;
        if ($surveyId < 1) {
            return ['ok' => false, 'message' => 'ID survei tidak valid.'];
        }
        $survey = $this->db
            ->where('id', $surveyId)
            ->limit(1)
            ->get('ipak_surveys')
            ->row_array();
        if (!$survey) {
            return ['ok' => false, 'message' => 'Survei tidak ditemukan.'];
        }
        if (!empty($survey['is_system_locked'])) {
            return ['ok' => false, 'message' => 'Survei sistem (SKM) tidak dapat dihapus.'];
        }
        if (!empty($survey['is_mandatory'])) {
            return ['ok' => false, 'message' => 'Survei wajib tidak dapat dihapus.'];
        }

        $responseCount = (int) $this->db
            ->where('survey_id', $surveyId)
            ->count_all_results('ipak_submission_surveys');
        if ($responseCount > 0) {
            return [
                'ok' => false,
                'message' => 'Survei masih mempunyai ' . $responseCount . ' data respons dan tidak dapat dihapus.',
            ];
        }

        $formRows = $this->db
            ->select('form_id')
            ->where('survey_id', $surveyId)
            ->get('ipak_form_surveys')
            ->result_array();
        $formIds = array_values(array_unique(array_column($formRows, 'form_id')));

        $this->db->trans_start();
        foreach ($formIds as $formId) {
            $formId = (int) $formId;
            $surveyCount = (int) $this->db
                ->where('form_id', $formId)
                ->count_all_results('ipak_form_surveys');
            $formRow = $this->db
                ->select('is_default')
                ->where('id', $formId)
                ->limit(1)
                ->get('ipak_forms')
                ->row_array();
            $isDefault = !empty($formRow['is_default']);
            if ($surveyCount <= 1 && !$isDefault) {
                $this->db->where('id', $formId)->delete('ipak_forms');
            } else {
                $this->db
                    ->where('form_id', $formId)
                    ->where('survey_id', $surveyId)
                    ->delete('ipak_form_surveys');
            }
        }
        $this->db->where('id', $surveyId)->delete('ipak_surveys');
        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return [
                'ok' => false,
                'message' => 'Survei belum berhasil dihapus. Periksa kembali relasi data survei.',
            ];
        }
        return [
            'ok' => true,
            'message' => 'Survei "' . $survey['survey_name'] . '" dan seluruh pengaturannya berhasil dihapus.',
        ];
    }

    public function save_survey(array $survey, array $questionIds)
    {
        $questionIds = array_values(array_filter(array_unique(array_map('intval', $questionIds))));
        if (!$questionIds) {
            return false;
        }
        if (!$this->ensure_shared_question_schema()) {
            return false;
        }
        $surveyId = isset($survey['id']) ? (int) $survey['id'] : 0;
        $existingSurvey = [];
        if ($surveyId > 0) {
            $existingSurvey = $this->db
                ->where('id', $surveyId)
                ->limit(1)
                ->get('ipak_surveys')
                ->row_array();
        }
        $existingQuestionLinks = [];
        if ($surveyId > 0) {
            $links = $this->db
                ->where('survey_id', $surveyId)
                ->get('ipak_survey_questions')
                ->result_array();
            foreach ($links as $link) {
                $existingQuestionLinks[(int) $link['question_id']] = $link;
            }
        }
        $isSystemLocked = !empty($existingSurvey['is_system_locked']);
        $data = [
            'survey_code' => $isSystemLocked
                ? $existingSurvey['survey_code']
                : strtoupper(trim((string) $survey['survey_code'])),
            'survey_name' => trim((string) $survey['survey_name']),
            'index_label' => trim((string) $survey['index_label']),
            'description' => trim((string) $survey['description']),
            'color' => trim((string) $survey['color']),
            'is_active' => $isSystemLocked ? 1 : (!empty($survey['is_active']) ? 1 : 0),
            'is_mandatory' => $isSystemLocked ? 1 : (!empty($survey['is_mandatory']) ? 1 : 0),
        ];
        if ($surveyId < 1) {
            $data['kode_unik'] = $this->new_survey_uuid();
        }
        $this->db->trans_start();
        if ($surveyId > 0) {
            $this->db->where('id', $surveyId)->update('ipak_surveys', $data);
        } else {
            $this->db->insert('ipak_surveys', $data);
            $surveyId = (int) $this->db->insert_id();
            $defaults = [
                ['Sangat Baik', 88.31, 100.00, '#0f9f6e', 1],
                ['Baik', 76.61, 88.30, '#3049d8', 2],
                ['Kurang Baik', 65.00, 76.60, '#e59b2f', 3],
                ['Tidak Baik', 0.00, 64.99, '#e35757', 4],
            ];
            foreach ($defaults as $category) {
                $this->db->insert('ipak_survey_score_categories', [
                    'survey_id' => $surveyId,
                    'category_label' => $category[0],
                    'minimum_score' => $category[1],
                    'maximum_score' => $category[2],
                    'color' => $category[3],
                    'sort_order' => $category[4],
                ]);
            }
        }
        $selectedIds = array_fill_keys($questionIds, true);
        $removedIds = array_diff(array_keys($existingQuestionLinks), array_keys($selectedIds));
        if ($removedIds) {
            $this->db
                ->where('survey_id', $surveyId)
                ->where_in('question_id', $removedIds)
                ->delete('ipak_survey_questions');
        }
        $nextSortOrder = 0;
        foreach ($existingQuestionLinks as $link) {
            $nextSortOrder = max($nextSortOrder, (int) $link['sort_order']);
        }
        foreach ($questionIds as $questionId) {
            if ($questionId < 1) {
                continue;
            }
            if (isset($existingQuestionLinks[$questionId])) {
                continue;
            }
            $nextSortOrder++;
            $this->db->insert('ipak_survey_questions', [
                'survey_id' => $surveyId,
                'question_id' => $questionId,
                'sort_order' => $nextSortOrder,
                'weight_override' => null,
                'is_required' => 1,
            ]);
        }
        $this->db->trans_complete();
        return $this->db->trans_status() ? $surveyId : false;
    }

    public function save_form(array $form, array $surveyIds, array $fieldSettings = [])
    {
        $surveyIds = array_values(array_filter(array_unique(array_map('intval', $surveyIds))));
        if (!$surveyIds) {
            return false;
        }
        $formId = isset($form['id']) ? (int) $form['id'] : 0;
        if ($formId > 0) {
            $existingSurveyIds = $this->get_form_survey_ids($formId);
            if (count($existingSurveyIds) === 1 && $surveyIds !== $existingSurveyIds) {
                return false;
            }
            if (count($existingSurveyIds) > 1 && count($surveyIds) < 2) {
                return false;
            }
        } elseif (count($surveyIds) === 1) {
            $standaloneForms = $this->get_standalone_forms_by_survey(false);
            if (isset($standaloneForms[$surveyIds[0]])) {
                return false;
            }
        }
        $requiresResi = $this->survey_ids_include_code($surveyIds, 'SKM');
        $containsNib = $this->survey_ids_include_code($surveyIds, 'NIB');
        $data = [
            'form_code' => strtoupper(trim((string) $form['form_code'])),
            'form_name' => trim((string) $form['form_name']),
            'description' => trim((string) $form['description']),
            'is_default' => !empty($form['is_default']) ? 1 : 0,
            'is_active' => !empty($form['is_active']) ? 1 : 0,
        ];
        if ($this->supports_form_public_visibility()) {
            if (array_key_exists('is_public_listed', $form)) {
                $data['is_public_listed'] = !empty($form['is_public_listed']) ? 1 : 0;
            } elseif ($formId > 0) {
                $existingVisibility = $this->db
                    ->select('is_public_listed')
                    ->where('id', $formId)
                    ->limit(1)
                    ->get('ipak_forms')
                    ->row_array();
                $data['is_public_listed'] = $existingVisibility
                    ? (int) $existingVisibility['is_public_listed']
                    : 1;
            } else {
                $data['is_public_listed'] = 1;
            }
        }
        $this->db->trans_start();
        if ($data['is_default']) {
            $this->db->update('ipak_forms', ['is_default' => 0]);
        }
        if ($formId > 0) {
            $this->db->where('id', $formId)->update('ipak_forms', $data);
        } else {
            $this->db->insert('ipak_forms', $data);
            $formId = (int) $this->db->insert_id();
        }
        $this->db->where('form_id', $formId)->delete('ipak_form_surveys');
        $sortOrder = 0;
        foreach ($surveyIds as $surveyId) {
            if ($surveyId < 1) {
                continue;
            }
            $sortOrder++;
            $this->db->insert('ipak_form_surveys', [
                'form_id' => $formId,
                'survey_id' => $surveyId,
                'sort_order' => $sortOrder,
            ]);
        }
        $hasDefault = $this->db
            ->where('is_default', 1)
            ->where('is_active', 1)
            ->count_all_results('ipak_forms');
        if (!$hasDefault) {
            $this->db->where('id', $formId)->update('ipak_forms', ['is_default' => 1, 'is_active' => 1]);
        }
        $this->save_form_fields($formId, $fieldSettings, $requiresResi, $containsNib);
        $this->db->trans_complete();
        return $this->db->trans_status() ? $formId : false;
    }

    public function create_wizard_form(
        array $survey,
        array $form,
        array $existingQuestionIds,
        array $newQuestions,
        array $fieldSettings
    ) {
        $this->db->trans_start();
        $questionIds = array_values(array_filter(array_unique(array_map('intval', $existingQuestionIds))));
        foreach ($newQuestions as $questionPackage) {
            if (empty($questionPackage['question']) || empty($questionPackage['options'])) {
                $this->db->trans_rollback();
                return false;
            }
            $questionId = $this->save_question($questionPackage['question'], $questionPackage['options']);
            if (!$questionId) {
                $this->db->trans_rollback();
                return false;
            }
            $questionIds[] = (int) $questionId;
        }

        if (!$questionIds) {
            $this->db->trans_rollback();
            return false;
        }
        $surveyId = $this->save_survey($survey, $questionIds);
        if (!$surveyId) {
            $this->db->trans_rollback();
            return false;
        }
        $formId = $this->save_form($form, [(int) $surveyId], $fieldSettings);
        if (!$formId) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return false;
        }
        return [
            'form_id' => (int) $formId,
            'survey_id' => (int) $surveyId,
            'question_ids' => $questionIds,
        ];
    }

    public function get_questions($activeOnly = true)
    {
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }
        $questions = $this->db
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get('ipak_questions')
            ->result_array();

        if (!$questions) {
            return [];
        }

        $questionIds = [];
        $result = [];
        foreach ($questions as $question) {
            $id = (int) $question['id'];
            $question['id'] = $id;
            $question['weight'] = (float) $question['weight'];
            $question['is_active'] = (int) $question['is_active'];
            $question['options'] = [];
            $result[$id] = $question;
            $questionIds[] = $id;
        }

        $this->db->where_in('question_id', $questionIds);
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }
        $options = $this->db
            ->order_by('question_id', 'ASC')
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get('ipak_answer_options')
            ->result_array();

        foreach ($options as $option) {
            $questionId = (int) $option['question_id'];
            if (!isset($result[$questionId])) {
                continue;
            }
            $option['id'] = (int) $option['id'];
            $option['question_id'] = $questionId;
            $option['option_value'] = (float) $option['option_value'];
            $option['normalized_score'] = (float) $option['normalized_score'];
            $option['is_active'] = (int) $option['is_active'];
            $result[$questionId]['options'][] = $option;
        }

        return $result;
    }

    public function build_answer_details(array $submitted, array $questions = [])
    {
        if (!$questions) {
            $questions = $this->get_questions(true);
        }
        $details = [];
        foreach ($questions as $questionId => $question) {
            $field = 'answer_' . (int) $questionId;
            $selectedId = isset($submitted[$field]) ? (int) $submitted[$field] : 0;
            $selected = null;
            foreach ($question['options'] as $option) {
                if ((int) $option['id'] === $selectedId && (int) $option['is_active'] === 1) {
                    $selected = $option;
                    break;
                }
            }
            if (!$selected) {
                return false;
            }
            $details[] = [
                'question_id' => (int) $questionId,
                'answer_option_id' => (int) $selected['id'],
                'option_value' => (float) $selected['option_value'],
                'normalized_score' => (float) $selected['normalized_score'],
                'question_text' => $question['question_text'],
                'option_label' => $selected['option_label'],
                'measurement_name' => $question['measurement_name'],
                'category_name' => $question['category_name'],
                'weight' => (float) $question['weight'],
            ];
        }
        return $details;
    }

    public function save_question(array $question, array $options)
    {
        $questionId = isset($question['id']) ? (int) $question['id'] : 0;
        $questionData = [
            'question_code' => trim((string) $question['question_code']),
            'question_text' => trim((string) $question['question_text']),
            'measurement_name' => trim((string) $question['measurement_name']),
            'category_name' => trim((string) $question['category_name']),
            'weight' => max(0.01, (float) $question['weight']),
            'sort_order' => (int) $question['sort_order'],
            'is_active' => !empty($question['is_active']) ? 1 : 0,
        ];

        $this->db->trans_start();
        if ($questionId > 0) {
            $this->db->where('id', $questionId)->update('ipak_questions', $questionData);
        } else {
            $this->db->insert('ipak_questions', $questionData);
            $questionId = (int) $this->db->insert_id();
        }

        foreach ($options as $index => $option) {
            $optionData = [
                'question_id' => $questionId,
                'option_code' => trim((string) $option['option_code']),
                'option_label' => trim((string) $option['option_label']),
                'option_value' => (float) $option['option_value'],
                'normalized_score' => max(0, min(100, (float) $option['normalized_score'])),
                'sort_order' => isset($option['sort_order']) ? (int) $option['sort_order'] : ($index + 1),
                'is_active' => !empty($option['is_active']) ? 1 : 0,
            ];
            $optionId = isset($option['id']) ? (int) $option['id'] : 0;
            if ($optionId > 0) {
                $this->db
                    ->where('id', $optionId)
                    ->where('question_id', $questionId)
                    ->update('ipak_answer_options', $optionData);
            } else {
                $this->db->insert('ipak_answer_options', $optionData);
            }
        }
        $this->db->trans_complete();

        return $this->db->trans_status() ? $questionId : false;
    }

    public function question_code_exists($code, $excludeId = 0)
    {
        $this->db->where('question_code', trim((string) $code));
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results('ipak_questions') > 0;
    }

    public function set_question_active($questionId, $isActive)
    {
        return $this->db
            ->where('id', (int) $questionId)
            ->update('ipak_questions', ['is_active' => $isActive ? 1 : 0]);
    }

    public function get_response_answers($responseId, $responseSource = 'SKM')
    {
        $identity = $this->parse_response_key($responseId, $responseSource);
        return $this->db
            ->select('ra.*,q.question_code')
            ->from('ipak_response_answers ra')
            ->join('ipak_questions q', 'q.id = ra.question_id', 'left')
            ->where(
                $identity['source'] === 'SURVEY' ? 'ra.flex_response_id' : 'ra.skm_data_id',
                $identity['id']
            )
            ->order_by('q.sort_order', 'ASC')
            ->order_by('ra.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function get_response_fields($responseId, $responseSource = 'SKM')
    {
        $identity = $this->parse_response_key($responseId, $responseSource);
        $rows = $this->db
            ->where(
                $identity['source'] === 'SURVEY' ? 'flex_response_id' : 'skm_data_id',
                $identity['id']
            )
            ->order_by('field_group', 'ASC')
            ->order_by('id', 'ASC')
            ->get('ipak_response_fields')
            ->result_array();
        foreach ($rows as $index => $row) {
            if ($row['field_key'] !== 'kbli') {
                continue;
            }
            $snapshot = json_decode((string) $row['field_value'], true);
            if (!is_array($snapshot) || !isset($snapshot['kbli_id'])) {
                continue;
            }
            $rows[$index]['kbli_id'] = (int) $snapshot['kbli_id'];
            $rows[$index]['kbli_kode'] = isset($snapshot['kode']) ? (string) $snapshot['kode'] : '';
            $rows[$index]['kbli_kode_gabungan'] = isset($snapshot['kode_gabungan']) ? (string) $snapshot['kode_gabungan'] : '';
            $rows[$index]['field_value'] = isset($snapshot['display_value']) ? (string) $snapshot['display_value'] : '';
        }
        return $rows;
    }

    private function apply_filters(array $filters, $alias = '')
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $this->db->group_start()
            ->like($prefix . 'data_skm_id', 'IPAK:', 'after')
            ->or_like($prefix . 'data_skm_id', 'FLEX:', 'after')
            ->group_end();

        if (!empty($filters['date_from'])) {
            $this->db->where($prefix . 'tgl_pengisian >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where($prefix . 'tgl_pengisian <=', $filters['date_to']);
        }
        if (!empty($filters['gender'])) {
            $this->db->where($prefix . 'gender', (int) $filters['gender']);
        }
        if (!empty($filters['education'])) {
            $this->db->where($prefix . 'pendidikan_id', (int) $filters['education']);
        }
        if (!empty($filters['job'])) {
            $this->db->where($prefix . 'pekerjaan_id', (int) $filters['job']);
        }
        if (!empty($filters['service'])) {
            $this->db->where($prefix . 'sektor', (int) $filters['service']);
        }
        if (!empty($filters['survey_type']) && in_array($filters['survey_type'], ['SKM', 'SURVEY'], true)) {
            $this->db->where($prefix . 'jenis_survei', $filters['survey_type']);
        }
        if (!empty($filters['unit_id'])) {
            $unitId = (int) $filters['unit_id'];
            $metadataUnit = "CAST(JSON_UNQUOTE(JSON_EXTRACT(" .
                "IF(JSON_VALID(" . $prefix . "keterangan), " . $prefix . "keterangan, '{}'), " .
                "'$.unit_id')) AS UNSIGNED)";
            $this->db->group_start()
                ->where(
                    $prefix . 'jenis_ijin IN (SELECT id FROM trperizinan WHERE dinas_pengelola = ' . $unitId . ')',
                    null,
                    false
                )
                ->or_where($metadataUnit . ' = ' . $unitId, null, false)
                ->group_end();
        }
        if (!empty($filters['keyword'])) {
            $keyword = trim($filters['keyword']);
            $this->db->group_start()
                ->like($prefix . 'resi', $keyword)
                ->or_like($prefix . 'nama_responden', $keyword)
                ->or_like($prefix . 'mobile', $keyword)
                ->or_like($prefix . 'responden', $keyword)
                ->or_like($prefix . 'kode_survei_unik', $keyword)
                ->or_like($prefix . 'kode_pengisian', $keyword)
                ->or_like($prefix . 'versi_survei', $keyword)
                ->group_end();
        }
    }

    public function summary(array $filters = [])
    {
        $surveyId = isset($filters['survey_id']) ? (int) $filters['survey_id'] : 0;
        if ($surveyId > 0 && $this->is_legacy_skm_survey($surveyId)) {
            $this->db
                ->select(
                    'COUNT(*) AS total_responses,COALESCE(AVG(rata),0) AS average_score,' .
                    'COALESCE(MIN(rata),0) AS minimum_score,COALESCE(MAX(rata),0) AS maximum_score',
                    false
                )
                ->from($this->table)
                ->where('flag_skm', 1);
            $this->apply_filters($filters);
            return $this->db->get()->row_array();
        }
        if ($surveyId > 0) {
            $this->db
                ->select(
                    'COUNT(*) AS total_responses,COALESCE(AVG(r.score),0) AS average_score,' .
                    'COALESCE(MIN(r.score),0) AS minimum_score,COALESCE(MAX(r.score),0) AS maximum_score',
                    false
                )
                ->from('ipak_submission_surveys r')
                ->join($this->flexResponseTable . ' s', 's.kode = r.flex_response_id', 'inner')
                ->where('r.survey_id', $surveyId);
            $this->apply_filters($filters, 's');
            return $this->db->get()->row_array();
        }
        $this->db->select('COUNT(*) AS total_responses, COALESCE(AVG(rata), 0) AS average_score, COALESCE(MIN(rata), 0) AS minimum_score, COALESCE(MAX(rata), 0) AS maximum_score', false);
        $this->apply_filters($filters);
        return $this->db->get($this->allResponsesView)->row_array();
    }

    public function monthly_scores($year, array $filters = [])
    {
        $surveyId = isset($filters['survey_id']) ? (int) $filters['survey_id'] : 0;
        if ($surveyId > 0 && $this->is_legacy_skm_survey($surveyId)) {
            $this->db
                ->select('MONTH(tgl_pengisian) AS month_no,COUNT(*) AS total,AVG(rata) AS score', false)
                ->from($this->table)
                ->where('flag_skm', 1)
                ->where('YEAR(tgl_pengisian)', (int) $year, false);
            $this->apply_filters($filters);
            $groupField = 'MONTH(tgl_pengisian)';
        } elseif ($surveyId > 0) {
            $this->db
                ->select('MONTH(s.tgl_pengisian) AS month_no,COUNT(*) AS total,AVG(r.score) AS score', false)
                ->from('ipak_submission_surveys r')
                ->join($this->flexResponseTable . ' s', 's.kode = r.flex_response_id', 'inner')
                ->where('r.survey_id', $surveyId)
                ->where('YEAR(s.tgl_pengisian)', (int) $year, false);
            $this->apply_filters($filters, 's');
            $groupField = 'MONTH(s.tgl_pengisian)';
        } else {
            $this->db
                ->select('MONTH(tgl_pengisian) AS month_no, COUNT(*) AS total, AVG(rata) AS score', false)
                ->from($this->allResponsesView)
                ->where('YEAR(tgl_pengisian)', (int) $year, false);
            $this->apply_filters($filters);
            $groupField = 'MONTH(tgl_pengisian)';
        }
        $rows = $this->db->group_by($groupField, false)
            ->order_by('month_no', 'ASC')
            ->get()
            ->result_array();

        $result = array_fill(1, 12, ['total' => 0, 'score' => 0]);
        foreach ($rows as $row) {
            $result[(int) $row['month_no']] = [
                'total' => (int) $row['total'],
                'score' => round((float) $row['score'], 2),
            ];
        }
        return $result;
    }

    public function dimension_chart($year, $dimension, $unitId = 0, $surveyId = 0, array $extraFilters = [])
    {
        $surveyId = (int) $surveyId;
        $chartFilters = $extraFilters;
        $chartFilters['unit_id'] = (int) $unitId;
        unset($chartFilters['survey_id']);
        $surveyLabel = 'Semua Hasil Survei';
        if ($surveyId > 0) {
            $survey = $this->db
                ->select('index_label')
                ->where('id', $surveyId)
                ->limit(1)
                ->get('ipak_surveys')
                ->row_array();
            if ($survey) {
                $surveyLabel = $survey['index_label'];
            }
        }
        $definitions = [
            'overall' => [
                'title' => $surveyLabel . ' Keseluruhan',
                'type' => 'line',
                'groups' => ['overall' => $surveyLabel],
            ],
            'gender' => [
                'title' => $surveyLabel . ' Berdasarkan Jenis Kelamin',
                'type' => 'column',
                'groups' => ['1' => 'Laki-laki', '2' => 'Perempuan'],
            ],
            'age' => [
                'title' => $surveyLabel . ' Berdasarkan Usia',
                'type' => 'column',
                'groups' => [
                    'age_1' => '15–16 tahun',
                    'age_2' => '17–25 tahun',
                    'age_3' => '26–35 tahun',
                    'age_4' => '36–45 tahun',
                    'age_5' => '46–55 tahun',
                    'age_6' => '56–65 tahun',
                    'age_7' => 'Di atas 65 tahun',
                ],
            ],
            'education' => [
                'title' => $surveyLabel . ' Berdasarkan Pendidikan',
                'type' => 'column',
                'groups' => [
                    '1' => 'SD',
                    '2' => 'SMP',
                    '3' => 'SMA/SMK',
                    '4' => 'Diploma',
                    '5' => 'Sarjana',
                    '6' => 'Pascasarjana',
                ],
            ],
            'job' => [
                'title' => $surveyLabel . ' Berdasarkan Pekerjaan',
                'type' => 'column',
                'groups' => [
                    '1' => 'ASN',
                    '2' => 'Pegawai Swasta',
                    '3' => 'Wiraswasta',
                    '4' => 'TNI/POLRI',
                    '5' => 'Lainnya',
                ],
            ],
            'service' => [
                'title' => $surveyLabel . ' Berdasarkan Sektor Layanan',
                'type' => 'bar',
                'groups' => [],
            ],
        ];

        if (!isset($definitions[$dimension])) {
            $dimension = 'overall';
        }
        $definition = $definitions[$dimension];

        if ($surveyId > 0 && $this->is_legacy_skm_survey($surveyId)) {
            $this->db
                ->select('tgl_pengisian,rata,gender,usia,pendidikan_id,pekerjaan_id,sektor')
                ->from($this->table)
                ->where('flag_skm', 1)
                ->where('YEAR(tgl_pengisian)', (int) $year, false);
            $this->apply_filters($chartFilters);
        } elseif ($surveyId > 0) {
            $this->db
                ->select('s.tgl_pengisian,r.score AS rata,s.gender,s.usia,s.pendidikan_id,s.pekerjaan_id,s.sektor', false)
                ->from('ipak_submission_surveys r')
                ->join($this->flexResponseTable . ' s', 's.kode = r.flex_response_id', 'inner')
                ->where('r.survey_id', $surveyId)
                ->where('YEAR(s.tgl_pengisian)', (int) $year, false);
            $this->apply_filters($chartFilters, 's');
        } else {
            $this->db
                ->select('tgl_pengisian,rata,gender,usia,pendidikan_id,pekerjaan_id,sektor')
                ->from($this->allResponsesView)
                ->where('YEAR(tgl_pengisian)', (int) $year, false);
            $this->apply_filters($chartFilters);
        }
        $rows = $this->db->get()->result_array();

        if ($dimension === 'service') {
            $usedSectorIds = [];
            foreach ($rows as $row) {
                $sectorId = (int) $row['sektor'];
                if ($sectorId > 0) {
                    $usedSectorIds[$sectorId] = $sectorId;
                }
            }
            if ($usedSectorIds) {
                $allSectorLabels = $this->sector_options();
                foreach ($usedSectorIds as $sectorId) {
                    $definition['groups'][(string) $sectorId] = isset($allSectorLabels[$sectorId])
                        ? $allSectorLabels[$sectorId]
                        : 'Sektor ' . $sectorId;
                }
            }
            if (!$definition['groups']) {
                return [
                    'dimension' => $dimension,
                    'title' => $definition['title'],
                    'type' => $definition['type'],
                    'categories' => [],
                    'series' => [],
                ];
            }
        }

        $sums = [];
        $counts = [];
        foreach ($definition['groups'] as $key => $label) {
            $sums[(string) $key] = array_fill(1, 12, 0.0);
            $counts[(string) $key] = array_fill(1, 12, 0);
        }

        foreach ($rows as $row) {
            $month = (int) date('n', strtotime($row['tgl_pengisian']));
            $groupKey = $this->chart_group_key($row, $dimension);
            if ($month < 1 || $month > 12 || $groupKey === null || !isset($sums[$groupKey])) {
                continue;
            }
            $sums[$groupKey][$month] += (float) $row['rata'];
            $counts[$groupKey][$month]++;
        }

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $series = [];

        if ($dimension === 'service') {
            for ($month = 1; $month <= 12; $month++) {
                $data = [];
                $responseCounts = [];
                foreach ($definition['groups'] as $key => $label) {
                    $key = (string) $key;
                    $data[] = $counts[$key][$month]
                        ? round($sums[$key][$month] / $counts[$key][$month], 2)
                        : 0;
                    $responseCounts[] = $counts[$key][$month];
                }
                $series[] = [
                    'name' => $monthNames[$month - 1],
                    'data' => $data,
                    'responseCounts' => $responseCounts,
                ];
            }

            return [
                'dimension' => $dimension,
                'title' => $definition['title'],
                'type' => $definition['type'],
                'categories' => array_values($definition['groups']),
                'series' => $series,
            ];
        }

        foreach ($definition['groups'] as $key => $label) {
            $key = (string) $key;
            $data = [];
            $responseCounts = [];
            for ($month = 1; $month <= 12; $month++) {
                $data[] = $counts[$key][$month]
                    ? round($sums[$key][$month] / $counts[$key][$month], 2)
                    : 0;
                $responseCounts[] = $counts[$key][$month];
            }
            $series[] = [
                'name' => $label,
                'data' => $data,
                'responseCounts' => $responseCounts,
            ];
        }

        return [
            'dimension' => $dimension,
            'title' => $definition['title'],
            'type' => $definition['type'],
            'categories' => $monthNames,
            'series' => $series,
        ];
    }

    private function chart_group_key(array $row, $dimension)
    {
        switch ($dimension) {
            case 'gender':
                return (string) (int) $row['gender'];
            case 'age':
                $age = (int) $row['usia'];
                if ($age <= 16) return 'age_1';
                if ($age <= 25) return 'age_2';
                if ($age <= 35) return 'age_3';
                if ($age <= 45) return 'age_4';
                if ($age <= 55) return 'age_5';
                if ($age <= 65) return 'age_6';
                return 'age_7';
            case 'education':
                return (string) (int) $row['pendidikan_id'];
            case 'job':
                return (string) (int) $row['pekerjaan_id'];
            case 'service':
                return (string) (int) $row['sektor'];
            default:
                return 'overall';
        }
    }

    public function question_averages(array $filters = [])
    {
        $surveyId = isset($filters['survey_id']) ? (int) $filters['survey_id'] : 0;
        if ($surveyId > 0 && $this->is_legacy_skm_survey($surveyId)) {
            $questions = $this->get_questions_for_survey($surveyId, true);
            $this->db
                ->select('data_skm_nilai')
                ->from($this->table)
                ->where('flag_skm', 1);
            $this->apply_filters($filters);
            $rows = $this->db->get()->result_array();
            $sums = [];
            $counts = [];
            $questionIds = array_slice(array_keys($questions), 0, 10);
            foreach ($rows as $row) {
                $values = explode(',', (string) $row['data_skm_nilai']);
                foreach ($questionIds as $position => $questionId) {
                    $value = isset($values[$position]) ? (float) trim($values[$position]) : 0;
                    if ($value <= 0) {
                        continue;
                    }
                    if (!isset($sums[$questionId])) {
                        $sums[$questionId] = 0.0;
                        $counts[$questionId] = 0;
                    }
                    $sums[$questionId] += $value * 25;
                    $counts[$questionId]++;
                }
            }
            $averages = [];
            foreach ($sums as $questionId => $sum) {
                $averages[(int) $questionId] = $counts[$questionId] > 0
                    ? round($sum / $counts[$questionId], 2)
                    : 0;
            }
            return $averages;
        }
        $this->db
            ->select('ra.question_id,AVG(ra.normalized_score) AS average_score,COUNT(*) AS total_answers', false)
            ->from('ipak_response_answers ra');
        if ($surveyId > 0) {
            $this->db
                ->join('ipak_submission_survey_answers rsa', 'rsa.response_answer_id = ra.id', 'inner')
                ->join('ipak_submission_surveys sr', 'sr.id = rsa.survey_result_id', 'inner')
                ->join($this->flexResponseTable . ' s', 's.kode = ra.flex_response_id', 'inner')
                ->where('sr.survey_id', $surveyId);
        } else {
            $this->db->join(
                $this->allResponsesView . ' s',
                "(s.response_source = 'SKM' AND s.kode = ra.skm_data_id) OR " .
                "(s.response_source = 'SURVEY' AND s.kode = ra.flex_response_id)",
                'inner',
                false
            );
        }
        $this->apply_filters($filters, 's');
        $rows = $this->db
            ->group_by('ra.question_id')
            ->get()
            ->result_array();
        $averages = [];
        foreach ($rows as $row) {
            $averages[(int) $row['question_id']] = round((float) $row['average_score'], 2);
        }
        return $averages;
    }

    public function answer_distribution(array $filters = [])
    {
        $surveyId = isset($filters['survey_id']) ? (int) $filters['survey_id'] : 0;
        if ($surveyId > 0 && $this->is_legacy_skm_survey($surveyId)) {
            $this->db
                ->select('data_skm_nilai')
                ->from($this->table)
                ->where('flag_skm', 1);
            $this->apply_filters($filters);
            $rows = $this->db->get()->result_array();
            $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            foreach ($rows as $row) {
                $values = array_slice(explode(',', (string) $row['data_skm_nilai']), 0, 10);
                foreach ($values as $value) {
                    $score = (float) trim($value) * 25;
                    if ($score <= 0) continue;
                    if ($score < 50) $distribution[1]++;
                    elseif ($score < 75) $distribution[2]++;
                    elseif ($score < 100) $distribution[3]++;
                    else $distribution[4]++;
                }
            }
            return $distribution;
        }
        $this->db
            ->select('ra.normalized_score')
            ->from('ipak_response_answers ra');
        if ($surveyId > 0) {
            $this->db
                ->join('ipak_submission_survey_answers rsa', 'rsa.response_answer_id = ra.id', 'inner')
                ->join('ipak_submission_surveys sr', 'sr.id = rsa.survey_result_id', 'inner')
                ->join($this->flexResponseTable . ' s', 's.kode = ra.flex_response_id', 'inner')
                ->where('sr.survey_id', $surveyId);
        } else {
            $this->db->join(
                $this->allResponsesView . ' s',
                "(s.response_source = 'SKM' AND s.kode = ra.skm_data_id) OR " .
                "(s.response_source = 'SURVEY' AND s.kode = ra.flex_response_id)",
                'inner',
                false
            );
        }
        $this->apply_filters($filters, 's');
        $rows = $this->db->get()->result_array();
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        foreach ($rows as $row) {
            $score = (float) $row['normalized_score'];
            if ($score < 50) $distribution[1]++;
            elseif ($score < 75) $distribution[2]++;
            elseif ($score < 100) $distribution[3]++;
            else $distribution[4]++;
        }
        return $distribution;
    }

    public function count_responses(array $filters = [])
    {
        $surveyId = isset($filters['survey_id']) ? (int) $filters['survey_id'] : 0;
        if ($surveyId > 0 && $this->is_legacy_skm_survey($surveyId)) {
            $this->db
                ->from($this->table)
                ->where('flag_skm', 1);
            $this->apply_filters($filters);
            return (int) $this->db->count_all_results();
        }
        if ($surveyId > 0) {
            $this->db
                ->from('ipak_submission_surveys r')
                ->join($this->flexResponseTable . ' s', 's.kode = r.flex_response_id', 'inner')
                ->where('r.survey_id', $surveyId);
            $this->apply_filters($filters, 's');
            return (int) $this->db->count_all_results();
        }
        $this->apply_filters($filters);
        return (int) $this->db->count_all_results($this->allResponsesView);
    }

    private function start_response_option_query($select, array $filters = [])
    {
        $surveyId = isset($filters['survey_id']) ? (int) $filters['survey_id'] : 0;
        $isLegacySurvey = $surveyId > 0 && $this->is_legacy_skm_survey($surveyId);
        if ($isLegacySurvey) {
            $this->db
                ->select($select, false)
                ->from($this->table . ' s')
                ->where('s.flag_skm', 1);
        } elseif ($surveyId > 0) {
            $this->db
                ->select($select, false)
                ->from($this->flexResponseTable . ' s')
                ->join('ipak_submission_surveys r', 'r.flex_response_id = s.kode', 'inner')
                ->where('r.survey_id', $surveyId);
        } else {
            $this->db
                ->select($select, false)
                ->from($this->allResponsesView . ' s');
        }
        $this->apply_filters($filters, 's');
    }

    public function response_year_options()
    {
        $this->start_response_option_query('YEAR(s.tgl_pengisian) AS option_year');
        $rows = $this->db
            ->where('s.tgl_pengisian IS NOT NULL', null, false)
            ->group_by('YEAR(s.tgl_pengisian)', false)
            ->order_by('option_year', 'DESC')
            ->get()
            ->result_array();
        $result = [];
        foreach ($rows as $row) {
            $year = (int) $row['option_year'];
            if ($year > 0) {
                $result[$year] = $year;
            }
        }
        return $result;
    }

    public function surveys_with_responses(array $filters = [], $activeOnly = true)
    {
        unset($filters['survey_id']);
        $surveys = $this->get_surveys($activeOnly);
        foreach ($surveys as $surveyId => $survey) {
            $surveyFilters = $filters;
            $surveyFilters['survey_id'] = (int) $surveyId;
            if ($this->count_responses($surveyFilters) < 1) {
                unset($surveys[$surveyId]);
            }
        }
        return $surveys;
    }

    public function response_unit_options(array $filters = [])
    {
        unset($filters['unit_id']);
        $metadataUnit = "CAST(JSON_UNQUOTE(JSON_EXTRACT(" .
            "IF(JSON_VALID(s.keterangan), s.keterangan, '{}'), '$.unit_id')) AS UNSIGNED)";
        $unitExpression = 'COALESCE(NULLIF(' . $metadataUnit . ', 0), NULLIF(p.dinas_pengelola, 0), 0)';
        $this->start_response_option_query($unitExpression . ' AS option_value', $filters);
        $query = $this->db
            ->join('trperizinan p', 'p.id = s.jenis_ijin', 'left')
            ->where($unitExpression . ' > 0', null, false)
            ->group_by($unitExpression, false)
            ->get();
        if (!$query) {
            return [];
        }
        $rows = $query->result_array();
        $ids = [];
        foreach ($rows as $row) {
            $value = (int) $row['option_value'];
            if ($value > 0) {
                $ids[$value] = $value;
            }
        }
        if (!$ids) {
            return [];
        }
        if (!$this->db->table_exists('trunitkerja')) {
            return [];
        }
        $unitRows = $this->db
            ->select('id,n_unitkerja')
            ->where_in('id', array_values($ids))
            ->order_by(
                "CASE WHEN n_unitkerja = 'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT' THEN 1 ELSE 2 END",
                '',
                false
            )
            ->order_by('n_unitkerja', 'ASC')
            ->get('trunitkerja')
            ->result_array();
        $result = [];
        foreach ($unitRows as $row) {
            $result[(int) $row['id']] = $row['n_unitkerja'];
        }
        return $result;
    }

    public function response_distinct_values($field, array $filters = [])
    {
        $definitions = [
            'gender' => ['column' => 'gender', 'filter' => 'gender'],
            'age' => ['column' => 'usia', 'filter' => ''],
            'education' => ['column' => 'pendidikan_id', 'filter' => 'education'],
            'job' => ['column' => 'pekerjaan_id', 'filter' => 'job'],
            'service' => ['column' => 'sektor', 'filter' => 'service'],
        ];
        if (!isset($definitions[$field])) {
            return [];
        }
        $definition = $definitions[$field];
        if ($definition['filter'] !== '') {
            unset($filters[$definition['filter']]);
        }
        $column = 's.' . $definition['column'];
        $this->start_response_option_query($column . ' AS option_value', $filters);
        $query = $this->db
            ->where($column . ' >', 0)
            ->group_by($column)
            ->order_by($column, 'ASC')
            ->get();
        if (!$query) {
            return [];
        }
        $rows = $query->result_array();
        $result = [];
        foreach ($rows as $row) {
            $value = (int) $row['option_value'];
            if ($value > 0) {
                $result[$value] = $value;
            }
        }
        return $result;
    }

    public function response_survey_type_options(array $filters = [])
    {
        unset($filters['survey_type']);
        $this->start_response_option_query('s.jenis_survei AS option_value', $filters);
        $rows = $this->db
            ->where_in('s.jenis_survei', ['SKM', 'SURVEY'])
            ->group_by('s.jenis_survei')
            ->order_by('s.jenis_survei', 'ASC')
            ->get()
            ->result_array();
        $labels = ['SKM' => 'SKM', 'SURVEY' => 'Survei biasa'];
        $result = [];
        foreach ($rows as $row) {
            $value = strtoupper(trim((string) $row['option_value']));
            if (isset($labels[$value])) {
                $result[$value] = $labels[$value];
            }
        }
        return $result;
    }

    public function sector_options()
    {
        $rows = $this->db
            ->select('id,n_sektor')
            ->order_by('urutan', 'ASC')
            ->order_by('n_sektor', 'ASC')
            ->get('trsektor')
            ->result_array();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = $row['n_sektor'];
        }
        return $result;
    }

    public function unit_options()
    {
        $rows = $this->get_units(true);
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = $row['n_unitkerja'];
        }
        return $result;
    }

    public function default_regular_unit()
    {
        if (!$this->db->table_exists('trunitkerja')) {
            return [];
        }
        $row = $this->db
            ->select('id,n_unitkerja')
            ->where(
                'n_unitkerja',
                'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT'
            )
            ->limit(1)
            ->get('trunitkerja')
            ->row_array();
        if (!$row) {
            return [];
        }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    public function get_units($visibleOnly = false)
    {
        if (!$this->db->table_exists('trunitkerja')) {
            return [];
        }

        $this->db
            ->select('u.id,u.n_unitkerja,u.nm_cap,COUNT(p.id) AS service_count', false)
            ->from('trunitkerja u')
            ->join('trperizinan p', 'p.dinas_pengelola = u.id', 'left')
            ->where('u.n_unitkerja !=', '-');
        if ($visibleOnly) {
            $this->db->where("COALESCE(u.nm_cap, '') NOT LIKE '!%'", null, false);
        }
        $rows = $this->db
            ->group_by(['u.id', 'u.n_unitkerja', 'u.nm_cap'])
            ->order_by(
                "CASE
                    WHEN u.n_unitkerja = 'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT' THEN 1
                    WHEN u.n_unitkerja = 'DINAS KOMUNIKASI DAN INFORMATIKA PROVINSI JAWA BARAT' THEN 2
                    ELSE 3
                END",
                '',
                false
            )
            ->order_by('u.n_unitkerja', 'ASC')
            ->get()
            ->result_array();

        foreach ($rows as $index => $row) {
            $rows[$index]['id'] = (int) $row['id'];
            $rows[$index]['service_count'] = (int) $row['service_count'];
            $rows[$index]['is_default'] = $this->is_default_unit_name($row['n_unitkerja']);
            $rows[$index]['is_visible'] = strpos((string) $row['nm_cap'], '!') !== 0;
        }
        return $rows;
    }

    public function unit_name_exists($unitName, $excludeId = 0)
    {
        if (!$this->db->table_exists('trunitkerja')) {
            return false;
        }

        $this->db->where('n_unitkerja', trim((string) $unitName));
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results('trunitkerja') > 0;
    }

    public function create_unit($unitName)
    {
        if (!$this->db->table_exists('trunitkerja')) {
            return false;
        }
        return $this->db->insert('trunitkerja', [
            'n_unitkerja' => trim((string) $unitName),
            'nm_cap' => '',
        ]);
    }

    public function set_unit_visibility($unitId, $isVisible)
    {
        if (!$this->db->table_exists('trunitkerja')) {
            return false;
        }
        $row = $this->db
            ->select('id,nm_cap')
            ->where('id', (int) $unitId)
            ->where('n_unitkerja !=', '-')
            ->get('trunitkerja')
            ->row_array();
        if (!$row) {
            return false;
        }

        $stamp = (string) $row['nm_cap'];
        $currentlyVisible = strpos($stamp, '!') !== 0;
        if ($currentlyVisible === (bool) $isVisible) {
            return true;
        }
        if ($isVisible) {
            $stamp = substr($stamp, 1);
        } else {
            if (strlen($stamp) >= 15) {
                return false;
            }
            $stamp = '!' . $stamp;
        }

        return $this->db
            ->where('id', (int) $unitId)
            ->update('trunitkerja', ['nm_cap' => $stamp]);
    }

    private function is_default_unit_name($unitName)
    {
        return in_array(strtoupper(trim((string) $unitName)), [
            'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU PROVINSI JAWA BARAT',
            'DINAS KOMUNIKASI DAN INFORMATIKA PROVINSI JAWA BARAT',
        ], true);
    }

    public function get_responses(array $filters = [], $limit = 20, $offset = 0)
    {
        $surveyId = isset($filters['survey_id']) ? (int) $filters['survey_id'] : 0;
        $orderPrefix = '';
        if ($surveyId > 0 && $this->is_legacy_skm_survey($surveyId)) {
            $this->db
                ->select("'SKM' AS response_source,kode,nib,resi,permohonan_id,jenis_ijin,nama_responden,responden,mobile,gender,usia,pendidikan_id,pekerjaan_id,sektor,tgl_pengisian,tgl_buat,rata,saran,keterangan,jenis_survei,kode_survei_unik,kode_pengisian,versi_survei,is_legacy_skm,flag_skm", false)
                ->from($this->table)
                ->where('flag_skm', 1);
            $this->apply_filters($filters);
        } elseif ($surveyId > 0) {
            $this->db
                ->select("'SURVEY' AS response_source,s.kode,s.nib,s.resi,s.permohonan_id,s.jenis_ijin,s.nama_responden,s.responden,s.mobile,s.gender,s.usia,s.pendidikan_id,s.pekerjaan_id,s.sektor,s.tgl_pengisian,s.tgl_buat,r.score AS rata,s.saran,s.keterangan,s.jenis_survei,s.kode_survei_unik,s.kode_pengisian,s.versi_survei,s.is_legacy_skm,s.flag_skm", false)
                ->from('ipak_submission_surveys r')
                ->join($this->flexResponseTable . ' s', 's.kode = r.flex_response_id', 'inner')
                ->where('r.survey_id', $surveyId);
            $this->apply_filters($filters, 's');
            $orderPrefix = 's.';
        } else {
            $this->db
                ->select('response_source,kode,nib,resi,permohonan_id,jenis_ijin,nama_responden,responden,mobile,gender,usia,pendidikan_id,pekerjaan_id,sektor,tgl_pengisian,tgl_buat,rata,saran,keterangan,jenis_survei,kode_survei_unik,kode_pengisian,versi_survei,is_legacy_skm,flag_skm')
                ->from($this->allResponsesView);
            $this->apply_filters($filters);
        }
        $rows = $this->db
            ->order_by('COALESCE(' . $orderPrefix . 'tgl_buat, CONCAT(' . $orderPrefix . "tgl_pengisian, ' 00:00:00'))", 'DESC', false)
            ->order_by($orderPrefix . 'kode', 'DESC')
            ->limit((int) $limit, (int) $offset)
            ->get()
            ->result_array();
        foreach ($rows as $index => $row) {
            $rows[$index] = $this->attach_response_identity($row);
        }
        return $rows;
    }

    public function get_all_for_export(array $filters = [])
    {
        $surveyId = isset($filters['survey_id']) ? (int) $filters['survey_id'] : 0;
        $orderPrefix = '';
        if ($surveyId > 0 && $this->is_legacy_skm_survey($surveyId)) {
            $this->db
                ->select("'SKM' AS response_source,s.*", false)
                ->from($this->table . ' s')
                ->where('s.flag_skm', 1);
            $this->apply_filters($filters, 's');
            $orderPrefix = 's.';
        } elseif ($surveyId > 0) {
            $this->db
                ->select("'SURVEY' AS response_source,s.*,r.score AS filtered_score", false)
                ->from('ipak_submission_surveys r')
                ->join($this->flexResponseTable . ' s', 's.kode = r.flex_response_id', 'inner')
                ->where('r.survey_id', $surveyId);
            $this->apply_filters($filters, 's');
            $orderPrefix = 's.';
        } else {
            $this->db
                ->select('*')
                ->from($this->allResponsesView);
            $this->apply_filters($filters);
        }
        $rows = $this->db
            ->order_by('COALESCE(' . $orderPrefix . 'tgl_buat, CONCAT(' . $orderPrefix . "tgl_pengisian, ' 00:00:00'))", 'DESC', false)
            ->order_by($orderPrefix . 'kode', 'DESC')
            ->get()
            ->result_array();
        foreach ($rows as $index => $row) {
            $rows[$index] = $this->attach_response_identity($row);
        }
        return $rows;
    }

    /**
     * Daftar respons sesuai filter tanpa paginasi, memakai kolom eksplisit.
     *
     * Relasi, urutan, dan kolom disamakan dengan get_responses() supaya hasil
     * export sama persis dengan tabel di halaman Data Responden. Perbedaannya
     * hanya tanpa LIMIT dan tanpa SELECT *, karena seluruh kolom tabel respons
     * tidak pernah dibutuhkan oleh export.
     *
     * @param  array $filters
     * @return array
     */
    public function get_responses_for_excel(array $filters = [])
    {
        $surveyId = isset($filters['survey_id']) ? (int) $filters['survey_id'] : 0;
        $orderPrefix = '';
        if ($surveyId > 0 && $this->is_legacy_skm_survey($surveyId)) {
            $this->db
                ->select($this->response_select_columns('s', 'SKM'), false)
                ->from($this->table . ' s')
                ->where('s.flag_skm', 1);
            $this->apply_filters($filters, 's');
            $orderPrefix = 's.';
        } elseif ($surveyId > 0) {
            $this->db
                ->select($this->response_select_columns('s', 'SURVEY') . ',r.score AS filtered_score', false)
                ->from('ipak_submission_surveys r')
                ->join($this->flexResponseTable . ' s', 's.kode = r.flex_response_id', 'inner')
                ->where('r.survey_id', $surveyId);
            $this->apply_filters($filters, 's');
            $orderPrefix = 's.';
        } else {
            $this->db
                ->select($this->response_select_columns('', ''))
                ->from($this->allResponsesView);
            $this->apply_filters($filters);
        }
        $rows = $this->db
            ->order_by('COALESCE(' . $orderPrefix . 'tgl_buat, CONCAT(' . $orderPrefix . "tgl_pengisian, ' 00:00:00'))", 'DESC', false)
            ->order_by($orderPrefix . 'kode', 'DESC')
            ->get()
            ->result_array();
        foreach ($rows as $index => $row) {
            $rows[$index] = $this->attach_response_identity($row);
        }
        return $rows;
    }

    /**
     * Daftar kolom respons yang dibutuhkan export.
     *
     * Dipisah agar get_responses() dan get_responses_for_excel() memakai kolom
     * yang sama persis, sehingga data tabel dan data export tidak pernah beda.
     *
     * @param  string $alias      Prefix kolom, misal 's'.
     * @param  string $source     Label sumber respons ('SKM'/'SURVEY'), kosong
     *                            bila kolomnya sudah ada di view.
     * @return string
     */
    private function response_select_columns($alias = '', $source = '')
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $columns = [
            'kode', 'nib', 'resi', 'permohonan_id', 'jenis_ijin', 'nama_responden',
            'responden', 'mobile', 'gender', 'usia', 'pendidikan_id', 'pekerjaan_id',
            'sektor', 'tgl_pengisian', 'tgl_buat', 'rata', 'saran', 'keterangan',
            'jenis_survei', 'kode_survei_unik', 'kode_pengisian', 'versi_survei',
            'is_legacy_skm', 'flag_skm',
        ];
        $selected = [];
        foreach ($columns as $column) {
            $selected[] = $prefix . $column;
        }
        $selected[] = $source !== ''
            ? "'" . $source . "' AS response_source"
            : 'response_source';
        return implode(',', $selected);
    }

    /**
     * Menyusun paket data export untuk sekumpulan respons sekaligus.
     *
     * Semua pertanyaan, jawaban, dan layanan diambil per respons memakai
     * relasi yang sama dengan halaman detail: ipak_submission_surveys untuk
     * survei yang benar-benar diisi, lalu ipak_submission_survey_answers dan
     * ipak_response_answers untuk jawaban milik survei tersebut. Tidak ada
     * master pertanyaan, master layanan, atau jawaban responden lain yang
     * ikut terbawa.
     *
     * Kueri dijalankan per batches, bukan per baris, sehingga jumlah query
     * tetap handful berapa pun jumlah respons.
     *
     * @param  array $filters
     * @return array
     */
    public function get_response_export_bundle(array $filters = [])
    {
        // Direset setiap pemanggilan supaya angka yang dilaporkan selalu
        // untuk export yang sedang berjalan, bukan export sebelumnya.
        $this->export_overwritten_answers = array();
        $this->export_unregistered_answers = array();
        $rows = $this->get_responses_for_excel($filters);
        if (!$rows) {
            return [];
        }

        $flexIds = [];
        $skmIds = [];
        foreach ($rows as $row) {
            if ($row['response_source'] === 'SURVEY') {
                $flexIds[] = (int) $row['kode'];
            } else {
                $skmIds[] = (int) $row['kode'];
            }
        }
        $flexIds = $this->positive_ids($flexIds);
        $skmIds = $this->positive_ids($skmIds);

        $linksByResponse = $this->export_survey_links_by_response($flexIds, $skmIds);
        $answersByResponseAndSurvey = $this->export_answers_by_response_and_survey($flexIds, $skmIds);

        // Responden yang belum punya baris ipak_submission_surveys (data lama)
        // tetap perlu ditampilkan: surjective-nya diambil dari identitasnya,
        // lalu jawabannya dibaca langsung dan dibatasi pertanyaan survei itu.
        $legacySurveyId = $this->legacy_skm_survey_id();
        $fallbackNeeded = [];
        foreach ($rows as $row) {
            $key = $row['response_key'];
            if (empty($linksByResponse[$key]) && $row['response_source'] === 'SKM') {
                $fallbackNeeded[] = $row;
            }
        }
        $fallbackAnswers = $this->export_legacy_fallback_answers($fallbackNeeded, $legacySurveyId, $linksByResponse);
        foreach ($fallbackAnswers as $groupKey => $answerMap) {
            if (!isset($answersByResponseAndSurvey[$groupKey])) {
                $answersByResponseAndSurvey[$groupKey] = $answerMap;
            }
        }

        $surveyQuestionCache = [];
        $bundle = [];
        foreach ($rows as $row) {
            $key = $row['response_key'];
            $source = $row['response_source'];
            $links = isset($linksByResponse[$key]) ? $linksByResponse[$key] : [];

            foreach ($links as $link) {
                $surveyId = (int) $link['survey_id'];
                if ($surveyId < 1) {
                    continue;
                }
                if (!array_key_exists($surveyId, $surveyQuestionCache)) {
                    $surveyQuestionCache[$surveyId] = $this->get_survey_question_ids($surveyId);
                }
                $allowedQuestions = $surveyQuestionCache[$surveyId];
                $linkKey = $key . '|' . $surveyId;
                $answerMap = isset($answersByResponseAndSurvey[$linkKey])
                    ? $answersByResponseAndSurvey[$linkKey]
                    : [];

                $items = [];
                foreach ($answerMap as $questionId => $answer) {
                    // Pengaman terakhir: jawaban harus milik pertanyaan yang
                    // memang terdaftar pada survei ini. Jawaban yang dibuang
                    // dicatat supaya tidak hilang tanpa jejak.
                    if ($allowedQuestions && !in_array((int) $questionId, $allowedQuestions, true)) {
                        $this->export_unregistered_answers[] = [
                            'reference' => $key,
                            'survey_name' => $link['survey_name'],
                            'question_code' => $answer['question_code'],
                            'option_label' => $answer['option_label'],
                        ];
                        continue;
                    }
                    $items[] = [
                        'survey_id' => $surveyId,
                        'survey_name' => $link['survey_name'],
                        'index_label' => $link['index_label'],
                        'question_id' => (int) $questionId,
                        'question_code' => $answer['question_code'],
                        'question_text' => $answer['question_text'],
                        'option_label' => $answer['option_label'],
                        'option_code' => $answer['option_code'],
                        'option_value' => $answer['option_value'],
                        'score' => $answer['normalized_score'],
                    ];
                }
                usort($items, function ($a, $b) {
                    return $a['question_id'] - $b['question_id'];
                });

                $bundle[] = [
                    'response' => $row,
                    'response_key' => $key,
                    'source' => $source,
                    'survey_id' => $surveyId,
                    'survey_name' => $link['survey_name'],
                    'survey_code' => $link['survey_code'],
                    'index_label' => $link['index_label'],
                    'form_name' => $link['form_name'],
                    'score' => $link['score'],
                    'category_label' => $link['category_label'],
                    'items' => $items,
                ];
            }
        }
        return $bundle;
    }

    /**
     * Jawaban yang tertimpa ketika bundle export terakhir dirakit.
     *
     * Dipakai laporan export supaya jawaban yang hilang sebelum masuk proses
     * transformasi tetap terlihat, lengkap dengan kode pertanyaannya.
     *
     * @return array
     */
    public function get_export_overwritten_answers()
    {
        return $this->export_overwritten_answers;
    }

    /**
     * Jawaban yang dibuang karena soalnya tidak terdaftar pada survei.
     *
     * @return array
     */
    public function get_export_unregistered_answers()
    {
        return $this->export_unregistered_answers;
    }

    /**
     * Survei yang benar-benar diisi tiap respons.
     *
     * @param  array $flexIds
     * @param  array $skmIds
     * @return array Kunci response_key.
     */
    private function export_survey_links_by_response(array $flexIds, array $skmIds)
    {
        if (!$flexIds && !$skmIds) {
            return [];
        }
        $this->db
            ->select(
                'r.survey_id,r.flex_response_id,r.skm_data_id,r.score,r.category_label,r.answer_count,'
                . 's.survey_name,s.survey_code,s.index_label,f.form_name',
                false
            )
            ->from('ipak_submission_surveys r')
            ->join('ipak_surveys s', 's.id = r.survey_id', 'inner')
            ->join('ipak_forms f', 'f.id = r.form_id', 'inner');
        $this->db->group_start();
        if ($flexIds) {
            $this->db->where_in('r.flex_response_id', $flexIds);
        }
        if ($flexIds && $skmIds) {
            $this->db->or_where_in('r.skm_data_id', $skmIds);
        } elseif ($skmIds) {
            $this->db->where_in('r.skm_data_id', $skmIds);
        }
        $this->db->group_end();
        $rows = $this->db
            ->order_by('s.survey_name', 'ASC')
            ->get()
            ->result_array();

        $result = [];
        foreach ($rows as $row) {
            $flexId = (int) $row['flex_response_id'];
            $skmId = (int) $row['skm_data_id'];
            $key = $flexId > 0
                ? $this->response_key($flexId, 'SURVEY')
                : $this->response_key($skmId, 'SKM');
            $result[$key][] = $row;
        }
        return $result;
    }

    /**
     * Jawaban tiap respons, dikelompokkan per survei yang mengoleksinya.
     *
     * Diambil lewat ipak_submission_survey_answers sehingga sebuah jawaban
     * hanya muncul pada survei tempat jawaban itu diberikan, bukan pada
     * seluruh survei yang memakai pertanyaan yang sama.
     *
     * @param  array $flexIds
     * @param  array $skmIds
     * @return array Kunci "response_key|survey_id".
     */
    private function export_answers_by_response_and_survey(array $flexIds, array $skmIds)
    {
        if (!$flexIds && !$skmIds) {
            return [];
        }
        $this->db
            ->select(
                'r.survey_id,r.flex_response_id,r.skm_data_id,ra.question_id,'
                . 'ra.option_label_snapshot,ra.option_code_snapshot,ra.option_value_snapshot,'
                // Kolom skor punya dua generasi. normalized_snapshot terisi 0
                // pada seluruh baris live, jadi skor diambil dari kolom aktif
                // lalu master opsi, dan baru dipakai apa adanya bila semuanya 0.
                . 'COALESCE(NULLIF(ra.normalized_score,0),NULLIF(ra.normalized_score_snapshot,0),'
                . 'NULLIF(oa_by_id.normalized_score,0),NULLIF(oa_by_label.normalized_score,0),0) AS normalized_score,'
                . 'q.question_code,q.question_text,q.sort_order',
                false
            )
            ->from('ipak_submission_survey_answers rsa')
            ->join('ipak_submission_surveys r', 'r.id = rsa.survey_result_id', 'inner')
            ->join('ipak_response_answers ra', 'ra.id = rsa.response_answer_id', 'inner')
            ->join('ipak_questions q', 'q.id = ra.question_id', 'inner')
            ->join('ipak_answer_options oa_by_id', 'oa_by_id.id = ra.answer_option_id', 'left')
            ->join(
                'ipak_answer_options oa_by_label',
                'oa_by_label.question_id = ra.question_id'
                . ' AND oa_by_label.option_label = ra.option_label_snapshot',
                'left'
            );
        $this->db->group_start();
        if ($flexIds) {
            $this->db->where_in('r.flex_response_id', $flexIds);
        }
        if ($flexIds && $skmIds) {
            $this->db->or_where_in('r.skm_data_id', $skmIds);
        } elseif ($skmIds) {
            $this->db->where_in('r.skm_data_id', $skmIds);
        }
        $this->db->group_end();
        $rows = $this->db
            ->order_by('q.sort_order', 'ASC')
            ->order_by('ra.id', 'ASC')
            ->get()
            ->result_array();

        $result = [];
        foreach ($rows as $row) {
            $flexId = (int) $row['flex_response_id'];
            $skmId = (int) $row['skm_data_id'];
            $responseKey = $flexId > 0
                ? $this->response_key($flexId, 'SURVEY')
                : $this->response_key($skmId, 'SKM');
            $groupKey = $responseKey . '|' . (int) $row['survey_id'];
            $questionId = (int) $row['question_id'];

            // Satu respons bisa punya lebih dari satu baris jawaban untuk soal
            // yang sama, misalnya survei diisi dua kali. Karena hasil disimpan
            // per question_id, baris kedua akan menimpa baris pertama. Penimpaan
            // dicatat supaya tidak ada jawaban yang hilang tanpa jejak.
            if (isset($result[$groupKey][$questionId])) {
                $this->export_overwritten_answers[] = [
                    'reference' => $responseKey,
                    'survey_id' => (int) $row['survey_id'],
                    'question_id' => $questionId,
                    'question_code' => $row['question_code'],
                    'kept' => $result[$groupKey][$questionId]['option_label'],
                    'dropped' => $row['option_label_snapshot'],
                ];
            }

            $result[$groupKey][$questionId] = [
                'question_code' => $row['question_code'],
                'question_text' => $row['question_text'],
                'option_label' => $row['option_label_snapshot'],
                'option_code' => $row['option_code_snapshot'],
                'option_value' => $row['option_value_snapshot'],
                'normalized_score' => $row['normalized_score'],
            ];
        }
        return $result;
    }

    /**
     * Jawaban untuk respons lama yang belum punya baris ipak_submission_surveys.
     *
     * Data SKM lama menyimpan tautan survei secara tidak langsung. Survei
     * tersebut disintesis dari identitas respons agar tetap bisa diekspor,
     * dan jawabannya dibaca langsung lalu tetap dibatasi pertanyaan survei itu.
     *
     * @param  array $rows
     * @param  int   $legacySurveyId
     * @param  array $linksByResponse Diisi langsung agar baris survei berikut dipakai
     *                            oleh pemanggil yang sama.
     * @return array Kunci "response_key|survey_id".
     */
    private function export_legacy_fallback_answers(array $rows, $legacySurveyId, array &$linksByResponse)
    {
        $legacySurveyId = (int) $legacySurveyId;
        if (!$rows || $legacySurveyId < 1) {
            return [];
        }
        $skmIds = [];
        foreach ($rows as $row) {
            $skmIds[] = (int) $row['kode'];
        }
        $skmIds = $this->positive_ids($skmIds);
        if (!$skmIds) {
            return [];
        }

        $survey = $this->db
            ->select('id,survey_name,survey_code,index_label')
            ->where('id', $legacySurveyId)
            ->limit(1)
            ->get('ipak_surveys')
            ->row_array();
        if (empty($survey)) {
            return [];
        }

        $answerRows = $this->db
            ->select(
                'ra.skm_data_id,ra.question_id,ra.option_label_snapshot,ra.option_code_snapshot,'
                . 'ra.option_value_snapshot,'
                // Sama seperti jalur utama: kolom skor aktif dulu, lalu master
                // opsi, baru nilai tersimpan apa adanya.
                . 'COALESCE(NULLIF(ra.normalized_score,0),NULLIF(ra.normalized_score_snapshot,0),'
                . 'NULLIF(oa_by_id.normalized_score,0),NULLIF(oa_by_label.normalized_score,0),0) AS normalized_score,'
                . 'q.question_code,q.question_text',
                false
            )
            ->from('ipak_response_answers ra')
            ->join('ipak_questions q', 'q.id = ra.question_id', 'inner')
            ->join('ipak_answer_options oa_by_id', 'oa_by_id.id = ra.answer_option_id', 'left')
            ->join(
                'ipak_answer_options oa_by_label',
                'oa_by_label.question_id = ra.question_id'
                . ' AND oa_by_label.option_label = ra.option_label_snapshot',
                'left'
            )
            ->where_in('ra.skm_data_id', $skmIds)
            ->order_by('q.sort_order', 'ASC')
            ->order_by('ra.id', 'ASC')
            ->get()
            ->result_array();

        $result = [];
        foreach ($rows as $row) {
            $key = $row['response_key'];
            $linksByResponse[$key][] = [
                'survey_id' => $legacySurveyId,
                'flex_response_id' => null,
                'skm_data_id' => (int) $row['kode'],
                'score' => $row['rata'],
                'category_label' => '',
                'answer_count' => 0,
                'survey_name' => $survey['survey_name'],
                'survey_code' => $survey['survey_code'],
                'index_label' => $survey['index_label'],
                'form_name' => '',
            ];
            $groupKey = $key . '|' . $legacySurveyId;
            foreach ($answerRows as $answer) {
                if ((int) $answer['skm_data_id'] !== (int) $row['kode']) {
                    continue;
                }
                $result[$groupKey][(int) $answer['question_id']] = [
                    'question_code' => $answer['question_code'],
                    'question_text' => $answer['question_text'],
                    'option_label' => $answer['option_label_snapshot'],
                    'option_code' => $answer['option_code_snapshot'],
                    'option_value' => $answer['option_value_snapshot'],
                    'normalized_score' => $answer['normalized_score'],
                ];
            }
        }
        return $result;
    }

    /**
     * @param  array $ids
     * @return array
     */
    private function positive_ids(array $ids)
    {
        $ids = array_map('intval', $ids);
        return array_values(array_unique(array_filter($ids)));
    }

    public function find_response($key, $responseSource = '')
    {
        $identity = $this->parse_response_key($key, $responseSource);
        if ($identity['source'] === 'SURVEY') {
            $row = $this->db
                ->where('kode', $identity['id'])
                ->limit(1)
                ->get($this->flexResponseTable)
                ->row_array();
            return $this->attach_response_identity($row ?: [], 'SURVEY');
        }

        $this->db
            ->from($this->table)
            ->where('kode', $identity['id'])
            ->where('flag_skm', 1);
        $this->apply_filters([]);
        $row = $this->db->limit(1)->get()->row_array();
        if ($row) {
            return $this->attach_response_identity($row, 'SKM');
        }

        $migrated = $this->db
            ->where('legacy_skm_data_id', $identity['id'])
            ->limit(1)
            ->get($this->flexResponseTable)
            ->row_array();
        return $this->attach_response_identity($migrated ?: [], 'SURVEY');
    }

    public function decode_metadata($value)
    {
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function sync_database()
    {
        $this->load->library('schema_definition');
        $requiredSchema = $this->schema_definition->get_required_schema();
        $requiredSchema['ipak_kbli'] = $this->kbli_schema_definition();
        $dbName = $this->db->database;
        $databaseForeignKeys = $this->get_database_foreign_keys();

        $results = [
            'tables_created' => [],
            'tables_existed' => [],
            'columns_added' => [],
            'columns_existed' => [],
            'indexes_created' => [],
            'indexes_existed' => [],
            'foreign_keys_created' => [],
            'foreign_keys_existed' => [],
            'foreign_keys_skipped' => [],
            'errors' => [],
        ];

        foreach ($requiredSchema as $tableName => $tableDef) {
            $tableExists = $this->db->table_exists($tableName);

            if (!$tableExists) {
                $createSql = $this->build_create_table_sql($tableName, $tableDef);
                try {
                    $this->db->query($createSql);
                    $results['tables_created'][] = $tableName;
                    $tableExists = true;
                } catch (Exception $e) {
                    $results['errors'][] = "Failed to create table {$tableName}: " . $e->getMessage();
                    continue;
                }
            } else {
                $results['tables_existed'][] = $tableName;
            }

            if ($tableExists) {
                $existingColumns = $this->get_table_columns($tableName);
                $requiredColumns = $tableDef['columns'];

                foreach ($requiredColumns as $colName => $colDef) {
                    if (!isset($existingColumns[$colName])) {
                        $alterSql = $this->build_add_column_sql($tableName, $colName, $colDef);
                        try {
                            $this->db->query($alterSql);
                            $results['columns_added'][] = "{$tableName}.{$colName}";
                        } catch (Exception $e) {
                            $results['errors'][] = "Failed to add column {$tableName}.{$colName}: " . $e->getMessage();
                        }
                    } else {
                        $results['columns_existed'][] = "{$tableName}.{$colName}";
                    }
                }

                $existingIndexes = $this->get_table_indexes($tableName);
                $requiredIndexes = isset($tableDef['indexes']) ? $tableDef['indexes'] : [];

                foreach ($requiredIndexes as $idxName => $idxDef) {
                    $idxColumns = implode(',', $idxDef['columns']);
                    $idxExists = false;
                    foreach ($existingIndexes as $existingIdx) {
                        if ($existingIdx['Key_name'] === $idxName ||
                            (isset($existingIdx['Column_name']) && $existingIdx['Column_name'] === $idxColumns && $existingIdx['Key_name'] !== 'PRIMARY')) {
                            $idxExists = true;
                            break;
                        }
                    }
                    if (!$idxExists && $idxName !== 'PRIMARY') {
                        $indexSql = $this->build_create_index_sql($tableName, $idxName, $idxDef);
                        try {
                            $this->db->query($indexSql);
                            $results['indexes_created'][] = "{$tableName}.{$idxName}";
                        } catch (Exception $e) {
                            $results['errors'][] = "Failed to create index {$tableName}.{$idxName}: " . $e->getMessage();
                        }
                    } else {
                        $results['indexes_existed'][] = "{$tableName}.{$idxName}";
                    }
                }

                $existingFks = $this->get_table_foreign_keys($tableName);
                $requiredFks = isset($tableDef['foreign_keys']) ? $tableDef['foreign_keys'] : [];

                foreach ($requiredFks as $fkName => $fkDef) {
                    $equivalentForeignKey = $this->find_equivalent_foreign_key($tableName, $fkDef, $databaseForeignKeys);
                    if ($equivalentForeignKey) {
                        $results['foreign_keys_existed'][] = "{$tableName}.{$equivalentForeignKey['CONSTRAINT_NAME']}";
                        continue;
                    }

                    $orphanInfo = $this->get_foreign_key_orphan_info($tableName, $fkDef);
                    if ($orphanInfo['count'] > 0) {
                        $orphanDescription = "Skipped foreign key {$tableName}.{$fkName}: found {$orphanInfo['count']} orphan child row(s)";
                        if ($orphanInfo['examples']) {
                            $orphanDescription .= ' with value(s) ' . implode(', ', $orphanInfo['examples']);
                        }
                        $orphanDescription .= '. Resolve these rows without deleting required response history, then run synchronization again.';
                        $results['foreign_keys_skipped'][] = $orphanDescription;
                        $results['errors'][] = $orphanDescription;
                        continue;
                    }

                    $actualFkName = $fkName;
                    if (isset($databaseForeignKeys[$actualFkName])) {
                        $actualFkName = $this->unique_foreign_key_name($tableName, $fkName, $fkDef, $databaseForeignKeys);
                    }
                    if (!isset($existingFks[$actualFkName])) {
                        $fkSql = $this->build_add_foreign_key_sql($tableName, $actualFkName, $fkDef);
                        try {
                            $this->db->query($fkSql);
                            $results['foreign_keys_created'][] = "{$tableName}.{$actualFkName}";
                            $databaseForeignKeys[$actualFkName] = ['TABLE_NAME' => $tableName];
                        } catch (Exception $e) {
                            $results['errors'][] = "Failed to add foreign key {$tableName}.{$actualFkName}: " . $e->getMessage();
                        }
                    } else {
                        $results['foreign_keys_existed'][] = "{$tableName}.{$actualFkName}";
                    }
                }
            }
        }

        return $results;
    }

    private function get_table_columns($tableName)
    {
        $query = $this->db->query("
            SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ", [$this->db->database, $tableName]);

        $columns = [];
        foreach ($query->result_array() as $row) {
            $columns[$row['COLUMN_NAME']] = $row;
        }
        return $columns;
    }

    private function get_table_indexes($tableName)
    {
        $query = $this->db->query("
            SELECT INDEX_NAME AS Key_name, COLUMN_NAME, NON_UNIQUE
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ", [$this->db->database, $tableName]);

        return $query->result_array();
    }

    private function get_table_foreign_keys($tableName)
    {
        $query = $this->db->query("
            SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$this->db->database, $tableName]);

        $fks = [];
        foreach ($query->result_array() as $row) {
            $fks[$row['CONSTRAINT_NAME']] = $row;
        }
        return $fks;
    }

    private function get_database_foreign_keys()
    {
        $query = $this->db->query("
            SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$this->db->database]);
        $foreignKeys = [];
        foreach ($query->result_array() as $row) {
            $foreignKeys[$row['CONSTRAINT_NAME']] = $row;
        }
        return $foreignKeys;
    }

    private function find_equivalent_foreign_key($tableName, array $definition, array $databaseForeignKeys)
    {
        $column = isset($definition['columns'][0]) ? $definition['columns'][0] : '';
        $referencedColumn = isset($definition['ref_columns'][0]) ? $definition['ref_columns'][0] : '';
        foreach ($databaseForeignKeys as $foreignKey) {
            if (
                $foreignKey['TABLE_NAME'] === $tableName
                && $foreignKey['COLUMN_NAME'] === $column
                && $foreignKey['REFERENCED_TABLE_NAME'] === $definition['ref_table']
                && $foreignKey['REFERENCED_COLUMN_NAME'] === $referencedColumn
            ) {
                return $foreignKey;
            }
        }
        return false;
    }

    private function get_foreign_key_orphan_info($tableName, array $definition)
    {
        $column = isset($definition['columns'][0]) ? $definition['columns'][0] : '';
        $referencedColumn = isset($definition['ref_columns'][0]) ? $definition['ref_columns'][0] : '';
        $referencedTable = isset($definition['ref_table']) ? $definition['ref_table'] : '';
        if ($column === '' || $referencedColumn === '' || $referencedTable === '') {
            return ['count' => 0, 'examples' => []];
        }
        if (!$this->db->table_exists($tableName) || !$this->db->table_exists($referencedTable)) {
            return ['count' => 0, 'examples' => []];
        }
        $child = '`' . $tableName . '`';
        $parent = '`' . $referencedTable . '`';
        $childColumn = '`' . $column . '`';
        $parentColumn = '`' . $referencedColumn . '`';
        $where = "c.{$childColumn} IS NOT NULL AND p.{$parentColumn} IS NULL";
        $countRow = $this->db->query(
            "SELECT COUNT(*) AS orphan_count FROM {$child} c LEFT JOIN {$parent} p ON c.{$childColumn} = p.{$parentColumn} WHERE {$where}"
        )->row_array();
        $count = $countRow ? (int) $countRow['orphan_count'] : 0;
        if (!$count) {
            return ['count' => 0, 'examples' => []];
        }
        $exampleRows = $this->db->query(
            "SELECT DISTINCT c.{$childColumn} AS orphan_value FROM {$child} c LEFT JOIN {$parent} p ON c.{$childColumn} = p.{$parentColumn} WHERE {$where} LIMIT 5"
        )->result_array();
        $examples = [];
        foreach ($exampleRows as $row) {
            $examples[] = (string) $row['orphan_value'];
        }
        return ['count' => $count, 'examples' => $examples];
    }

    private function unique_foreign_key_name($tableName, $baseName, array $definition, array $databaseForeignKeys)
    {
        $signature = $tableName . '|' . implode(',', $definition['columns']) . '|'
            . $definition['ref_table'] . '|' . implode(',', $definition['ref_columns']);
        $suffix = '_' . substr(sha1($signature), 0, 10);
        $candidate = substr($baseName, 0, 64 - strlen($suffix)) . $suffix;
        $counter = 1;
        while (isset($databaseForeignKeys[$candidate])) {
            $counterSuffix = '_' . $counter++;
            $candidate = substr($baseName, 0, 64 - strlen($suffix) - strlen($counterSuffix)) . $suffix . $counterSuffix;
        }
        return $candidate;
    }

    private function build_create_table_sql($tableName, $tableDef)
    {
        $cols = [];
        $primaryKeys = [];

        foreach ($tableDef['columns'] as $colName => $colDef) {
            $colSql = "`{$colName}` {$colDef['type']}";

            if (!empty($colDef['nullable']) === false) {
                $colSql .= ' NOT NULL';
            } else {
                $colSql .= ' NULL';
            }

            if (isset($colDef['default']) && $colDef['default'] !== '') {
                $default = $colDef['default'];
                if (is_string($default) && strpos($default, 'CURRENT_TIMESTAMP') === false && strpos($default, '(') === false) {
                    if (stripos($colDef['type'], 'INT') !== false || stripos($colDef['type'], 'DECIMAL') !== false || stripos($colDef['type'], 'FLOAT') !== false) {
                        $colSql .= " DEFAULT {$default}";
                    } else {
                        $colSql .= " DEFAULT '{$default}'";
                    }
                } else {
                    $colSql .= " DEFAULT {$default}";
                }
            }

            if (!empty($colDef['auto_increment'])) {
                $colSql .= ' AUTO_INCREMENT';
            }

            if (!empty($colDef['on_update_column'])) {
                $colSql .= ' ON UPDATE ' . $colDef['on_update_column'];
            }

            if (!empty($colDef['primary'])) {
                $primaryKeys[] = $colName;
            }

            $cols[] = $colSql;
        }

        if (!empty($primaryKeys)) {
            $cols[] = 'PRIMARY KEY (`' . implode('`, `', $primaryKeys) . '`)';
        }

        $uniqueKeys = [];
        $indexKeys = [];
        foreach ($tableDef['indexes'] as $idxName => $idxDef) {
            if ($idxDef['type'] === 'UNIQUE' && $idxName !== 'PRIMARY') {
                $uniqueKeys[] = "UNIQUE KEY `{$idxName}` (`" . implode('`, `', $idxDef['columns']) . "`)";
            } elseif ($idxDef['type'] === 'INDEX') {
                $indexKeys[] = "KEY `{$idxName}` (`" . implode('`, `', $idxDef['columns']) . "`)";
            }
        }
        $cols = array_merge($cols, $uniqueKeys, $indexKeys);

        $foreignKeys = [];

        foreach ($tableDef['foreign_keys'] as $fkName => $fkDef) {
            $fkCols = implode('`, `', $fkDef['columns']);
            $refCols = implode('`, `', $fkDef['ref_columns']);

            $onUpdate = !empty($fkDef['on_update'])
                ? " ON UPDATE {$fkDef['on_update']}"
                : '';

            $onDelete = !empty($fkDef['on_delete'])
                ? " ON DELETE {$fkDef['on_delete']}"
                : '';

            $foreignKeys[] = "CONSTRAINT `{$fkName}` "
                . "FOREIGN KEY (`{$fkCols}`) "
                . "REFERENCES `{$fkDef['ref_table']}` (`{$refCols}`)"
                . "{$onUpdate}{$onDelete}";
        }

        $cols = array_merge($cols, $foreignKeys);

        $engine = isset($tableDef['engine']) ? $tableDef['engine'] : 'InnoDB';
        $charset = isset($tableDef['charset']) ? $tableDef['charset'] : 'utf8';
        $collate = isset($tableDef['collate']) ? $tableDef['collate'] : 'utf8_general_ci';

        return "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n    " . implode(",\n    ", $cols) . "\n) ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collate};";
    }

    private function build_add_column_sql($tableName, $colName, $colDef)
    {
        $colSql = "`{$colName}` {$colDef['type']}";

        if (!empty($colDef['nullable']) === false) {
            $colSql .= ' NOT NULL';
        } else {
            $colSql .= ' NULL';
        }

        if (isset($colDef['default']) && $colDef['default'] !== '') {
            $default = $colDef['default'];
            if (is_string($default) && strpos($default, 'CURRENT_TIMESTAMP') === false && strpos($default, '(') === false) {
                if (stripos($colDef['type'], 'INT') !== false || stripos($colDef['type'], 'DECIMAL') !== false || stripos($colDef['type'], 'FLOAT') !== false) {
                    $colSql .= " DEFAULT {$default}";
                } else {
                    $colSql .= " DEFAULT '{$default}'";
                }
            } else {
                $colSql .= " DEFAULT {$default}";
            }
        }

        if (!empty($colDef['auto_increment'])) {
            $colSql .= ' AUTO_INCREMENT';
        }

        if (!empty($colDef['on_update_column'])) {
            $colSql .= ' ON UPDATE ' . $colDef['on_update_column'];
        }

        return "ALTER TABLE `{$tableName}` ADD COLUMN {$colSql};";
    }

    private function build_create_index_sql($tableName, $idxName, $idxDef)
    {
        $cols = implode('`, `', $idxDef['columns']);
        if ($idxDef['type'] === 'UNIQUE') {
            return "ALTER TABLE `{$tableName}` ADD UNIQUE KEY `{$idxName}` (`{$cols}`);";
        }
        return "ALTER TABLE `{$tableName}` ADD KEY `{$idxName}` (`{$cols}`);";
    }

    private function build_add_foreign_key_sql($tableName, $fkName, $fkDef)
    {
        $cols = implode('`, `', $fkDef['columns']);
        $refCols = implode('`, `', $fkDef['ref_columns']);
        $onUpdate = !empty($fkDef['on_update']) ? " ON UPDATE {$fkDef['on_update']}" : '';
        $onDelete = !empty($fkDef['on_delete']) ? " ON DELETE {$fkDef['on_delete']}" : '';

        return "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$cols}`) REFERENCES `{$fkDef['ref_table']}` (`{$refCols}`){$onUpdate}{$onDelete};";
    }
}
