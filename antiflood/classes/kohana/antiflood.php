<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Antiflood {

	// Merged configuration settings
	protected $config = array(
                'control_dir'             => 'application/control/antiflood',
                'control_max_requests'    => 5,
                'control_request_timeout' => 3600,
                'control_ban_time'        => 600
	);

	protected $control_dir;
	protected $control_max_requests;
        protected $control_request_timeout;
        protected $control_ban_time;

        protected $control_lock_file;
        protected $control_db;
        protected $user_ip;

	public function __construct(array $config = array())
	{
                if (!defined("DOCROOT")) die("NO DOCROOT DEFINED");

                $this->user_ip = $_SERVER["REMOTE_ADDR"];
		$this->config = $this->config_group() + $this->config;

		$this->setup($config);

	}

	public function config_group($group = 'default')
	{
		$config_file = Kohana::$config->load('antiflood');
		$config['group'] = (string) $group;
		while (isset($config['group']) AND isset($config_file->$config['group']))
		{
			$group = $config['group'];
			unset($config['group']);
			$config += $config_file->$group;
		}

		unset($config['group']);

		return $config;
	}

	public function setup(array $config = array())
	{
		if (isset($config['group']))
		{
			$config += $this->config_group($config['group']);
		}

		$this->config = $config + $this->config;

                $this->control_dir =
                   (isset($config['control_dir']) ? $config['control_dir'] : $this->config['control_dir']);
                $this->control_max_requests =
                   (isset($config['control_max_requests']) ? $config['control_max_requests'] : $this->config['control_max_requests']);
                $this->control_request_timeout =
                   (isset($config['control_request_timeout']) ? $config['control_request_timeout'] : $this->config['control_request_timeout']);
                $this->control_ban_time =
                   (isset($config['control_ban_time']) ? $config['control_ban_time'] : $this->config['control_ban_time']);
                $this->control_db = DOCROOT . $this->control_dir . "/control.db";
                $this->control_lock_file = DOCROOT . $this->control_dir . "/" . md5($this->user_ip) . ".lock";

	}


        public function check()
        {
            if (file_exists($this->control_lock_file))
            {
                $diff = time() - filemtime($this->control_lock_file);
		if ($diff > $this->control_ban_time)
                {
		    unlink($this->control_lock_file);
                    return true;
		}
                else
                {
	            touch($this->control_lock_file);
		    return false;
		}

            }
            else
            {
                return true;
            }
        }

        public function countRequests()
        {
		$control = Array();

		if (file_exists($this->control_db)) {
			$fh = fopen($this->control_db, "r");
			$control = array_merge($control, unserialize(fread($fh, filesize($this->control_db))));
			fclose($fh);
		}

		if (isset($control[$this->user_ip])) {
			if (time()-$control[$this->user_ip]["t"] < $this->control_request_timeout)
                        {
				$control[$this->user_ip]["c"]++;
			} else {
				$control[$this->user_ip]["c"] = 1;
			}
		} else {
			$control[$this->user_ip]["c"] = 1;
		}
		$control[$this->user_ip]["t"] = time();

		if ($control[$this->user_ip]["c"] >= $this->control_max_requests)
                {
			$fh = fopen($this->control_lock_file, "w");
			fwrite($fh, $this->user_ip);
			fclose($fh);
                        $control[$this->user_ip]["c"] = 0;
		}

		$fh = fopen($this->control_db, "w");
		fwrite($fh, serialize($control));
		fclose($fh);

        }

	public function __get($key)
	{
	    return isset($this->$key) ? $this->$key : NULL;
	}

	public function __set($key, $value)
	{
            $this->setup(array($key => $value));
	}

}
