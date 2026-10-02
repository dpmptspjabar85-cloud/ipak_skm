<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Endpoint maintenance sinkronisasi database.
 *
 * Dipanggil lewat sync-database.php di root, bukan lewat routing publik.
 * Tidak memakai login backoffice dan tidak meminta ID maupun password.
 *
 * Pembatasnya adalah asal jaringan: sinkronisasi hanya berjalan bila request
 * datang dari jaringan internal. Alamat IP privat (10/8, 172.16/12,
 * 192.168/16, 127/8, dan IPv6 loopback) tidak dapat diusuulkan dari internet
 * publik, jadi ini batas yang nyata, bukan sekadar menyamarkan URL.
 *
 * CATATAN KEAMANAN
 * Fungsi sync_database() menjalankan DDL terhadap database: CREATE TABLE,
 * ALTER TABLE, dan ADD COLUMN. Endpoint ini karena itu TIDAK boleh dipasang
 * di server yang bisa dijangkau publik tanpa pembatas jaringan tambahan.
 *
 * Yang dipanggil tetap Ipaksurvey_model::sync_database() supaya tidak ada
 * logika sinkronisasi kedua di aplikasi.
 *
 * @see application/config/ipak.php
 * @see Ipaksurvey_model::sync_database()
 */
class Maintenance extends CI_Controller
{
    /** @var array Pesan kesalahan untuk ditampilkan di halaman. */
    private $errors = array();

    /** @var array Warning/notice PHP yang tertangkap selama sinkronisasi. */
    private $captured = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ipaksurvey_model', 'ipak');
        $this->config->load('ipak');
        $this->load->helper('url');

