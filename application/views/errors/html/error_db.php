<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$error_heading = isset($heading) ? $heading : 'Kesalahan basis data';
$error_message = isset($message) ? $message : 'Basis data belum dapat diakses.';
require __DIR__ . '/error_layout.php';
