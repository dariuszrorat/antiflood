<?php

defined('SYSPATH') or die('No direct script access.');

return array(
    'file' => array(
        'driver' => 'file',
        'control_dir' => APPPATH . 'control/antiflood',
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 20
    ),
    'sqlite' => array(
        'driver' => 'sqlite',
        'database' => APPPATH . 'control/antiflood/kohana-antiflood.sql3',
        'schema' => 'CREATE TABLE controls(id integer PRIMARY KEY AUTOINCREMENT, user_ip VARCHAR(20), uri varchar(255), last_access datetime, requests INTEGER, locked INTEGER, locked_access datetime)',
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 20
    ),
    'mysql' => array(
        'driver' => 'mysql',
        'hostname' => 'localhost',
        'database' => 'mojastrona',
        'username' => 'root',
        'password' => 'root',
        'schema' =>
        'CREATE TABLE controls (' .
        'id int(10) unsigned NOT NULL AUTO_INCREMENT,' .
        'user_ip varchar(20) NOT NULL,' .
        'uri varchar(255) NOT NULL,' .
        'last_access datetime NOT NULL,' .
        'requests int(10) unsigned NOT NULL,' .
        'locked tinyint(1) NOT NULL,' .
        'locked_access datetime NOT NULL,' .
        'PRIMARY KEY (`id`)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8;',
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 20
    ),
    'postgresql' => array(
        'driver' => 'postgresql',
        'hostname' => 'localhost',
        'database' => 'finance',
        'username' => 'postgres',
        'password' => 'postgres',
        'schema' =>
        'CREATE TABLE controls' .
        '(' .
        '  id serial NOT NULL,' .
        '  user_ip character varying(20) NOT NULL,' .
        '  uri character varying(255) NOT NULL,' .
        '  last_access character varying(20) NOT NULL,' .
        '  requests integer NOT NULL,' .
        '  locked integer NOT NULL,' .
        '  locked_access character varying(20) NOT NULL,' .
        '  CONSTRAINT pk_controls PRIMARY KEY (id)' .
        ')',
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 600
    ),
);
