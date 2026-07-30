<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Survey_api_model extends CI_Model
{
    private $clientsTable = 'ipak_api_clients';
    private $logsTable = 'ipak_api_access_logs';

    public function resource_definitions()
    {
        return [
            'summary' => [
                'label' => 'Ringkasan nilai',
                'help' => 'Total respons, nilai rata-rata, nilai minimum, nilai maksimum, dan kategori per survei.',
            ],
            'chart' => [
                'label' => 'Data grafik',
                'help' => 'Seri grafik bulanan atau pengelompokan responden sesuai dimensi yang diizinkan.',
            ],
            'details' => [
                'label' => 'Detail respons',
                'help' => 'Daftar respons dengan kolom yang dipilih khusus untuk aplikasi peminta.',
            ],
            'questions' => [
                'label' => 'Struktur pertanyaan',
                'help' => 'Pertanyaan, kategori, pengukuran, bobot, dan pilihan jawaban pada survei.',
            ],
        ];
    }

    public function dimension_definitions()
    {
        return [
            'overall' => 'Bulanan / keseluruhan',
            'gender' => 'Jenis kelamin',
            'age' => 'Kelompok usia',
            'education' => 'Pendidikan',
            'job' => 'Pekerjaan',
            'service' => 'Sektor layanan',
            'questions' => 'Nilai per pertanyaan',
        ];
    }

    public function detail_field_definitions()
    {
        return [
            'response_id' => ['label' => 'ID respons', 'privacy' => false],
            'reference' => ['label' => 'Nomor resi / referensi', 'privacy' => false],
            'submission_group' => ['label' => 'Kode kelompok pengisian', 'privacy' => false],
            'date' => ['label' => 'Tanggal pengisian', 'privacy' => false],
            'survey' => ['label' => 'Identitas survei', 'privacy' => false],
            'score' => ['label' => 'Nilai dan kategori', 'privacy' => false],
            'name' => ['label' => 'Nama responden', 'privacy' => true],
            'email' => ['label' => 'Email responden', 'privacy' => true],
            'phone' => ['label' => 'Nomor telepon', 'privacy' => true],
            'nib' => ['label' => 'Nomor identitas / NIB', 'privacy' => true],
            'gender' => ['label' => 'Jenis kelamin', 'privacy' => false],
            'age' => ['label' => 'Usia', 'privacy' => false],
            'education' => ['label' => 'Pendidikan', 'privacy' => false],
            'job' => ['label' => 'Pekerjaan', 'privacy' => false],
            'sector' => ['label' => 'Sektor layanan', 'privacy' => false],
            'unit' => ['label' => 'Perangkat daerah', 'privacy' => false],
            'suggestion' => ['label' => 'Saran responden', 'privacy' => true],
            'custom_fields' => ['label' => 'Isian identitas fleksibel', 'privacy' => true],
            'answers' => ['label' => 'Jawaban per pertanyaan', 'privacy' => true],
        ];
    }

    public function get_clients()
    {
        $rows = $this->db
            ->select(
                'c.*,COUNT(l.id) AS request_count,' .
                'SUM(CASE WHEN l.requested_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS request_count_24h',
                false
            )
            ->from($this->clientsTable . ' c')
            ->join($this->logsTable . ' l', 'l.api_client_id = c.id', 'left')
            ->group_by('c.id')
            ->order_by('c.is_active', 'DESC')
            ->order_by('c.client_name', 'ASC')
            ->get()
            ->result_array();
        foreach ($rows as $index => $row) {
            $rows[$index] = $this->normalize_client($row);
            $rows[$index]['request_count'] = (int) $row['request_count'];
            $rows[$index]['request_count_24h'] = (int) $row['request_count_24h'];
        }
        return $rows;
    }

    public function get_client($id)
    {
        $row = $this->db
            ->where('id', (int) $id)
            ->limit(1)
            ->get($this->clientsTable)
            ->row_array();
        return $row ? $this->normalize_client($row) : false;
    }

    public function get_client_by_code($code)
    {
        $row = $this->db
            ->where('client_code', strtolower(trim((string) $code)))
            ->limit(1)
            ->get($this->clientsTable)
            ->row_array();
        return $row ? $this->normalize_client($row) : false;
    }

    public function client_code_exists($code, $excludeId = 0)
    {
        $this->db->where('client_code', strtolower(trim((string) $code)));
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results($this->clientsTable) > 0;
    }

    public function create_client(array $data)
    {
        $apiKey = $this->new_api_key();
        $data['api_key_prefix'] = substr($apiKey, 0, 17);
        $data['api_key_hash'] = hash('sha256', $apiKey);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->clientsTable, $this->database_client_data($data));
        $id = (int) $this->db->insert_id();
        if ($id < 1) {
            return false;
        }
        return ['id' => $id, 'api_key' => $apiKey];
    }

    public function update_client($id, array $data)
    {
        unset($data['api_key_prefix'], $data['api_key_hash'], $data['created_at'], $data['created_by']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('id', (int) $id)
            ->update($this->clientsTable, $this->database_client_data($data));
    }

    public function regenerate_key($id)
    {
        $apiKey = $this->new_api_key();
        $updated = $this->db
            ->where('id', (int) $id)
            ->update($this->clientsTable, [
                'api_key_prefix' => substr($apiKey, 0, 17),
                'api_key_hash' => hash('sha256', $apiKey),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return $updated ? $apiKey : false;
    }

    public function set_active($id, $isActive)
    {
        return $this->db
            ->where('id', (int) $id)
            ->update($this->clientsTable, [
                'is_active' => $isActive ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function authenticate($clientCode, $apiKey, $requestIp)
    {
        $client = $this->get_client_by_code($clientCode);
        if (!$client || trim((string) $apiKey) === '') {
            return ['ok' => false, 'status' => 401, 'error' => 'Kunci API tidak valid.'];
        }
        if (!hash_equals((string) $client['api_key_hash'], hash('sha256', trim((string) $apiKey)))) {
            return ['ok' => false, 'status' => 401, 'error' => 'Kunci API tidak valid.'];
        }
        if (!$client['is_active']) {
            return ['ok' => false, 'status' => 403, 'error' => 'Akses API sedang dinonaktifkan.', 'client' => $client];
        }
        if ($client['expires_at'] !== '' && strtotime($client['expires_at']) < time()) {
            return ['ok' => false, 'status' => 403, 'error' => 'Masa berlaku akses API telah berakhir.', 'client' => $client];
        }
        if (!$this->ip_is_allowed($requestIp, $client['allowed_ip_addresses'])) {
            return ['ok' => false, 'status' => 403, 'error' => 'Alamat IP tidak diizinkan.', 'client' => $client];
        }

        $used = $this->request_count_since((int) $client['id'], date('Y-m-d H:i:s', time() - 60));
        $limit = max(1, (int) $client['rate_limit_per_minute']);
        if ($used >= $limit) {
            return [
                'ok' => false,
                'status' => 429,
                'error' => 'Batas permintaan per menit telah tercapai.',
                'client' => $client,
                'rate_limit' => $limit,
                'rate_remaining' => 0,
            ];
        }

        return [
            'ok' => true,
            'client' => $client,
            'rate_limit' => $limit,
            'rate_remaining' => max(0, $limit - $used - 1),
        ];
    }

    public function log_request(array $data)
    {
        $row = [
            'api_client_id' => (int) $data['api_client_id'],
            'resource_name' => substr((string) $data['resource_name'], 0, 30),
            'survey_id' => !empty($data['survey_id']) ? (int) $data['survey_id'] : null,
            'request_method' => substr((string) $data['request_method'], 0, 10),
            'request_ip' => substr((string) $data['request_ip'], 0, 45),
            'query_string' => isset($data['query_string']) ? substr((string) $data['query_string'], 0, 4000) : '',
            'status_code' => (int) $data['status_code'],
            'response_count' => isset($data['response_count']) ? max(0, (int) $data['response_count']) : 0,
            'duration_ms' => isset($data['duration_ms']) ? max(0, (int) $data['duration_ms']) : 0,
            'requested_at' => date('Y-m-d H:i:s'),
        ];
        $saved = $this->db->insert($this->logsTable, $row);
        if ($saved && (int) $data['status_code'] < 500) {
            $this->db
                ->where('id', (int) $data['api_client_id'])
                ->update($this->clientsTable, ['last_used_at' => date('Y-m-d H:i:s')]);
        }
        return $saved;
    }

    public function recent_logs($clientId = 0, $limit = 25)
    {
        $this->db
            ->select('l.*,c.client_name,c.client_code')
            ->from($this->logsTable . ' l')
            ->join($this->clientsTable . ' c', 'c.id = l.api_client_id', 'inner');
        if ((int) $clientId > 0) {
            $this->db->where('l.api_client_id', (int) $clientId);
        }
        return $this->db
            ->order_by('l.id', 'DESC')
            ->limit(max(1, min(100, (int) $limit)))
            ->get()
            ->result_array();
    }

    private function database_client_data(array $data)
    {
        $listFields = [
            'allowed_survey_ids',
            'allowed_resources',
            'allowed_dimensions',
            'allowed_detail_fields',
        ];
        foreach ($listFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode(array_values($data[$field]), JSON_UNESCAPED_SLASHES);
            }
        }
        $allowed = [
            'client_name',
            'client_code',
            'description',
            'api_key_prefix',
            'api_key_hash',
            'survey_scope',
            'allowed_survey_ids',
            'allowed_resources',
            'allowed_dimensions',
            'allowed_detail_fields',
            'max_page_size',
            'rate_limit_per_minute',
            'allowed_ip_addresses',
            'allowed_origin',
            'expires_at',
            'is_active',
            'created_by',
            'created_at',
            'updated_at',
        ];
        $result = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $result[$field] = $data[$field];
            }
        }
        return $result;
    }

    private function normalize_client(array $row)
    {
        foreach (['allowed_survey_ids', 'allowed_resources', 'allowed_dimensions', 'allowed_detail_fields'] as $field) {
            $decoded = json_decode(isset($row[$field]) ? (string) $row[$field] : '[]', true);
            $row[$field] = is_array($decoded) ? array_values($decoded) : [];
        }
        $row['id'] = (int) $row['id'];
        $row['is_active'] = (int) $row['is_active'] === 1;
        $row['max_page_size'] = max(1, (int) $row['max_page_size']);
        $row['rate_limit_per_minute'] = max(1, (int) $row['rate_limit_per_minute']);
        $row['expires_at'] = !empty($row['expires_at']) ? $row['expires_at'] : '';
        $row['allowed_ip_addresses'] = isset($row['allowed_ip_addresses'])
            ? trim((string) $row['allowed_ip_addresses'])
            : '';
        $row['allowed_origin'] = isset($row['allowed_origin']) ? trim((string) $row['allowed_origin']) : '';
        return $row;
    }

    private function request_count_since($clientId, $since)
    {
        return (int) $this->db
            ->where('api_client_id', (int) $clientId)
            ->where('requested_at >=', $since)
            ->count_all_results($this->logsTable);
    }

    private function ip_is_allowed($requestIp, $allowedIpAddresses)
    {
        $raw = trim((string) $allowedIpAddresses);
        if ($raw === '') {
            return true;
        }
        $allowed = preg_split('/[\s,;]+/', $raw);
        foreach ($allowed as $value) {
            if (trim((string) $value) !== '' && hash_equals(trim((string) $value), trim((string) $requestIp))) {
                return true;
            }
        }
        return false;
    }

    private function new_api_key()
    {
        if (function_exists('random_bytes')) {
            $bytes = random_bytes(24);
        } else {
            $bytes = openssl_random_pseudo_bytes(24);
        }
        return 'skm_live_' . bin2hex($bytes);
    }
}
