<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Kohana Antiflood provides a common interface to a variety of antiflood engines.
 * Kohana Antiflood supports multiple instances of antiflood engines through a
 * grouped singleton pattern.
 *
 * ### Supported antiflood engines
 *
 * *  File
 * *  [Memcache](http://memcached.org/)
 * *  [Redis](http://redis.io/)
 * *  [SSDB](http://ssdb.io/)
 * *  [SQLite](http://www.sqlite.org/)
 * *  [MySQL](http://www.mysql.com/)
 * *  [PostgreSQL](http://www.postgresql.org/)
 *
 * ### Introduction to antiflood
 *
 * Antiflood used to protect applications against too many requests at a given time.
 * User will be blocked for a certain time based on its IP if it exceeds a set limit
 * requests at a given time.
 *
 * ### Configuration settings
 *
 * Kohana Antiflood uses configuration groups to create cache instances. A configuration group can
 * use any supported driver, with successive groups using the same driver type if required.
 *
 * #### Configuration example
 *
 *
 *     return array(
 *            'file' => array(
 *                   'driver' => 'file',
 *                   'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
 *                   'control_dir' => APPPATH . 'control/antiflood',
 *                   'control_max_requests' => 3,
 *                   'control_request_timeout' => 3600,
 *                   'control_ban_time' => 600,
 *                   'expiration' => 172800
 *                  ),
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

 *
 * Details of the settings specific to each driver are available within the drivers documentation.
 *
 * ### System requirements
 *
 * *  Kohana 3.0.x
 * *  PHP 5.2.4 or greater
 *
 * @package    Kohana/Antiflood
 * @category   Security
 * @version    1.0
 * @author     Dariusz Rorat
 * @copyright  (c) 2015-2016 Dariusz Rorat
 * @license    GNU GPL
 */

abstract class Kohana_Antiflood {

        const DEFAULT_MAX_REQUESTS = 5;
        const DEFAULT_REQUEST_TIMEOUT = 3600;
        const DEFAULT_BAN_TIME = 600;
        const DEFAULT_EXPIRE = 3600;

	protected $_control_max_requests;
        protected $_control_request_timeout;
        protected $_control_ban_time;
	/**
	 * @var   integer expiration used by garbage collector
         * this value should be greather than control ban time
         * if less, control ban time is used
	 */
        protected $_expiration;

        /**
	 * @var   string control key
	 */

        protected $_control_key;

	/**
	 * @var   string     default driver to use
	 */
	public static $default = 'file';

	/**
	 * @var   Kohana_Antiflood instances
	 */
	public static $instances = array();

	/**
	 * Creates a singleton of a Kohana Antiflood group. If no group is supplied
	 * the __default__ antiflood group is used.
	 *
	 *     // Create an instance of the default group
	 *     $default_group = Antiflood::instance();
	 *
	 * @param   string  $group the name of the antiflood group to use [Optional]
	 * @return  Antiflood
	 * @throws  Antiflood_Exception
	 */
	public static function instance($group = NULL)
	{
		// If there is no group supplied
		if ($group === NULL)
		{
			// Use the default setting
			$group = Antiflood::$default;
		}

		if (isset(Antiflood::$instances[$group]))
		{
			// Return the current group if initiated already
			return Antiflood::$instances[$group];
		}

		$config = Kohana::$config->load('antiflood');

		if ( ! $config->offsetExists($group))
		{
			throw new Antiflood_Exception(
				'Failed to load Kohana Antiflood group: :group',
				array(':group' => $group)
			);
		}

		$config = $config->get($group);

		// Create a new antiflood type instance
		$antiflood_class = 'Antiflood_'.ucfirst($config['driver']);
		Antiflood::$instances[$group] = new $antiflood_class($config);

		// Return the instance
		return Antiflood::$instances[$group];
	}

	/**
	 * @var  Config
	 */
	protected $_config = array();

	/**
	 * Ensures singleton pattern is observed, loads the default expiry
	 *
	 * @param  array  $config  configuration
	 */
	protected function __construct(array $config)
	{
		$this->config($config);                
	}

	/**
	 * Getter and setter for the configuration. If no argument provided, the
	 * current configuration is returned. Otherwise the configuration is set
	 * to this class.
	 *
	 *     // Overwrite all configuration
	 *     $antiflood->config(array('driver' => 'file', '...'));
	 *
	 *     // Set a new configuration setting
	 *     $antiflood->config('connection', array(
	 *          'foo' => 'bar',
	 *          '...'
	 *          ));
	 *
	 *     // Get a configuration setting
	 *     $connection = $antiflood->config('connection');
	 *
	 * @param   mixed    key to set to array, either array or config path
	 * @param   mixed    value to associate with key
	 * @return  mixed
	 */
	public function config($key = NULL, $value = NULL)
	{
		if ($key === NULL)
			return $this->_config;

		if (is_array($key))
		{
			$this->_config = $key;
		}
		else
		{
			if ($value === NULL)
				return Arr::get($this->_config, $key);

			$this->_config[$key] = $value;
		}

		return $this;
	}

	/**
	 * Overload the __clone() method to prevent cloning
	 *
	 * @return  void
	 * @throws  Antiflood_Exception
	 */
	final public function __clone()
	{
		throw new Antiflood_Exception('Cloning of Kohana_Antiflood objects is forbidden');
	}

}
