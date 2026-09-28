<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'dashboard';
$route['404_override'] = '';
$route['translate_uri_dashes'] = TRUE;

$route['login'] = 'auth/login';
$route['logout'] = 'auth/logout';

$route['admin'] = 'admin/users';

$route['inventory'] = 'inventory/ingredients';
$route['inventory/reports'] = 'inventory/reports/index/value';
$route['inventory/reports/(value|aging|slow|dead)'] = 'inventory/reports/index/$1';