        // Di production display_errors dimatikan, sehingga fatal error hanya
        // menghasilkan halaman kosong tanpa keterangan apa pun. Penangkap ini
        // membuat error tetap terlihat oleh whoever yang sedang mengirim form.
        register_shutdown_function(array($this, 'report_fatal'));
    }

    /**
     * Satu-satunya method yang dipanggil. Menampilkan tombol pada GET dan
     * menjalankan sinkronisasi hanya pada POST dari jaringan internal.
     *
     * @return void
     */
    public function sync_database()
    {
        $allowed = $this->request_allowed();
        $ip = $this->client_ip();

        $results = null;
        $ran = false;

        // method() mengembalikan huruf kecil pada versi CodeIgniter ini, jadi
        // dibandingkan setelah diubah ke huruf besar.
        if (strtoupper($this->input->method()) === 'POST') {
            if (!$allowed) {
                $this->errors[] = 'Sinkronisasi hanya dapat dijalankan dari '
                    . 'jaringan internal.';
            } elseif (!$this->csrf_token_valid()) {
                $this->errors[] = 'Token keamanan tidak valid atau kedaluwarsa. '
                    . 'Muat ulang halaman lalu coba lagi.';
            } else {
                // Warning dan notice dikumpulkan supaya bisa ditampilkan,
                // bukan hilang bersama display_errors yang dimatikan.
                set_error_handler(array($this, 'collect_php_error'));
                try {
                    $results = $this->ipak->sync_database();
                    $ran = true;
                } catch (Exception $e) {
                    $this->errors[] = 'Sinkronisasi terhenti: ' . $e->getMessage();
                }
                restore_error_handler();
            }
        }

        $this->load->view('maintenance/sync_database', [
            'ran' => $ran,
            'results' => $results,
            'errors' => $this->errors,
            'allowed' => $allowed,
            'client_ip' => $ip,
            'csrf_token' => $this->csrf_token(),
            'db_name' => (string) $this->db->database,
            'debug' => $this->diagnostics($allowed),
            // Token milik CodeIgniter sendiri. Pengaman CSRF bawaan framework
            // berlaku untuk semua POST, jadi form harus ikut memikulnya.
            'ci_csrf_name' => $this->security->get_csrf_token_name(),
            'ci_csrf_hash' => $this->security->get_csrf_hash(),
        ]);
    }

    /**
     * Apakah request ini boleh menjalankan sinkronisasi.
     *
     * Dua syarat, keduanya harus lolos:
     *   1..ipak_maintenance_restrict_to_internal tidak dimatikan, maka IP
     *      pengirim harus berada di jaringan internal; dan
     *   2. IP tersebut tidak boleh terblokir oleh ipak_maintenance_blocked_ips.
     *
     * @return bool
     */
    private function request_allowed()
    {
        $ip = $this->client_ip();

        if (trim((string) $this->config->item('ipak_maintenance_blocked_ips')) !== '') {
            foreach (explode(',', (string) $this->config->item('ipak_maintenance_blocked_ips')) as $candidate) {
                if (trim($candidate) === $ip && $ip !== '') {
                    return false;
                }
            }
        }

        $restrict = $this->config->item('ipak_maintenance_restrict_to_internal');
        $restrict = ($restrict === null || $restrict === '') ? true : (bool) $restrict;

        return $restrict ? $this->is_internal_ip($ip) : true;
    }

    /**
     * Apakah alamat IP berada di jaringan internal.
     *
     * Loopback, 10/8, 172.16/12, 192.168/16, dan 169.254/16 link-local
     * dianggap internal, ditambah IPv6 loopback. Alamat IPv4 yang dibungkus
     * IPv6 seperti ::ffff:192.168.1.10 dipisahkan lebih dulu.
     *
     * @param string $ip
     * @return bool
     */
    private function is_internal_ip($ip)
    {
        if ($ip === '') {
            return false;
        }

        // IPv4 dalam bentuk IPv6, contoh ::ffff:192.168.1.10
        if (stripos($ip, '::ffff:') === 0) {
            $ip = substr($ip, 7);
        }

        // Perbandingan dilakukan pada byte hasil inet_pton, bukan lewat
        // ip2long(). Pada PHP 32-bit ip2long() mengembalikan nilai negatif
        // untuk alamat mulai 128.0.0.0, sehingga pergeseran bitnya salah dan
        // 192.168/16 terbaca sebagai bukan jaringan internal.
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16) {
            // IPv6: hanya loopback ::1 yang diizinkan
            return $packed === str_repeat("\0", 15) . "\1";
        }
        if (strlen($packed) !== 4) {
            return false;
        }

        $a = ord($packed[0]);
        $b = ord($packed[1]);

        if ($a === 127) {
            return true; // 127.0.0.0/8 loopback
        }
        if ($a === 10) {
            return true; // 10.0.0.0/8
        }
        if ($a === 172 && $b >= 16 && $b <= 31) {
            return true; // 172.16.0.0/12
        }
        if ($a === 192 && $b === 168) {
            return true; // 192.168.0.0/16
        }
        if ($a === 169 && $b === 254) {
            return true; // 169.254.0.0/16 link-local
        }

        return false;
    }

    /**
     * Menangkap warning/notice PHP selama sinkronisasi berjalan.
     *
     * Mengembalikan true supaya PHP tidak menanganinya sendiri; dengan begitu
     * pesan tidak hilang begitu saja ketika display_errors dimatikan.
     *
     * @return bool
     */
    public function collect_php_error($no, $str, $file, $line)
    {
        $names = array(
            E_ERROR => 'Error', E_WARNING => 'Warning', E_PARSE => 'Parse error',
            E_NOTICE => 'Notice', E_CORE_ERROR => 'Core error',
            E_CORE_WARNING => 'Core warning', E_COMPILE_ERROR => 'Compile error',
            E_COMPILE_WARNING => 'Compile warning', E_USER_ERROR => 'User error',
            E_USER_WARNING => 'User warning', E_USER_NOTICE => 'User notice',
        );
        $label = isset($names[$no]) ? $names[$no] : 'Galat';
        $short = str_replace(FCPATH, '', $file);
        $this->captured[] = $label . ': ' . $str . ' (' . $short . ' baris ' . $line . ')';
        return true;
    }

    /**
     * Menampilkan fatal error yang membuat halaman kosong.
     *
     * Dipanggil lewat register_shutdown_function, jadi tetap berjalan meski
     * PHP berhenti di tengah eksekusi.
     *
     * @return void
     */
    public function report_fatal()
    {
        $last = error_get_last();
        if ($last === null) {
            return;
        }
        $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        if (!in_array($last['type'], $fatalTypes, true)) {
            return;
        }

        $short = str_replace(FCPATH, '', $last['file']);
        $stamp = date('Y-m-d H:i:s');
        $message = '[' . $stamp . '] ' . $last['message']
            . ' di ' . $short . ' baris ' . (int) $last['line'] . "\n";

        // Ditulis ke berkas lebih dulu. Kalau proses PHP berhenti sebelum
        // sempat mengirim HTML, jejaknya tetap bisa dibaca dari server.
        @file_put_contents(APPPATH . 'logs/maintenance_fatal.log', $message, FILE_APPEND);

        echo '<div style="margin:20px auto;max-width:900px;padding:16px;'
            . 'border:2px solid #b91c1c;background:#fef2f2;color:#7f1d1d;'
            . 'font-family:Consolas,monospace;font-size:14px;line-height:1.6">'
            . '<strong>FATAL ERROR - sinkronisasi berhenti</strong><br>'
            . htmlspecialchars($last['message'], ENT_QUOTES, 'UTF-8') . '<br>'
            . 'di ' . htmlspecialchars($short, ENT_QUOTES, 'UTF-8')
            . ' baris ' . (int) $last['line'] . '<br>'
            . 'Sinkronisasi tidak selesai. Berkas catatan: application/logs/maintenance_fatal.log'
            . '</div>';
    }

    /**
     * Kumpulan informasi untuk membantu mencari masalah di server.
     *
     * Tidak memuat password maupun token CSRF.
     *
     * @param bool $allowed
     * @return array
     */
    private function diagnostics($allowed)
    {
        $ip = $this->client_ip();
        $sv = array(
            'REQUEST_METHOD' => '-',
            'REQUEST_URI' => '-',
            'SCRIPT_NAME' => '-',
            'HTTP_X_FORWARDED_FOR' => '(tidak ada)',
            'HTTP_X_FORWARDED_PROTO' => '(tidak ada)',
            'HTTPS' => '(tidak ada)',
            'SERVER_PORT' => '-',
            'DOCUMENT_ROOT' => '-',
        );
        foreach ($sv as $key => $default) {
            $sv[$key] = isset($_SERVER[$key]) && $_SERVER[$key] !== ''
                ? $_SERVER[$key] : $default;
        }

        $savePath = (string) $this->config->item('sess_save_path');
        $writable = '-';
        if ($savePath !== '' && is_dir($savePath)) {
            $writable = is_writable($savePath) ? 'YA' : 'TIDAK - session tidak akan tersimpan';
        } elseif ($savePath !== '') {
            $writable = 'TIDAK - folder tidak ada';
        }

        $dbCheck = 'tidak';
        $dbDetail = '';
        $try = $this->db->query('SELECT 1');
        if ($try !== false) {
            $dbCheck = 'ya';
        } else {
            $dbDetail = 'query SELECT 1 gagal';
        }

        $d = array();
        $d['ENVIRONMENT'] = ENVIRONMENT;
        $d['PHP versi'] = PHP_VERSION . ' (' . PHP_INT_SIZE . ' bit)';
        $d['display_errors'] = ini_get('display_errors') === '1' ? 'ON' : 'OFF';
        $d['error_log'] = ini_get('error_log') !== '' ? ini_get('error_log') : '(default PHP)';
        $d['base_url'] = (string) $this->config->item('base_url');
        $d['REMOTE_ADDR'] = $ip !== '' ? $ip : '(kosong)';
        $d['REMOTE_ADDR internal?'] = $this->is_internal_ip($ip) ? 'YA' : 'TIDAK';
        $d['endpoint diizinkan?'] = $allowed ? 'YA' : 'TIDAK';
        $d['restrict_to_internal'] = var_export(
            (bool) $this->config->item('ipak_maintenance_restrict_to_internal'), true
        );
        $d['REQUEST_METHOD'] = $sv['REQUEST_METHOD'];
        $d['REQUEST_URI'] = $sv['REQUEST_URI'];
        $d['SCRIPT_NAME'] = $sv['SCRIPT_NAME'];
        $d['X-Forwarded-For'] = $sv['HTTP_X_FORWARDED_FOR'];
        $d['X-Forwarded-Proto'] = $sv['HTTP_X_FORWARDED_PROTO'];
        $d['HTTPS'] = $sv['HTTPS'];
        $d['SERVER_PORT'] = $sv['SERVER_PORT'];
        $d['DOCUMENT_ROOT'] = $sv['DOCUMENT_ROOT'];
        $d['session_id'] = session_id() !== '' ? session_id() : '(tidak ada - session gagal start)';
        $d['session save_path'] = $savePath !== '' ? $savePath : '(kosong)';
        $d['save_path writable?'] = $writable;
        $d['maintenance_csrf tersimpan?'] = $this->session->userdata('maintenance_csrf_token') !== null
            ? 'YA' : 'TIDAK';
        $d['maintenance_csrf cocok?'] = $sv['REQUEST_METHOD'] === 'POST'
            ? ($this->csrf_token_valid() ? 'YA' : 'TIDAK - token tidak cocok')
            : 'n/a, baru diperiksa pada POST';
        $d['CI CSRF name'] = $this->security->get_csrf_token_name();
        $d['db host'] = (string) $this->db->hostname;
        $d['db name'] = (string) $this->db->database;
        $d['db connect'] = $dbCheck . ($dbDetail !== '' ? ' (' . $dbDetail . ')' : '');
        $d['log CodeIgniter terakhir'] = $this->last_log_lines();
        $d['galat PHP saat sinkronisasi'] = !empty($this->captured)
            ? implode(' ;; ', $this->captured) : '(tidak ada)';

        return $d;
    }

    /**
     * Beberapa baris terakhir dari log CodeIgniter.
     *
     * @return string
     */
    private function last_log_lines()
    {
        $dir = APPPATH . 'logs';
        if (!is_dir($dir)) {
            return '(folder log tidak ada)';
        }
        $files = glob($dir . '/log-*.php');
        if (empty($files)) {
            return '(belum ada file log)';
        }
        $newest = max($files);
        $lines = @file($newest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || empty($lines)) {
            return '(log kosong: ' . basename($newest) . ')';
        }
        $tail = array_slice($lines, -5);
        $clean = array();
        foreach ($tail as $line) {
            // Copot tag HTML bawaan CI agar mudah dibaca.
            $line = preg_replace('#</?font[^>]*>#i', '', $line);
            $clean[] = html_entity_decode(strip_tags($line), ENT_QUOTES, 'UTF-8');
        }
        return 'log-' . date('Y-m-d', filemtime($newest)) . ' :: ' . implode(' | ', $clean);
    }

    /**
     * @return string
     */
    private function client_ip()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
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
            // endpoint harus tetap bisa membuka halamannya.
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
}