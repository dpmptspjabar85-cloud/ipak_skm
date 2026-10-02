<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Endpoint maintenance sinkronisasi database.
 *
 * Dipanggil lewat sync-database.php di root, bukan lewat routing publik.
 * Autentikasi, POST, dan CSRF ditangani di sini secara mandiri; login
 * backoffice tidak diubah dan tidak dilewati.
 *
 * Yang dipanggil tetap Ipaksurvey_model::sync_database() supaya tidak ada
 * logika sinkronisasi kedua di aplikasi.
 */
class Maintenance extends CI_Controller
{
    /** @var array Pesan kesalahan untuk ditampilkan di halaman. */
    private $errors = array();

    /** @var string Nama berkas penanda jumlah percobaan gagal. */
    private $attemptFile;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ipaksurvey_model', 'ipak');
        $this->config->load('ipak');
        $this->load->helper('url');
        $this->attemptFile = $this->attempt_file_path();
    }

    /**
     * Satu-satunya method yang dipanggil. Menyampilkan form pada GET dan
     * menjalankan sinkronisasi hanya pada POST yang sah.
     *
     * @return void
     */
    public function sync_database()
    {
        $configured = $this->credentials_configured();
        $ipAllowed = $this->ip_allowed();

        if (!$configured) {
            $this->errors[] = 'Kredensial maintenance belum diisi di '
                . 'application/config/' . ENVIRONMENT . '/ipak.php '
                . '(ipak_maintenance_id dan ipak_maintenance_password). '
                . 'Endpoint dinonaktifkan sampai keduanya tersedia.';
        }

        if (!$ipAllowed) {
            $this->errors[] = 'Alamat IP ini tidak ada di daftar '
                . 'ipak_maintenance_allowed_ips, jadi endpoint dinonaktifkan.';
        }

        $results = null;
        $ran = false;

        // method() mengembalikan huruf kecil pada versi CodeIgniter ini, jadi
        // dibandingkan setelah diubah ke huruf besar.
        if (strtoupper($this->input->method()) === 'POST' && $configured && $ipAllowed) {
            $lock = $this->lockout_remaining();
            if ($lock > 0) {
                $this->errors[] = 'Terlalu banyak percobaan gagal. Coba lagi dalam '
                    . ceil($lock / 60) . ' menit.';
            } elseif (!$this->csrf_token_valid()) {
                $this->errors[] = 'Token keamanan tidak valid atau kedaluwarsa. '
                    . 'Muat ulang halaman lalu coba lagi.';
            } elseif ($this->credentials_valid()) {
                $this->clear_attempts();
                $results = $this->ipak->sync_database();
                $ran = true;
            } else {
                $this->register_failure();
                $this->errors[] = 'ID atau Password tidak sesuai.';
            }
        }

        $this->load->view('maintenance/sync_database', [
            'ran' => $ran,
            'results' => $results,
            'errors' => $this->errors,
            'configured' => $configured,
            'ip_allowed' => $ipAllowed,
            'csrf_token' => $this->csrf_token(),
            'db_name' => (string) $this->db->database,
            // Token milik CodeIgniter sendiri. Pengaman CSRF bawaan framework
            // berlaku untuk semua POST, jadi form harus ikut memikulnya.
            'ci_csrf_name' => $this->security->get_csrf_token_name(),
            'ci_csrf_hash' => $this->security->get_csrf_hash(),
        ]);
    }

    /**
     * Apakah kredensial maintenance sudah diisi di .env.
     *
     * @return bool
     */
    private function credentials_configured()
    {
        $id = (string) $this->config->item('ipak_maintenance_id');
        $password = (string) $this->config->item('ipak_maintenance_password');
        return trim($id) !== '' && $password !== '';
    }

    /**
     * Pembatasan IP bila ipak_maintenance_allowed_ips diisi.
     *
     * @return bool
     */
    private function ip_allowed()
    {
        $allowed = trim((string) $this->config->item('ipak_maintenance_allowed_ips'));
        if ($allowed === '') {
            return true;
        }
        $ip = $this->client_ip();
        foreach (explode(',', $allowed) as $candidate) {
            if (trim($candidate) === $ip) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validasi ID dan Password dengan perbandingan waktu-tetap.
     *
     * @return bool
     */
    private function credentials_valid()
    {
        $expectedId = (string) $this->config->item('ipak_maintenance_id');
        $expectedPassword = (string) $this->config->item('ipak_maintenance_password');

        $givenId = (string) $this->input->post('maintenance_id', true);
        $givenPassword = (string) $this->input->post('maintenance_password', false);

        // Dua perbandingan selalu dijalankan supaya waktu prosesnya tidak
        // membocorkan bagian mana yang salah.
        $idOk = hash_equals($expectedId, $givenId);
        $passwordOk = hash_equals($expectedPassword, $givenPassword);

        return $idOk && $passwordOk;
    }

    /**
     * Token CSRF untuk form.
     *
     * @return string
     */
    private function csrf_token()
    {
        $token = $this->session->userdata('maintenance_csrf_token');
        if (!is_string($token) || strlen($token) !== 40) {
            // openssl bisa tidak tersedia pada konfigurasi PHP minimal, dan
            // endpoint harus tetap bisa membuka formulirnya.
            $token = function_exists('openssl_random_pseudo_bytes')
                ? bin2hex(openssl_random_pseudo_bytes(20))
                : sha1(uniqid((string) mt_rand(), true));
            $this->session->set_userdata('maintenance_csrf_token', $token);
        }
        return $token;
    }

    /**
     * @return bool
     */
    private function csrf_token_valid()
    {
        $expected = (string) $this->session->userdata('maintenance_csrf_token');
        $given = (string) $this->input->post('maintenance_csrf', true);
        if ($expected === '' || $given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }

    /**
     * Sisa waktu kunci dalam detik, 0 bila tidak terkunci.
     *
     * @return int
     */
    private function lockout_remaining()
    {
        $state = $this->read_attempts();
        if ($state['attempts'] < (int) $this->config->item('ipak_maintenance_max_attempts')) {
            return 0;
        }
        $elapsed = time() - $state['last'];
        $limit = (int) $this->config->item('ipak_maintenance_lockout_minutes') * 60;
        if ($elapsed >= $limit) {
            $this->clear_attempts();
            return 0;
        }
        return $limit - $elapsed;
    }

    /**
     * Mencatat satu percobaan gagal.
     *
     * @return void
     */
    private function register_failure()
    {
        $state = $this->read_attempts();
        $state['attempts']++;
        $state['last'] = time();
        @file_put_contents($this->attemptFile, json_encode($state), LOCK_EX);
    }

    /**
     * @return void
     */
    private function clear_attempts()
    {
        if (is_file($this->attemptFile)) {
            @unlink($this->attemptFile);
        }
    }

    /**
     * @return array
     */
    private function read_attempts()
    {
        $default = ['attempts' => 0, 'last' => 0];
        if (!is_file($this->attemptFile)) {
            return $default;
        }
        $raw = @file_get_contents($this->attemptFile);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || !isset($decoded['attempts'])) {
            return $default;
        }
        return [
            'attempts' => (int) $decoded['attempts'],
            'last' => isset($decoded['last']) ? (int) $decoded['last'] : 0,
        ];
    }

    /**
     * Lokasi berkas penanda percobaan, di luar document root.
     *
     * Dibatasi per IP. Bila memakai satu berkas bersama, siapa pun bisa mengunci
     * endpoint untuk semua orang lain hanya dengan mengirim lima permintaan
     * buruk, dan satu salah ketik admin pun terkunci untuk Pengunjungnya.
     *
     * @return string
     */
    private function attempt_file_path()
    {
        // Hash dipakai supaya nilai IP tidak pernah menjadi bagian dari path.
        $key = substr(sha1('ipak-maintenance|' . $this->client_ip()), 0, 16);
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ipak_maintenance_lock_' . $key . '.json';
    }

    /**
     * @return string
     */
    private function client_ip()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
    }
}
