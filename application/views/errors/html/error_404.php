<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$error_heading = isset($heading) ? $heading : 'Halaman tidak ditemukan';
$error_message = isset($message) ? $message : 'Halaman yang diminta tidak tersedia.';
$production_heading = 'Halaman tidak ditemukan';
$production_message = 'Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.';
require __DIR__ . '/error_layout.php';
