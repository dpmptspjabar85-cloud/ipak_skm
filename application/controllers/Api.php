<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api extends CI_Controller
{
    private $requestStartedAt;
    private $authenticatedClient = false;
    private $requestedResource = '';
    private $requestedSurveyId = 0;

    public function __construct()
    {
        parent::__construct();
        $this->requestStartedAt = microtime(true);
        $this->load->model('Ipaksurvey_model', 'ipak');
        $this->load->model('Survey_api_model', 'survey_api');
        $this->config->load('ipak');
    }

    public function survey_data($clientCode = '')
    {
        $clientCode = strtolower(trim((string) $clientCode));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,49}$/', $clientCode)) {
            return $this->respond_error(404, 'Endpoint API tidak ditemukan.');
        }

        $requestMethod = strtoupper($this->input->method());
        if ($requestMethod === 'OPTIONS') {
            $client = $this->survey_api->get_client_by_code($clientCode);
            if (!$client) {
                return $this->respond_error(404, 'Endpoint API tidak ditemukan.');
            }
            $this->apply_cors($client);
            $this->output
                ->set_status_header(204)
                ->set_header('Access-Control-Allow-Methods: GET, OPTIONS')
                ->set_header('Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type')
                ->set_header('Access-Control-Max-Age: 600')
                ->set_output('');
            return;
        }
        if ($requestMethod !== 'GET') {
            $this->output->set_header('Allow: GET, OPTIONS');
            return $this->respond_error(405, 'Metode yang diizinkan hanya GET.');
        }

        $apiKey = $this->request_api_key();
        $authentication = $this->survey_api->authenticate(
            $clientCode,
            $apiKey,
            $this->input->ip_address()
        );
        if (!empty($authentication['client'])) {
            $this->authenticatedClient = $authentication['client'];
            $this->apply_cors($this->authenticatedClient);
        }
        if (empty($authentication['ok'])) {
            return $this->respond_error(
                isset($authentication['status']) ? (int) $authentication['status'] : 401,
                isset($authentication['error']) ? $authentication['error'] : 'Akses API ditolak.'
            );
        }

        $this->authenticatedClient = $authentication['client'];
        $this->apply_cors($this->authenticatedClient);
        $this->output
            ->set_header('X-RateLimit-Limit: ' . (int) $authentication['rate_limit'])
            ->set_header('X-RateLimit-Remaining: ' . (int) $authentication['rate_remaining']);

        $resource = strtolower(trim((string) $this->input->get('resource', true)));
        if ($resource === '') {
            $resource = 'summary';
        }
        $this->requestedResource = $resource;
        if (!in_array($resource, $this->authenticatedClient['allowed_resources'], true)) {
            return $this->respond_error(403, 'Jenis data ini tidak diizinkan untuk kunci API tersebut.');
        }

        $surveys = $this->allowed_surveys($this->authenticatedClient);
        if (!$surveys) {
            return $this->respond_error(403, 'Belum ada survei aktif yang diizinkan untuk akses ini.');
        }
        $selection = $this->selected_surveys($surveys);
        if (!$selection['ok']) {
            return $this->respond_error($selection['status'], $selection['error'], [
                'available_surveys' => $this->survey_options_payload($surveys),
            ]);
        }
        $selectedSurveys = $selection['surveys'];
        if (count($selectedSurveys) === 1) {
            $selectedKeys = array_keys($selectedSurveys);
            $this->requestedSurveyId = (int) $selectedKeys[0];
        }

        $filters = $this->request_filters();
        $requestMeta = [
            'resource' => $resource,
            'surveys' => $this->survey_options_payload($selectedSurveys),
            'filters' => $filters['public'],
        ];

        if ($resource === 'summary') {
            $data = $this->summary_data($selectedSurveys, $filters['query']);
            return $this->respond_success($resource, $requestMeta, $data, count($data));
        }
        if ($resource === 'chart') {
            $dimension = strtolower(trim((string) $this->input->get('dimension', true)));
            if ($dimension === '') {
                $allowedDimensions = $this->authenticatedClient['allowed_dimensions'];
                $dimension = in_array('overall', $allowedDimensions, true)
                    ? 'overall'
                    : reset($allowedDimensions);
            }
            if (!in_array($dimension, $this->authenticatedClient['allowed_dimensions'], true)) {
                return $this->respond_error(403, 'Dimensi grafik tersebut tidak diizinkan.', [
                    'allowed_dimensions' => $this->authenticatedClient['allowed_dimensions'],
                ]);
            }
            $requestMeta['dimension'] = $dimension;
            $data = $this->chart_data($selectedSurveys, $filters['query'], $filters['year'], $dimension);
            return $this->respond_success($resource, $requestMeta, $data, count($data));
        }
        if ($resource === 'questions') {
            $data = $this->question_data($selectedSurveys);
            $questionCount = 0;
            foreach ($data as $surveyData) {
                $questionCount += count($surveyData['questions']);
            }
            return $this->respond_success($resource, $requestMeta, $data, $questionCount);
        }
        if ($resource === 'details') {
            if (count($selectedSurveys) !== 1) {
                return $this->respond_error(400, 'Pilih tepat satu survei untuk meminta data detail.', [
                    'hint' => 'Tambahkan parameter survey menggunakan ID, kode, atau UUID survei.',
                    'available_surveys' => $this->survey_options_payload($selectedSurveys),
                ]);
            }
            $surveyKeys = array_keys($selectedSurveys);
            $surveyId = (int) $surveyKeys[0];
            $survey = $selectedSurveys[$surveyId];
            $page = max(1, (int) $this->input->get('page', true));
            $requestedSize = (int) $this->input->get('per_page', true);
            $perPage = $requestedSize > 0 ? $requestedSize : min(25, $this->authenticatedClient['max_page_size']);
            $perPage = min(max(1, $perPage), $this->authenticatedClient['max_page_size']);
            $details = $this->detail_data($survey, $filters['query'], $page, $perPage);
            $requestMeta['page'] = $page;
            $requestMeta['per_page'] = $perPage;
            return $this->respond_success(
                $resource,
                $requestMeta,
                $details['rows'],
                count($details['rows']),
                [
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total_records' => $details['total'],
                        'total_pages' => max(1, (int) ceil($details['total'] / $perPage)),
                    ],
                ]
            );
        }

        return $this->respond_error(400, 'Jenis data API tidak dikenali.');
    }

    private function summary_data(array $surveys, array $filters)
    {
        $data = [];
        foreach ($surveys as $surveyId => $survey) {
            $surveyFilters = $filters;
            $surveyFilters['survey_id'] = (int) $surveyId;
            $summary = $this->ipak->summary($surveyFilters);
            $total = (int) $summary['total_responses'];
            $average = round((float) $summary['average_score'], 2);
            $category = $total > 0
                ? $this->ipak->survey_score_category((int) $surveyId, $average)
                : ['label' => 'Belum ada data', 'color' => '#64748b'];
            $data[] = [
                'survey' => $this->survey_payload($survey),
                'total_responses' => $total,
                'average_score' => $average,
                'minimum_score' => round((float) $summary['minimum_score'], 2),
                'maximum_score' => round((float) $summary['maximum_score'], 2),
                'category' => [
                    'label' => $category['label'],
                    'color' => $category['color'],
                ],
            ];
        }
        return $data;
    }

    private function chart_data(array $surveys, array $filters, $year, $dimension)
    {
        $data = [];
        foreach ($surveys as $surveyId => $survey) {
            if ($dimension === 'questions') {
                $surveyFilters = $filters;
                $surveyFilters['survey_id'] = (int) $surveyId;
                $averages = $this->ipak->question_averages($surveyFilters);
                $questions = $this->ipak->get_questions_for_survey((int) $surveyId, true);
                $categories = [];
                $values = [];
                foreach ($questions as $questionId => $question) {
                    $categories[] = [
                        'question_id' => (int) $questionId,
                        'code' => $question['question_code'],
                        'text' => $question['question_text'],
                    ];
                    $values[] = isset($averages[$questionId]) ? round((float) $averages[$questionId], 2) : 0;
                }
                $chart = [
                    'dimension' => 'questions',
                    'title' => $survey['index_label'] . ' per Pertanyaan',
                    'type' => 'bar',
                    'categories' => $categories,
                    'series' => [[
                        'name' => $survey['index_label'],
                        'data' => $values,
                    ]],
                ];
            } else {
                $unitId = isset($filters['unit_id']) ? (int) $filters['unit_id'] : 0;
                $chart = $this->ipak->dimension_chart(
                    (int) $year,
                    $dimension,
                    $unitId,
                    (int) $surveyId,
                    $filters
                );
            }
            $data[] = [
                'survey' => $this->survey_payload($survey),
                'chart' => $chart,
            ];
        }
        return $data;
    }

    private function question_data(array $surveys)
    {
        $data = [];
        foreach ($surveys as $surveyId => $survey) {
            $questions = [];
            foreach ($this->ipak->get_questions_for_survey((int) $surveyId, true) as $question) {
                $options = [];
                if (!empty($question['options'])) {
                    foreach ($question['options'] as $option) {
                        $options[] = [
                            'code' => $option['option_code'],
                            'label' => $option['option_label'],
                            'value' => (float) $option['option_value'],
                            'normalized_score' => (float) $option['normalized_score'],
                        ];
                    }
                }
                $questions[] = [
                    'id' => (int) $question['id'],
                    'code' => $question['question_code'],
                    'text' => $question['question_text'],
                    'measurement' => $question['measurement_name'],
                    'category' => $question['category_name'],
                    'weight' => isset($question['survey_weight'])
                        ? (float) $question['survey_weight']
                        : (float) $question['weight'],
                    'options' => $options,
                ];
            }
            $data[] = [
                'survey' => $this->survey_payload($survey),
                'questions' => $questions,
            ];
        }
        return $data;
    }

    private function detail_data(array $survey, array $filters, $page, $perPage)
    {
        $filters['survey_id'] = (int) $survey['id'];
        $total = $this->ipak->count_responses($filters);
        $rows = $this->ipak->get_responses($filters, $perPage, ($page - 1) * $perPage);
        $education = $this->config->item('ipak_education');
        $jobs = $this->config->item('ipak_jobs');
        $sectors = $this->ipak->sector_options();
        $allowedFields = $this->authenticatedClient['allowed_detail_fields'];
        $result = [];

        foreach ($rows as $row) {
            $metadata = $this->ipak->decode_metadata($row['keterangan']);
            $item = [];
            foreach ($allowedFields as $field) {
                switch ($field) {
                    case 'response_id':
                        $item['response_id'] = (int) $row['kode'];
                        $item['response_source'] = isset($row['response_source'])
                            ? strtolower((string) $row['response_source'])
                            : 'skm';
                        $item['response_key'] = isset($row['response_key'])
                            ? (string) $row['response_key']
                            : (string) $row['kode'];
                        break;
                    case 'reference':
                        $item['reference'] = (string) $row['resi'];
                        break;
                    case 'submission_group':
                        $item['submission_group_code'] = isset($row['kode_pengisian'])
                            ? (string) $row['kode_pengisian']
                            : '';
                        break;
                    case 'date':
                        $item['submitted_at'] = !empty($row['tgl_buat'])
                            ? date(DATE_ATOM, strtotime($row['tgl_buat']))
                            : date(DATE_ATOM, strtotime($row['tgl_pengisian']));
                        break;
                    case 'survey':
                        $item['survey'] = $this->survey_payload($survey);
                        $item['survey']['version'] = isset($row['versi_survei'])
                            ? (string) $row['versi_survei']
                            : (isset($survey['survey_version']) ? $survey['survey_version'] : '');
                        break;
                    case 'score':
                        $category = $this->ipak->survey_score_category((int) $survey['id'], (float) $row['rata']);
                        $item['result'] = [
                            'score' => round((float) $row['rata'], 2),
                            'category' => $category['label'],
                        ];
                        break;
                    case 'name':
                        $item['respondent_name'] = (string) $row['nama_responden'];
                        break;
                    case 'email':
                        $item['email'] = isset($metadata['email']) && $metadata['email'] !== ''
                            ? (string) $metadata['email']
                            : (string) $row['responden'];
                        break;
                    case 'phone':
                        $item['phone'] = (string) $row['mobile'];
                        break;
                    case 'nib':
                        $item['identity_number'] = isset($row['nib']) ? (string) $row['nib'] : '';
                        break;
                    case 'gender':
                        $genderId = (int) $row['gender'];
                        $item['gender'] = [
                            'id' => $genderId,
                            'label' => $genderId === 1 ? 'Laki-laki' : ($genderId === 2 ? 'Perempuan' : ''),
                        ];
                        break;
                    case 'age':
                        $item['age'] = (int) $row['usia'] > 0 ? (int) $row['usia'] : null;
                        break;
                    case 'education':
                        $educationId = (int) $row['pendidikan_id'];
                        $item['education'] = [
                            'id' => $educationId,
                            'label' => isset($education[$educationId]) ? $education[$educationId] : '',
                        ];
                        break;
                    case 'job':
                        $jobId = (int) $row['pekerjaan_id'];
                        $item['job'] = [
                            'id' => $jobId,
                            'label' => $jobId === 5 && !empty($metadata['job_other'])
                                ? $metadata['job_other']
                                : (isset($jobs[$jobId]) ? $jobs[$jobId] : ''),
                        ];
                        break;
                    case 'sector':
                        $sectorId = (int) $row['sektor'];
                        $item['sector'] = [
                            'id' => $sectorId,
                            'label' => !empty($metadata['sector_name'])
                                ? $metadata['sector_name']
                                : (!empty($metadata['service_other'])
                                    ? $metadata['service_other']
                                    : (isset($sectors[$sectorId]) ? $sectors[$sectorId] : '')),
                        ];
                        break;
                    case 'unit':
                        $item['regional_unit'] = [
                            'id' => isset($metadata['unit_id']) ? (int) $metadata['unit_id'] : 0,
                            'name' => isset($metadata['unit_name']) ? (string) $metadata['unit_name'] : '',
                        ];
                        break;
                    case 'suggestion':
                        $item['suggestion'] = (string) $row['saran'];
                        break;
                    case 'custom_fields':
                        $item['custom_fields'] = [];
                        foreach ($this->ipak->get_response_fields(
                            (int) $row['kode'],
                            isset($row['response_source']) ? $row['response_source'] : 'SKM'
                        ) as $customField) {
                            $item['custom_fields'][] = [
                                'key' => $customField['field_key'],
                                'label' => $customField['field_label_snapshot'],
                                'group' => $customField['field_group'],
                                'value' => $customField['field_value'],
                            ];
                        }
                        break;
                    case 'answers':
                        $item['answers'] = [];
                        foreach ($this->ipak->get_response_answers(
                            (int) $row['kode'],
                            isset($row['response_source']) ? $row['response_source'] : 'SKM'
                        ) as $answer) {
                            $item['answers'][] = [
                                'question_id' => (int) $answer['question_id'],
                                'question_code' => isset($answer['question_code']) ? $answer['question_code'] : '',
                                'question' => $answer['question_text_snapshot'],
                                'answer' => $answer['option_label_snapshot'],
                                'value' => (float) $answer['answer_value'],
                                'normalized_score' => (float) $answer['normalized_score'],
                                'measurement' => $answer['measurement_snapshot'],
                                'category' => $answer['category_snapshot'],
                            ];
                        }
                        break;
                }
            }
            $result[] = $item;
        }

        return ['total' => $total, 'rows' => $result];
    }

    private function allowed_surveys(array $client)
    {
        $allSurveys = $this->ipak->get_surveys(true);
        if ($client['survey_scope'] === 'all') {
            return $allSurveys;
        }
        $allowedIds = array_map('intval', $client['allowed_survey_ids']);
        foreach (array_keys($allSurveys) as $surveyId) {
            if (!in_array((int) $surveyId, $allowedIds, true)) {
                unset($allSurveys[$surveyId]);
            }
        }
        return $allSurveys;
    }

    private function selected_surveys(array $allowedSurveys)
    {
        $requested = trim((string) $this->input->get('survey', true));
        if ($requested === '') {
            $requested = trim((string) $this->input->get('survey_id', true));
        }
        if ($requested === '') {
            return ['ok' => true, 'surveys' => $allowedSurveys];
        }
        foreach ($allowedSurveys as $surveyId => $survey) {
            if (
                (ctype_digit($requested) && (int) $requested === (int) $surveyId)
                || strcasecmp($requested, (string) $survey['survey_code']) === 0
                || strcasecmp($requested, (string) $survey['kode_unik']) === 0
            ) {
                return ['ok' => true, 'surveys' => [(int) $surveyId => $survey]];
            }
        }
        return [
            'ok' => false,
            'status' => 403,
            'error' => 'Survei tidak ditemukan atau tidak diizinkan untuk kunci API tersebut.',
        ];
    }

    private function request_filters()
    {
        $years = $this->ipak->response_year_options();
        $year = (int) $this->input->get('year', true);
        if ($year < 2000 || $year > 2100) {
            $yearValues = array_keys($years);
            $year = $yearValues ? (int) $yearValues[0] : (int) date('Y');
        }
        $dateFrom = $this->safe_date($this->input->get('date_from', true));
        $dateTo = $this->safe_date($this->input->get('date_to', true));
        if ($dateFrom === '' && $dateTo === '') {
            $dateFrom = $year . '-01-01';
            $dateTo = $year . '-12-31';
        }
        $query = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'gender' => max(0, (int) $this->input->get('gender', true)),
            'education' => max(0, (int) $this->input->get('education', true)),
            'job' => max(0, (int) $this->input->get('job', true)),
            'service' => max(0, (int) $this->input->get('service', true)),
            'unit_id' => max(0, (int) $this->input->get('unit_id', true)),
        ];
        $public = ['year' => $year];
        foreach ($query as $key => $value) {
            if ($value !== '' && $value !== 0) {
                $public[$key] = $value;
            }
        }
        return ['year' => $year, 'query' => $query, 'public' => $public];
    }

    private function survey_options_payload(array $surveys)
    {
        $result = [];
        foreach ($surveys as $survey) {
            $result[] = $this->survey_payload($survey);
        }
        return $result;
    }

    private function survey_payload(array $survey)
    {
        return [
            'id' => (int) $survey['id'],
            'code' => (string) $survey['survey_code'],
            'uuid' => (string) $survey['kode_unik'],
            'name' => (string) $survey['survey_name'],
            'index_label' => (string) $survey['index_label'],
        ];
    }

    private function request_api_key()
    {
        $key = trim((string) $this->input->get_request_header('X-API-Key', true));
        if ($key !== '') {
            return $key;
        }
        $authorization = trim((string) $this->input->get_request_header('Authorization', true));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private function safe_date($value)
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }
        $parts = array_map('intval', explode('-', $value));
        return checkdate($parts[1], $parts[2], $parts[0]) ? $value : '';
    }

    private function apply_cors(array $client)
    {
        $allowedOrigin = trim((string) $client['allowed_origin']);
        if ($allowedOrigin === '') {
            return;
        }
        $requestOrigin = trim((string) $this->input->get_request_header('Origin', true));
        if ($allowedOrigin === '*') {
            $this->output->set_header('Access-Control-Allow-Origin: *');
            return;
        }
        if ($requestOrigin !== '' && hash_equals($allowedOrigin, $requestOrigin)) {
            $this->output
                ->set_header('Access-Control-Allow-Origin: ' . $requestOrigin)
                ->set_header('Vary: Origin');
        }
    }

    private function respond_success($resource, array $requestMeta, array $data, $responseCount, array $extra = [])
    {
        $payload = array_merge([
            'success' => true,
            'api_version' => 'v1',
            'resource' => $resource,
            'client' => [
                'code' => $this->authenticatedClient['client_code'],
                'name' => $this->authenticatedClient['client_name'],
            ],
            'request' => $requestMeta,
            'generated_at' => date(DATE_ATOM),
            'data' => $data,
        ], $extra);
        return $this->respond_json($payload, 200, $responseCount);
    }

    private function respond_error($status, $message, array $extra = [])
    {
        $payload = array_merge([
            'success' => false,
            'api_version' => 'v1',
            'error' => [
                'status' => (int) $status,
                'message' => (string) $message,
            ],
            'generated_at' => date(DATE_ATOM),
        ], $extra);
        return $this->respond_json($payload, (int) $status, 0);
    }

    private function respond_json(array $payload, $status, $responseCount)
    {
        if ($this->authenticatedClient) {
            $this->survey_api->log_request([
                'api_client_id' => (int) $this->authenticatedClient['id'],
                'resource_name' => $this->requestedResource !== '' ? $this->requestedResource : 'authentication',
                'survey_id' => $this->requestedSurveyId,
                'request_method' => strtoupper($this->input->method()),
                'request_ip' => $this->input->ip_address(),
                'query_string' => $this->input->server('QUERY_STRING', true),
                'status_code' => (int) $status,
                'response_count' => (int) $responseCount,
                'duration_ms' => (int) round((microtime(true) - $this->requestStartedAt) * 1000),
            ]);
        }
        $this->output
            ->set_status_header((int) $status)
            ->set_content_type('application/json', 'utf-8')
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
