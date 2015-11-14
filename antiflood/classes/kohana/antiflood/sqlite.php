<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * [Kohana Antiflood](api/Kohana_Antiflood) Sqlite driver. Provides a sqlite based
 * driver for the Kohana Antiflood library. This driver can use the same database 
 * file and the same dir for the different URI. 
 *
 * ### Configuration example
 *
 * Below is an example of a _file_ server configuration.
 *
 *     return array(
 * 	'sqlite'   => array(
 * 		'driver'             => 'sqlite',
 * 		'database'           => APPPATH.'control/antiflood/kohana-antiflood.sql3',
 * 		'schema'             => 'CREATE TABLE controls(id integer PRIMARY KEY AUTOINCREMENT, ip VARCHAR(20), requests INTEGER, locked INTEGER)',
 *              'control_max_requests'    => 3,
 *              'control_request_timeout' => 3600,
 *              'control_ban_time'        => 600
 * 	),
 *     )
 *
 * In cases where only one antiflood group is required, if the group is named `default` there is
 * no need to pass the group name when instantiating a antiflood instance. The database dir 
 * APPPATH.'control/antiflood/ must be created on disk.
 *
 * #### General antiflood group configuration settings
 *
 * Below are the settings available to all types of cache driver.
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
                // Create the caches table
                $this->_db->query(Arr::get($this->_config, 'schema', NULL));
            } catch (PDOException $e)
            {
                throw new Cache_Exception('Failed to create new SQLite caches table with the following error : :error', array(':error' => $e->getMessage()));
            }
        }
    }

}
