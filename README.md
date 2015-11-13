# Antiflood module for Kohana Framework

This module can be used to protect Your application against too many requests.
It can not protect against DDoS attacks.

This is a derivative work based on:

https://github.com/damog/planetalinux/blob/master/www/principal/suscripcion/lib/antiflood.hack.php


## Information

Your project have following structure if You use default file driver

```
 application
   control
     antiflood
 modules
   antiflood
 system
 index.php
```

You must create antiflood control dir from Your antiflood config file. Default
is: application/control/antiflood

## Additional information

You must set DOCROOT in Your index.php

` define('DOCROOT', realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR); `

Add:

` 'antiflood' => MODPATH . 'antiflood' `

to Your bootstrap.php

## Example usage:

Using default file driver:

```php

 $antiflood = Antiflood::instance();
 if ($antiflood->check())
 {
     $antiflood->count_requests();
     $this->template = View::factory('welcome/index');
 }
 else
 {
     header('HTTP/1.1 503 Service Unavailable');
     die();
 }

```

Using sqlite driver:

```php

 $antiflood = Antiflood::instance('sqlite');
 if ($antiflood->check())
 {
     $antiflood->count_requests();
     $this->template = View::factory('welcome/index');
 }
 else
 {
     header('HTTP/1.1 503 Service Unavailable');
     die();
 }

```


## Config

antiflood.php

```php
<?php defined('SYSPATH') or die('No direct script access.');

return array(

       'file' => array(
              'driver'                  => 'file',
              'control_dir'             => APPPATH . 'control/antiflood',
              'control_max_requests'    => 3,
              'control_request_timeout' => 3600,
              'control_ban_time'        => 600
       ),
       'sqlite' => array(
              'driver'                  => 'sqlite',
              'database'                => APPPATH.'control/antiflood/kohana-antiflood.sql3',
              'schema'                  => 'CREATE TABLE controls(id VARCHAR(127) PRIMARY KEY, iphash VARCHAR(50), requests INTEGER, locked INTEGER)',
              'control_max_requests'    => 3,
              'control_request_timeout' => 3600,
              'control_ban_time'        => 600
        ),
        'mysql'   => array(
	      'driver'             => 'mysql',
	      'hostname'           => 'localhost',
              'database'           => 'mysqldb',
              'username'           => 'root',
	      'password'           => '',
	      'persistent'         => FALSE,
	      'schema'             => 'CREATE TABLE controls(id VARCHAR(127) PRIMARY KEY, iphash VARCHAR(50), requests INTEGER, locked INTEGER)',
              'control_max_requests'    => 3,
              'control_request_timeout' => 3600,
              'control_ban_time'        => 600
        ),
        'postgresql'   => array(
	      'driver'             => 'postgresql',
	      'hostname'           => 'localhost',
              'database'           => 'postgresqldb',
	      'username'           => 'postgres',
	      'password'           => '',
	      'persistent'         => FALSE,
	      'schema'             => 'CREATE TABLE controls(id VARCHAR(127) PRIMARY KEY, iphash VARCHAR(50), requests INTEGER, locked INTEGER)',
              'control_max_requests'    => 3,
              'control_request_timeout' => 3600,
              'control_ban_time'        => 600
        ),

);
```

