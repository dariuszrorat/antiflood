<?php defined('SYSPATH') or die('No direct script access.');

abstract class Kohana_Antiflood {

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

        protected $_user_ip;
        protected $_uri;

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
	 *     // Create an instance of a group
	 *     $foo_group = Antiflood::instance('foo');
	 *
	 *     // Access an instantiated group directly
	 *     $foo_group = Antiflood::$instances['default'];
	 *
	 * @param   string  $group  the name of the cache group to use [Optional]
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
                $this->_user_ip = $_SERVER["REMOTE_ADDR"];
                $this->_uri = $_SERVER['REQUEST_URI'];
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

    protected function _seconds_between($now, $then)
    {
        $datetime_now = new DateTime($now);
        $datetime_then = new DateTime($then);

        $days = $datetime_then->diff($datetime_now)->format('%d');
        $hours = $datetime_then->diff($datetime_now)->format('%h');
        $minutes = $datetime_then->diff($datetime_now)->format('%i');
        $seconds = $datetime_then->diff($datetime_now)->format('%s');
        return $days * 24 * 60 * 60 + $hours * 60 * 60 + $minutes * 60 + $seconds;
    }


}
