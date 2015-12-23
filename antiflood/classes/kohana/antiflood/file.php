<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * [Kohana Antiflood](api/Kohana_Antiflood) File driver. Provides a file based
 * driver for the Kohana Antiflood library. This is one of the slowest
 * methods. This driver can use the same database file and the same dir for the 
 * different URI. The lock file names contains sha1 of user ip and request URI.
 *
 * ### Configuration example
 *
 * Below is an example of a _file_ server configuration.
 *
 *     return array(
 *          'file'   => array(                          // File driver group
 *                  'driver'         => 'file',         // using File driver
 *                  'control_dir'     => APPPATH.'control/antiflood', // Control location
 *                  'control_max_requests'    => 5,
 *                  'control_request_timeout' => 3600,
 *                  'control_ban_time'        => 600
 *           ),
 *     )
 *
 * In cases where only one antiflood group is required, if the group is named `default` there is
 * no need to pass the group name when instantiating a antiflood instance. The control dir
 * APPPATH.'control/antiflood/ must be created on disk.
 *
 * #### General antiflood group configuration settings
 *
 * Below are the settings available to all types of antiflood driver.
 *
 * Name               | Required | Description
 * --------------     | -------- | ---------------------------------------------------------------
 * driver             | __YES__  | (_string_) The driver type to use
 * control_dir        | __NO__   | (_string_) The antiflood directory to use for this antiflood instance
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
class Kohana_Antiflood_File extends Antiflood implements Antiflood_GarbageCollect
{

    /**
     * @var  string the antiflood control directory
     */
    protected $_control_dir;
    protected $_control_lock_file;
    protected $_control_db;

    /**
     * Constructs the file antiflood driver. This method cannot be invoked externally. The file antiflood driver must
     * be instantiated using the `Antiflood::instance()` method.
     *
     * @param   array  $config  config
     * @throws  Antiflood_Exception
     */
    protected function __construct(array $config)
    {
        // Setup parent
        parent::__construct($config);
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

        $this->_control_db = $this->_control_dir . "/" . sha1($this->_uri) . ".ser";
        $this->_control_lock_file = $this->_control_dir . "/" . sha1($this->_user_ip . $this->_uri) . ".lock";
    }

    /**
     * Check if user locked
     *
     * @return  bool
     */
    public function check()
    {
        $this->_load_configuration();
        if (file_exists($this->_control_lock_file))
        {
            $diff = time() - filemtime($this->_control_lock_file);
            if ($diff > $this->_control_ban_time)
            {
                try
                {
                    unlink($this->_control_lock_file);
                    return true;
                } catch (ErrorException $e)
                {
                    if ($e->getCode() === E_NOTICE)
                    {
                        throw new Antiflood_Exception(__METHOD__ . ' failed to unlink lock file with message : ' . $e->getMessage());
                    }

                    throw $e;
                }
            } else
            {
                try
                {
                    touch($this->_control_lock_file);
                    return false;
                } catch (ErrorException $e)
                {
                    if ($e->getCode() === E_NOTICE)
                    {
                        throw new Antiflood_Exception(__METHOD__ . ' failed to touch lock file with message : ' . $e->getMessage());
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
        $control = Array();
        $control_key = $this->_user_ip;
        $request_count = 0;

        if (file_exists($this->_control_db))
        {
            try
            {
                $fh = fopen($this->_control_db, "r");
                $control = array_merge($control, unserialize(fread($fh, filesize($this->_control_db))));
                fclose($fh);
            } catch (ErrorException $e)
            {
                if ($e->getCode() === E_NOTICE)
                {
                    throw new Antiflood_Exception(__METHOD__ . ' failed to unserialize control data with message : ' . $e->getMessage());
                }

                throw $e;
            }
        }

        if (isset($control[$control_key]))
        {
            if (time() - $control[$control_key]["time"] < $this->_control_request_timeout)
            {
                $control[$control_key]["count"] ++;
            } else
            {
                $control[$control_key]["count"] = 1;
            }
        } else
        {
            $control[$control_key]["count"] = 1;
        }
        $control[$control_key]["time"] = time();

        if ($control[$control_key]["count"] >= $this->_control_max_requests)
        {
            try
            {
                $fh = fopen($this->_control_lock_file, "w");
                fwrite($fh, $this->_user_ip . ' ' . $this->_uri);
                fclose($fh);
                $control[$control_key]["count"] = 0;
            } catch (ErrorException $e)
            {
                if ($e->getCode() === E_NOTICE)
                {
                    throw new Antiflood_Exception(__METHOD__ . ' failed to serialize control data with message : ' . $e->getMessage());
                }

                throw $e;
            }
        }
        $request_count = $control[$control_key]["count"];

        try
        {
            $fh = fopen($this->_control_db, "w");
            if (flock($fh, LOCK_EX))
            {
                fwrite($fh, serialize($control));
                flock($fh, LOCK_UN);
            }
            fclose($fh);
        } catch (ErrorException $e)
        {
            if ($e->getCode() === E_NOTICE)
            {
                throw new Antiflood_Exception(__METHOD__ . ' failed to serialize control data with message : ' . $e->getMessage());
            }

            throw $e;
        }
        return $request_count;
    }

    /**
     * Garbage collection method that cleans any expired
     * antiflood entries from the database file.
     *
     * @return  void
     */
    public function garbage_collect()
    {
        $this->_load_configuration();
        if (!file_exists($this->_control_db))
        {
            return;
        }

        $now = time();
        $control = Array();

        try
        {
            $fh = fopen($this->_control_db, "r");
            $control = array_merge($control, unserialize(fread($fh, filesize($this->_control_db))));
            fclose($fh);
        } catch (ErrorException $e)
        {
            if ($e->getCode() === E_NOTICE)
            {
                throw new Antiflood_Exception(__METHOD__ . ' failed to unserialize control data with message : ' . $e->getMessage());
            }

            throw $e;
        }

        foreach ($control as $key => $value)
        {
            if ($now - $value['time'] > $this->_expiration)
            {
                unset($control[$key]);
                $lock_file = $this->_control_dir . "/" . sha1($key . $this->_uri) . ".lock";
                if (file_exists($lock_file) && is_writable($lock_file))
                {
                    try
                    {
                        unlink($lock_file);
                    } catch (ErrorException $e)
                    {
                        if ($e->getCode() === E_NOTICE)
                        {
                            throw new Antiflood_Exception(__METHOD__ . ' failed to unlink lock file with message : ' . $e->getMessage());
                        }

                        throw $e;
                    }
                }
            }
        }

        if (!empty($control))
        {
            try
            {
                $fh = fopen($this->_control_db, "w");
                if (flock($fh, LOCK_EX))
                {
                    fwrite($fh, serialize($control));
                    flock($fh, LOCK_UN);
                }
                fclose($fh);
            } catch (ErrorException $e)
            {
                if ($e->getCode() === E_NOTICE)
                {
                    throw new Antiflood_Exception(__METHOD__ . ' failed to serialize control data with message : ' . $e->getMessage());
                }

                throw $e;
            }
        } else
        {
            if (file_exists($this->_control_db) && is_writable($this->_control_db))
            {
                try
                {
                    unlink($this->_control_db);
                } catch (ErrorException $e)
                {
                    if ($e->getCode() === E_NOTICE)
                    {
                        throw new Antiflood_Exception(__METHOD__ . ' failed to unlink control db file with message : ' . $e->getMessage());
                    }

                    throw $e;
                }
            }
        }
        return;
    }

}
