<?php
defined('BASEPATH') OR exit('No direct script access allowed');
if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    return;
}
$error_heading = isset($severity) ? $severity : 'PHP Error';
$error_message = (isset($message) ? $message : 'Unknown error') .
    "\n" . (isset($filepath) ? $filepath : '') .
    ':' . (isset($line) ? $line : '');
require __DIR__ . '/error_layout.php';
