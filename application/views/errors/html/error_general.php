<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$error_heading = isset($heading) ? $heading : 'Terjadi kesalahan';
$error_message = isset($message) ? $message : 'Permintaan belum dapat diproses.';
require __DIR__ . '/error_layout.php';
