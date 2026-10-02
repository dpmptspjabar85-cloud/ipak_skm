<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Kredensial endpoint maintenance untuk ENVIRONMENT = development.
|
| Dipakai hanya oleh mesin lokal saat endpoint perlu diuji. Nilainya boleh
| apa saja karena tidak pernah dipakai di server.
*/

$config['ipak_maintenance_id'] = 'dpmptsp_jabar';
$config['ipak_maintenance_password'] = 'Lian123!@#';
$config['ipak_maintenance_allowed_ips'] = '';