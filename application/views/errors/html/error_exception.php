<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$error_heading = isset($heading) ? $heading : 'Terjadi exception';
$error_message = isset($message) ? $message : 'Aplikasi mengalami kesalahan.';
if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production' && isset($exception)) {
    $error_message .= "\n" . $exception->getFile() . ':' . $exception->getLine();
}
require __DIR__ . '/error_layout.php';
