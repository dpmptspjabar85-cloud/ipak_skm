<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ipaksurvey_model extends CI_Model
{
    private $table = 'skm_data_skm';
    private $flexResponseTable = 'ipak_survey_responses';
    private $allResponsesView = 'ipak_all_responses';

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
            $rows[$index]['survey_count'] = (int) $row['survey_count'];
        }
        return $rows;
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
            $options = isset($setting['options']) ? $setting['options'] : [];
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
                'field_options' => $options ? json_encode(array_values($options), JSON_UNESCAPED_SLASHES) : null,
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
        $this->db->insert('ipak_forms', [
            'form_code' => $formCode,
            'form_name' => 'Form ' . $survey['survey_name'],
            'description' => 'Form mandiri untuk ' . $survey['survey_name'] . '.',
            'is_default' => 0,
            'is_active' => (int) $survey['is_active'] === 1 ? 1 : 0,
        ]);
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
            return true;
        }

        return (bool) $this->db->query(
            'ALTER TABLE ipak_survey_questions DROP INDEX uq_ipak_question_single_survey'
        );
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
        $form['respondent_fields'] = $this->get_form_fields((int) $form['id']);
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
        $this->db->where('survey_id', $surveyId)->delete('ipak_survey_questions');
        $sortOrder = 0;
        foreach ($questionIds as $questionId) {
            if ($questionId < 1) {
                continue;
            }
            $sortOrder++;
            $this->db->insert('ipak_survey_questions', [
                'survey_id' => $surveyId,
                'question_id' => $questionId,
                'sort_order' => $sortOrder,
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
        return $this->db
            ->where(
                $identity['source'] === 'SURVEY' ? 'flex_response_id' : 'skm_data_id',
                $identity['id']
            )
            ->order_by('field_group', 'ASC')
            ->order_by('id', 'ASC')
            ->get('ipak_response_fields')
            ->result_array();
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
        $this->db->where('n_unitkerja', trim((string) $unitName));
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results('trunitkerja') > 0;
    }

    public function create_unit($unitName)
    {
        return $this->db->insert('trunitkerja', [
            'n_unitkerja' => trim((string) $unitName),
            'nm_cap' => '',
        ]);
    }

    public function set_unit_visibility($unitId, $isVisible)
    {
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
}
