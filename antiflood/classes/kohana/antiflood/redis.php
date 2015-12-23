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
require_once Kohana::find_file('vendor/predis', 'autoload');

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
        $serialized = $this->_client->get($this->_control_lock_key);
        if ($serialized !== null)
        {
            $data = unserialize($serialized);
            $now = time();
            $diff = $now - $data['time'];
            if ($diff > $this->_control_ban_time)
            {
                try
                {
                    $this->_client->del($this->_control_lock_key);
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
                    $this->_client->set($this->_control_lock_key, serialize($data));
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
            $serialized = $this->_client->get($this->_control_db_key);
            $control = ($serialized !== null) ? unserialize($serialized) : null;
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
                $this->_client->set($this->_control_lock_key, serialize(
                                array(
                                    'ip' => $this->_user_ip,
                                    'uri' => $this->_uri,
                                    'time' => $now)));
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
            $this->_client->set($this->_control_db_key, serialize($control));
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

    public function delete()
    {
        $this->_client->del($this->_control_db_key);
        $this->_client->del($this->_control_lock_key);
        return;
    }
    
    public function delete_all()
    {
        return;
    }

}
