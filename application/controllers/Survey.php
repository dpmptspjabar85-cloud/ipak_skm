<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Survey extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ipaksurvey_model', 'ipak');
        $this->config->load('ipak');
    }

    public function index()
    {
        $resi = $this->request_resi();
        $formCode = $this->request_form_code();
        $data = $this->page_data($formCode);
        if (empty($data['form_definition']) || empty($data['question_definitions'])) {
            return $this->render_access_page(
                'Form survei belum tersedia atau sedang dinonaktifkan.',
                $resi,
                'warning'
            );
        }

        $requiresResi = !empty($data['form_definition']['requires_resi']);
        $permit = [];
        if ($requiresResi) {
            if ($resi === '') {
                return $this->render_access_page(
                    'Form ini memuat Survei SKM. Masukkan nomor resi izin yang telah terbit untuk melanjutkan.',
                    '',
                    'info'
                );
            }
            if (!$this->valid_resi($resi)) {
                return $this->render_access_page(
                    'Format nomor resi tidak valid. Periksa kembali nomor yang terdapat pada dokumen izin.',
                    $resi,
                    'error'
                );
            }
            $permit = $this->ipak->find_permit_by_resi($resi);
            if (!$permit) {
                return $this->render_access_page(
                    'Nomor resi tidak ditemukan pada data permohonan.',
                    $resi,
                    'error'
                );
            }
            if (empty($permit['is_issued'])) {
                return $this->render_access_page(
                    'Izin untuk nomor resi tersebut belum berstatus terbit atau selesai.',
                    $resi,
                    'warning'
                );
            }
            if ($this->ipak->has_ipak_response($resi)) {
                return $this->render_access_page(
                    'Penilaian SKM untuk nomor resi tersebut sudah pernah dikirim.',
                    $resi,
                    'success'
                );
            }
        }

        $old = $this->session->flashdata('old') ?: [];
        $prefill = $requiresResi ? [
            'name' => $this->permit_applicant_name($permit),
            'phone' => $this->permit_phone($permit),
            'email' => $this->permit_email($permit),
            'identity_number' => substr(trim((string) $permit['nib']), 0, 20),
        ] : [];
        $data['old'] = array_merge($prefill, $old);
        $data['validation_errors'] = $this->session->flashdata('validation_errors') ?: [];
        $data['permit'] = $permit;
        $data['resi'] = $resi;
        $data['requires_resi'] = $requiresResi;
        $data['form_code'] = $data['form_definition']['form_code'];
        $this->load->view('public/survey', $data);
    }

    public function dashboard()
    {
        $requestedResi = $this->request_resi();
        if ($requestedResi !== '') {
            return redirect($this->survey_url($requestedResi, $this->request_form_code()));
        }

        $yearInput = $this->input->get('year', true);
        if ($yearInput === null || $yearInput === '') {
            $yearInput = $this->input->get('thnskm', true);
        }
        $year = (int) $yearInput;
        if ($year < 2020 || $year > ((int) date('Y') + 1)) {
            $year = (int) date('Y');
        }

        $dimension = trim((string) $this->input->get('dimension', true));
        if ($dimension === '') {
            $tokenMap = [1 => 'gender', 2 => 'age', 3 => 'education', 4 => 'job', 5 => 'service', 6 => 'overall'];
            $legacyToken = (int) $this->input->get('token', true);
            $dimension = isset($tokenMap[$legacyToken]) ? $tokenMap[$legacyToken] : 'overall';
        }
        $allowedDimensions = ['overall', 'gender', 'age', 'education', 'job', 'service'];
        if (!in_array($dimension, $allowedDimensions, true)) {
            $dimension = 'overall';
        }
        $unitInput = $this->input->get('unit_id', true);
        if ($unitInput === null || $unitInput === '') {
            $unitInput = $this->input->get('stsd', true);
        }
        $unitId = max(0, (int) $unitInput);
        $units = $this->ipak->unit_options();
        if ($unitId && !isset($units[$unitId])) {
            $unitId = 0;
        }
        $surveys = $this->ipak->get_surveys(true);
        $surveyInput = $this->input->get('survey_id', true);
        $surveyId = ($surveyInput === null || $surveyInput === '')
            ? $this->ipak->legacy_skm_survey_id()
            : max(0, (int) $surveyInput);
        if ($surveyId > 0 && !isset($surveys[$surveyId])) {
            $surveyId = 0;
        }

        $filters = [
            'date_from' => $year . '-01-01',
            'date_to' => $year . '-12-31',
            'unit_id' => $unitId,
            'survey_id' => $surveyId,
        ];
        $summary = $this->ipak->summary($filters);
        if ((int) $summary['total_responses'] > 0) {
            $category = $surveyId > 0
                ? $this->ipak->survey_score_category($surveyId, (float) $summary['average_score'])
                : $this->score_category((float) $summary['average_score']);
        } else {
            $category = ['label' => 'Belum ada data', 'color' => '#64748b'];
        }
        $selectedSurvey = $surveyId > 0 ? $surveys[$surveyId] : [];
        $questionDefinitions = $surveyId > 0
            ? $this->ipak->get_questions_for_survey($surveyId, true)
            : $this->page_data()['question_definitions'];

        $data = array_merge($this->page_data(), [
            'year' => $year,
            'dimension' => $dimension,
            'unit_id' => $unitId,
            'units' => $units,
            'unit_label' => $unitId ? $units[$unitId] : 'Seluruh Perangkat Daerah',
            'surveys' => $surveys,
            'survey_id' => $surveyId,
            'selected_survey' => $selectedSurvey,
            'index_label' => $selectedSurvey ? $selectedSurvey['index_label'] : 'Semua Hasil Survei',
            'summary' => $summary,
            'category' => $category,
            'chart_data' => $this->ipak->dimension_chart($year, $dimension, $unitId, $surveyId),
            'question_averages' => $this->ipak->question_averages($filters),
            'distribution' => $this->ipak->answer_distribution($filters),
            'question_definitions' => $questionDefinitions,
            'public_forms' => $this->ipak->get_public_forms('', 'all', 4, 0),
            'public_form_count' => $this->ipak->count_public_forms(),
        ]);
        $this->load->view('public/dashboard', $data);
    }

    public function catalog()
    {
        $search = trim((string) $this->input->get('q', true));
        $search = function_exists('mb_substr') ? mb_substr($search, 0, 80) : substr($search, 0, 80);

        $type = strtolower(trim((string) $this->input->get('type', true)));
        $allowedTypes = ['all', 'skm', 'regular', 'combined'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }

        $perPage = 9;
        $totalForms = $this->ipak->count_public_forms($search, $type);
        $totalPages = max(1, (int) ceil($totalForms / $perPage));
        $page = max(1, (int) $this->input->get('page', true));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $data = array_merge($this->page_data(), [
            'title' => 'Pilih Survei',
            'forms' => $this->ipak->get_public_forms($search, $type, $perPage, ($page - 1) * $perPage),
            'search' => $search,
            'type' => $type,
            'page' => $page,
            'per_page' => $perPage,
            'total_forms' => $totalForms,
            'total_pages' => $totalPages,
        ]);
        $this->load->view('public/surveys', $data);
    }

    public function submit()
    {
        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
        }

        $resi = trim((string) $this->input->post('resi', true));
        $formCode = strtoupper(trim((string) $this->input->post('form_code', true)));
        $formDefinition = $this->ipak->get_form_definition($formCode);
        $questionDefinitions = !empty($formDefinition['questions']) ? $formDefinition['questions'] : [];
        $sectorOptions = $this->ipak->sector_options();
        if (!$questionDefinitions) {
            $this->session->set_flashdata('access_error', 'Pertanyaan survei belum tersedia.');
            return redirect($this->survey_url($resi, $formCode));
        }

        $requiresResi = !empty($formDefinition['requires_resi']);
        $permit = [];
        if ($requiresResi) {
            if (!$this->valid_resi($resi)) {
                $this->session->set_flashdata('access_error', 'Nomor resi SKM tidak valid.');
                return redirect($this->survey_url('', $formCode));
            }
            $permit = $this->ipak->find_permit_by_resi($resi);
            if (!$permit || empty($permit['is_issued'])) {
                $this->session->set_flashdata('access_error', 'Izin belum ditemukan atau belum berstatus terbit.');
                return redirect($this->survey_url($resi, $formCode));
            }
            if ($this->ipak->has_ipak_response($resi)) {
                $this->session->set_flashdata('access_error', 'Penilaian SKM untuk nomor resi ini sudah pernah dikirim.');
                return redirect($this->survey_url($resi, $formCode));
            }
        } else {
            $resi = '';
        }

        $rules = [
            ['field' => 'suggestion', 'label' => 'Saran', 'rules' => 'trim|max_length[2000]'],
            ['field' => 'consent', 'label' => 'Persetujuan privasi', 'rules' => 'required|in_list[1]'],
        ];
        $fieldSettings = !empty($formDefinition['respondent_fields'])
            ? $formDefinition['respondent_fields']
            : [];
        $fieldRules = [
            'name' => 'trim|max_length[100]',
            'email' => 'trim|valid_email|max_length[100]',
            'phone' => 'trim|min_length[8]|max_length[25]',
            'identity_number' => 'trim|max_length[20]',
            'age' => 'trim|integer|greater_than_equal_to[15]|less_than_equal_to[100]',
            'gender' => 'in_list[1,2]',
            'education' => 'in_list[' . implode(',', array_map('intval', array_keys($this->config->item('ipak_education')))) . ']',
            'job' => 'in_list[' . implode(',', array_map('intval', array_keys($this->config->item('ipak_jobs')))) . ']',
            'service' => 'in_list[' . implode(',', array_map('intval', array_keys($sectorOptions))) . ']',
        ];
        $extraFieldSettings = [];
        foreach ($fieldRules as $fieldKey => $validationRule) {
            if (empty($fieldSettings[$fieldKey]) || $fieldSettings[$fieldKey]['field_mode'] === 'hidden') {
                continue;
            }
            $isRequired = $fieldSettings[$fieldKey]['field_mode'] === 'required';
            $rules[] = [
                'field' => $fieldKey,
                'label' => $fieldSettings[$fieldKey]['field_label'],
                'rules' => ($isRequired ? 'required|' : '') . $validationRule,
            ];
        }
        foreach ($fieldSettings as $fieldKey => $fieldSetting) {
            if ($fieldSetting['field_mode'] === 'hidden' || isset($fieldRules[$fieldKey])) {
                continue;
            }
            $inputName = 'extra_' . $fieldKey;
            $fieldType = isset($fieldSetting['field_type']) ? $fieldSetting['field_type'] : 'text';
            $validationRule = 'trim|max_length[255]';
            if ($fieldType === 'email') {
                $validationRule = 'trim|valid_email|max_length[100]';
            } elseif ($fieldType === 'tel') {
                $validationRule = 'trim|min_length[8]|max_length[25]';
            } elseif ($fieldType === 'number') {
                $validationRule = 'trim|numeric|max_length[30]';
            } elseif ($fieldType === 'textarea') {
                $validationRule = 'trim|max_length[2000]';
            }
            $rules[] = [
                'field' => $inputName,
                'label' => $fieldSetting['field_label'],
                'rules' => ($fieldSetting['field_mode'] === 'required' ? 'required|' : '') . $validationRule,
            ];
            $extraFieldSettings[$inputName] = $fieldSetting;
        }

        foreach ($questionDefinitions as $questionId => $question) {
            $allowedOptionIds = [];
            foreach ($question['options'] as $option) {
                $allowedOptionIds[] = (int) $option['id'];
            }
            $rules[] = [
                'field' => 'answer_' . (int) $questionId,
                'label' => 'Jawaban ' . $question['question_code'],
                'rules' => 'required|in_list[' . implode(',', $allowedOptionIds) . ']',
            ];
        }

        $this->form_validation->set_rules($rules);
        if (!$this->form_validation->run()) {
            $this->session->set_flashdata('old', $this->input->post(NULL, true));
            $this->session->set_flashdata('validation_errors', $this->form_validation->error_array());
            return redirect($this->survey_url($resi, $formCode));
        }

        foreach ($extraFieldSettings as $inputName => $fieldSetting) {
            if ($fieldSetting['field_type'] !== 'select' || empty($fieldSetting['options'])) {
                continue;
            }
            $selectedValue = trim((string) $this->input->post($inputName, true));
            if ($selectedValue !== '' && !in_array($selectedValue, $fieldSetting['options'], true)) {
                $this->session->set_flashdata('old', $this->input->post(NULL, true));
                $this->session->set_flashdata('validation_errors', [
                    $inputName => 'Pilihan untuk ' . $fieldSetting['field_label'] . ' tidak valid.',
                ]);
                return redirect($this->survey_url($resi, $formCode));
            }
        }

        $jobVisible = !empty($fieldSettings['job']) && $fieldSettings['job']['field_mode'] !== 'hidden';
        $serviceVisible = !empty($fieldSettings['service']) && $fieldSettings['service']['field_mode'] !== 'hidden';
        $job = $jobVisible ? (int) $this->input->post('job', true) : 0;
        $jobOther = trim((string) $this->input->post('job_other', true));

        if ($jobVisible && $job === 5 && $jobOther === '') {
            $this->session->set_flashdata('old', $this->input->post(NULL, true));
            $this->session->set_flashdata('validation_errors', ['job_other' => 'Pekerjaan lainnya wajib diisi.']);
            return redirect($this->survey_url($resi, $formCode));
        }
        $service = $serviceVisible ? (int) $this->input->post('service', true) : 0;
        $serviceOther = '';

        $submitted = $this->input->post(NULL, true);
        $answerDetails = $this->ipak->build_answer_details($submitted, $questionDefinitions);
        if (!$answerDetails) {
            $this->session->set_flashdata('old', $submitted);
            $this->session->set_flashdata('validation_errors', ['answers' => 'Pilihan jawaban tidak valid.']);
            return redirect($this->survey_url($resi, $formCode));
        }

        $permitName = $requiresResi ? $this->permit_applicant_name($permit) : '';
        $permitPhone = $requiresResi ? $this->permit_phone($permit) : '';
        $permitEmail = $requiresResi ? $this->permit_email($permit) : '';
        $name = $permitName !== '' ? $permitName : trim((string) $this->input->post('name', true));
        $phone = $permitPhone !== '' ? $permitPhone : trim((string) $this->input->post('phone', true));
        $email = $permitEmail !== '' ? $permitEmail : strtolower(trim((string) $this->input->post('email', true)));
        $permitNib = $requiresResi ? trim((string) $permit['nib']) : '';
        $identityNumber = trim((string) $this->input->post('identity_number', true));
        $defaultUnit = $requiresResi ? [] : $this->ipak->default_regular_unit();
        $selectedSectorName = (!$requiresResi && $service > 0 && isset($sectorOptions[$service]))
            ? $sectorOptions[$service]
            : '';
        $responseFields = [];
        foreach ($extraFieldSettings as $inputName => $fieldSetting) {
            $responseFields[] = [
                'field_key' => $fieldSetting['field_key'],
                'field_label' => $fieldSetting['field_label'],
                'field_group' => $fieldSetting['field_group'],
                'field_value' => trim((string) $this->input->post($inputName, true)),
            ];
        }

        $result = $this->ipak->create_response([
            'resi' => $resi,
            'permohonan_id' => $requiresResi ? (int) $permit['permohonan_id'] : null,
            'permit_type_id' => $requiresResi ? (int) $permit['permit_type_id'] : 0,
            'permit_status' => $requiresResi ? trim((string) $permit['status_berkas']) : '',
            'permit_name' => $requiresResi ? trim((string) $permit['permit_name']) : '',
            'sector_name' => $requiresResi ? trim((string) $permit['sector_name']) : $selectedSectorName,
            'sector_source' => $requiresResi ? 'permit' : ($service > 0 ? 'trsektor' : 'not_collected'),
            'unit_id' => $requiresResi
                ? (isset($permit['unit_id']) ? (int) $permit['unit_id'] : 0)
                : (isset($defaultUnit['id']) ? (int) $defaultUnit['id'] : 0),
            'unit_name' => $requiresResi
                ? (isset($permit['unit_name']) ? trim((string) $permit['unit_name']) : '')
                : (isset($defaultUnit['n_unitkerja']) ? trim((string) $defaultUnit['n_unitkerja']) : ''),
            'name' => substr($name, 0, 100),
            'phone' => substr($phone, 0, 25),
            'email' => substr(strtolower($email), 0, 100),
            'nib' => substr($permitNib !== '' ? $permitNib : $identityNumber, 0, 20),
            'age' => (int) $this->input->post('age', true),
            'gender' => (int) $this->input->post('gender', true),
            'education' => (int) $this->input->post('education', true),
            'job' => $job,
            'job_other' => $jobOther,
            'service' => $requiresResi ? (int) $permit['trsektor_id'] : $service,
            'service_other' => $serviceOther,
            'suggestion' => trim((string) $this->input->post('suggestion', true)),
            'response_fields' => $responseFields,
            'answer_details' => $answerDetails,
            'form_definition' => $formDefinition,
        ]);

        if (!$result) {
            $this->session->set_flashdata('old', $this->input->post(NULL, true));
            $this->session->set_flashdata('save_error', 'Data belum berhasil disimpan. Silakan coba kembali.');
            return redirect($this->survey_url($resi, $formCode));
        }

        $this->session->set_flashdata('submitted_reference', $result['reference']);
        $this->session->set_flashdata('submitted_results', $result['survey_results']);
        return redirect('survey/success/' . rawurlencode($result['reference']));
    }

    public function success($reference = '')
    {
        $sessionReference = (string) $this->session->flashdata('submitted_reference');
        if (!$sessionReference || !hash_equals($sessionReference, (string) $reference)) {
            return redirect('survey');
        }

        $data = $this->page_data();
        $data['reference'] = $reference;
        $data['survey_results'] = $this->session->flashdata('submitted_results') ?: [];
        $this->load->view('public/success', $data);
    }

    private function page_data($formCode = '')
    {
        $formDefinition = $this->ipak->get_form_definition($formCode);
        $definitions = !empty($formDefinition['questions']) ? $formDefinition['questions'] : [];
        $questions = [];
        foreach ($definitions as $questionId => $question) {
            $questions[(int) $questionId] = $question['question_text'];
        }
        return [
            'title' => !empty($formDefinition['form_name'])
                ? $formDefinition['form_name']
                : $this->config->item('ipak_title'),
            'agency' => $this->config->item('ipak_agency'),
            'questions' => $questions,
            'question_definitions' => $definitions,
            'form_definition' => $formDefinition,
            'answer_labels' => [
                1 => 'Nilai di bawah 50',
                2 => 'Nilai 50–74',
                3 => 'Nilai 75–99',
                4 => 'Nilai 100',
            ],
            'education' => $this->config->item('ipak_education'),
            'jobs' => $this->config->item('ipak_jobs'),
            'services' => $this->ipak->sector_options(),
        ];
    }

    private function score_category($score)
    {
        foreach ($this->config->item('ipak_score_categories') as $category) {
            if ($score >= $category['min']) {
                return $category;
            }
        }
        return ['label' => '-', 'color' => '#64748b'];
    }

    private function request_resi()
    {
        foreach (['resi', 'nomor_resi', 'CODE', 'code'] as $key) {
            $value = trim((string) $this->input->get($key, true));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function request_form_code()
    {
        $code = strtoupper(trim((string) $this->input->get('form', true)));
        return preg_match('/^[A-Z0-9_-]{1,30}$/', $code) ? $code : '';
    }

    private function valid_resi($resi)
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]{5,20}$/', trim((string) $resi));
    }

    private function survey_url($resi, $formCode = '')
    {
        $query = [];
        if (trim((string) $resi) !== '') {
            $query[] = 'resi=' . rawurlencode(trim((string) $resi));
        }
        if ($formCode !== '') {
            $query[] = 'form=' . rawurlencode($formCode);
        }
        return site_url('survey') . ($query ? '?' . implode('&', $query) : '');
    }

    private function permit_applicant_name(array $permit)
    {
        $company = trim((string) $permit['namaPerusahaan']);
        $name = $company !== '' ? $company : trim((string) $permit['namaPemohon']);
        return substr($name, 0, 100);
    }

    private function permit_phone(array $permit)
    {
        $companyPhone = trim((string) $permit['telpPerusahaan']);
        $phone = $companyPhone !== '' ? $companyPhone : trim((string) $permit['telpPemohon']);
        return substr($phone, 0, 25);
    }

    private function permit_email(array $permit)
    {
        $email = strtolower(trim((string) $permit['emailPerusahaan']));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? substr($email, 0, 100) : '';
    }

    private function render_access_page($message, $resi, $status)
    {
        $flashError = $this->session->flashdata('access_error');
        if ($flashError) {
            $message = $flashError;
            $status = 'error';
        }
        $formCode = $this->request_form_code();
        $data = array_merge($this->page_data($formCode), [
            'access_message' => $message,
            'access_status' => $status,
            'resi' => $resi,
            'form_code' => $formCode,
        ]);
        $this->load->view('public/access', $data);
    }
}
