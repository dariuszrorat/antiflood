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
 *                  'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
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
 * control_key               | __YES__  | (_string_) The control key used to check (IP or anything)
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
    protected $_control_db;

    /**
     * Creates a hashed filename based on the string. This is used
     * to create shorter unique IDs for each control filename.
     *
     *     // Create the control filename
     *     $filename = Antiflood_File::filename($control_key);
     *
     * @param   string  $string  string to hash into filename
     * @return  string
     */
    protected static function filename($string)
    {
        return sha1($string) . '.chk';
    }

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
        $dirname = Arr::get($this->_config, 'control_dir', APPPATH . 'control/antiflood');
        $this->_control_dir = new SplFileInfo($dirname);

        $this->_control_key = Arr::get($this->_config, 'control_key', '#');
        $this->_control_max_requests = Arr::get($this->_config, 'control_max_requests', Antiflood::DEFAULT_MAX_REQUESTS);
        $this->_control_request_timeout = Arr::get($this->_config, 'control_request_timeout', Antiflood::DEFAULT_REQUEST_TIMEOUT);
        $this->_control_ban_time = Arr::get($this->_config, 'control_ban_time', Antiflood::DEFAULT_BAN_TIME);
        $this->_expiration = Arr::get($this->_config, 'expiration', Antiflood::DEFAULT_EXPIRE);

        if ($this->_expiration < $this->_control_ban_time)
        {
            $this->_expiration = $this->_control_ban_time;
        }

        $filename = Antiflood_File::filename($this->_control_key);
        $directory = $this->_resolve_directory($filename);
        $this->_control_db = $directory . $filename;
    }

    /**
     * Check if user locked
     *
     * @return  bool
     */
    public function check()
    {
        $this->_load_configuration();
        $resource = new SplFileInfo($this->_control_db);

        // If file exists
        if ($resource->isFile())
        {
            $data = $this->_unserialize($resource);
            $locked = (bool) $data['locked'];
            $locked_access = $data['locked_access'];
            $now = time();

            if ($locked === true)
            {
                $diff = $now - $locked_access;
                if ($diff > $this->_control_ban_time)
                {
                    $data['locked'] = FALSE;
                    $data['locked_access'] = $now;
                    $this->_serialize($resource, $data);
                    return true;
                } else
                {
                    $data['locked_access'] = $now;
                    $this->_serialize($resource, $data);
                    return false;
                }
            } else
            {
                return true;
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

        $filename = Antiflood_File::filename($this->_control_key);
        $directory = $this->_resolve_directory($filename);

        // Open directory
        $dir = new SplFileInfo($directory);

        // If the directory path is not a directory
        if (!$dir->isDir())
        {
            $this->_make_directory($directory, 0777, TRUE);
        }

        // Open file
        $resource = new SplFileInfo($this->_control_db);
        $now = time();

        $control = $this->_unserialize($resource);
        // If array not empty
        if (!empty($control))
        {
            if ($now - $control["last_access"] < $this->_control_request_timeout)
            {
                $control["requests"] ++;
            } else
            {
                $control["requests"] = 1;
            }
            $control["last_access"] = $now;
        } else
        {
            $control = array(
                "requests" => 1,
                "last_access" => $now,
                "locked" => false,
                "locked_access" => $now
            );
        }

        if ($control["requests"] >= $this->_control_max_requests)
        {
            $control["requests"] = 0;
            $control["locked"] = true;
            $control["locked_access"] = $now;
        }
        $request_count = $control["requests"];
        $this->_serialize($resource, $control);

        return $request_count;
    }

    /**
     * Delete current antiflood control method
     *
     * @return  void
     */
    public function delete()
    {
        $this->_load_configuration();
        $filename = Antiflood_File::filename($this->_control_key);
        $directory = $this->_resolve_directory($filename);
        return $this->_delete_file(new SplFileInfo($directory . $filename), FALSE, TRUE);
    }

    /**
     * Delete all antiflood controls method
     *
     * @return  void
     */
    public function delete_all()
    {
        $this->_load_configuration();
        return $this->_delete_file($this->_control_dir, FALSE);
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
        $this->_delete_file($this->_control_dir, TRUE, TRUE, TRUE);
        return;
    }

    /**
     * Serialize data to file
     *
     * @return  array
     * @throws Antiflood_exception
     */
    protected function _serialize(SplFileInfo $file, $data)
    {
        $fh = $file->openFile('w');

        try
        {
            $serialized = serialize($data);
            $fh->fwrite($serialized, strlen($serialized));
            $fh->fflush();
        } catch (ErrorException $e)
        {
            throw new Antiflood_Exception(__METHOD__ . ' failed to serialize control data with message : ' . $e->getMessage());
        }
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
     * Deletes files recursively and returns FALSE on any errors
     *
     *     // Delete a file or folder whilst retaining parent directory and ignore all errors
     *     $this->_delete_file($folder, TRUE, TRUE);
     *
     * @param   SplFileInfo  $file                     file
     * @param   boolean      $retain_parent_directory  retain the parent directory
     * @param   boolean      $ignore_errors            ignore_errors to prevent all exceptions interrupting exec
     * @param   boolean      $only_expired             only expired files
     * @return  boolean
     * @throws  Antiflood_Exception
     */
    protected function _delete_file(SplFileInfo $file, $retain_parent_directory = FALSE, $ignore_errors = FALSE, $only_expired = FALSE)
    {
        try
        {
            if ($file->isFile())
            {
                try
                {
                    if (in_array($file->getFilename(), $this->config('ignore_on_delete')))
                    {
                        $delete = FALSE;
                    } elseif ($only_expired === FALSE)
                    {
                        $delete = TRUE;
                    } else
                    {
                        $delete = $this->_is_expired($file);
                    }

                    if ($delete === TRUE)
                        return unlink($file->getRealPath());
                    else
                        return FALSE;
                } catch (ErrorException $e)
                {
                    if ($e->getCode() === E_WARNING)
                    {
                        throw new Antiflood_Exception(__METHOD__ . ' failed to delete file : :file', array(':file' => $file->getRealPath()));
                    }
                }
            } elseif ($file->isDir())
            {
                $files = new DirectoryIterator($file->getPathname());

                while ($files->valid())
                {
                    $name = $files->getFilename();
                    if ($name != '.' AND $name != '..')
                    {
                        $fp = new SplFileInfo($files->getRealPath());
                        $this->_delete_file($fp, $retain_parent_directory, $ignore_errors, $only_expired);
                    }

                    $files->next();
                }

                if ($retain_parent_directory)
                {
                    return TRUE;
                }

                try
                {
                    unset($files);
                    if (!in_array($file->getFilename(), $this->config('ignore_on_delete')))
                    {
                        return rmdir($file->getRealPath());
                    }
                    else
                    {
                        return true;
                    }
                } catch (ErrorException $e)
                {
                    if ($e->getCode() === E_WARNING)
                    {
                        throw new Antiflood_Exception(__METHOD__ . ' failed to delete directory : :directory', array(':directory' => $file->getRealPath()));
                    }
                    throw $e;
                }
            } else
            {
                return FALSE;
            }
        } catch (Exception $e)
        {
            if ($ignore_errors === TRUE)
            {
                return FALSE;
            }
            throw $e;
        }
    }

    /**
     * Resolves the antiflood directory real path from the filename
     *
     *      // Get the realpath of the antiflood folder
     *      $realpath = $this->_resolve_directory($filename);
     *
     * @param   string  $filename  filename to resolve
     * @return  string
     */
    protected function _resolve_directory($filename)
    {
        return $this->_control_dir->getRealPath() . DIRECTORY_SEPARATOR . $filename[0] . $filename[1] . DIRECTORY_SEPARATOR;
    }

    /**
     * Makes the antiflood directory if it doesn't exist. Simply a wrapper for
     * `mkdir` to ensure DRY principles
     *
     * @link    http://php.net/manual/en/function.mkdir.php
     * @param   string    $directory    directory path
     * @param   integer   $mode         chmod mode
     * @param   boolean   $recursive    allows nested directories creation
     * @param   resource  $context      a stream context
     * @return  SplFileInfo
     * @throws  Antiflood_Exception
     */
    protected function _make_directory($directory, $mode = 0777, $recursive = FALSE, $context = NULL)
    {
        // call mkdir according to the availability of a passed $context param
        $mkdir_result = $context ?
                mkdir($directory, $mode, $recursive, $context) :
                mkdir($directory, $mode, $recursive);

        // throw an exception if unsuccessful
        if (!$mkdir_result)
        {
            throw new Antiflood_Exception('Failed to create the defined antiflood directory : :directory', array(':directory' => $directory));
        }

        // chmod to solve potential umask issues
        chmod($directory, $mode);

        return new SplFileInfo($directory);
    }

    /**
     * Test if antiflood file is expired
     *
     * @param SplFileInfo $file the antiflood file
     * @return boolean TRUE if expired false otherwise
     */
    protected function _is_expired(SplFileInfo $file)
    {
        $now = time();
        $control = $this->_unserialize($file);
        return (isset($control["last_access"]) AND ( $now - $control["last_access"]) > $this->_expiration);
    }

}
