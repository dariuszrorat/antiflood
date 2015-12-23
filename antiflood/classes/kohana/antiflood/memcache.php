<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * [Kohana Antiflood](api/Kohana_Antiflood) Memcache driver. Provides a memcache based
 * driver for the Kohana Antiflood library. 
 * *
 *
 * #### General antiflood group configuration settings
 *
 * Below are the settings available to all types of antiflood driver.
 *
 * Name               | Required | Description
 * --------------     | -------- | ---------------------------------------------------------------
 * driver             | __YES__  | (_string_) The driver type to use

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
        $this->_control_dir = Arr::get($this->_config, 'control_dir', APPPATH . 'control/antiflood');
        $this->_control_max_requests = Arr::get($this->_config, 'control_max_requests', 5);
        $this->_control_request_timeout = Arr::get($this->_config, 'control_request_timeout', 3600);
        $this->_control_ban_time = Arr::get($this->_config, 'control_ban_time', 600);
        $this->_expiration = Arr::get($this->_config, 'expiration', Antiflood::DEFAULT_EXPIRE);

        if ($this->_expiration < $this->_control_ban_time)
        {
            $this->_expiration = $this->_control_ban_time;
        }

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
                try
                {
                    $this->_memcache->delete($this->_control_lock_key, 0);
                    return true;
                } catch (ErrorException $e)
                {
                    if ($e->getCode() === E_NOTICE)
                    {
                        throw new Antiflood_Exception(__METHOD__ . ' failed to delete lock key with message : ' . $e->getMessage());
                    }

                    throw $e;
                }
            } else
            {
                try
                {
                    $data['time'] = $now;
                    $this->_memcache->set($this->_control_lock_key, serialize($data), $this->_flags, Antiflood_Memcache::MAX_LIFE);
                    return false;
                } catch (ErrorException $e)
                {
                    if ($e->getCode() === E_NOTICE)
                    {
                        throw new Antiflood_Exception(__METHOD__ . ' failed to update lock key with message : ' . $e->getMessage());
                    }

                    throw $e;
                }
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


        try
        {
            $serialized = $this->_memcache->get($this->_control_db_key);
            $control = ($serialized !== false) ? unserialize($serialized) : null;
        } catch (ErrorException $e)
        {
            if ($e->getCode() === E_NOTICE)
            {
                throw new Antiflood_Exception(__METHOD__ . ' failed to read control data with message : ' . $e->getMessage());
            }

            throw $e;
        }

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
            try
            {
                $this->_memcache->set($this->_control_lock_key, serialize(
                                array(
                                    'ip' => $this->_user_ip,
                                    'uri' => $this->_uri,
                                    'time' => $now)), $this->_flags, Antiflood_Memcache::MAX_LIFE
                );
                $control["count"] = 0;
            } catch (ErrorException $e)
            {
                if ($e->getCode() === E_NOTICE)
                {
                    throw new Antiflood_Exception(__METHOD__ . ' failed to set lock data with message : ' . $e->getMessage());
                }

                throw $e;
            }
        }
        $request_count = $control["count"];

        try
        {
            $this->_memcache->set($this->_control_db_key, serialize($control), $this->_flags, Antiflood_Memcache::MAX_LIFE);
        } catch (ErrorException $e)
        {
            if ($e->getCode() === E_NOTICE)
            {
                throw new Antiflood_Exception(__METHOD__ . ' failed to set control data with message : ' . $e->getMessage());
            }

            throw $e;
        }
        return $request_count;
    }

    public function delete($timeout = 0)
    {
        $this->_memcache->delete($this->_control_db_key, $timeout);
        $this->_memcache->delete($this->_control_lock_key, $timeout);
        return;
    }

    public function delete_all()
    {
        $result = $this->_memcache->flush();
        sleep(1);
        return $result;
    }

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
