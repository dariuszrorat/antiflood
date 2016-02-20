<?php

defined('SYSPATH') or die('No direct script access.');

return array(
    'file' => array(
        'driver' => 'file',
        'control_dir' => APPPATH . 'control/antiflood',
        'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 20,
        'expiration' => 172800
    ),
    'sqlite' => array(
        'driver' => 'sqlite',
        'database' => APPPATH . 'control/antiflood/kohana-antiflood.sql3',
        'schema' => 'CREATE TABLE controls(id integer PRIMARY KEY AUTOINCREMENT, control_key varchar(255), last_access datetime, requests INTEGER, locked INTEGER, locked_access datetime)',
        'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 20,
        'expiration' => 172800
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
        'control_key varchar(255) NOT NULL,' .
        'last_access INT(11) NOT NULL,' .
        'requests int(10) unsigned NOT NULL,' .
        'locked tinyint(1) NOT NULL,' .
        'locked_access INT(11) NOT NULL,' .
        'PRIMARY KEY (id)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8;',
        'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 20,
        'expiration' => 172800
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
        '  control_key character varying(255) NOT NULL,' .
        '  last_access bigint NOT NULL,' .
        '  requests integer NOT NULL,' .
        '  locked integer NOT NULL,' .
        '  locked_access bigint NOT NULL,' .
        '  CONSTRAINT pk_controls PRIMARY KEY (id)' .
        ')',
        'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 600,
        'expiration' => 172800
    ),
    'redis' => array(
        'driver' => 'redis',
        'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 600,
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 15
    ),
    'ssdb' => array(
        'driver' => 'SSDB',
        'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 600,
        'host' => '127.0.0.1',
        'port' => 8888,
        'timeout' => 2000
    ),
    'memcache' => array(
        'driver' => 'memcache',
        'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 600,
        'compression' => FALSE, // Use Zlib compression (can cause issues with integers)
        'servers' => array(
            'local' => array(
                'host' => 'localhost', // Memcache Server
                'port' => 11211, // Memcache port number
                'persistent' => FALSE, // Persistent connection
                'weight' => 1,
                'timeout' => 1,
                'retry_interval' => 15,
                'status' => TRUE
            ),
        ),
        // Take server offline immediately on first fail (no retry)
        'instant_death' => TRUE
    ),
    'mongo' => array(
        'driver' => 'mongo',
        'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 600,
        'expiration' => 172800,
        'host' => 'localhost',
        'port' => 27017,
        'database' => 'control',
        'collection' => 'antiflood'
    ),

);
