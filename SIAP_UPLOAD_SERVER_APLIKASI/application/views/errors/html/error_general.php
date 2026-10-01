<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| 403 means the request was rejected before the controller could run. On the
| public survey this is almost always a form that was left open too long, so the
| token in the page no longer matches the browser cookie. Showing CodeIgniter's
| raw "The action you have requested is not allowed." to a member of the public
| reads like the whole system is broken, so the page is rewritten here with a
| link back to the same form.
*/

$isForbidden = isset($status_code) && (int) $status_code === 403;

if ($isForbidden) {
    /*
     * show_error() can fire while CI_Input is still being constructed, which is
     * before the url helper is autoloaded, so site_url() is not available here.
     * The base URL is read straight from the config instead.
     */
    $baseUrl = function_exists('config_item') ? (string) config_item('base_url') : '';
    if ($baseUrl === '' || substr($baseUrl, -1) !== '/') {
        $baseUrl .= '/';
    }

    $formCode = isset($_POST['form_code']) ? preg_replace('/[^A-Z0-9_-]/', '', strtoupper((string) $_POST['form_code'])) : '';
    $resi = isset($_POST['resi']) ? preg_replace('/[^A-Za-z0-9._-]/', '', (string) $_POST['resi']) : '';

    $retryUrl = $baseUrl . 'survey';
    if ($formCode !== '') {
        $retryUrl .= '?form=' . rawurlencode($formCode);
        if ($resi !== '') {
            $retryUrl .= '&resi=' . rawurlencode($resi);
        }
    }
    $surveyHome = $baseUrl . 'surveys';

    $error_heading = 'Sesi pengisian sudah berakhir';
    $error_message = 'Formulir ini sudah terlalu lama dibuka sehingga sesi pengisian tidak lagi berlaku. '
        . 'Silakan buka kembali formulir dan isi ulang jawaban Anda. '
        . 'Jawaban yang sudah terkirim sebelumnya tidak tersimpan.';
    $production_heading = $error_heading;
    $production_message = $error_message;

    require __DIR__ . '/error_layout.php';
    ?>
    <p style="margin:18px 0 0">
      <a href="<?= htmlspecialchars($retryUrl, ENT_QUOTES, 'UTF-8') ?>"
         style="display:inline-block;padding:10px 18px;border-radius:8px;background:#3049d8;color:#fff;font-size:13px;text-decoration:none">
        Buka formulir lagi
      </a>
      <a href="<?= htmlspecialchars($surveyHome, ENT_QUOTES, 'UTF-8') ?>"
         style="margin-left:10px;font-size:13px;color:#626c7e">Atau pilih survei lain</a>
    </p>
    <?php
    return;
}

$error_heading = isset($heading) ? $heading : 'Terjadi kesalahan';
$error_message = isset($message) ? $message : 'Permintaan belum dapat diproses.';
require __DIR__ . '/error_layout.php';