<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ipaksurvey_model', 'ipak');
        $this->config->load('ipak');
    }

    public function index()
    {
        return $this->is_logged_in() ? redirect('admin/dashboard') : redirect('admin/login');
    }

    public function login()
    {
        if ($this->is_logged_in()) {
            return redirect('admin/dashboard');
        }

        $error = '';
        if (strtoupper($this->input->method()) === 'POST') {
            $this->form_validation->set_rules('username', 'Username', 'trim|required|max_length[100]');
            $this->form_validation->set_rules('password', 'Password', 'required|max_length[255]');

            if ($this->form_validation->run()) {
                $username = trim((string) $this->input->post('username', true));
                $password = (string) $this->input->post('password', false);
                $user = $this->ipak->get_admin_by_username($username);

                if ($user && $this->verify_password($password, $user['password'])) {
                    $this->session->sess_regenerate(true);
                    $this->session->set_userdata([
                        'ipak_admin_id' => (int) $user['id'],
                        'ipak_admin_username' => $user['username'],
                        'ipak_admin_name' => $user['nama'] ?: $user['username'],
                        'ipak_admin_role' => isset($user['role_name']) ? $user['role_name'] : 'admin',
                        'ipak_admin_logged_in' => true,
                    ]);
                    $this->ipak->touch_admin_login($user['id']);
                    return redirect('admin/dashboard');
                }
                $error = 'Username atau password tidak sesuai.';
            }
        }

        $this->load->view('admin/login', [
            'error' => $error,
            'title' => 'Login Backoffice Survei',
        ]);
    }

    public function logout()
    {
        $this->session->unset_userdata([
            'ipak_admin_id',
            'ipak_admin_username',
            'ipak_admin_name',
            'ipak_admin_role',
            'ipak_admin_logged_in',
        ]);
        $this->session->sess_regenerate(true);
        return redirect('admin/login');
    }

    public function dashboard()
    {
        $this->require_login();
        $availableYears = $this->ipak->response_year_options();
        $yearInput = $this->input->get('year', true);
        if ($yearInput === null || $yearInput === '') {
            $yearInput = $this->input->get('thnskm', true);
        }
        $year = (int) $yearInput;
        if (!$availableYears) {
            $year = (int) date('Y');
        } elseif (!isset($availableYears[$year])) {
            $yearValues = array_keys($availableYears);
            $year = isset($availableYears[(int) date('Y')])
                ? (int) date('Y')
                : (int) $yearValues[0];
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
        $yearFilters = [
            'date_from' => $year . '-01-01',
            'date_to' => $year . '-12-31',
        ];
        $surveys = $this->ipak->surveys_with_responses($yearFilters, true);
        $surveyInput = $this->input->get('survey_id', true);
        if ($surveyInput === null || $surveyInput === '') {
            $legacySurveyId = $this->ipak->legacy_skm_survey_id();
            $surveyValues = array_keys($surveys);
            $surveyId = isset($surveys[$legacySurveyId])
                ? $legacySurveyId
                : ($surveyValues ? (int) $surveyValues[0] : 0);
        } else {
            $surveyId = max(0, (int) $surveyInput);
        }
        if ($surveyId > 0 && !isset($surveys[$surveyId])) {
            $surveyValues = array_keys($surveys);
            $surveyId = $surveyValues ? (int) $surveyValues[0] : 0;
        }
        $unitInput = $this->input->get('unit_id', true);
        if ($unitInput === null || $unitInput === '') {
            $unitInput = $this->input->get('stsd', true);
        }
        $unitId = max(0, (int) $unitInput);
        $unitOptionFilters = $yearFilters;
        $unitOptionFilters['survey_id'] = $surveyId;
        $units = $this->ipak->response_unit_options($unitOptionFilters);
        if ($unitId && !isset($units[$unitId])) {
            $unitId = 0;
        }
        $filters = [
            'date_from' => $year . '-01-01',
            'date_to' => $year . '-12-31',
            'unit_id' => $unitId,
            'survey_id' => $surveyId,
        ];

        $availableDimensions = ['overall'];
        foreach (['gender', 'age', 'education', 'job', 'service'] as $dimensionOption) {
            if ($this->ipak->response_distinct_values($dimensionOption, $filters)) {
                $availableDimensions[] = $dimensionOption;
            }
        }
        if (!in_array($dimension, $availableDimensions, true)) {
            $dimension = 'overall';
        }
        $summary = $this->ipak->summary($filters);
        $selectedSurvey = $surveyId > 0 ? $surveys[$surveyId] : [];
        $defaultForm = $this->ipak->get_form_definition();
        $questionDefinitions = $surveyId > 0
            ? $this->ipak->get_questions_for_survey($surveyId, true)
            : (!empty($defaultForm['questions']) ? $defaultForm['questions'] : []);
        if ((int) $summary['total_responses'] > 0) {
            $category = $surveyId > 0
                ? $this->ipak->survey_score_category($surveyId, (float) $summary['average_score'])
                : $this->score_category((float) $summary['average_score']);
        } else {
            $category = ['label' => 'Belum ada data', 'color' => '#64748b'];
        }
        $this->render('admin/dashboard', [
            'page_title' => 'Dashboard',
            'year' => $year,
            'dimension' => $dimension,
            'unit_id' => $unitId,
            'units' => $units,
            'unit_label' => $unitId
                ? $units[$unitId]
                : (count($units) === 1 ? reset($units) : 'Seluruh Perangkat Daerah'),
            'surveys' => $surveys,
            'survey_id' => $surveyId,
            'selected_survey' => $selectedSurvey,
            'available_years' => $availableYears,
            'available_dimensions' => $availableDimensions,
            'has_data' => (int) $summary['total_responses'] > 0,
            'index_label' => $selectedSurvey ? $selectedSurvey['index_label'] : 'Semua Hasil Survei',
            'summary' => $summary,
            'category' => $category,
            'monthly' => $this->ipak->monthly_scores($year, ['unit_id' => $unitId, 'survey_id' => $surveyId]),
            'chart_data' => $this->ipak->dimension_chart($year, $dimension, $unitId, $surveyId),
            'question_averages' => $this->ipak->question_averages($filters),
            'distribution' => $this->ipak->answer_distribution($filters),
            'question_definitions' => $questionDefinitions,
            'answer_labels' => [
                1 => 'Nilai di bawah 50',
                2 => 'Nilai 50–74',
                3 => 'Nilai 75–99',
                4 => 'Nilai 100',
            ],
        ]);
    }

    public function entry()
    {
        $this->require_login();
        show_error(
            'Input respons manual telah dinonaktifkan. Respons hanya dapat dikirim melalui form publik/front office.',
            403,
            'Input respons dinonaktifkan'
        );
    }

    public function responses()
    {
        $this->require_login();
        $filters = $this->filters();
        $dateContext = [
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];
        $surveyTypes = $this->ipak->response_survey_type_options($dateContext);
        if ($filters['survey_type'] !== '' && !isset($surveyTypes[$filters['survey_type']])) {
            $filters['survey_type'] = '';
        }
        $surveyContext = $dateContext;
        $surveyContext['survey_type'] = $filters['survey_type'];
        $surveys = $this->ipak->surveys_with_responses($surveyContext, true);
        if ($filters['survey_id'] > 0 && !isset($surveys[$filters['survey_id']])) {
            $filters['survey_id'] = 0;
        }
        $optionContext = $surveyContext;
        $optionContext['survey_id'] = $filters['survey_id'];

        $units = $this->ipak->response_unit_options($optionContext);
        if ($filters['unit_id'] > 0 && !isset($units[$filters['unit_id']])) {
            $filters['unit_id'] = 0;
        }

        $allServices = $this->ipak->sector_options();
        $serviceValues = $this->ipak->response_distinct_values('service', $optionContext);
        $services = [];
        foreach ($serviceValues as $serviceId) {
            $services[$serviceId] = isset($allServices[$serviceId])
                ? $allServices[$serviceId]
                : 'Sektor #' . $serviceId;
        }
        if ($filters['service'] > 0 && !isset($services[$filters['service']])) {
            $filters['service'] = 0;
        }

        $genderLabels = [1 => 'Laki-laki', 2 => 'Perempuan'];
        $genderValues = $this->ipak->response_distinct_values('gender', $optionContext);
        $genders = [];
        foreach ($genderValues as $genderId) {
            if (isset($genderLabels[$genderId])) {
                $genders[$genderId] = $genderLabels[$genderId];
            }
        }
        if ($filters['gender'] > 0 && !isset($genders[$filters['gender']])) {
            $filters['gender'] = 0;
        }

        $allEducation = $this->config->item('ipak_education');
        $educationValues = $this->ipak->response_distinct_values('education', $optionContext);
        $education = [];
        foreach ($educationValues as $educationId) {
            if (isset($allEducation[$educationId])) {
                $education[$educationId] = $allEducation[$educationId];
            }
        }
        if ($filters['education'] > 0 && !isset($education[$filters['education']])) {
            $filters['education'] = 0;
        }

        $allJobs = $this->config->item('ipak_jobs');
        $jobValues = $this->ipak->response_distinct_values('job', $optionContext);
        $jobs = [];
        foreach ($jobValues as $jobId) {
            if (isset($allJobs[$jobId])) {
                $jobs[$jobId] = $allJobs[$jobId];
            }
        }
        if ($filters['job'] > 0 && !isset($jobs[$filters['job']])) {
            $filters['job'] = 0;
        }

        $page = max(1, (int) $this->input->get('page', true));
        $perPage = 20;
        $total = $this->ipak->count_responses($filters);
        $rows = $this->ipak->get_responses($filters, $perPage, ($page - 1) * $perPage);

        $this->render('admin/responses', [
            'page_title' => 'Data Respons',
            'filters' => $filters,
            'rows' => $rows,
            'page' => $page,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
            'education' => $education,
            'jobs' => $jobs,
            'genders' => $genders,
            'services' => $services,
            'units' => $units,
            'surveys' => $surveys,
            'survey_types' => $surveyTypes,
            'has_filter_data' => $this->ipak->count_responses([]) > 0,
        ]);
    }

    public function detail($id)
    {
        $this->require_login();
        $row = $this->ipak->find_response($id);
        if (!$row) {
            show_404();
        }
        $responseSource = isset($row['response_source']) ? $row['response_source'] : 'SKM';

        $this->render('admin/detail', [
            'page_title' => 'Detail Respons',
            'row' => $row,
            'metadata' => $this->ipak->decode_metadata($row['keterangan']),
            'response_answers' => $this->ipak->get_response_answers((int) $row['kode'], $responseSource),
            'response_fields' => $this->ipak->get_response_fields((int) $row['kode'], $responseSource),
            'survey_results' => $this->ipak->get_response_survey_results((int) $row['kode'], $responseSource),
            'education' => $this->config->item('ipak_education'),
            'jobs' => $this->config->item('ipak_jobs'),
            'services' => $this->ipak->sector_options(),
            'category' => $this->score_category((float) $row['rata']),
        ]);
    }

    public function export()
    {
        $this->require_login();
        $rows = $this->ipak->get_all_for_export($this->filters());
        $education = $this->config->item('ipak_education');
        $jobs = $this->config->item('ipak_jobs');
        $services = $this->ipak->sector_options();
        $questions = $this->ipak->get_questions(false);
        $surveys = $this->ipak->get_surveys(true);

        $filename = 'respons-survei-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        $header = ['Resi / Referensi', 'Jenis Survei', 'Kode Unik Survei', 'Kode Kelompok Pengisian', 'Versi Survei', 'Tanggal', 'Nama', 'Telepon', 'Email', 'Nomor Identitas / NIB', 'Usia', 'Gender', 'Pendidikan', 'Pekerjaan', 'Sektor / Layanan', 'Data Form Fleksibel'];
        foreach ($questions as $question) {
            $header[] = $question['question_code'];
        }
        foreach ($surveys as $survey) {
            $header[] = $survey['index_label'];
        }
        $header[] = 'Nilai Gabungan';
        $header[] = 'Saran';
        fputcsv($output, $header);

        foreach ($rows as $row) {
            $meta = $this->ipak->decode_metadata($row['keterangan']);
            $responseSource = isset($row['response_source']) ? $row['response_source'] : 'SKM';
            $responseAnswers = $this->ipak->get_response_answers((int) $row['kode'], $responseSource);
            $answersByQuestion = [];
            foreach ($responseAnswers as $answer) {
                $answersByQuestion[(int) $answer['question_id']] = $answer['option_label_snapshot'];
            }
            $flexibleFieldLabels = [];
            foreach ($this->ipak->get_response_fields((int) $row['kode'], $responseSource) as $responseField) {
                $flexibleFieldLabels[] = $responseField['field_label_snapshot'] . ': ' . $responseField['field_value'];
            }
            $line = [
                $row['resi'],
                isset($row['jenis_survei']) ? $row['jenis_survei'] : 'SKM',
                isset($row['kode_survei_unik']) ? $row['kode_survei_unik'] : '',
                isset($row['kode_pengisian']) ? $row['kode_pengisian'] : '',
                isset($row['versi_survei']) ? $row['versi_survei'] : '',
                $row['tgl_buat'] ?: $row['tgl_pengisian'],
                $row['nama_responden'],
                $row['mobile'],
                isset($meta['email']) ? $meta['email'] : $row['responden'],
                $row['nib'],
                (int) $row['usia'] > 0 ? (int) $row['usia'] : '',
                (int) $row['gender'] === 1 ? 'Laki-laki' : ((int) $row['gender'] === 2 ? 'Perempuan' : '-'),
                isset($education[(int) $row['pendidikan_id']]) ? $education[(int) $row['pendidikan_id']] : '-',
                ((int) $row['pekerjaan_id'] === 5 && !empty($meta['job_other'])) ? $meta['job_other'] : (isset($jobs[(int) $row['pekerjaan_id']]) ? $jobs[(int) $row['pekerjaan_id']] : '-'),
                !empty($meta['sector_name'])
                    ? $meta['sector_name']
                    : (!empty($meta['service_other'])
                        ? $meta['service_other']
                        : (isset($services[(int) $row['sektor']]) ? $services[(int) $row['sektor']] : '-')),
                implode(' | ', $flexibleFieldLabels),
            ];
            foreach ($questions as $questionId => $question) {
                $line[] = isset($answersByQuestion[$questionId]) ? $answersByQuestion[$questionId] : '';
            }
            $surveyScores = [];
            foreach ($this->ipak->get_response_survey_results((int) $row['kode'], $responseSource) as $surveyResult) {
                $surveyScores[(int) $surveyResult['survey_id']] = $surveyResult['score'];
            }
            foreach ($surveys as $surveyId => $survey) {
                $line[] = isset($surveyScores[$surveyId])
                    ? number_format((float) $surveyScores[$surveyId], 2, '.', '')
                    : '';
            }
            $line[] = number_format((float) $row['rata'], 2, '.', '');
            $line[] = $row['saran'];
            fputcsv($output, $line);
        }
        fclose($output);
        exit;
    }

    public function questions()
    {
        $this->require_login();

        if (strtoupper($this->input->method()) === 'POST') {
            $this->require_superadmin();
            $action = trim((string) $this->input->post('action', true));
            $surveyFilter = max(0, (int) $this->input->post('survey_filter', true));
            $page = max(1, (int) $this->input->post('page', true));
            $returnParams = [];
            if ($surveyFilter > 0) {
                $returnParams['survey_id'] = $surveyFilter;
            } elseif ($page > 1) {
                $returnParams['page'] = $page;
            }
            $returnUrl = 'admin/questions' . ($returnParams ? '?' . http_build_query($returnParams) : '');

            if ($action === 'toggle') {
                $questionId = (int) $this->input->post('question_id', true);
                $isActive = (int) $this->input->post('is_active', true) === 1;
                $this->ipak->set_question_active($questionId, $isActive);
                $this->session->set_flashdata('question_success', 'Status pertanyaan berhasil diperbarui.');
                return redirect($returnUrl);
            }

            $questionId = (int) $this->input->post('question_id', true);
            if ($questionId < 1) {
                $this->session->set_flashdata('question_create_error', 'Pertanyaan baru harus dibuat melalui halaman khusus.');
                return redirect('admin/questions/create');
            }
            list($question, $options, $errors) = $this->parse_question_submission($questionId);

            if ($errors) {
                $this->session->set_flashdata('question_error', implode(' ', $errors));
                return redirect($returnUrl);
            }

            $savedId = $this->ipak->save_question($question, $options);
            if (!$savedId) {
                $this->session->set_flashdata('question_error', 'Pertanyaan belum berhasil disimpan.');
                return redirect($returnUrl);
            }
            $this->session->set_flashdata('question_success', 'Pertanyaan dan opsi jawaban berhasil disimpan.');
            return redirect($returnUrl);
        }

        $surveys = $this->ipak->get_surveys(false);
        $surveyId = max(0, (int) $this->input->get('survey_id', true));
        if ($surveyId > 0 && !isset($surveys[$surveyId])) {
            $surveyId = 0;
        }
        $allQuestions = $surveyId > 0
            ? $this->ipak->get_questions_for_survey($surveyId, false)
            : $this->ipak->get_questions(false);
        $totalQuestions = count($allQuestions);
        $perPage = 6;
        $totalPages = $surveyId > 0 ? 1 : max(1, (int) ceil($totalQuestions / $perPage));
        $page = $surveyId > 0 ? 1 : max(1, (int) $this->input->get('page', true));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $questions = $surveyId > 0
            ? $allQuestions
            : array_slice($allQuestions, ($page - 1) * $perPage, $perPage, true);

        $this->render('admin/questions', [
            'page_title' => 'Pertanyaan & Jawaban',
            'question_definitions' => $questions,
            'surveys' => $surveys,
            'survey_id' => $surveyId,
            'selected_survey' => $surveyId > 0 ? $surveys[$surveyId] : [],
            'question_assignments' => $this->ipak->get_question_assignments(),
            'total_questions' => $totalQuestions,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'question_success' => $this->session->flashdata('question_success'),
            'question_error' => $this->session->flashdata('question_error'),
            'is_superadmin' => $this->is_superadmin(),
        ]);
    }

    public function create_question()
    {
        $this->require_login();
        $this->require_superadmin();

        if (strtoupper($this->input->method()) === 'POST') {
            list($question, $options, $errors) = $this->parse_question_submission(0);
            if ($errors) {
                $this->session->set_flashdata('question_create_error', implode(' ', $errors));
                $this->session->set_flashdata('question_create_old', $this->input->post(NULL, true));
                return redirect('admin/questions/create');
            }
            if (!$this->ipak->save_question($question, $options)) {
                $this->session->set_flashdata('question_create_error', 'Pertanyaan belum berhasil disimpan.');
                $this->session->set_flashdata('question_create_old', $this->input->post(NULL, true));
                return redirect('admin/questions/create');
            }
            $this->session->set_flashdata('question_success', 'Pertanyaan dan pilihan jawaban baru berhasil ditambahkan.');
            return redirect('admin/questions');
        }

        $this->render('admin/question_create', [
            'page_title' => 'Tambah Pertanyaan',
            'question_error' => $this->session->flashdata('question_create_error'),
            'old' => $this->session->flashdata('question_create_old') ?: [],
            'next_sort_order' => count($this->ipak->get_questions(false)) + 1,
        ]);
    }

    public function surveys()
    {
        $this->require_login();

        if (strtoupper($this->input->method()) === 'POST') {
            $this->require_superadmin();
            $entity = trim((string) $this->input->post('entity', true));
            $errors = [];

            if ($entity === 'survey') {
                $surveyId = (int) $this->input->post('survey_id', true);
                if ($surveyId < 1) {
                    $this->session->set_flashdata(
                        'wizard_error',
                        'Pembuatan survei baru hanya tersedia melalui wizard lengkap tiga langkah.'
                    );
                    return redirect('admin/forms/create');
                }
                $survey = [
                    'id' => $surveyId,
                    'survey_code' => strtoupper(trim((string) $this->input->post('survey_code', true))),
                    'survey_name' => trim((string) $this->input->post('survey_name', true)),
                    'index_label' => trim((string) $this->input->post('index_label', true)),
                    'description' => trim((string) $this->input->post('description', true)),
                    'color' => trim((string) $this->input->post('color', true)),
                    'is_active' => (int) $this->input->post('is_active', true) === 1,
                    'is_mandatory' => (int) $this->input->post('is_mandatory', true) === 1,
                ];
                $questionIds = $this->input->post('question_ids', true);
                $questionIds = is_array($questionIds) ? $questionIds : [];
                $questionIds = array_values(array_filter(array_map('intval', $questionIds)));
                if ($this->ipak->is_legacy_skm_survey($surveyId) && count($questionIds) > 10) {
                    $errors[] = 'SKM lama maksimal menggunakan 10 pertanyaan. Buat versi survei baru jika struktur SKM berubah.';
                }
                if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $survey['survey_code'])) {
                    $errors[] = 'Kode survei harus terdiri dari huruf, angka, garis bawah, atau tanda hubung.';
                }
                if ($survey['survey_name'] === '' || $survey['index_label'] === '') {
                    $errors[] = 'Nama survei dan label indeks wajib diisi.';
                }
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $survey['color'])) {
                    $errors[] = 'Warna survei tidak valid.';
                }
                if (!$questionIds) {
                    $errors[] = 'Pilih minimal satu pertanyaan.';
                }
                if ($this->ipak->survey_code_exists($survey['survey_code'], $surveyId)) {
                    $errors[] = 'Kode survei sudah digunakan.';
                }
                if (!$errors) {
                    $savedSurveyId = $this->ipak->save_survey($survey, $questionIds);
                    if (!$savedSurveyId) {
                        $errors[] = 'Survei belum berhasil disimpan. Pastikan migration 04_ALLOW_SHARED_QUESTIONS.sql sudah diterapkan pada database server.';
                    } elseif (!$this->ipak->ensure_standalone_form($savedSurveyId)) {
                        $errors[] = 'Survei tersimpan, tetapi shortcut form mandiri belum berhasil dibuat.';
                    }
                }
                $message = $errors ? implode(' ', $errors) : 'Survei dan susunan pertanyaan berhasil disimpan.';
            } elseif ($entity === 'form') {
                $formId = (int) $this->input->post('form_id', true);
                if ($formId < 1) {
                    $this->session->set_flashdata(
                        'wizard_error',
                        'Pembuatan form baru hanya tersedia melalui wizard lengkap tiga langkah.'
                    );
                    return redirect('admin/forms/create');
                }
                $form = [
                    'id' => $formId,
                    'form_code' => strtoupper(trim((string) $this->input->post('form_code', true))),
                    'form_name' => trim((string) $this->input->post('form_name', true)),
                    'description' => trim((string) $this->input->post('description', true)),
                    'is_default' => (int) $this->input->post('is_default', true) === 1,
                    'is_active' => (int) $this->input->post('is_active', true) === 1,
                ];
                $surveyIds = $this->input->post('survey_ids', true);
                $surveyIds = is_array($surveyIds) ? $surveyIds : [];
                $surveyIds = array_values(array_filter(array_map('intval', $surveyIds)));
                if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $form['form_code'])) {
                    $errors[] = 'Kode form tidak valid.';
                }
                if ($form['form_name'] === '') {
                    $errors[] = 'Nama form wajib diisi.';
                }
                if (!$surveyIds) {
                    $errors[] = 'Pilih minimal satu survei untuk form.';
                }
                if ($this->ipak->form_code_exists($form['form_code'], $formId)) {
                    $errors[] = 'Kode form sudah digunakan.';
                }
                if (!$errors && !$this->ipak->save_form($form, $surveyIds)) {
                    $errors[] = 'Form belum berhasil disimpan.';
                }
                $message = $errors ? implode(' ', $errors) : 'Form dan survei di dalamnya berhasil disimpan.';
            } else {
                $errors[] = 'Jenis data tidak dikenali.';
                $message = implode(' ', $errors);
            }

            $this->session->set_flashdata($errors ? 'survey_error' : 'survey_success', $message);
            return redirect('admin/surveys');
        }

        $surveys = $this->ipak->get_surveys(false);
        foreach ($surveys as $surveyId => $survey) {
            $surveys[$surveyId]['question_ids'] = $this->ipak->get_survey_question_ids($surveyId);
            $this->ipak->ensure_standalone_form((int) $surveyId);
        }
        $primaryForms = $this->ipak->get_standalone_forms_by_survey(false);
        $this->render('admin/surveys', [
            'page_title' => 'Survei',
            'surveys' => $surveys,
            'primary_forms' => $primaryForms,
            'questions' => $this->ipak->get_questions(false),
            'question_assignments' => $this->ipak->get_question_assignments(),
            'survey_success' => $this->session->flashdata('survey_success'),
            'survey_error' => $this->session->flashdata('survey_error'),
            'is_superadmin' => $this->is_superadmin(),
        ]);
    }

    public function delete_survey($id = 0)
    {
        $this->require_login();
        $this->require_superadmin();
        if (strtoupper($this->input->method()) !== 'POST') {
            show_error('Metode tidak diizinkan.', 405, 'Akses ditolak');
        }
        $surveyId = max(0, (int) $id);
        $result = $this->ipak->delete_survey($surveyId);
        $this->session->set_flashdata(
            $result['ok'] ? 'survey_success' : 'survey_error',
            $result['message']
        );
        return redirect('admin/surveys');
    }

    public function users()
    {
        $this->require_login();
        $this->require_superadmin();

        $this->render('admin/users', [
            'page_title' => 'Kelola Akun',
            'users' => $this->ipak->get_admin_users(),
            'roles' => $this->ipak->get_admin_roles(),
            'user_success' => $this->session->flashdata('user_success'),
            'user_error' => $this->session->flashdata('user_error'),
        ]);
    }

    public function create_user()
    {
        $this->require_login();
        $this->require_superadmin();

        if (strtoupper($this->input->method()) === 'POST') {
            $username = strtoupper(trim((string) $this->input->post('username', true)));
            $nama = trim((string) $this->input->post('nama', true));
            $password = (string) $this->input->post('password', false);
            $roleName = trim((string) $this->input->post('role_name', true));

            $errors = [];
            if (!preg_match('/^[A-Z0-9_]{3,100}$/', $username)) {
                $errors[] = 'Username harus huruf besar, angka, atau garis bawah (minimal 3 karakter).';
            }
            if ($this->ipak->admin_username_exists(strtolower($username))) {
                $errors[] = 'Username sudah digunakan.';
            }
            if ($nama === '') {
                $errors[] = 'Nama lengkap wajib diisi.';
            }
            if ($password === '' || strlen($password) < 6) {
                $errors[] = 'Password minimal 6 karakter.';
            }
            if (!in_array($roleName, ['superadmin', 'admin'], true)) {
                $errors[] = 'Role tidak valid.';
            }

            if (!$errors) {
                $userId = $this->ipak->save_admin([
                    'username' => strtolower($username),
                    'nama' => $nama,
                    'password' => $password,
                    'role_name' => $roleName,
                    'is_active' => 1,
                ]);
                if (!$userId) {
                    $errors[] = 'Akun tidak berhasil dibuat.';
                } else {
                    $this->session->set_flashdata('user_success', 'Akun pengguna berhasil dibuat.');
                    return redirect('admin/users');
                }
            }
            $this->session->set_flashdata('user_error', implode(' ', $errors));
            $this->session->set_flashdata('user_create_old', $this->input->post(NULL, true));
            return redirect('admin/users/create');
        }

        $this->render('admin/user_form', [
            'page_title' => 'Buat Akun Pengguna',
            'roles' => $this->ipak->get_admin_roles(),
            'mode' => 'create',
        ]);
    }

    public function edit_user($id = 0)
    {
        $this->require_login();
        $this->require_superadmin();

        $userId = (int) $id;
        $user = $this->ipak->get_admin_by_id($userId);
        if (!$user) {
            show_404();
        }

        if (strtoupper($this->input->method()) === 'POST') {
            $nama = trim((string) $this->input->post('nama', true));
            $password = (string) $this->input->post('password', false);
            $roleName = trim((string) $this->input->post('role_name', true));
            $isActive = (int) $this->input->post('is_active', true) === 1;

            $errors = [];
            if ($nama === '') {
                $errors[] = 'Nama lengkap wajib diisi.';
            }
            if (!in_array($roleName, ['superadmin', 'admin'], true)) {
                $errors[] = 'Role tidak valid.';
            }

            if (!$errors) {
                $this->ipak->save_admin([
                    'id' => $userId,
                    'nama' => $nama,
                    'password' => $password,
                    'role_name' => $roleName,
                    'is_active' => $isActive ? 1 : 0,
                ]);
                $this->session->set_flashdata('user_success', 'Akun pengguna berhasil diperbarui.');
                return redirect('admin/users');
            }
            $this->session->set_flashdata('user_error', implode(' ', $errors));
            return redirect('admin/users/edit/' . $userId);
        }

        $this->render('admin/user_form', [
            'page_title' => 'Edit Akun Pengguna',
            'roles' => $this->ipak->get_admin_roles(),
            'user' => $user,
            'mode' => 'edit',
        ]);
    }

    public function change_password()
    {
        $this->require_login();

        if (strtoupper($this->input->method()) !== 'POST') {
            show_error('Metode tidak diizinkan.', 405, 'Akses ditolak');
        }

        $currentPassword = (string) $this->input->post('current_password', false);
        $newPassword = (string) $this->input->post('new_password', false);
        $confirmPassword = (string) $this->input->post('confirm_password', false);

        $adminId = (int) $this->session->userdata('ipak_admin_id');
        $user = $this->ipak->get_admin_by_id($adminId);

        $errors = [];
        if (!$this->verify_password($currentPassword, $user['password'])) {
            $errors[] = 'Password saat ini tidak sesuai.';
        }
        if ($newPassword === '' || strlen($newPassword) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }

        if (!$errors) {
            $this->ipak->change_admin_password($adminId, $newPassword);
            $this->session->set_flashdata('user_success', 'Password berhasil diubah.');
        } else {
            $this->session->set_flashdata('user_error', implode(' ', $errors));
        }
        return redirect('admin/profile');
    }

    public function profile()
    {
        $this->require_login();

        $adminId = (int) $this->session->userdata('ipak_admin_id');
        $user = $this->ipak->get_admin_by_id($adminId);
        if (!$user) {
            $this->session->unset_userdata([
                'ipak_admin_id', 'ipak_admin_username', 'ipak_admin_name',
                'ipak_admin_role', 'ipak_admin_logged_in',
            ]);
            redirect('admin/login');
            exit;
        }

        $this->render('admin/profile', [
            'page_title' => 'Profil &amp; Akun',
            'user' => $user,
            'roles' => $this->ipak->get_admin_roles(),
            'user_success' => $this->session->flashdata('user_success'),
            'user_error' => $this->session->flashdata('user_error'),
        ]);
    }

    public function delete_user($id = 0)
    {
        $this->require_login();
        $this->require_superadmin();
        if (strtoupper($this->input->method()) !== 'POST') {
            show_error('Metode tidak diizinkan.', 405, 'Akses ditolak');
        }

        $userId = (int) $id;
        $currentUserId = (int) $this->session->userdata('ipak_admin_id');
        if ($userId === $currentUserId) {
            $this->session->set_flashdata('user_error', 'Anda tidak dapat menghapus akun sendiri.');
            return redirect('admin/users');
        }

        $user = $this->ipak->get_admin_by_id($userId);
        if (!$user) {
            $this->session->set_flashdata('user_error', 'Akun tidak ditemukan.');
            return redirect('admin/users');
        }

        $this->ipak->delete_admin($userId);
        $this->session->set_flashdata('user_success', 'Akun pengguna berhasil dihapus.');
        return redirect('admin/users');
    }

    public function forms()
    {
        $this->require_login();
        $fieldDefinitions = $this->ipak->respondent_field_definitions();
        $surveys = $this->ipak->get_surveys(false);
        foreach ($surveys as $surveyId => $survey) {
            $this->ipak->ensure_standalone_form((int) $surveyId);
        }
        $standaloneForms = $this->ipak->get_standalone_forms_by_survey(false);
        $primarySurveyByFormId = [];
        foreach ($standaloneForms as $surveyId => $standaloneForm) {
            $primarySurveyByFormId[(int) $standaloneForm['form_id']] = (int) $surveyId;
        }

        if (strtoupper($this->input->method()) === 'POST') {
            $this->require_superadmin();
            $errors = [];
            $formId = (int) $this->input->post('form_id', true);
            $formKind = trim((string) $this->input->post('form_kind', true));
            $isNewCombinedPackage = $formId < 1 && $formKind === 'combined';
            if ($formId < 1 && !$isNewCombinedPackage) {
                $this->session->set_flashdata(
                    'wizard_error',
                    'Survei dan form utamanya harus dibuat melalui wizard lengkap tiga langkah.'
                );
                return redirect('admin/forms/create');
            }
            $primarySurveyId = isset($primarySurveyByFormId[$formId])
                ? (int) $primarySurveyByFormId[$formId]
                : 0;
            $existingSurveyIds = $formId > 0 ? $this->ipak->get_form_survey_ids($formId) : [];
            $isCombinedPackage = $isNewCombinedPackage
                || ($primarySurveyId < 1 && count($existingSurveyIds) > 1);
            $returnType = $isCombinedPackage ? 'combined' : 'primary';
            $form = [
                'id' => $formId,
                'form_code' => strtoupper(trim((string) $this->input->post('form_code', true))),
                'form_name' => trim((string) $this->input->post('form_name', true)),
                'description' => trim((string) $this->input->post('description', true)),
                'is_default' => (int) $this->input->post('is_default', true) === 1,
                'is_active' => (int) $this->input->post('is_active', true) === 1,
                'is_public_listed' => (int) $this->input->post('is_public_listed', true) === 1,
            ];
            $surveyIds = $this->input->post('survey_ids', true);
            $surveyIds = is_array($surveyIds)
                ? array_values(array_filter(array_unique(array_map('intval', $surveyIds))))
                : [];
            $rawFieldSettings = $this->input->post('respondent_fields', true);
            $rawFieldSettings = is_array($rawFieldSettings) ? $rawFieldSettings : [];
            $fieldSettings = [];
            foreach ($fieldDefinitions as $fieldKey => $definition) {
                $setting = isset($rawFieldSettings[$fieldKey]) && is_array($rawFieldSettings[$fieldKey])
                    ? $rawFieldSettings[$fieldKey]
                    : [];
                $mode = isset($setting['mode']) ? trim((string) $setting['mode']) : 'hidden';
                if (!in_array($mode, ['hidden', 'optional', 'required'], true)) {
                    $mode = 'hidden';
                }
                $fieldSettings[$fieldKey] = [
                    'mode' => $mode,
                    'label' => isset($setting['label']) ? trim((string) $setting['label']) : $definition['label'],
                    'help_text' => isset($setting['help_text']) ? trim((string) $setting['help_text']) : $definition['help_text'],
                ];
            }
            $existingFields = $formId > 0 ? $this->ipak->get_form_fields($formId) : [];
            foreach ($rawFieldSettings as $fieldKey => $setting) {
                if (isset($fieldDefinitions[$fieldKey]) || !isset($existingFields[$fieldKey]) || !is_array($setting)) {
                    continue;
                }
                if (!preg_match('/^custom_[a-z0-9_]{1,23}$/', (string) $fieldKey)) {
                    continue;
                }
                $mode = isset($setting['mode']) ? trim((string) $setting['mode']) : $existingFields[$fieldKey]['field_mode'];
                if (!in_array($mode, ['hidden', 'optional', 'required'], true)) {
                    $mode = $existingFields[$fieldKey]['field_mode'];
                }
                $fieldSettings[$fieldKey] = [
                    'mode' => $mode,
                    'label' => isset($setting['label']) ? trim((string) $setting['label']) : $existingFields[$fieldKey]['field_label'],
                    'help_text' => isset($setting['help_text']) ? trim((string) $setting['help_text']) : $existingFields[$fieldKey]['help_text'],
                    'group' => $existingFields[$fieldKey]['field_group'],
                    'type' => $existingFields[$fieldKey]['field_type'],
                    'options' => $existingFields[$fieldKey]['options'],
                    'sort_order' => (int) $existingFields[$fieldKey]['sort_order'],
                ];
            }
            if ($isNewCombinedPackage && !$rawFieldSettings) {
                $fieldSettings = [];
            }

            if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $form['form_code'])) {
                $errors[] = 'Kode form harus berisi huruf, angka, garis bawah, atau tanda hubung tanpa spasi.';
            }
            if ($form['form_name'] === '') {
                $errors[] = 'Nama form wajib diisi.';
            }
            if ($primarySurveyId > 0) {
                if ($surveyIds !== [$primarySurveyId]) {
                    $errors[] = 'Form utama harus tetap terhubung hanya dengan satu survei. Gunakan Paket Survei Gabungan untuk menggabungkan survei.';
                }
                $surveyIds = [$primarySurveyId];
            } elseif ($isCombinedPackage) {
                if (count($surveyIds) < 2) {
                    $errors[] = 'Paket survei gabungan harus berisi minimal dua survei.';
                }
            } else {
                $errors[] = 'Form ini tidak mempunyai hubungan survei yang valid.';
            }
            if ($this->ipak->form_code_exists($form['form_code'], $formId)) {
                $errors[] = 'Kode form sudah digunakan.';
            }
            if (!$errors && !$this->ipak->save_form($form, $surveyIds, $fieldSettings)) {
                $errors[] = 'Form belum berhasil disimpan.';
            }

            $this->session->set_flashdata(
                $errors ? 'form_error' : 'form_success',
                $errors
                    ? implode(' ', $errors)
                    : ($isCombinedPackage
                        ? 'Paket survei gabungan dan shortcut publik berhasil disimpan.'
                        : 'Form utama, data responden, dan shortcut publik berhasil disimpan.')
            );
            return redirect('admin/forms?type=' . $returnType);
        }

        $forms = $this->ipak->get_forms(false);
        $formsById = [];
        $combinedForms = [];
        $combinedFormShortcuts = [];
        foreach ($forms as $index => $form) {
            $definition = $this->ipak->get_form_definition($form['form_code'], false);
            $forms[$index]['survey_ids'] = $this->ipak->get_form_survey_ids((int) $form['id']);
            $forms[$index]['respondent_fields'] = !empty($definition['respondent_fields'])
                ? $definition['respondent_fields']
                : $this->ipak->get_form_fields((int) $form['id']);
            $forms[$index]['requires_resi'] = !empty($definition['requires_resi']);
            $forms[$index]['shortcut_surveys'] = [];
            $forms[$index]['shortcut_color'] = '#3049d8';
            if (!empty($definition['surveys'])) {
                foreach ($definition['surveys'] as $survey) {
                    $forms[$index]['shortcut_surveys'][] = [
                        'code' => $survey['survey_code'],
                        'name' => $survey['survey_name'],
                    ];
                    if ($forms[$index]['shortcut_color'] === '#3049d8' && !empty($survey['color'])) {
                        $forms[$index]['shortcut_color'] = $survey['color'];
                    }
                }
            }
            if (isset($primarySurveyByFormId[(int) $form['id']])) {
                $forms[$index]['form_kind'] = 'primary';
                $forms[$index]['primary_survey_id'] = $primarySurveyByFormId[(int) $form['id']];
            } elseif (count($forms[$index]['survey_ids']) > 1) {
                $forms[$index]['form_kind'] = 'combined';
                $forms[$index]['primary_survey_id'] = 0;
                $combinedForms[] = $forms[$index];
                if ((int) $form['is_active'] === 1) {
                    $combinedFormShortcuts[] = $forms[$index];
                }
            } else {
                $forms[$index]['form_kind'] = 'unclassified';
                $forms[$index]['primary_survey_id'] = 0;
            }
            $formsById[(int) $form['id']] = $forms[$index];
        }

        $primaryForms = [];
        $primaryFormShortcuts = [];
        foreach ($surveys as $surveyId => $survey) {
            if (empty($standaloneForms[$surveyId])) {
                continue;
            }
            $formId = (int) $standaloneForms[$surveyId]['form_id'];
            if (!isset($formsById[$formId])) {
                continue;
            }
            $primaryForms[] = $formsById[$formId];
            if ((int) $formsById[$formId]['is_active'] === 1) {
                $primaryFormShortcuts[] = $formsById[$formId];
            }
        }

        $formType = strtolower(trim((string) $this->input->get('type', true)));
        if (!in_array($formType, ['primary', 'combined', 'shortcuts'], true)) {
            $formType = 'primary';
        }

        $this->render('admin/forms', [
            'page_title' => $formType === 'combined'
                ? 'Form Gabungan'
                : ($formType === 'shortcuts' ? 'Shortcut Publik' : 'Form Utama'),
            'form_type' => $formType,
            'forms' => $forms,
            'surveys' => $surveys,
            'primary_forms' => $primaryForms,
            'primary_form_shortcuts' => $primaryFormShortcuts,
            'combined_forms' => $combinedForms,
            'combined_form_shortcuts' => $combinedFormShortcuts,
            'field_definitions' => $fieldDefinitions,
            'form_success' => $this->session->flashdata('form_success'),
            'form_error' => $this->session->flashdata('form_error'),
            'is_superadmin' => $this->is_superadmin(),
        ]);
    }

    public function create_form()
    {
        $this->require_login();
        $this->require_superadmin();
        $kbliSchema = $this->ipak->sync_kbli_schema();
        if (!empty($kbliSchema['errors'])) {
            $this->session->set_flashdata('wizard_error', 'Struktur KBLI belum siap: ' . implode(' ', $kbliSchema['errors']));
            return redirect('admin/forms');
        }

        if (strtoupper($this->input->method()) === 'POST') {
            $parsed = $this->parse_wizard_submission(0, 0);
            if ($parsed['errors']) {
                $this->session->set_flashdata('wizard_error', implode(' ', $parsed['errors']));
                $this->session->set_flashdata('wizard_old', $parsed['posted']);
                return redirect('admin/forms/create');
            }
            $result = $this->ipak->create_wizard_form(
                array_merge($parsed['survey'], ['id' => 0, 'is_active' => true]),
                array_merge($parsed['form'], [
                    'id' => 0,
                    'is_default' => false,
                    'is_active' => true,
                ]),
                $parsed['question_ids'],
                $parsed['new_questions'],
                $parsed['field_settings']
            );
            if (!$result) {
                $this->session->set_flashdata(
                    'wizard_error',
                    'Form belum berhasil dibuat. Tidak ada data yang disimpan. Pastikan migration 04_ALLOW_SHARED_QUESTIONS.sql sudah diterapkan pada database server.'
                );
                $this->session->set_flashdata('wizard_old', $parsed['posted']);
                return redirect('admin/forms/create');
            }
            $this->session->set_flashdata('form_success', 'Form survei berhasil dibuat melalui tiga langkah dan siap diuji.');
            return redirect('admin/forms');
        }

        $fieldDefinitions = $this->ipak->respondent_field_definitions();
        $questions = $this->ipak->get_questions(false);
        $this->render('admin/form_wizard', [
            'page_title' => 'Buat Form Survei',
            'field_definitions' => $fieldDefinitions,
            'kbli_display_columns' => $this->ipak->kbli_display_columns(),
            'choice_options' => [
                'gender' => [1 => 'Laki-laki', 2 => 'Perempuan'],
                'education' => $this->config->item('ipak_education'),
                'job' => $this->config->item('ipak_jobs'),
                'service' => $this->ipak->sector_options(),
            ],
            'available_questions' => $questions,
            'wizard_error' => $this->session->flashdata('wizard_error'),
            'old' => $this->session->flashdata('wizard_old') ?: [],
            'mode' => 'create',
            'form_id' => 0,
        ]);
    }

    public function edit_form($id = 0)
    {
        $this->require_login();
        $this->require_superadmin();
        $kbliSchema = $this->ipak->sync_kbli_schema();
        if (!empty($kbliSchema['errors'])) {
            $this->session->set_flashdata('wizard_error', 'Struktur KBLI belum siap: ' . implode(' ', $kbliSchema['errors']));
            return redirect('admin/forms');
        }

        $formId = max(0, (int) $id);
        $form = $this->ipak->get_form_by_id($formId);
        if (!$form) {
            show_404();
        }

        $surveyIds = $form['survey_ids'];
        if (count($surveyIds) !== 1) {
            $this->session->set_flashdata(
                'wizard_error',
                'Form ini tidak dapat diedit melalui panduan tiga langkah karena bukan form utama tunggal. Gunakan editor di halaman daftar form.'
            );
            return redirect('admin/forms');
        }
        $surveyId = (int) $surveyIds[0];
        $survey = $this->ipak->get_survey_by_id($surveyId);
        if (!$survey) {
            show_404();
        }
        if ($this->ipak->is_legacy_skm_survey($surveyId)) {
            $this->session->set_flashdata('wizard_error', 'Survei sistem (SKM lama) tidak dapat diedit melalui panduan ini.');
            return redirect('admin/forms');
        }

        if (strtoupper($this->input->method()) === 'POST') {
            $parsed = $this->parse_wizard_submission($formId, $surveyId);
            if ($parsed['errors']) {
                $this->session->set_flashdata('wizard_error', implode(' ', $parsed['errors']));
                $this->session->set_flashdata('wizard_old', $parsed['posted']);
                return redirect('admin/forms/edit/' . $formId);
            }
            $result = $this->ipak->create_wizard_form(
                array_merge($parsed['survey'], [
                    'id' => $surveyId,
                    'is_active' => (int) $survey['is_active'] === 1,
                ]),
                array_merge($parsed['form'], [
                    'id' => $formId,
                    'is_default' => (int) $form['is_default'] === 1,
                    'is_active' => (int) $form['is_active'] === 1,
                ]),
                $parsed['question_ids'],
                $parsed['new_questions'],
                $parsed['field_settings']
            );
            if (!$result) {
                $this->session->set_flashdata(
                    'wizard_error',
                    'Form belum berhasil diperbarui. Tidak ada data yang tersimpan. Pastikan migration 04_ALLOW_SHARED_QUESTIONS.sql sudah diterapkan pada database server.'
                );
                $this->session->set_flashdata('wizard_old', $parsed['posted']);
                return redirect('admin/forms/edit/' . $formId);
            }
            $this->session->set_flashdata('form_success', 'Form survei berhasil diperbarui.');
            return redirect('admin/forms');
        }

        $fieldDefinitions = $this->ipak->respondent_field_definitions();
        $respondentFields = $this->ipak->get_effective_form_fields($formId, $surveyIds);
        $questionIds = $this->ipak->get_survey_question_ids($surveyId);

        $this->render('admin/form_wizard', [
            'page_title' => 'Edit Form Survei',
            'field_definitions' => $fieldDefinitions,
            'kbli_display_columns' => $this->ipak->kbli_display_columns(),
            'choice_options' => [
                'gender' => [1 => 'Laki-laku', 2 => 'Perempuan'],
                'education' => $this->config->item('ipak_education'),
                'job' => $this->config->item('ipak_jobs'),
                'service' => $this->ipak->sector_options(),
            ],
            'available_questions' => $this->ipak->get_questions(false),
            'wizard_error' => $this->session->flashdata('wizard_error'),
            'old' => $this->session->flashdata('wizard_old') ?: $this->build_wizard_old_data($form, $survey, $respondentFields, $questionIds),
            'mode' => 'edit',
            'form_id' => $formId,
        ]);
    }

    private function parse_wizard_submission($excludeFormId = 0, $excludeSurveyId = 0)
    {
        $posted = $this->input->post(NULL, true);
        $errors = [];
        $formCode = strtoupper(trim((string) $this->input->post('form_code', true)));
        $surveyCode = strtoupper(trim((string) $this->input->post('survey_code', true)));
        $formName = trim((string) $this->input->post('form_name', true));
        $surveyName = trim((string) $this->input->post('survey_name', true));
        $indexLabel = trim((string) $this->input->post('index_label', true));
        $description = trim((string) $this->input->post('description', true));
        $color = trim((string) $this->input->post('color', true));
        $isPublicListed = (int) $this->input->post('is_public_listed', true) === 1;

        if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $formCode)) {
            $errors[] = 'Kode form harus berisi huruf, angka, garis bawah, atau tanda hubung tanpa spasi.';
        }
        if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $surveyCode)) {
            $errors[] = 'Kode survei harus berisi huruf, angka, garis bawah, atau tanda hubung tanpa spasi.';
        }
        if ($formName === '' || $surveyName === '' || $indexLabel === '') {
            $errors[] = 'Nama form, nama survei, dan label nilai wajib diisi.';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $errors[] = 'Warna grafik tidak valid.';
        }
        if ($this->ipak->form_code_exists($formCode, $excludeFormId)) {
            $errors[] = 'Kode form sudah digunakan.';
        }
        if ($this->ipak->survey_code_exists($surveyCode, $excludeSurveyId)) {
            $errors[] = 'Kode survei sudah digunakan.';
        }

        $fieldDefinitions = $this->ipak->respondent_field_definitions();
        $fieldSettings = [];
        foreach ($fieldDefinitions as $fieldKey => $definition) {
            $fieldSettings[$fieldKey] = [
                'mode' => 'hidden',
                'label' => $definition['label'],
                'help_text' => $definition['help_text'],
                'group' => $definition['field_group'],
                'type' => $definition['field_type'],
                'sort_order' => $definition['sort_order'],
            ];
        }

        $initialFields = $this->input->post('initial_fields', true);
        $initialFields = is_array($initialFields) ? $initialFields : [];
        $initialCount = 0;
        foreach (['email', 'phone', 'identity_number'] as $fieldKey) {
            if (in_array($fieldKey, $initialFields, true)) {
                $fieldSettings[$fieldKey]['mode'] = 'required';
                $fieldSettings[$fieldKey]['group'] = 'access';
                $initialCount++;
            }
        }
        $initialCustom = $this->normalize_custom_fields(
            $this->input->post('initial_custom', true),
            'access',
            true,
            110
        );
        foreach ($initialCustom as $fieldKey => $setting) {
            if ($setting['type'] === 'select' && count($setting['options']) < 2) {
                $errors[] = 'Input awal "' . $setting['label'] . '" memerlukan minimal dua pilihan dengan value angka.';
            }
            $fieldSettings[$fieldKey] = $setting;
            $initialCount++;
        }
        if ($initialCount < 1) {
            $errors[] = 'Langkah 1 harus mempunyai minimal satu input awal wajib.';
        }

        $identityInput = $this->input->post('identity_fields', true);
        $identityInput = is_array($identityInput) ? $identityInput : [];
        foreach (['name', 'address', 'gender', 'age', 'education', 'job', 'service', 'kbli'] as $fieldKey) {
            $mode = isset($identityInput[$fieldKey]) ? trim((string) $identityInput[$fieldKey]) : 'hidden';
            if (!in_array($mode, ['hidden', 'optional', 'required'], true)) {
                $mode = 'hidden';
            }
            $fieldSettings[$fieldKey]['mode'] = $mode;
            $fieldSettings[$fieldKey]['group'] = 'identity';
        }
        $kbliInput = $this->input->post('identity_kbli', true);
        if (!is_array($kbliInput)) {
            $kbliInput = [];
        }
        if ($fieldSettings['kbli']['mode'] !== 'hidden') {
            $codeField = isset($kbliInput['code_field']) ? trim((string) $kbliInput['code_field']) : 'kode_gabungan';
            $displayColumns = isset($kbliInput['display_columns']) && is_array($kbliInput['display_columns'])
                ? array_values(array_unique($kbliInput['display_columns']))
                : [];
            $allowedColumns = array_keys($this->ipak->kbli_display_columns());
            $displayColumns = array_values(array_intersect($allowedColumns, $displayColumns));
            if (!in_array($codeField, ['kode', 'kode_gabungan'], true)) {
                $errors[] = 'Pilih kolom kode KBLI yang valid.';
            }
            if (!$displayColumns) {
                $errors[] = 'Pilih minimal satu kolom informasi KBLI yang ditampilkan.';
            }
            $fieldSettings['kbli']['options'] = [
                'source' => 'ipak_kbli',
                'code_field' => $codeField,
                'display_columns' => $displayColumns,
            ];
            if ($this->ipak->count_kbli_rows() < 1) {
                $errors[] = 'Katalog KBLI masih kosong. Impor atau tambahkan data KBLI sebelum mengaktifkan field ini.';
            }
        }
        $identityOptions = $this->input->post('identity_options', true);
        $identityOptions = is_array($identityOptions) ? $identityOptions : [];
        $supportedChoiceValues = [
            'gender' => [1, 2],
            'education' => array_map('intval', array_keys($this->config->item('ipak_education'))),
            'job' => array_map('intval', array_keys($this->config->item('ipak_jobs'))),
            'service' => array_map('intval', array_keys($this->ipak->sector_options())),
        ];
        foreach (['gender', 'education', 'job', 'service'] as $fieldKey) {
            if ($fieldSettings[$fieldKey]['mode'] === 'hidden') {
                continue;
            }
            if (empty($identityOptions[$fieldKey]) || !is_array($identityOptions[$fieldKey])) {
                continue;
            }
            $options = $this->normalize_numeric_choice_options($identityOptions[$fieldKey]);
            if (count($options) < 2) {
                $errors[] = 'Pilihan untuk "' . $fieldDefinitions[$fieldKey]['label'] . '" harus memiliki minimal dua baris dengan value angka.';
                continue;
            }
            $invalidValues = array_diff(
                array_map('intval', array_column($options, 'value')),
                $supportedChoiceValues[$fieldKey]
            );
            if ($invalidValues) {
                $errors[] = 'Value untuk "' . $fieldDefinitions[$fieldKey]['label'] . '" harus memakai kode kategori yang tersedia agar grafik tetap sesuai.';
                continue;
            }
            $fieldSettings[$fieldKey]['options'] = $options;
        }
        $identityCustom = $this->normalize_custom_fields(
            $this->input->post('identity_custom', true),
            'identity',
            false,
            210
        );
        foreach ($identityCustom as $fieldKey => $setting) {
            if ($setting['type'] === 'select' && count($setting['options']) < 2) {
                $errors[] = 'Identitas "' . $setting['label'] . '" memerlukan minimal dua pilihan dengan value angka.';
            }
            $fieldSettings[$fieldKey] = $setting;
        }

        $questionIds = $this->input->post('question_ids', true);
        $questionIds = is_array($questionIds)
            ? array_values(array_filter(array_unique(array_map('intval', $questionIds))))
            : [];
        $newQuestionInput = $this->input->post('new_questions', true);
        $newQuestionInput = is_array($newQuestionInput) ? $newQuestionInput : [];
        $newQuestions = [];
        $questionSequence = 0;
        foreach ($newQuestionInput as $questionIndex => $questionInput) {
            if (!is_array($questionInput)) {
                continue;
            }
            $questionText = trim((string) (isset($questionInput['question_text']) ? $questionInput['question_text'] : ''));
            if ($questionText === '') {
                continue;
            }
            $questionSequence++;
            $measurementName = trim((string) (isset($questionInput['measurement_name']) ? $questionInput['measurement_name'] : ''));
            $categoryName = trim((string) (isset($questionInput['category_name']) ? $questionInput['category_name'] : ''));
            if ($measurementName === '' || $categoryName === '') {
                $errors[] = 'Pengukuran dan kategori pada pertanyaan baru ke-' . $questionSequence . ' wajib diisi.';
                continue;
            }
            $optionsInput = isset($questionInput['options']) && is_array($questionInput['options'])
                ? $questionInput['options']
                : [];
            $options = [];
            foreach ($optionsInput as $optionIndex => $optionInput) {
                if (!is_array($optionInput)) {
                    continue;
                }
                $optionLabel = trim((string) (isset($optionInput['label']) ? $optionInput['label'] : ''));
                if ($optionLabel === '') {
                    continue;
                }
                $options[] = [
                    'id' => 0,
                    'option_code' => 'O' . ($optionIndex + 1),
                    'option_label' => $optionLabel,
                    'option_value' => isset($optionInput['value']) ? (float) $optionInput['value'] : ($optionIndex + 1),
                    'normalized_score' => isset($optionInput['score']) ? max(0, min(100, (float) $optionInput['score'])) : 0,
                    'sort_order' => $optionIndex + 1,
                    'is_active' => true,
                ];
            }
            if (count($options) < 2) {
                $errors[] = 'Pertanyaan baru ke-' . $questionSequence . ' harus mempunyai minimal dua pilihan jawaban.';
                continue;
            }
            $newQuestions[] = [
                'question' => [
                    'id' => 0,
                    'question_code' => $this->next_question_code($surveyCode, $questionSequence),
                    'question_text' => $questionText,
                    'measurement_name' => $measurementName,
                    'category_name' => $categoryName,
                    'weight' => isset($questionInput['weight']) ? max(0.01, (float) $questionInput['weight']) : 1,
                    'sort_order' => count($this->ipak->get_questions(false)) + $questionSequence,
                    'is_active' => true,
                ],
                'options' => $options,
            ];
        }
        if (!$questionIds && !$newQuestions) {
            $errors[] = 'Langkah 3 harus mempunyai minimal satu pertanyaan lama atau pertanyaan baru.';
        }

        return [
            'posted' => $posted,
            'errors' => $errors,
            'survey' => [
                'survey_code' => $surveyCode,
                'survey_name' => $surveyName,
                'index_label' => $indexLabel,
                'description' => $description,
                'color' => $color,
            ],
            'form' => [
                'form_code' => $formCode,
                'form_name' => $formName,
                'description' => $description,
                'is_public_listed' => $isPublicListed,
            ],
            'question_ids' => $questionIds,
            'new_questions' => $newQuestions,
            'field_settings' => $fieldSettings,
        ];
    }

    private function build_wizard_old_data(array $form, array $survey, array $respondentFields, array $questionIds)
    {
        $fieldDefinitions = $this->ipak->respondent_field_definitions();
        $old = [
            'form_code' => isset($form['form_code']) ? $form['form_code'] : '',
            'form_name' => isset($form['form_name']) ? $form['form_name'] : '',
            'survey_code' => isset($survey['survey_code']) ? $survey['survey_code'] : '',
            'survey_name' => isset($survey['survey_name']) ? $survey['survey_name'] : '',
            'index_label' => isset($survey['index_label']) ? $survey['index_label'] : '',
            'description' => isset($form['description']) ? $form['description'] : '',
            'color' => isset($survey['color']) ? $survey['color'] : '#8b5cf6',
            'is_public_listed' => isset($form['is_public_listed']) ? (int) $form['is_public_listed'] : 1,
        ];

        $initialFields = [];
        $identityFields = [];
        $identityOptions = [];
        $identityKbli = ['code_field' => 'kode_gabungan', 'display_columns' => ['kode_gabungan', 'sektor_bps', 'judul']];
        $initialCustom = [];
        $identityCustom = [];

        $accessFields = ['email', 'phone', 'identity_number'];
        $identityFieldKeys = ['name', 'address', 'gender', 'age', 'education', 'job', 'service', 'kbli'];
        $choiceFieldKeys = ['gender', 'education', 'job', 'service'];

        foreach (array_merge($accessFields, $identityFieldKeys) as $fieldKey) {
            $field = isset($respondentFields[$fieldKey]) ? $respondentFields[$fieldKey] : [];
            $mode = isset($field['field_mode']) ? (string) $field['field_mode'] : 'hidden';
            $group = isset($field['field_group'])
                ? (string) $field['field_group']
                : (isset($fieldDefinitions[$fieldKey]['field_group']) ? $fieldDefinitions[$fieldKey]['field_group'] : 'identity');

            if (in_array($fieldKey, $accessFields, true)) {
                if ($group === 'access' && $mode === 'required') {
                    $initialFields[] = $fieldKey;
                }
                $identityFields[$fieldKey] = $mode;
            } else {
                $identityFields[$fieldKey] = $mode;
            }

            if (in_array($fieldKey, $choiceFieldKeys, true)) {
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                if ($options) {
                    $identityOptions[$fieldKey] = [];
                    foreach ($options as $option) {
                        $identityOptions[$fieldKey][] = [
                            'label' => isset($option['label']) ? $option['label'] : '',
                            'value' => isset($option['value']) ? $option['value'] : '',
                        ];
                    }
                }
            }

            if ($fieldKey === 'kbli') {
                $options = isset($field['options']) ? $field['options'] : [];
                if (is_array($options) && isset($options['source']) && $options['source'] === 'ipak_kbli') {
                    $identityKbli = [
                        'code_field' => isset($options['code_field']) ? (string) $options['code_field'] : 'kode_gabungan',
                        'display_columns' => isset($options['display_columns']) && is_array($options['display_columns'])
                            ? $options['display_columns']
                            : [],
                    ];
                }
            }
        }

        $customIndex = 0;
        foreach ($respondentFields as $fieldKey => $field) {
            if (!empty($field['is_system'])) {
                continue;
            }
            $group = isset($field['field_group']) ? (string) $field['field_group'] : 'identity';
            $setting = [
                'label' => isset($field['field_label']) ? $field['field_label'] : '',
                'type' => isset($field['field_type']) ? $field['field_type'] : 'text',
                'help_text' => isset($field['help_text']) ? $field['help_text'] : '',
                'mode' => isset($field['field_mode']) ? (string) $field['field_mode'] : 'required',
            ];
            $options = isset($field['options']) ? $field['options'] : [];
            if ($setting['type'] === 'select' && is_array($options) && !empty($options)) {
                if (isset($options['source'])) {
                    $setting['value_mode'] = 'numeric';
                    $setting['options'] = [['label' => '', 'value' => 1]];
                } else {
                    $isLabelMode = false;
                    foreach ($options as $opt) {
                        if (is_array($opt) && isset($opt['value']) && !is_numeric($opt['value'])) {
                            $isLabelMode = true;
                            break;
                        }
                    }
                    $setting['value_mode'] = $isLabelMode ? 'label' : 'numeric';
                    $setting['options'] = [];
                    foreach ($options as $opt) {
                        $setting['options'][] = [
                            'label' => isset($opt['label']) ? $opt['label'] : '',
                            'value' => isset($opt['value']) ? $opt['value'] : '',
                        ];
                    }
                }
            } elseif ($setting['type'] === 'select') {
                $setting['value_mode'] = 'numeric';
                $setting['options'] = '';
            } elseif (is_string($options) && $options !== '') {
                $setting['options'] = $options;
            } else {
                $setting['options'] = '';
            }
            if ($group === 'access') {
                $initialCustom[$customIndex] = $setting;
            } else {
                $identityCustom[$customIndex] = $setting;
            }
            $customIndex++;
        }

        $old['initial_fields'] = $initialFields;
        $old['initial_custom'] = $initialCustom;
        $old['identity_fields'] = $identityFields;
        $old['identity_options'] = $identityOptions;
        $old['identity_kbli'] = $identityKbli;
        $old['identity_custom'] = $identityCustom;
        $old['new_questions'] = [];
        $old['question_ids'] = array_values(array_map('intval', $questionIds));

        return $old;
    }

    public function api_builder()
    {
        $this->require_login();
        $this->require_superadmin();
        $this->load->model('Survey_api_model', 'survey_api');

        $this->render('admin/api_clients', [
            'page_title' => 'API Builder',
            'clients' => $this->survey_api->get_clients(),
            'surveys' => $this->ipak->get_surveys(true),
            'resources' => $this->survey_api->resource_definitions(),
            'recent_logs' => $this->survey_api->recent_logs(0, 20),
            'credentials' => $this->session->flashdata('api_credentials'),
            'success' => $this->session->flashdata('api_success'),
            'error' => $this->session->flashdata('api_error'),
        ]);
    }

    public function api_client_form($id = 0)
    {
        $this->require_login();
        $this->require_superadmin();
        $this->load->model('Survey_api_model', 'survey_api');
        $id = max(0, (int) $id);
        $client = $id > 0 ? $this->survey_api->get_client($id) : false;
        if ($id > 0 && !$client) {
            show_404();
        }

        $errors = [];
        if (strtoupper($this->input->method()) === 'POST') {
            list($submitted, $errors) = $this->parse_api_client_submission($id);
            $client = array_merge($client ?: [], $submitted);
            if (!$errors) {
                if ($id > 0) {
                    if (!$this->survey_api->update_client($id, $submitted)) {
                        $errors[] = 'Konfigurasi API belum berhasil diperbarui.';
                    } else {
                        $this->session->set_flashdata('api_success', 'Konfigurasi API berhasil diperbarui.');
                        return redirect('admin/api-builder');
                    }
                } else {
                    $created = $this->survey_api->create_client($submitted);
                    if (!$created) {
                        $errors[] = 'Akses API belum berhasil dibuat.';
                    } else {
                        $this->session->set_flashdata('api_credentials', [
                            'client_id' => (int) $created['id'],
                            'client_name' => $submitted['client_name'],
                            'endpoint' => site_url('api/v1/survey-data/' . $submitted['client_code']),
                            'api_key' => $created['api_key'],
                        ]);
                        $this->session->set_flashdata(
                            'api_success',
                            'Akses API berhasil dibuat. Salin kunci sekarang karena tidak akan ditampilkan kembali.'
                        );
                        return redirect('admin/api-builder');
                    }
                }
            }
        }

        if (!$client) {
            $client = [
                'id' => 0,
                'client_name' => '',
                'client_code' => '',
                'description' => '',
                'survey_scope' => 'selected',
                'allowed_survey_ids' => [],
                'allowed_resources' => ['summary', 'chart'],
                'allowed_dimensions' => ['overall', 'gender', 'age', 'education', 'job', 'service'],
                'allowed_detail_fields' => [
                    'response_id',
                    'reference',
                    'submission_group',
                    'date',
                    'survey',
                    'score',
                ],
                'max_page_size' => 50,
                'rate_limit_per_minute' => 60,
                'allowed_ip_addresses' => '',
                'allowed_origin' => '',
                'expires_at' => '',
                'is_active' => true,
            ];
        }

        $this->render('admin/api_client_form', [
            'page_title' => $id > 0 ? 'Ubah Akses API' : 'Buat Akses API',
            'client' => $client,
            'surveys' => $this->ipak->get_surveys(true),
            'resources' => $this->survey_api->resource_definitions(),
            'dimensions' => $this->survey_api->dimension_definitions(),
            'detail_fields' => $this->survey_api->detail_field_definitions(),
            'errors' => $errors,
        ]);
    }

    public function api_client_toggle($id)
    {
        $this->require_login();
        $this->require_superadmin();
        $this->load->model('Survey_api_model', 'survey_api');
        if (strtoupper($this->input->method()) !== 'POST') {
            show_error('Metode tidak diizinkan.', 405, 'Akses ditolak');
        }
        $client = $this->survey_api->get_client((int) $id);
        if (!$client) {
            show_404();
        }
        $isActive = (int) $this->input->post('is_active', true) === 1;
        $this->survey_api->set_active((int) $id, $isActive);
        $this->session->set_flashdata(
            'api_success',
            $isActive ? 'Akses API berhasil diaktifkan.' : 'Akses API berhasil dinonaktifkan.'
        );
        return redirect('admin/api-builder');
    }

    public function api_client_regenerate($id)
    {
        $this->require_login();
        $this->require_superadmin();
        $this->load->model('Survey_api_model', 'survey_api');
        if (strtoupper($this->input->method()) !== 'POST') {
            show_error('Metode tidak diizinkan.', 405, 'Akses ditolak');
        }
        $client = $this->survey_api->get_client((int) $id);
        if (!$client) {
            show_404();
        }
        $apiKey = $this->survey_api->regenerate_key((int) $id);
        if (!$apiKey) {
            $this->session->set_flashdata('api_error', 'Kunci API belum berhasil diperbarui.');
            return redirect('admin/api-builder');
        }
        $this->session->set_flashdata('api_credentials', [
            'client_id' => (int) $client['id'],
            'client_name' => $client['client_name'],
            'endpoint' => site_url('api/v1/survey-data/' . $client['client_code']),
            'api_key' => $apiKey,
        ]);
        $this->session->set_flashdata(
            'api_success',
            'Kunci baru berhasil dibuat. Kunci lama langsung tidak berlaku.'
        );
        return redirect('admin/api-builder');
    }

    public function api_client_documentation($id)
    {
        $this->require_login();
        $this->require_superadmin();
        $this->load->model('Survey_api_model', 'survey_api');
        $client = $this->survey_api->get_client((int) $id);
        if (!$client) {
            show_404();
        }

        $allSurveys = $this->ipak->get_surveys(true);
        $surveys = [];
        if ($client['survey_scope'] === 'all') {
            $surveys = $allSurveys;
        } else {
            $allowedIds = array_map('intval', $client['allowed_survey_ids']);
            foreach ($allSurveys as $surveyId => $survey) {
                if (in_array((int) $surveyId, $allowedIds, true)) {
                    $surveys[(int) $surveyId] = $survey;
                }
            }
        }

        $this->load->library('Api_documentation_pdf');
        $endpoint = site_url('api/v1/survey-data/' . $client['client_code']);
        $pdf = $this->api_documentation_pdf->build(
            $client,
            $surveys,
            $this->survey_api->resource_definitions(),
            $this->survey_api->dimension_definitions(),
            $this->survey_api->detail_field_definitions(),
            $endpoint
        );
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $client['client_code']);
        $filename = 'Dokumentasi-API-DPMPTSP-' . trim($safeName, '-') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
        exit;
    }  

    public function kbli()
    {
        $this->require_login();
        $schemaResult = $this->ipak->sync_kbli_schema();
        if (!empty($schemaResult['errors'])) {
            show_error('Sinkronisasi struktur KBLI gagal: ' . html_escape(implode(' ', $schemaResult['errors'])), 500, 'Database KBLI');
        }
        $errors = [];
        $message = '';

        if (strtoupper($this->input->method()) === 'POST') {
            $this->require_superadmin();
            $action = trim((string) $this->input->post('action', true));
            if ($action === 'import') {
                if (!isset($_FILES['kbli_csv']) || !is_uploaded_file($_FILES['kbli_csv']['tmp_name'])) {
                    $errors[] = 'Pilih file CSV terlebih dahulu.';
                } elseif ((int) $_FILES['kbli_csv']['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'File CSV gagal diunggah. Batas ukuran maksimal 10 MB.';
                } elseif ((int) $_FILES['kbli_csv']['size'] > 10 * 1024 * 1024) {
                    $errors[] = 'Ukuran file CSV melebihi batas 10 MB.';
                } else {
                    list($csvRows, $csvErrors) = $this->parse_kbli_csv($_FILES['kbli_csv']['tmp_name']);
                    $errors = array_merge($errors, $csvErrors);
                    $replaceExisting = $this->input->post('import_mode', true) === 'replace';
                    if ($replaceExisting && $this->input->post('confirm_replace', true) !== '1') {
                        $errors[] = 'Konfirmasi penggantian seluruh katalog KBLI wajib dicentang.';
                    }
                    if (!$errors && !$this->ipak->import_kbli($csvRows, $replaceExisting)) {
                        $errors[] = 'Impor KBLI gagal dan perubahan telah dibatalkan.';
                    } elseif (!$errors) {
                        $message = count($csvRows) . ' baris KBLI berhasil diimpor. ' . ($replaceExisting
                            ? 'Isi katalog lama telah diganti.'
                            : 'Data lama dipertahankan; kode yang sama diperbarui.');
                    }
                }
            } elseif ($action === 'create') {
                $row = $this->parse_kbli_form();
                $errors = $this->validate_kbli_row($row);
                if (!$errors && $this->ipak->kbli_code_exists($row['kode_gabungan'])) {
                    $errors[] = 'Kode gabungan tersebut sudah ada.';
                }
                if (!$errors && !$this->ipak->save_kbli($row)) {
                    $errors[] = 'Data KBLI belum berhasil disimpan.';
                }
                if (!$errors) {
                    $message = 'Data KBLI berhasil ditambahkan.';
                }
            } else {
                $errors[] = 'Aksi KBLI tidak dikenali.';
            }

            $this->session->set_flashdata($errors ? 'kbli_error' : 'kbli_success', $errors ? implode(' ', $errors) : $message);
            if ($errors && $action === 'create') {
                $this->session->set_flashdata('kbli_old', $this->input->post(NULL, true));
            }
            return redirect('admin/kbli');
        }

        $search = trim((string) $this->input->get('q', true));
        $search = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);
        $perPage = 50;
        $total = $this->ipak->count_kbli_rows($search);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, (int) $this->input->get('page', true));
        $page = min($page, $totalPages);

        $this->render('admin/kbli', [
            'page_title' => 'KBLI',
            'rows' => $this->ipak->get_kbli_rows($search, $perPage, ($page - 1) * $perPage),
            'search' => $search,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'success' => $this->session->flashdata('kbli_success'),
            'error' => $this->session->flashdata('kbli_error'),
            'old' => $this->session->flashdata('kbli_old') ?: [],
            'is_superadmin' => $this->is_superadmin(),
        ]);
    }

    public function edit_kbli($id = 0)
    {
        $this->require_login();
        $schemaResult = $this->ipak->sync_kbli_schema();
        if (!empty($schemaResult['errors'])) {
            show_error('Sinkronisasi struktur KBLI gagal: ' . html_escape(implode(' ', $schemaResult['errors'])), 500, 'Database KBLI');
        }
        $row = $this->ipak->get_kbli_by_id((int) $id);
        if (!$row) {
            show_404();
        }
        if (strtoupper($this->input->method()) === 'POST') {
            $this->require_superadmin();
            $row = $this->parse_kbli_form((int) $id);
            $errors = $this->validate_kbli_row($row);
            if (!$errors && $this->ipak->kbli_code_exists($row['kode_gabungan'], (int) $id)) {
                $errors[] = 'Kode gabungan tersebut sudah dipakai baris lain.';
            }
            if (!$errors && !$this->ipak->save_kbli($row)) {
                $errors[] = 'Perubahan KBLI belum berhasil disimpan.';
            }
            $this->session->set_flashdata($errors ? 'kbli_error' : 'kbli_success', $errors ? implode(' ', $errors) : 'Data KBLI berhasil diperbarui.');
            if ($errors) {
                $this->session->set_flashdata('kbli_edit_old', $this->input->post(NULL, true));
                return redirect('admin/kbli/edit/' . (int) $id);
            }
            return redirect('admin/kbli');
        }

        $this->render('admin/kbli_edit', [
            'page_title' => 'Ubah Data KBLI',
            'row' => $row,
            'error' => $this->session->flashdata('kbli_error'),
            'old' => $this->session->flashdata('kbli_edit_old') ?: [],
            'is_superadmin' => $this->is_superadmin(),
        ]);
    }

    public function delete_kbli($id = 0)
    {
        $this->require_login();
        $this->require_superadmin();
        $schemaResult = $this->ipak->sync_kbli_schema();
        if (!empty($schemaResult['errors'])) {
            show_error('Sinkronisasi struktur KBLI gagal: ' . html_escape(implode(' ', $schemaResult['errors'])), 500, 'Database KBLI');
        }
        if (strtoupper($this->input->method()) !== 'POST') {
            show_error('Metode tidak diizinkan.', 405, 'Akses ditolak');
        }
        $row = $this->ipak->get_kbli_by_id((int) $id);
        if (!$row) {
            $this->session->set_flashdata('kbli_error', 'Data KBLI tidak ditemukan.');
        } elseif (!$this->ipak->delete_kbli((int) $id)) {
            $this->session->set_flashdata('kbli_error', 'Data KBLI belum berhasil dihapus.');
        } else {
            $this->session->set_flashdata('kbli_success', 'Data KBLI ' . $row['kode_gabungan'] . ' berhasil dihapus.');
        }
        return redirect('admin/kbli');
    }

    private function parse_kbli_form($id = 0)
    {
        return [
            'id' => (int) $id,
            'kode_gabungan' => trim((string) $this->input->post('kode_gabungan', true)),
            'kategori' => trim((string) $this->input->post('kategori', true)),
            'kode' => trim((string) $this->input->post('kode', true)),
            'sektor_bps' => trim((string) $this->input->post('sektor_bps', true)),
            'judul' => trim((string) $this->input->post('judul', true)),
            'deskripsi' => trim((string) $this->input->post('deskripsi', true)),
            'digit' => (int) $this->input->post('digit', true),
            'hirarki' => trim((string) $this->input->post('hirarki', true)),
        ];
    }

    private function validate_kbli_row(array $row)
    {
        $errors = [];
        if ($row['kode_gabungan'] === '' || strlen($row['kode_gabungan']) > 32) $errors[] = 'Kode Gabungan wajib diisi (maksimal 32 karakter).';
        if ($row['kategori'] === '' || strlen($row['kategori']) > 32) $errors[] = 'Kategori wajib diisi (maksimal 32 karakter).';
        if ($row['kode'] === '' || strlen($row['kode']) > 32) $errors[] = 'Kode wajib diisi (maksimal 32 karakter).';
        if ($row['sektor_bps'] === '' || strlen($row['sektor_bps']) > 255) $errors[] = 'Sektor BPS wajib diisi (maksimal 255 karakter).';
        if ($row['judul'] === '' || strlen($row['judul']) > 255) $errors[] = 'Judul wajib diisi (maksimal 255 karakter).';
        if (strlen($row['deskripsi']) > 65535) $errors[] = 'Deskripsi terlalu panjang.';
        if ($row['digit'] < 1 || $row['digit'] > 255) $errors[] = 'Digit harus berupa angka antara 1 dan 255.';
        if ($row['hirarki'] === '' || strlen($row['hirarki']) > 40) $errors[] = 'Hirarki wajib diisi (maksimal 40 karakter).';
        return $errors;
    }

    private function parse_kbli_csv($path)
    {
        $errors = [];
        $rows = [];
        $handle = fopen($path, 'rb');
        if (!$handle) return [[], ['File CSV tidak dapat dibaca.']];
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return [[], ['File CSV kosong.']];
        }
        $delimiters = [',', ';', "\t"];
        $delimiter = ',';
        $columnCount = 0;
        foreach ($delimiters as $candidate) {
            $count = count(str_getcsv($firstLine, $candidate, '"', '\\'));
            if ($count > $columnCount) {
                $columnCount = $count;
                $delimiter = $candidate;
            }
        }
        rewind($handle);
        $headers = fgetcsv($handle, 1000000, $delimiter, '"', '\\');
        if (!$headers) {
            fclose($handle);
            return [[], ['Header CSV tidak ditemukan.']];
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $normalizeHeader = function ($header) {
            $header = strtolower(trim((string) $header));
            return preg_replace('/[^a-z0-9]+/', '', $header);
        };
        $aliases = [
            'kode_gabungan' => ['kodegabungan'],
            'kategori' => ['kategori'],
            'kode' => ['kode'],
            'sektor_bps' => ['sektorbps', 'sektor'],
            'judul' => ['judul'],
            'deskripsi' => ['deskripsi'],
            'digit' => ['digit'],
            'hirarki' => ['hirarki', 'hierarki'],
        ];
        $headerIndexes = [];
        foreach ($headers as $index => $header) {
            $normalized = $normalizeHeader($header);
            foreach ($aliases as $field => $fieldAliases) {
                if (in_array($normalized, $fieldAliases, true)) $headerIndexes[$field] = $index;
            }
        }
        $requiredHeaders = array_keys($aliases);
        $missingHeaders = array_diff($requiredHeaders, array_keys($headerIndexes));
        if ($missingHeaders) {
            fclose($handle);
            return [[], ['Kolom CSV tidak lengkap. Header wajib: Kode Gabungan, Kategori, Kode, Sektor BPS, Judul, Deskripsi, Digit, Hirarki.']];
        }
        $lineNumber = 1;
        while (($values = fgetcsv($handle, 1000000, $delimiter, '"', '\\')) !== false) {
            $lineNumber++;
            if (count($values) === 1 && trim((string) $values[0]) === '') continue;
            $row = [];
            foreach ($headerIndexes as $field => $index) {
                $row[$field] = isset($values[$index]) ? trim((string) $values[$index]) : '';
            }
            if ($row['digit'] !== '' && ctype_digit($row['digit'])) $row['digit'] = (int) $row['digit'];
            else $row['digit'] = 0;
            $rowErrors = $this->validate_kbli_row($row);
            if ($rowErrors) {
                $errors[] = 'Baris ' . $lineNumber . ': ' . implode(' ', $rowErrors);
                if (count($errors) >= 20) {
                    $errors[] = 'Validasi dihentikan setelah 20 kesalahan.';
                    break;
                }
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);
        if (!$rows && !$errors) $errors[] = 'Tidak ada baris data pada CSV.';
        if (!$errors) {
            $seen = [];
            foreach ($rows as $row) {
                if (isset($seen[$row['kode_gabungan']])) {
                    $errors[] = 'Kode Gabungan duplikat di dalam file: ' . $row['kode_gabungan'];
                    break;
                }
                $seen[$row['kode_gabungan']] = true;
            }
        }
        return [$rows, $errors];
    }

    public function units()
    {
        $this->require_login();
        show_404();
    }

    public function help()
    {
        $this->require_login();
        $this->render('admin/help', [
            'page_title' => 'Panduan Pengguna',
        ]);
    }

    private function parse_question_submission($questionId)
    {
        $questionId = (int) $questionId;
        $question = [
            'id' => $questionId,
            'question_code' => strtoupper(trim((string) $this->input->post('question_code', true))),
            'question_text' => trim((string) $this->input->post('question_text', true)),
            'measurement_name' => trim((string) $this->input->post('measurement_name', true)),
            'category_name' => trim((string) $this->input->post('category_name', true)),
            'weight' => (float) $this->input->post('weight', true),
            'sort_order' => (int) $this->input->post('sort_order', true),
            'is_active' => (int) $this->input->post('is_active', true) === 1,
        ];
        $optionsInput = $this->input->post('options', true);
        $optionsInput = is_array($optionsInput) ? $optionsInput : [];
        $options = [];
        $activeOptions = 0;
        $optionCodes = [];
        $hasDuplicateOptionCode = false;

        foreach ($optionsInput as $index => $option) {
            if (!is_array($option)) {
                continue;
            }
            $optionCode = strtoupper(trim((string) (isset($option['option_code']) ? $option['option_code'] : '')));
            $optionLabel = trim((string) (isset($option['option_label']) ? $option['option_label'] : ''));
            if ($optionCode === '' || $optionLabel === '') {
                continue;
            }
            if (isset($optionCodes[$optionCode])) {
                $hasDuplicateOptionCode = true;
            }
            $optionCodes[$optionCode] = true;
            $isOptionActive = !empty($option['is_active']);
            if ($isOptionActive) {
                $activeOptions++;
            }
            $options[] = [
                'id' => isset($option['id']) ? (int) $option['id'] : 0,
                'option_code' => $optionCode,
                'option_label' => $optionLabel,
                'option_value' => isset($option['option_value']) ? (float) $option['option_value'] : 0,
                'normalized_score' => isset($option['normalized_score']) ? (float) $option['normalized_score'] : 0,
                'sort_order' => isset($option['sort_order']) ? (int) $option['sort_order'] : ($index + 1),
                'is_active' => $isOptionActive,
            ];
        }

        $errors = [];
        foreach (['question_code', 'question_text', 'measurement_name', 'category_name'] as $requiredField) {
            if ($question[$requiredField] === '') {
                $errors[] = 'Kode, pertanyaan, pengukuran, dan kategori wajib diisi.';
                break;
            }
        }
        if (!preg_match('/^[A-Z0-9_-]{2,20}$/', $question['question_code'])) {
            $errors[] = 'Kode pertanyaan hanya boleh berisi huruf, angka, garis bawah, atau tanda hubung.';
        }
        if ($question['weight'] <= 0) {
            $errors[] = 'Bobot pertanyaan harus lebih besar dari nol.';
        }
        if ($activeOptions < 2) {
            $errors[] = 'Setiap pertanyaan harus memiliki minimal dua pilihan jawaban aktif.';
        }
        if ($hasDuplicateOptionCode) {
            $errors[] = 'Kode pilihan jawaban tidak boleh sama dalam satu pertanyaan.';
        }
        if ($this->ipak->question_code_exists($question['question_code'], $questionId)) {
            $errors[] = 'Kode pertanyaan sudah digunakan.';
        }

        return [$question, $options, $errors];
    }

    private function normalize_custom_fields($input, $group, $forceRequired, $startOrder)
    {
        $input = is_array($input) ? $input : [];
        $result = [];
        $allowedTypes = ['text', 'email', 'tel', 'number', 'textarea', 'select'];
        $position = 0;
        foreach ($input as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $label = trim((string) (isset($field['label']) ? $field['label'] : ''));
            if ($label === '') {
                continue;
            }
            $position++;
            $type = isset($field['type']) ? trim((string) $field['type']) : 'text';
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'text';
            }
            $mode = $forceRequired
                ? 'required'
                : (isset($field['mode']) ? trim((string) $field['mode']) : 'required');
            if (!in_array($mode, ['optional', 'required'], true)) {
                $mode = 'required';
            }
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label));
            $slug = trim($slug, '_');
            if ($slug === '') {
                $slug = 'field';
            }
            $key = substr('custom_' . ($group === 'access' ? 'a' : 'i') . $position . '_' . $slug, 0, 30);
            $optionsInput = isset($field['options']) ? $field['options'] : [];
            $valueMode = isset($field['value_mode']) && $field['value_mode'] === 'label' ? 'label' : 'numeric';
            if (is_string($optionsInput)) {
                $labels = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $optionsInput))));
                $optionsInput = [];
                foreach ($labels as $optionIndex => $optionLabel) {
                    $optionsInput[] = ['label' => $optionLabel, 'value' => $valueMode === 'label' ? $optionLabel : $optionIndex + 1];
                }
            }
            if (is_array($optionsInput)) {
                if ($valueMode === 'label') {
                    $optionsInput = $this->normalize_label_choice_options($optionsInput);
                } else {
                    $optionsInput = $this->normalize_numeric_choice_options($optionsInput);
                }
            } else {
                $optionsInput = [];
            }
            $result[$key] = [
                'mode' => $mode,
                'label' => $label,
                'help_text' => trim((string) (isset($field['help_text']) ? $field['help_text'] : '')),
                'group' => $group,
                'type' => $type,
                'value_mode' => $valueMode,
                'options' => $optionsInput,
                'sort_order' => $startOrder + $position,
            ];
        }
        return $result;
    }

    private function normalize_label_choice_options(array $input)
    {
        $options = [];
        $seenLabels = [];
        foreach ($input as $option) {
            if (!is_array($option)) {
                continue;
            }
            $label = trim((string) (isset($option['label']) ? $option['label'] : ''));
            if ($label === '') {
                continue;
            }
            $label = substr($label, 0, 100);
            $signature = strtolower($label);
            if (isset($seenLabels[$signature])) {
                continue;
            }
            $seenLabels[$signature] = true;
            $options[] = ['label' => $label, 'value' => $label];
        }
        return $options;
    }

    private function normalize_numeric_choice_options(array $input)
    {
        $options = [];
        $seenValues = [];
        foreach ($input as $option) {
            if (!is_array($option)) {
                continue;
            }
            $label = trim((string) (isset($option['label']) ? $option['label'] : ''));
            $rawValue = isset($option['value']) ? trim((string) $option['value']) : '';
            if ($label === '' && $rawValue === '') {
                continue;
            }
            if ($label === '' || $rawValue === '' || !preg_match('/^-?\d+$/', $rawValue)) {
                continue;
            }
            $value = (int) $rawValue;
            if (isset($seenValues[$value])) {
                continue;
            }
            $seenValues[$value] = true;
            $options[] = ['label' => substr($label, 0, 100), 'value' => $value];
        }
        return $options;
    }

    private function next_question_code($surveyCode, $sequence)
    {
        $base = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $surveyCode));
        $base = substr($base !== '' ? $base : 'Q', 0, 14);
        $number = max(1, (int) $sequence);
        do {
            $code = substr($base . '-' . str_pad($number, 2, '0', STR_PAD_LEFT), 0, 20);
            $number++;
        } while ($this->ipak->question_code_exists($code));
        return $code;
    }

    private function parse_api_client_submission($clientId)
    {
        $this->load->model('Survey_api_model', 'survey_api');
        $clientName = trim((string) $this->input->post('client_name', true));
        $clientCode = strtolower(trim((string) $this->input->post('client_code', true)));
        $description = trim((string) $this->input->post('description', true));
        $surveyScope = trim((string) $this->input->post('survey_scope', true));
        if (!in_array($surveyScope, ['all', 'selected'], true)) {
            $surveyScope = 'selected';
        }

        $allSurveys = $this->ipak->get_surveys(true);
        $surveyIdsInput = $this->input->post('allowed_survey_ids', true);
        $surveyIdsInput = is_array($surveyIdsInput) ? $surveyIdsInput : [];
        $surveyIds = [];
        foreach (array_unique(array_map('intval', $surveyIdsInput)) as $surveyId) {
            if ($surveyId > 0 && isset($allSurveys[$surveyId])) {
                $surveyIds[] = $surveyId;
            }
        }

        $resourceDefinitions = $this->survey_api->resource_definitions();
        $resourceInput = $this->input->post('allowed_resources', true);
        $resourceInput = is_array($resourceInput) ? $resourceInput : [];
        $resources = array_values(array_intersect(array_keys($resourceDefinitions), $resourceInput));

        $dimensionDefinitions = $this->survey_api->dimension_definitions();
        $dimensionInput = $this->input->post('allowed_dimensions', true);
        $dimensionInput = is_array($dimensionInput) ? $dimensionInput : [];
        $dimensions = array_values(array_intersect(array_keys($dimensionDefinitions), $dimensionInput));

        $detailDefinitions = $this->survey_api->detail_field_definitions();
        $detailInput = $this->input->post('allowed_detail_fields', true);
        $detailInput = is_array($detailInput) ? $detailInput : [];
        $detailFields = array_values(array_intersect(array_keys($detailDefinitions), $detailInput));

        $maxPageSize = max(1, min(500, (int) $this->input->post('max_page_size', true)));
        $rateLimit = max(1, min(600, (int) $this->input->post('rate_limit_per_minute', true)));
        $allowedIps = trim((string) $this->input->post('allowed_ip_addresses', true));
        $allowedOrigin = trim((string) $this->input->post('allowed_origin', true));
        $expiresInput = trim((string) $this->input->post('expires_at', true));
        $expiresAt = '';
        if ($expiresInput !== '') {
            $expiresDate = DateTime::createFromFormat('Y-m-d\TH:i', $expiresInput);
            if ($expiresDate && $expiresDate->format('Y-m-d\TH:i') === $expiresInput) {
                $expiresAt = $expiresDate->format('Y-m-d H:i:s');
            }
        }

        $errors = [];
        if ($clientName === '' || strlen($clientName) < 3 || strlen($clientName) > 120) {
            $errors[] = 'Nama peminta API wajib berisi 3 sampai 120 karakter.';
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,49}$/', $clientCode)) {
            $errors[] = 'Kode endpoint harus berisi 3–50 huruf kecil, angka, garis bawah, atau tanda hubung.';
        } elseif ($this->survey_api->client_code_exists($clientCode, (int) $clientId)) {
            $errors[] = 'Kode endpoint sudah digunakan akses API lain.';
        }
        if ($description !== '' && strlen($description) > 255) {
            $errors[] = 'Catatan kebutuhan maksimal 255 karakter.';
        }
        if ($surveyScope === 'selected' && !$surveyIds) {
            $errors[] = 'Pilih minimal satu survei yang boleh dibaca.';
        }
        if (!$resources) {
            $errors[] = 'Pilih minimal satu jenis keluaran API.';
        }
        if (in_array('chart', $resources, true) && !$dimensions) {
            $errors[] = 'Pilih minimal satu dimensi untuk data grafik.';
        }
        if (in_array('details', $resources, true) && !$detailFields) {
            $errors[] = 'Pilih minimal satu kolom untuk data detail respons.';
        }
        if ($expiresInput !== '' && $expiresAt === '') {
            $errors[] = 'Tanggal berakhir akses tidak valid.';
        }
        if ($allowedOrigin !== ''
            && $allowedOrigin !== '*'
            && !preg_match('#^https?://[a-z0-9.-]+(?::[0-9]{1,5})?$#i', $allowedOrigin)
        ) {
            $errors[] = 'Asal aplikasi harus berupa origin, misalnya https://aplikasi.jabarprov.go.id, tanpa path.';
        }
        if ($allowedIps !== '') {
            if (strlen($allowedIps) > 2000) {
                $errors[] = 'Daftar IP maksimal 2.000 karakter.';
            }
            $ipValues = preg_split('/[\s,;]+/', $allowedIps);
            foreach ($ipValues as $ipValue) {
                if ($ipValue !== '' && filter_var($ipValue, FILTER_VALIDATE_IP) === false) {
                    $errors[] = 'Daftar IP hanya boleh berisi alamat IPv4 atau IPv6 yang valid.';
                    break;
                }
            }
        }

        return [[
            'client_name' => $clientName,
            'client_code' => $clientCode,
            'description' => $description,
            'survey_scope' => $surveyScope,
            'allowed_survey_ids' => $surveyIds,
            'allowed_resources' => $resources,
            'allowed_dimensions' => $dimensions,
            'allowed_detail_fields' => $detailFields,
            'max_page_size' => $maxPageSize,
            'rate_limit_per_minute' => $rateLimit,
            'allowed_ip_addresses' => $allowedIps,
            'allowed_origin' => $allowedOrigin,
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'is_active' => (int) $this->input->post('is_active', true) === 1 ? 1 : 0,
            'created_by' => (int) $this->session->userdata('ipak_admin_id'),
        ], $errors];
    }

    private function filters()
    {
        $surveyType = strtoupper(trim((string) $this->input->get('survey_type', true)));
        if (!in_array($surveyType, ['SKM', 'SURVEY'], true)) {
            $surveyType = '';
        }
        return [
            'date_from' => $this->safe_date($this->input->get('date_from', true)),
            'date_to' => $this->safe_date($this->input->get('date_to', true)),
            'gender' => (int) $this->input->get('gender', true),
            'education' => (int) $this->input->get('education', true),
            'job' => (int) $this->input->get('job', true),
            'service' => (int) $this->input->get('service', true),
            'unit_id' => (int) $this->input->get('unit_id', true),
            'survey_id' => (int) $this->input->get('survey_id', true),
            'survey_type' => $surveyType,
            'keyword' => trim((string) $this->input->get('keyword', true)),
        ];
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

    private function verify_password($password, $stored)
    {
        $stored = (string) $stored;
        if ($stored === '') {
            return false;
        }
        if (password_get_info($stored)['algo'] && password_verify($password, $stored)) {
            return true;
        }

        $candidates = [
            hash('sha256', $password),
            sha1($password),
            md5($password),
        ];
        foreach ($candidates as $candidate) {
            if (hash_equals(strtolower($stored), strtolower($candidate))) {
                return true;
            }
        }
        return false;
    }

    private function is_logged_in()
    {
        return (bool) $this->session->userdata('ipak_admin_logged_in');
    }

    private function require_login()
    {
        if (!$this->is_logged_in()) {
            redirect('admin/login');
            exit;
        }
    }

    private function is_superadmin()
    {
        return $this->session->userdata('ipak_admin_role') === 'superadmin';
    }

    public function sync_database()
    {
        $this->require_login();
        $this->require_superadmin();

        if (strtoupper($this->input->method()) === 'POST') {
            $results = $this->ipak->sync_database();

            $this->render('admin/sync_database', [
                'page_title' => 'Sinkronisasi Database',
                'results' => $results,
            ]);
            return;
        }

        $this->render('admin/sync_database', [
            'page_title' => 'Sinkronisasi Database',
            'results' => null,
        ]);
    }

    private function require_superadmin()
    {
        if (!$this->is_superadmin()) {
            show_error('Aksi ini hanya dapat dilakukan oleh superadmin.', 403, 'Akses ditolak');
            exit;
        }
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

    private function render($view, array $data)
    {
        $data['admin_name'] = $this->session->userdata('ipak_admin_name');
        $data['admin_role'] = $this->session->userdata('ipak_admin_role') ?: 'admin';
        $data['content_view'] = $view;
        $this->load->view('admin/layout', $data);
    }
}
