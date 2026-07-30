<?php
defined('BASEPATH') OR exit('No direct script access allowed');
echo (isset($severity) ? $severity : 'PHP Error') . ': ';
echo (isset($message) ? $message : 'Unknown error');
echo ' in ' . (isset($filepath) ? $filepath : 'unknown file');
echo ' on line ' . (isset($line) ? $line : '0') . PHP_EOL;
