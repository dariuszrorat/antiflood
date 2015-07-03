# Antiflood module for Kohana Framework

This module can be used to protect Your application against too many requests.
It can not protect against DDoS attacks.

This is derivative work from:
https://github.com/damog/planetalinux/blob/master/www/principal/suscripcion/lib/antiflood.hack.php


## Information

Your project must have following structure

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

` 'antiflood' => MODPATH . 'antifloood' `

to Your bootstrap.php

## Example usage:

```php
 $antiflood = new Antiflood();
 if ($antiflood->check())
 {
     $antiflood->countRequests();
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

          // Application defaults
          'default' => array(
                    'control_dir'             => 'application/control/antiflood',
                    'control_max_requests'    => 3,
                    'control_request_timeout' => 3600,   //in seconds
                    'control_ban_time'        => 10      //in seconds
          ),

);
```

