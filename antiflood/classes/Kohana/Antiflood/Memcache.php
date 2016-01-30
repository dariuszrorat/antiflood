<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * [Kohana Antiflood](api/Kohana_Antiflood) Memcache driver. Provides a memcache based
 * driver for the Kohana Antiflood library.
 *
 * ### Configuration example
 *
 * Below is an example of a _file_ server configuration.
 *
 *     return array(
 *         'memcache' => array(
 *             'driver' => 'memcache',
 *             'control_max_requests' => 3,
 *             'control_request_timeout' => 3600,
 *             'control_ban_time' => 600,
 *             'compression' => FALSE, // Use Zlib compression (can cause issues with integers)
 *             'servers' => array(
 *                 'local' => array(
 *                     'host' => 'localhost', // Memcache Server
 *                     'port' => 11211, // Memcache port number
 *                     'persistent' => FALSE, // Persistent connection
 *                     'weight' => 1,
 *                     'timeout' => 1,
 *                     'retry_interval' => 15,
 *                     'status' => TRUE
 *                 ),
 *             ),
 *             // Take server offline immediately on first fail (no retry)
 *             'instant_death' => TRUE
 *         )
 *     )
 *
 *
 * #### General antiflood group configuration settings
 *
 * Below are the settings available to all types of antiflood driver.
 *
 * Name                          | Required | Description
 * ----------------------------- | -------- | ---------------------------------------------------------------
 * driver                        | __YES__  | (_string_) The driver type to use
 * control_max_requests          | __YES__  | (_integer_) The maximum of requests in control request timeout
 * control_request_timeout       | __YES__  | (_integer_) The control request timeout in s
 * control_ban_time              | __YES__  | (_integer_) The user IP ban time in s
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
class Kohana_Antiflood_Memcache extends Antiflood
{

    const MAX_LIFE = 2592000;

    /**
     * @var  string the antiflood control directory
     */
    protected $_control_lock_key;
    protected $_control_db_key;

    /**
     * Memcache resource
     *
     * @var Memcache
     */
    protected $_memcache;

    /**
     * Flags to use when storing values
     *
     * @var string
     */
    protected $_flags;

    /**
     * The default configuration for the memcached server
     *
     * @var array
     */
    protected $_default_config = array();

    /**
     * Constructs the redis antiflood driver. This method cannot be invoked externally. The redis antiflood driver must
     * be instantiated using the `Antiflood::instance()` method.
     *
     * @param   array  $config  config
     * @throws  Antiflood_Exception
     */
    protected function __construct(array $config)
    {
        // Check for the memcache extention
        if (!extension_loaded('memcache'))
        {
            throw new Antiflood_Exception('Memcache PHP extention not loaded');
        }

        // Setup parent
        parent::__construct($config);

        // Setup Memcache
        $this->_memcache = new Memcache;

        // Load servers from configuration
        $servers = Arr::get($this->_config, 'servers', NULL);

        if (!$servers)
        {
            // Throw an exception if no server found
            throw new Antiflood_Exception('No Memcache servers defined in configuration');
        }

        // Setup default server configuration
        $this->_default_config = array(
            'host' => 'localhost',
            'port' => 11211,
            'persistent' => FALSE,
            'weight' => 1,
            'timeout' => 1,
            'retry_interval' => 15,
            'status' => TRUE,
            'instant_death' => TRUE,
            'failure_callback' => array($this, '_failed_request')
        );

        // Add the memcache servers to the pool
        foreach ($servers as $server)
        {
            // Merge the defined config with defaults
            $server += $this->_default_config;

            if (!$this->_memcache->addServer($server['host'], $server['port'], $server['persistent'], $server['weight'], $server['timeout'], $server['retry_interval'], $server['status'], $server['failure_callback']))
            {
                throw new Antiflood_Exception('Memcache could not connect to host \':host\' using port \':port\'', array(':host' => $server['host'], ':port' => $server['port']));
            }
        }

        // Setup the flags
        $this->_flags = Arr::get($this->_config, 'compression', FALSE) ? MEMCACHE_COMPRESSED : FALSE;
    }

    protected function _load_configuration()
    {
        $this->_control_max_requests = Arr::get($this->_config, 'control_max_requests', Antiflood::DEFAULT_MAX_REQUESTS);
        $this->_control_request_timeout = Arr::get($this->_config, 'control_request_timeout', Antiflood::DEFAULT_REQUEST_TIMEOUT);
        $this->_control_ban_time = Arr::get($this->_config, 'control_ban_time', Antiflood::DEFAULT_BAN_TIME);

        $this->_control_db_key = 'db_' . sha1($this->_user_ip . $this->_uri);
        $this->_control_lock_key = 'lock_' . sha1($this->_user_ip . $this->_uri);
    }

    /**
     * Check if user locked
     *
     * @return  bool
     */
    public function check()
    {
        $this->_load_configuration();

        $serialized = $this->_memcache->get($this->_control_lock_key);
        if ($serialized !== false)
        {
            $data = unserialize($serialized);
            $now = time();
            $diff = $now - $data['time'];
            if ($diff > $this->_control_ban_time)
            {
                $this->_memcache->delete($this->_control_lock_key, 0);
                return true;
            } else
            {
                $data['time'] = $now;
                $this->_memcache->set($this->_control_lock_key, serialize($data), $this->_flags, Antiflood_Memcache::MAX_LIFE);
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
        $control_key = $this->_user_ip;
        $request_count = 0;

        $serialized = $this->_memcache->get($this->_control_db_key);
        $control = ($serialized !== false) ? unserialize($serialized) : null;

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
            $this->_memcache->set($this->_control_lock_key, serialize(
                            array(
                                'ip' => $this->_user_ip,
                                'uri' => $this->_uri,
                                'time' => $now)), $this->_flags, Antiflood_Memcache::MAX_LIFE
            );
            $control["count"] = 0;
        }
        $request_count = $control["count"];

        $this->_memcache->set($this->_control_db_key, serialize($control), $this->_flags, Antiflood_Memcache::MAX_LIFE);
        return $request_count;
    }

    /**
     * Delete current antiflood control method
     *
     * @return  void
     */

    public function delete($timeout = 0)
    {
        $this->_memcache->delete($this->_control_db_key, $timeout);
        $this->_memcache->delete($this->_control_lock_key, $timeout);
        return;
    }

    /**
     * Delete all antiflood controls method
     *
     * @return  void
     */

    public function delete_all()
    {
        $result = $this->_memcache->flush();
        sleep(1);
        return $result;
    }

    /**
     * Failure callback method
     *
     * @return  mixed
     */

    public function _failed_request($hostname, $port)
    {
        if (!$this->_config['instant_death'])
            return;

        // Setup non-existent host
        $host = FALSE;

        // Get host settings from configuration
        foreach ($this->_config['servers'] as $server)
        {
            // Merge the defaults, since they won't always be set
            $server += $this->_default_config;
            // We're looking at the failed server
            if ($hostname == $server['host'] and $port == $server['port'])
            {
                // Server to disable, since it failed
                $host = $server;
                continue;
            }
        }

        if (!$host)
            return;
        else
        {
            return $this->_memcache->setServerParams(
                            $host['host'], $host['port'], $host['timeout'], $host['retry_interval'], FALSE, // Server is offline
                            array($this, '_failed_request'
            ));
        }
    }

}
