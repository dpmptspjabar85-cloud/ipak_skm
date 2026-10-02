<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Konfigurasi database untuk ENVIRONMENT = development.
|
| CodeIgniter menggabungkan berkas ini di atas application/config/database.php
| hanya ketika ENVIRONMENT di index.php bernilai 'development'. Nilai di sini
| menggantikan nilai yang sama, jadi tidak perlu menulis ulang seluruh array.
|
| Kredensial lokal development. Jangan pernah berisi kredensial server.
*/
$active_group = 'default';
$query_builder = true;

$db['default'] = [
    'dsn' => '',
    'hostname' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'database' => 'backoffice',
    'port' => '3306',
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => false,
    'db_debug' => true,
    'cache_on' => false,
    'cachedir' => '',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'swap_pre' => '',
    'encrypt' => false,
    'compress' => false,
    'stricton' => false,
    'failover' => [],
    'save_queries' => true,
];
