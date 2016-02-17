<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * [Kohana Antiflood](api/Kohana_Antiflood) MongoDB driver. Provides a mongo based
 * driver for the Kohana Antiflood library.
 *
 * ### Configuration example
 *
 * Below is an example of a _mongo_ configuration.
 *
 *     return array(
 *          'mongo'   => array(                          // Mongo driver group
 *                  'driver'         => 'mongo',         // using Mongo driver
 *                  'control_key' => $_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_URI'],
 *                  'control_max_requests'    => 5,
 *                  'control_request_timeout' => 3600,
 *                  'control_ban_time'        => 600,
 *                  'expiration'              => 172800,
 *                  'host' => 'localhost',
 *                  'port' => 27017,
 *                  'database' => 'control',
 *                  'collection' => 'antiflood'
 *           ),
 *     )
 *
 * In cases where only one antiflood group is required, if the group is named `default` there is
 * no need to pass the group name when instantiating a antiflood instance.
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
 * expiration                | __YES__  | (_integer_) Antiflood GC expiration time
 * host                      | __YES__  | (_string_) The mongo db host
 * port                      | __YES__  | (_integer_) The mongo db port
 * database                  | __YES__  | (_string_) Database
 * collection                | __YES__  | (_string_) Collection
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
class Kohana_Antiflood_Mongo extends Antiflood implements Antiflood_GarbageCollect
{

    const DEFAULT_HOST = 'localhost';
    const DEFAULT_PORT = 27017;
    const DEFAULT_DATABASE = 'control';
    const DEFAULT_COLLECTION = 'antiflood';

    protected $_host;
    protected $_port;
    protected $_database;
    protected $_collection;
    protected $_client;
    protected $_selected_collection;

    /**
     * Constructs the Mongo antiflood driver. This method cannot be invoked externally. The mongo antiflood driver must
     * be instantiated using the `Antiflood::instance()` method.
     *
     * @param   array  $config  config
     * @throws  Antiflood_Exception
     */
    protected function __construct(array $config)
    {
        // Setup parent
        parent::__construct($config);

        $this->_host = Arr::get($this->_config, 'host', Antiflood_Mongo::DEFAULT_HOST);
        $this->_port = Arr::get($this->_config, 'port', Antiflood_Mongo::DEFAULT_PORT);
        $this->_database = Arr::get($this->_config, 'database', Antiflood_Mongo::DEFAULT_DATABASE);
        $this->_collection = Arr::get($this->_config, 'collection', Antiflood_Mongo::DEFAULT_COLLECTION);

        $dsn = 'mongodb://' . $this->_host . ':' . $this->_port;
        try
        {
            $this->_client = new MongoClient($dsn);
            $this->_selected_collection = $this->_client->selectCollection($this->_database, $this->_collection);
        }
        catch (Exception $e)
        {
            throw new Antiflood_Exception('Failed to connect to MongoDB server with the following error : :error', array(':error' => $e->getMessage()));
        }
    }

    protected function _load_configuration()
    {
        $this->_control_key = Arr::get($this->_config, 'control_key', '#');
        $this->_control_max_requests = Arr::get($this->_config, 'control_max_requests', Antiflood::DEFAULT_MAX_REQUESTS);
        $this->_control_request_timeout = Arr::get($this->_config, 'control_request_timeout', Antiflood::DEFAULT_REQUEST_TIMEOUT);
        $this->_control_ban_time = Arr::get($this->_config, 'control_ban_time', Antiflood::DEFAULT_BAN_TIME);

        $this->_expiration = Arr::get($this->_config, 'expiration', Antiflood::DEFAULT_EXPIRE);

        if ($this->_expiration < $this->_control_ban_time)
        {
            $this->_expiration = $this->_control_ban_time;
        }
    }

