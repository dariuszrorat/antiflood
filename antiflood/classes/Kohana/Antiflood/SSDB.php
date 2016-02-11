<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * [Kohana Antiflood](api/Kohana_Antiflood) SSDB driver. Provides a SSDB based
 * driver for the Kohana Antiflood library. 
 *
 * ### Configuration example
 *
 * Below is an example of a _file_ server configuration.
 *
 *     return array(
 *          'ssdb'   => array(                          // File driver group
 *                  'driver'         => 'SSDB',         // using Redis driver (must be uppercase in Kohana 3.3+)
 *                  'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
 *                  'control_max_requests'    => 5,
 *                  'control_request_timeout' => 3600,
 *                  'control_ban_time'        => 600,
 *                  'host'           => '127.0.0.1',     // SSDB host
 *                  'port'           => 8888,            // SSDB port
 *                  'timeout'        => 2000             // connection timeout in ms
 *           ),
 *     )
 *
 *
 * #### General antiflood group configuration settings
 *
 * Below are the settings available to all types of antiflood driver.
 *
 * Name                      | Required | Description
 * ------------------------- | -------- | ---------------------------------------------------------------
 * driver                    | __YES__  | (_string_) The driver type to use
 * control_key               | __YES__  | (_string_) The control key used to check (IP or anything)
 * control_max_requests      | __YES__  | (_integer_) The maximum of requests in control request timeout
 * control_request_timeout   | __YES__  | (_integer_) The control request timeout in s
 * control_ban_time          | __YES__  | (_integer_) The user IP ban time in s
 * expiration                | __YES__  | (_integer_) The expiration time in s used by garbage collector
 * host                      | __YES__  | (_string_) The antiflood SSDB hostname to use for this antiflood instance
 * port                      | __YES__  | (_integer_) The antiflood SSDB port to use for this antiflood instance
 * timeout                   | __NO__   | (_integer_) The antiflood server connection timeout
 *
 * ### System requirements
 *
 * *  Kohana 3.0.x
 * *  PHP 5.2.4 or greater
 *
 * @package    Kohana/Antiflood
 * @category   Security
 * @author     Dariusz Rorat
 * @copyright  (c) 2015 Dariusz Rorat
 */

class Kohana_Antiflood_SSDB extends Antiflood_Nosql
{

    /**
     * Constructs the SSDB antiflood driver. This method cannot be invoked externally. The SSDB antiflood driver must
     * be instantiated using the `Antiflood::instance()` method.
     *
     * @param   array  $config  config
     * @throws  Antiflood_Exception
     */
    protected function __construct(array $config)
    {
        // Using external vendor SSDB library
        // from ssdb.io
        require_once Kohana::find_file('vendor/SSDB', 'SSDB');
        // Setup parent
        parent::__construct($config);

        $host = $config['host'];
        $port = $config['port'];
        $timeout = $config['timeout'];

        $this->_client = new SimpleSSDB($host, $port, $timeout);
    }

}
