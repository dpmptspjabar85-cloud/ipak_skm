<?php
defined('BASEPATH') OR exit('No direct script access allowed');
echo (isset($heading) ? $heading : 'Exception') . PHP_EOL;
echo (isset($message) ? strip_tags((string) $message) : '') . PHP_EOL;
if (isset($exception)) {
    echo $exception->getFile() . ':' . $exception->getLine() . PHP_EOL;
    echo $exception->getTraceAsString() . PHP_EOL;
}
