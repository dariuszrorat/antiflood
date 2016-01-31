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
 * Below is an example of a _file_ configuration.
 *
 *     return array(
 *          'file'   => array(                          // File driver group
 *                  'driver'         => 'file',         // using File driver
 *                  'control_dir'     => APPPATH.'control/antiflood', // Control location
 *                  'control_max_requests'    => 5,
 *                  'control_request_timeout' => 3600,
 *                  'control_ban_time'        => 600,
 *                  'expiration'              => 172800
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
 * Name                      | Required | Description
 * ------------------------- | -------- | ---------------------------------------------------------------
 * driver                    | __YES__  | (_string_) The driver type to use
 * control_dir               | __YES__  | (_string_) The antiflood directory to use for this antiflood instance
 * control_max_requests      | __YES__  | (_integer_) The maximum of requests in control request timeout
 * control_request_timeout   | __YES__  | (_integer_) The control request timeout in s
 * control_ban_time          | __YES__  | (_integer_) The user IP ban time in s
 * expiration                | __YES__  | (_integer_) The expiration time in s used by garbage collector
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
        $this->_control_max_requests = Arr::get($this->_config, 'control_max_requests', Antiflood::DEFAULT_MAX_REQUESTS);
        $this->_control_request_timeout = Arr::get($this->_config, 'control_request_timeout', Antiflood::DEFAULT_REQUEST_TIMEOUT);
        $this->_control_ban_time = Arr::get($this->_config, 'control_ban_time', Antiflood::DEFAULT_BAN_TIME);
        $this->_expiration = Arr::get($this->_config, 'expiration', Antiflood::DEFAULT_EXPIRE);

        if ($this->_expiration < $this->_control_ban_time)
        {
            $this->_expiration = $this->_control_ban_time;
        }

        $this->_control_db = $this->_control_dir . "/" . sha1($this->_user_ip . $this->_uri) . ".ser";
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
        // Open file
        $resource = new SplFileInfo($this->_control_lock_file);

        // If file exists
        if ($resource->isFile())
        {
            $diff = time() - $resource->getMTime();
            if ($diff > $this->_control_ban_time)
            {
                return $this->_delete_file($resource);
            } else
            {
                return !$this->_update_filemtime($resource);
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
        $request_count = 0;

        // Open file
        $resource = new SplFileInfo($this->_control_db);
        $now = time();

        $control = $this->_unserialize($resource);
        // If array not empty
        if (!empty($control))
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
            $control = array("count" => 1, "time" => $now);
        }

        if ($control["count"] >= $this->_control_max_requests)
        {
            // Open lock file to inspect
            $resouce = new SplFileInfo($this->_control_lock_file);
            $file = $resouce->openFile('w');

            try
            {
                $data = $this->_user_ip . "\n" . $this->_uri;
                $file->fwrite($data, strlen($data));
                $file->fflush();

                $control["count"] = 0;
            } catch (ErrorException $e)
            {
                throw new Antiflood_Exception(__METHOD__ . ' failed to save control lock file with message : ' . $e->getMessage());
            }
        }
        $request_count = $control["count"];

        // Open control db file to inspect
        $resouce = new SplFileInfo($this->_control_db);
        $file = $resouce->openFile('w');

        try
        {
            $data = serialize($control);
            $file->fwrite($data, strlen($data));
            $file->fflush();
        } catch (ErrorException $e)
        {
            throw new Antiflood_Exception(__METHOD__ . ' failed to serialize control data with message : ' . $e->getMessage());
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
        $now = time();

        // Create new DirectoryIterator
        $files = new DirectoryIterator($this->_control_dir);

        // Iterate over each entry
        while ($files->valid())
        {
            // Extract the entry name
            $name = $files->getFilename();
            $ext = $files->getExtension();

            // If the name is not a dot
            if ($name != '.' AND $name != '..')
            {
                if ($ext === 'ser')
                {
                    $resource = new SplFileInfo($files->getRealPath());
                    $control = $this->_unserialize($resource);
                    // Check if control db is older than expiration
                    if (isset($control["time"]) AND ( $now - $control["time"]) > $this->_expiration)
                    {
                        // Then delete control db
                        $this->_delete_file($resource);
                        
                        // And delete control lock file if exists
                        $without_ext = preg_replace('/\\.[^.\\s]{3,4}$/', '', $name);
                        $lock_file = $this->_control_dir . "/" . $without_ext . ".lock";
                        $res = new SplFileInfo($lock_file);
                        $this->_delete_file($res);
                    }
                }
            }
            // Move the file pointer on
            $files->next();
        }
        // Remove the files iterator
        // (fixes Windows PHP which has permission issues with open iterators)
        unset($files);

        return;
    }

    /**
     * Delete current antiflood control method
     *
     * @return  void
     */
    public function delete()
    {
        $this->_load_configuration();
        // Delete control db file
        $resource = new SplFileInfo($this->_control_db);
        $this->_delete_file($resource);
        // Delete control lok file
        $resource = new SplFileInfo($this->_control_lock_file);
        $this->_delete_file($resource);
        return;
    }

    /**
     * Delete all antiflood controls method
     *
     * @return  void
     */
    public function delete_all()
    {
        $this->_load_configuration();

        // Create new DirectoryIterator
        $files = new DirectoryIterator($this->_control_dir);

        // Iterate over each entry
        while ($files->valid())
        {
            // Extract the entry name
            $name = $files->getFilename();

            // If the name is not a dot
            if ($name != '.' AND $name != '..')
            {
                // Create new file resource
                $resource = new SplFileInfo($files->getRealPath());
                // Delete the file
                $this->_delete_file($resource);
            }

            // Move the file pointer on
            $files->next();
        }
        // Remove the files iterator
        // (fixes Windows PHP which has permission issues with open iterators)
        unset($files);

        return;
    }

    /**
     * Unserialize data from file
     *
     * @return  array
     * @throws Antiflood_exception
     */
    protected function _unserialize(SplFileInfo $file)
    {
        try
        {
            // If file exists
            if ($file->isFile())
            {
                try
                {
                    $data = $file->openFile();
                    if ($data->eof())
                    {
                        throw new Antiflood_Exception(__METHOD__ . ' corrupted data file!');
                    }
                    $serialized_data = '';

                    while ($data->eof() === FALSE)
                    {
                        $serialized_data .= $data->fgets();
                    }

                    return unserialize($serialized_data);
                } catch (ErrorException $e)
                {
                    throw new Antiflood_Exception(__METHOD__ . ' failed to unserialize data with message : ' . $e->getMessage());
                }
            } else
            {
                return array();
            }
        }
        // Catch all exceptions
        catch (Exception $e)
        {
            // Throw exception
            throw $e;
        }
    }

    /**
     * Update file modification time using SplFileInfo
     *
     * @return  bool
     * @throws Antiflood_Exception
     */
    protected function _update_filemtime(SplFileInfo $file)
    {
        try
        {
            if ($file->isFile())
            {
                touch($file->getRealPath());
                return true;
            } else
            {
                return false;
            }
        } catch (ErrorException $e)
        {
            throw new Antiflood_Exception(__METHOD__ . ' failed to update filemtime with message : ' . $e->getMessage());
        }
    }

    /**
     * Delete file using SplFileInfo
     *
     * @return  bool
     * @throws Antiflood_Exception
     */
    protected function _delete_file(SplFileInfo $file)
    {
        try
        {
            // If is file
            if ($file->isFile())
            {
                try
                {
                    return unlink($file->getRealPath());
                } catch (ErrorException $e)
                {
                    // Catch any delete file warnings
                    if ($e->getCode() === E_WARNING)
                    {
                        throw new Antiflood_Exception(__METHOD__ . ' failed to delete file : :file', array(':file' => $file->getRealPath()));
                    }
                }
            } else
            {
                return false;
            }
        }
        // Catch all exceptions
        catch (Exception $e)
        {
            // Throw exception
            throw $e;
        }
    }

}
