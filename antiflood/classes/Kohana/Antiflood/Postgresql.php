<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * [Kohana Antiflood](api/Kohana_Antiflood) PostgreSQL driver. Provides a postgresql based
 * driver for the Kohana Antiflood library. This driver can use the same database
 * for the different URI.
 *
 * ### Configuration example
 *
 * Below is an example of a _mysql_ configuration.
 *
 *     return array(
 *            'postgresql' => array(
 *                'driver' => 'postgresql',
 *                'hostname' => 'localhost',
 *                'database' => 'finance',
 *                'username' => 'postgres',
 *                'password' => '',
 *                'schema' =>
 *                'CREATE TABLE controls' .
 *                '(' .
 *                '  id serial NOT NULL,' .
 *                '  user_ip character varying(20) NOT NULL,' .
 *                '  uri character varying(255) NOT NULL,' .
 *                '  last_access bigint NOT NULL,' .
 *                '  requests integer NOT NULL,' .
 *                '  locked integer NOT NULL,' .
 *                '  locked_access bigint NOT NULL,' .
 *                '  CONSTRAINT pk_controls PRIMARY KEY (id)' .
 *                ')',
 *                'control_max_requests' => 3,
 *                'control_request_timeout' => 3600,
 *                'control_ban_time' => 600,
 *                'expiration' => 172800
 *            ),
 *         )
 *
 * #### General antiflood group configuration settings
 *
 * Below are the settings available to all types of antiflood driver.
 *
 * Name                      | Required | Description
 * ------------------------- | -------- | ---------------------------------------------------------------
 * driver                    | __YES__  | (_string_) The driver type to use
 * hostname                  | __YES__  | (_string_) The antiflood database hostname to use for this antiflood instance
 * database                  | __YES__  | (_string_) The antiflood database to use for this antiflood instance
 * username                  | __YES__  | (_string_) The antiflood database username to use for this antiflood instance
 * password                  | __YES__  | (_string_) The antiflood database password to use for this antiflood instance
 * schema                    | __YES__  | (_string_) The antiflood table schema to use for this antiflood instance
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
class Kohana_Antiflood_Postgresql extends Antiflood_Database
{

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

        $database = Arr::get($this->_config, 'database', NULL);
        if ($database === NULL)
        {
            throw new Antiflood_Exception('Database name not available in Kohana Antiflood configuration');
        }
        $hostname = Arr::get($this->_config, 'hostname', 'localhost');
        $username = Arr::get($this->_config, 'username', 'postgres');
        $password = Arr::get($this->_config, 'password', 'postgres');
        $dsn = 'pgsql:host=' . $hostname . ';dbname=' . $database;
        // Load new Mysql DB
        $this->_db = new PDO($dsn, $username, $password);

        // Test for existing DB
        $result = $this->_db->query("SELECT * FROM information_schema.tables WHERE table_schema = '" . $database . "' AND table_name = 'controls' LIMIT 1;")->fetchAll();
        
        // If there is no table, create a new one
        if (0 == count($result))
        {
            $database_schema = Arr::get($this->_config, 'schema', NULL);

            if ($database_schema === NULL)
            {
                throw new Antiflood_Exception('Database schema not found in Kohana Antiflood configuration');
            }

            try
            {
                // Create the table
                $this->_db->query(Arr::get($this->_config, 'schema', NULL));
            } catch (PDOException $e)
            {
                throw new Antiflood_Exception('Failed to create new SQLite table with the following error : :error', array(':error' => $e->getMessage()));
            }
        }
    }

}