    /**
     * Check if user locked
     *
     * @return  bool
     */
    public function check()
    {
        $this->_load_configuration();

        $where = array('control_key' => $this->_control_key);
        $fields = array('locked', 'locked_access');
        $options = array('multiple' => false);

        try
        {
            $result = $this->_selected_collection->findOne($where, $fields);
        }
        catch (Exception $e)
        {
            throw new Antiflood_Exception('Failed to retrieve MongoDB data with the following error : :error', array(':error' => $e->getMessage()));
        }

        if ($result !== NULL)
        {
            $locked = (bool) $result['locked'];
            $locked_access = $result['locked_access'];
            $now = time();

            if ($locked === true)
            {
                $diff = $now - $locked_access;

                if ($diff > $this->_control_ban_time)
                {
                    $data = array(
                        'locked' => false,
                        'locked_access' => $now
                    );
                    try
                    {
                        $this->_selected_collection->update(
                            $where, array('$set' => $data), $options);
                    }
                    catch (Exception $e)
                    {
                        throw new Antiflood_Exception('Failed to update MongoDB data with the following error : :error', array(':error' => $e->getMessage()));
                    }

                    return true;
                } else
                {
                    $data = array(
                        'locked_access' => $now
                    );
                    try
                    {
                        $this->_selected_collection->update(
                            $where, array('$set' => $data), $options);
                    }
                    catch (Exception $e)
                    {
                        throw new Antiflood_Exception('Failed to update MongoDB data with the following error : :error', array(':error' => $e->getMessage()));
                    }

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
        $now = time();
        $where = array('control_key' => $this->_control_key);

        try
        {
            $result = $this->_selected_collection->findOne($where);
        }
        catch (Exception $e)
        {
            throw new Antiflood_Exception('Failed to retrieve MongoDB data with the following error : :error', array(':error' => $e->getMessage()));
        }

        if ($result === NULL)
        {
            $data = array(
                'control_key' => $this->_control_key,
                'last_access' => $now,
                'requests' => 1,
                'locked' => FALSE,
                'locked_access' => $now
            );
            try
            {
                $this->_selected_collection->insert($data);
            }
            catch (Exception $e)
            {
                throw new Antiflood_Exception('Failed to insert MongoDB data with the following error : :error', array(':error' => $e->getMessage()));
            }

            $request_count = 1;
        } else
        {
            $requests = $result['requests'];
            $locked_access = $result['locked_access'];
            $locked = FALSE;

            $diff = $now - $result['last_access'];

            if ($diff < $this->_control_request_timeout)
            {
                $requests++;
            } else
            {
                $requests = 1;
            }
            $last_access = $now;
            if ($requests >= $this->_control_max_requests)
            {
                $locked = TRUE;
                $requests = 0;
                $locked_access = $last_access;
            }

            $request_count = $requests;
            $options = array('multiple' => FALSE);
            $data = array(
                'last_access' => $last_access,
                'requests' => $requests,
                'locked' => $locked,
                'locked_access' => $locked_access
            );
            try
            {
                $this->_selected_collection->update(
                    $where, array('$set' => $data), $options);
            }
            catch (Exception $e)
            {
                throw new Antiflood_Exception('Failed to update MongoDB data with the following error : :error', array(':error' => $e->getMessage()));
            }

        }
        return $request_count;
    }

    /**
     * Garbage collection method that cleans any expired
     * antiflood entries from the database.
     *
     * @return  void
     */
    public function garbage_collect()
    {
        $this->_load_configuration();
        $now = time();
        $old_date = $now - $this->_expiration;

        $cond = array('$lt' => $old_date);
        $where = array('last_access' => $cond);
        $options = array('justOne' => false);
        try
        {
            $this->_selected_collection->remove($where, $options);
        }
        catch (Exception $e)
        {
            throw new Antiflood_Exception('Failed to delete MongoDB data with the following error : :error', array(':error' => $e->getMessage()));
        }

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
        $where = array('control_key' => $this->_control_key);
        $options = array('justOne' => true);
        try
        {
            $this->_selected_collection->remove($where, $options);
        }
        catch (Exception $e)
        {
            throw new Antiflood_Exception('Failed to delete MongoDB data with the following error : :error', array(':error' => $e->getMessage()));
        }

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
        try
        {
            $this->_selected_collection->remove();
        }
        catch (Exception $e)
        {
            throw new Antiflood_Exception('Failed to delete MongoDB data with the following error : :error', array(':error' => $e->getMessage()));
        }

        return;
    }

}
