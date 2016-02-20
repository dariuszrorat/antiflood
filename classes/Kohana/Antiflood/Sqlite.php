<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * [Kohana Antiflood](api/Kohana_Antiflood) Sqlite driver. Provides a sqlite based
 * driver for the Kohana Antiflood library. This driver can use the same database 
 * file and the same dir for the different URI. 
 *
 * ### Configuration example
 *
 * Below is an example of a _sqlite_ configuration.
 *
 *     return array(
 *         'sqlite' => array(
 *             'driver' => 'sqlite',
 *             'database' => APPPATH . 'control/antiflood/kohana-antiflood.sql3',
 *             'schema' => 'CREATE TABLE controls(id integer PRIMARY KEY AUTOINCREMENT, control_key varchar(255), last_access datetime, requests INTEGER, locked INTEGER, locked_access datetime)',
 *             'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
 *             'control_max_requests' => 3,
 *             'control_request_timeout' => 3600,
 *             'control_ban_time' => 600,
 *             'expiration' => 172800
 *         )
 *     )
 *
 * #### General antiflood group configuration settings
 *
 * Below are the settings available to all types of antiflood driver.
 *
 * Name                      | Required | Description
 * ------------------------- | -------- | ---------------------------------------------------------------
 * driver                    | __YES__  | (_string_) The driver type to use
 * database                  | __YES__  | (_string_) The antiflood database file to use for this antiflood instance
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
class Kohana_Antiflood_Sqlite extends Antiflood_Database
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
            throw new Antiflood_Exception('Database path not available in Kohana Antiflood configuration');
        }
        // Load new Sqlite DB
        $this->_db = new PDO('sqlite:' . $database);

        // Test for existing DB
        $result = $this->_db->query("SELECT * FROM sqlite_master WHERE name = 'controls' AND type = 'table'")->fetchAll();

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
