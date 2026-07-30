<?php
defined('BASEPATH') OR exit('No direct script access allowed');
echo (isset($heading) ? $heading : 'Error') . PHP_EOL;
echo (isset($message) ? strip_tags((string) $message) : '') . PHP_EOL;
