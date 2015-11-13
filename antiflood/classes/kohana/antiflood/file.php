<?php defined('SYSPATH') or die('No direct script access.');
/**
 * [Kohana Antiflood](api/Kohana_Antiflood) File driver. Provides a file based
 * driver for the Kohana Antiflood library. This is one of the slowest
 * methods.
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
 * no need to pass the group name when instantiating a antiflood instance.
 *
 * #### General antiflood group configuration settings
 *
 * Below are the settings available to all types of cache driver.
 *
 * Name               | Required | Description
 * --------------     | -------- | ---------------------------------------------------------------
 * driver             | __YES__  | (_string_) The driver type to use
 * control_dir        | __NO__   | (_string_) The cache directory to use for this cache instance
 *
 * ### System requirements
 *
 * *  Kohana 3.0.x
 * *  PHP 5.2.4 or greater
 *
 * @package    Kohana/Antiflood
 * @category   Safety
 * @author     Dariusz Rorat
 * @copyright  (c) 2015 Dariusz Rorat
 */

class Kohana_Antiflood_File extends Antiflood {

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

            $this->_user_ip = $_SERVER["REMOTE_ADDR"];
            $this->_control_db = $this->_control_dir . "/control.db";
            $this->_control_lock_file = $this->_control_dir . "/" . sha1($this->_user_ip) . ".lock";
        }

        public function check()
        {
            $this->_load_configuration();
            if (file_exists($this->_control_lock_file))
            {
                $diff = time() - filemtime($this->_control_lock_file);
		if ($diff > $this->_control_ban_time)
                {
		    unlink($this->_control_lock_file);
                    return true;
		}
                else
                {
	            touch($this->_control_lock_file);
		    return false;
		}

            }
            else
            {
                return true;
            }
        }

        public function count_requests()
        {
                $this->_load_configuration();
		$control = Array();

		if (file_exists($this->_control_db)) {
			$fh = fopen($this->_control_db, "r");
			$control = array_merge($control, unserialize(fread($fh, filesize($this->_control_db))));
			fclose($fh);
		}

		if (isset($control[$this->_user_ip])) {
			if (time()-$control[$this->_user_ip]["t"] < $this->_control_request_timeout)
                        {
				$control[$this->_user_ip]["c"]++;
			} else {
				$control[$this->_user_ip]["c"] = 1;
			}
		} else {
			$control[$this->_user_ip]["c"] = 1;
		}
		$control[$this->_user_ip]["t"] = time();

		if ($control[$this->_user_ip]["c"] >= $this->_control_max_requests)
                {
			$fh = fopen($this->_control_lock_file, "w");
			fwrite($fh, $this->_user_ip);
			fclose($fh);
                        $control[$this->_user_ip]["c"] = 0;
		}

		$fh = fopen($this->_control_db, "w");
		fwrite($fh, serialize($control));
		fclose($fh);

        }


}