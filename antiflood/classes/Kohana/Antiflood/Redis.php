<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * [Kohana Antiflood](api/Kohana_Antiflood) Redis driver. Provides a redis based
 * driver for the Kohana Antiflood library. 
 *
 * ### Configuration example
 *
 * Below is an example of a _file_ server configuration.
 *
 *     return array(
 *          'redis'   => array(                          // File driver group
 *                  'driver'         => 'redis',         // using Redis driver
 *                  'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
 *                  'control_max_requests'    => 5,
 *                  'control_request_timeout' => 3600,
 *                  'control_ban_time'        => 600,
 *                  'host'           => '127.0.0.1',     // Redis host
 *                  'port'           => 6379,            // Redis port
 *                  'database'       => 15               // Redis database
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
 * host                      | __YES__  | (_string_) The antiflood redis hostname to use for this antiflood instance
 * port                      | __YES__  | (_integer_) The antiflood redis port to use for this antiflood instance
 * database                  | __YES__  | (_integer_) The antiflood redis database to use for this antiflood instance
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

class Kohana_Antiflood_Redis extends Antiflood
{
    /**
     * @var  string the antiflood control directory
     */
    protected $_control_lock_key;
    protected $_control_db_key;

    /**
     *  Redis client
     */
    protected $_client = null;

    /**
     * Constructs the redis antiflood driver. This method cannot be invoked externally. The redis antiflood driver must
     * be instantiated using the `Antiflood::instance()` method.
     *
     * @param   array  $config  config
     * @throws  Antiflood_Exception
     */
    protected function __construct(array $config)
    {
        // Using external vendor Predis library
        require_once Kohana::find_file('vendor/predis', 'autoload');
        // Setup parent
        parent::__construct($config);

        $single_server = array(
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database']
        );

        $this->_client = new Predis\Client($single_server);
    }

    protected function _load_configuration()
    {
        $this->_control_key = Arr::get($this->_config, 'control_key', '#');
        $this->_control_max_requests = Arr::get($this->_config, 'control_max_requests', Antiflood::DEFAULT_MAX_REQUESTS);
        $this->_control_request_timeout = Arr::get($this->_config, 'control_request_timeout', Antiflood::DEFAULT_REQUEST_TIMEOUT);
        $this->_control_ban_time = Arr::get($this->_config, 'control_ban_time', Antiflood::DEFAULT_BAN_TIME);

        $this->_control_db_key = 'db_' . sha1($this->_control_key);
        $this->_control_lock_key = 'lock_' . sha1($this->_control_key);
    }

    /**
     * Check if user locked
     *
     * @return  bool
     */
    public function check()
    {
        $this->_load_configuration();
        $serialized = $this->_client->get($this->_control_lock_key);
        if ($serialized !== null)
        {
            $data = unserialize($serialized);
            $now = time();
            $diff = $now - $data['time'];
            if ($diff > $this->_control_ban_time)
            {
                $this->_client->del($this->_control_lock_key);
                return true;
            } else
            {
                $data['time'] = $now;
                $this->_client->set($this->_control_lock_key, serialize($data));
                return false;
            }
        } else
        {
            return true;
        }
    }

    /**
     * Count requests, returns elapsed requests
     *
     * @return  int
     */
    public function count_requests()
    {
        $this->_load_configuration();
        $control = null;        
        $request_count = 0;

        $serialized = $this->_client->get($this->_control_db_key);
        $control = ($serialized !== null) ? unserialize($serialized) : null;

        $now = time();
        if ($control !== null)
        {
            if ($now - $control["time"] < $this->_control_request_timeout)
            {
                $control["count"] ++;
            } else
            {
                $control["count"] = 1;
            }
        } else
        {
            $control["count"] = 1;
        }
        $control["time"] = $now;

        if ($control["count"] >= $this->_control_max_requests)
        {
            $this->_client->set($this->_control_lock_key, serialize(
                            array(
                                'key' => $this->_control_key,
                                'time' => $now)));
            $control["count"] = 0;
        }
        $request_count = $control["count"];

        $this->_client->set($this->_control_db_key, serialize($control));
        return $request_count;
    }

    /**
     * Delete current antiflood control method
     *
     * @return  void
     */

    public function delete()
    {
        $this->_client->del($this->_control_db_key);
        $this->_client->del($this->_control_lock_key);
        return;
    }

    /**
     * Delete all antiflood controls method
     *
     * @return  void
     */

    public function delete_all()
    {
        return;
    }

}
