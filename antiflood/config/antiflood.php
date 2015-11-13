<?php defined('SYSPATH') or die('No direct script access.');

return array(

        'file' => array(
                'driver'                  => 'file',
                'control_dir'             => APPPATH . 'control/antiflood',
                'control_max_requests'    => 3,
                'control_request_timeout' => 3600,
                'control_ban_time'        => 600
          ),
	'sqlite'   => array(
		'driver'             => 'sqlite',
		'database'           => APPPATH.'control/antiflood/kohana-antiflood.sql3',
		'schema'             => 'CREATE TABLE controls(id integer PRIMARY KEY AUTOINCREMENT, iphash VARCHAR(50), requests INTEGER, locked INTEGER)',
                'control_max_requests'    => 3,
                'control_request_timeout' => 3600,
                'control_ban_time'        => 600
	),
	'mysql'   => array(
		'driver'             => 'mysql',
		'hostname'           => 'localhost',
                'database'           => 'mysqldb',
		'username'   => 'root',
		'password'   => '',
		'persistent' => FALSE,
		'schema'             => 'CREATE TABLE controls(id int(11) UNSIGNED NOT NULL AUTO_INCREMENT, iphash VARCHAR(50), requests INTEGER, locked INTEGER)',
                'control_max_requests'    => 3,
                'control_request_timeout' => 3600,
                'control_ban_time'        => 600
	),
	'postgresql'   => array(
		'driver'             => 'postgresql',
		'hostname'           => 'localhost',
                'database'           => 'postgresqldb',
		'username'   => 'postgres',
		'password'   => '',
		'persistent' => FALSE,
		'schema'             => 'CREATE TABLE controls(id serial, iphash VARCHAR(50), requests INTEGER, locked INTEGER)',
                'control_max_requests'    => 3,
                'control_request_timeout' => 3600,
                'control_ban_time'        => 600
	),

);