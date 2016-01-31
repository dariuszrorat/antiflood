<?php

defined('SYSPATH') or die('No direct script access.');

abstract class Kohana_Antiflood_Database extends Antiflood implements Antiflood_GarbageCollect
{

    protected $_db;

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
        $statement = $this->_db->prepare("SELECT locked, locked_access FROM controls WHERE control_key = :control_key");

        try
        {
            $statement->execute(array(':control_key' => $this->_control_key));
        } catch (PDOException $e)
        {
            throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
        }

        if ($result = $statement->fetch(PDO::FETCH_OBJ))
        {
            $locked = (bool) $result->locked;
            $locked_access = $result->locked_access;
            $now = time();

            if ($locked === true)
            {
                $diff = $now - $locked_access;
                if ($diff > $this->_control_ban_time)
                {
                    $statement = $this->_db->prepare("UPDATE controls SET locked_access = :locked_access, locked = 0 WHERE control_key = :control_key");
                    try
                    {
                        $statement->execute(array(':locked_access' => $now, ':control_key' => $this->_control_key));
                    } catch (PDOException $e)
                    {
                        throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
                    }

                    return true;
                } else
                {
                    $statement = $this->_db->prepare("UPDATE controls SET locked_access = :locked_access WHERE control_key = :control_key");

                    try
                    {
                        $statement->execute(array(':locked_access' => $now, ':control_key' => $this->_control_key));
                    } catch (PDOException $e)
                    {
                        throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
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
        $statement = $this->_db->prepare("SELECT * FROM controls WHERE control_key = :control_key");
        $request_count = 0;

        try
        {
            $statement->execute(array(':control_key' => $this->_control_key));
        } catch (PDOException $e)
        {
            throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
        }

        if (!$result = $statement->fetch(PDO::FETCH_OBJ))
        {
            $statement = $this->_db->prepare("INSERT INTO controls (control_key, last_access, requests, locked, locked_access) VALUES (:control_key, :last_access, 1, 0, :last_access)");

            try
            {
                $statement->execute(array(':control_key' => $this->_control_key, ':last_access' => $now));
            } catch (PDOException $e)
            {
                throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
            }
            $request_count = 1;
        } else
        {
            $requests = $result->requests;
            $locked_access = $result->locked_access;
            $locked = 0;

            $diff = $now - $result->last_access;

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
                $locked = 1;
                $requests = 0;
                $locked_access = $last_access;
            }

            $request_count = $requests;
            $statement = $this->_db->prepare("UPDATE controls SET last_access = :last_access, requests = :requests, locked = :locked, locked_access = :locked_access WHERE control_key = :control_key");

            try
            {
                $statement->execute(array(':last_access' => $last_access, ':requests' => $requests, ':locked' => $locked, ':locked_access' => $locked_access, ':control_key' => $this->_control_key));
            } catch (PDOException $e)
            {
                throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
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
        $statement = $this->_db->prepare("DELETE FROM controls WHERE last_access < :old_date");

        try
        {
            $statement->execute(array(':old_date' => $old_date));
        } catch (PDOException $e)
        {
            throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
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
        $statement = $this->_db->prepare("DELETE FROM controls WHERE control_key = :control_key");

        try
        {
            $statement->execute(array(':control_key' => $this->_control_key));
        } catch (PDOException $e)
        {
            throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
        }

        return;
    }

    /**
     * Delete all antiflood control method
     *
     * @return  void
     */

    public function delete_all()
    {
        $statement = $this->_db->prepare("DELETE FROM controls");

        try
        {
            $statement->execute();
        } catch (PDOException $e)
        {
            throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
        }

        return;        
    }

}
