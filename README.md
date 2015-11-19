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
is: application/control/antiflood. You can use the same control dir for the
different request URI.

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

## Probablistic garbage collection

```php
$antiflood = Antiflood::instance();

// Set a GC probability of 10%
$gc = 10;

// If the GC probability is a hit
if (rand(0, 99) <= $gc and $antiflood instanceof Antiflood_GarbageCollect)
{
    // Garbage Collect
    $antiflood->garbage_collect();
}
```

The garbage collector uses expiration config value to delete old and not used
records.

## Config

antiflood.php

```php
<?php

defined('SYSPATH') or die('No direct script access.');

return array(
    'file' => array(
        'driver' => 'file',
        'control_dir' => APPPATH . 'control/antiflood',
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 600,
        'expiration' => 172800
    ),
    'sqlite' => array(
        'driver' => 'sqlite',
        'database' => APPPATH . 'control/antiflood/kohana-antiflood.sql3',
        'schema' => 'CREATE TABLE controls(id integer PRIMARY KEY AUTOINCREMENT, user_ip VARCHAR(20), uri varchar(255), last_access datetime, requests INTEGER, locked INTEGER, locked_access datetime)',
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 600,
        'expiration' => 172800
    ),
    'mysql' => array(
        'driver' => 'mysql',
        'hostname' => 'localhost',
        'database' => 'mydb',
        'username' => 'root',
        'password' => '',
        'schema' =>
        'CREATE TABLE controls (' .
        'id int(10) unsigned NOT NULL AUTO_INCREMENT,' .
        'user_ip varchar(20) NOT NULL,' .
        'uri varchar(255) NOT NULL,' .
        'last_access INT(11) NOT NULL,' .
        'requests int(10) unsigned NOT NULL,' .
        'locked tinyint(1) NOT NULL,' .
        'locked_access INT(11) NOT NULL,' .
        'PRIMARY KEY (id)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8;',
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 20,
        'expiration' => 172800
    ),
    'postgresql' => array(
        'driver' => 'postgresql',
        'hostname' => 'localhost',
        'database' => 'mydb',
        'username' => 'postgres',
        'password' => '',
        'schema' =>
        'CREATE TABLE controls' .
        '(' .
        '  id serial NOT NULL,' .
        '  user_ip character varying(20) NOT NULL,' .
        '  uri character varying(255) NOT NULL,' .
        '  last_access bigint NOT NULL,' .
        '  requests integer NOT NULL,' .
        '  locked integer NOT NULL,' .
        '  locked_access bigint NOT NULL,' .
        '  CONSTRAINT pk_controls PRIMARY KEY (id)' .
        ')',
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 600,
        'expiration' => 172800
    ),
);

```

