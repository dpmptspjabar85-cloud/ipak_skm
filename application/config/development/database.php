<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = true;

$db['default'] = [
    'dsn' => '',
    'hostname' => getenv('IPAK_DB_HOST') !== false ? getenv('IPAK_DB_HOST') : '127.0.0.1',
    'username' => getenv('IPAK_DB_USER') !== false ? getenv('IPAK_DB_USER') : 'root',
    'password' => getenv('IPAK_DB_PASSWORD') !== false ? getenv('IPAK_DB_PASSWORD') : '',
    'database' => getenv('IPAK_DB_NAME') !== false ? getenv('IPAK_DB_NAME') : 'backoffice',
    'port' => getenv('IPAK_DB_PORT') !== false ? getenv('IPAK_DB_PORT') : '3306',
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
