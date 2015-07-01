<?php defined('SYSPATH') or die('No direct script access.');

return array(

          // Application defaults
          'default' => array(
                    'control_dir'             => 'application/control/antiflood',
                    'control_max_requests'    => 5,
                    'control_request_timeout' => 3600,
                    'control_ban_time'        => 600
          ),

);