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

## Requirements

The redis driver requires predis library:
https://github.com/nrk/predis

Install this library on:

application/vendor

autoload.php must be in:

application/vendor/predis

This library is internal included included by:

No need to install Redis PHP extension module.

The SSDB nosql driver uses SSDB PHP library

http://ssdb.io

Install php SSDB class in vendor/SSDB

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

Using custom config:

```php

 $antiflood = Antiflood::instance();
 $antiflood->config('control_max_requests', 3);
 $antiflood->reload_configuration();

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
if (rand(0, 99) < $gc and $antiflood instanceof Antiflood_GarbageCollect)
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
return array(
    'file' => array(
        'driver' => 'file',
        'control_dir' => APPPATH . 'control/antiflood',
        'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
        'control_max_requests' => 3,
        'control_request_timeout' => 3600,
        'control_ban_time' => 20,
        'expiration' => 172800,
	'ignore_on_delete' => array(
	    '.gitignore',
	    '.git',
	    '.svn',
            'antiflood' // ignore control_dir delete
	)
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

```

