<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Kredensial database dibaca dari .env (lihat .env.example).
| Database resto WAJIB terpisah dari database moizhospitalapps.
*/
$active_group = 'default';
$query_builder = TRUE;

$db['default'] = array(
	'dsn'      => '',
	'hostname' => env('DB_HOST', 'localhost'),
	'port'     => (int) env('DB_PORT', 3306),
	'username' => env('DB_USER', ''),
	'password' => env('DB_PASS', ''),
	'database' => env('DB_NAME', ''),
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => (ENVIRONMENT !== 'production'),
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8mb4',
	'dbcollat' => 'utf8mb4_unicode_ci',
	'swap_pre' => '',
	'encrypt'  => FALSE,
	'compress' => FALSE,
	'stricton' => TRUE,
	'failover' => array(),
	'save_queries' => (ENVIRONMENT !== 'production')
);
