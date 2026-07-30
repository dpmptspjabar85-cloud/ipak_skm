<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'survey/dashboard';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['dashboard'] = 'survey/dashboard';
$route['surveys'] = 'survey/catalog';
$route['survei/pilih'] = 'survey/catalog';
$route['survey'] = 'survey/index';
$route['survei'] = 'survey/index';
$route['skm/Survei.php'] = 'survey/index';
$route['survey/submit']['post'] = 'survey/submit';
$route['survey/success/(:any)'] = 'survey/success/$1';
$route['api/v1/survey-data/(:any)'] = 'api/survey_data/$1';

$route['admin'] = 'admin/index';
$route['admin/login'] = 'admin/login';
$route['admin/logout'] = 'admin/logout';
$route['admin/dashboard'] = 'admin/dashboard';
$route['admin/responses'] = 'admin/responses';
$route['admin/responses/(:any)'] = 'admin/detail/$1';
$route['admin/export'] = 'admin/export';
$route['admin/questions'] = 'admin/questions';
$route['admin/questions/create'] = 'admin/create_question';
$route['admin/surveys'] = 'admin/surveys';
$route['admin/forms'] = 'admin/forms';
$route['admin/forms/create'] = 'admin/create_form';
$route['admin/api-builder'] = 'admin/api_builder';
$route['admin/api-builder/create'] = 'admin/api_client_form';
$route['admin/api-builder/edit/(:num)'] = 'admin/api_client_form/$1';
$route['admin/api-builder/toggle/(:num)']['post'] = 'admin/api_client_toggle/$1';
$route['admin/api-builder/regenerate/(:num)']['post'] = 'admin/api_client_regenerate/$1';
$route['admin/api-builder/documentation/(:num)'] = 'admin/api_client_documentation/$1';
$route['admin/units'] = 'admin/units';
$route['admin/help'] = 'admin/help';
