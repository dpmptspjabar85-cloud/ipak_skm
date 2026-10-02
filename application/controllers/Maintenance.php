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

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ipaksurvey_model', 'ipak');
        $this->config->load('ipak');
        $this->load->helper('url');
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
                $results = $this->ipak->sync_database();
                $ran = true;
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