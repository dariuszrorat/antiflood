<?php

defined('SYSPATH') or die('No direct script access.');

abstract class Kohana_Antiflood_Database extends Antiflood
{
    protected $_db;
    
    protected function _load_configuration()
    {
        $this->_control_max_requests = Arr::get($this->_config, 'control_max_requests', 5);
        $this->_control_request_timeout = Arr::get($this->_config, 'control_request_timeout', 3600);
        $this->_control_ban_time = Arr::get($this->_config, 'control_ban_time', 600);
    }
    
    public function check()
    {
        $this->_load_configuration();
        $statement = $this->_db->prepare("SELECT locked, locked_access FROM controls WHERE (user_ip = :user_ip) AND (uri = :uri)");

        try
        {
            $statement->execute(array(':user_ip' => $this->_user_ip, ':uri' => $this->_uri));
        } catch (PDOException $e)
        {
            throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
        }

        if ($result = $statement->fetch(PDO::FETCH_OBJ))
        {            
            $locked = (bool) $result->locked;
            $locled_access = $result->locked_access;
            $now = date('Y-m-d H:i:s');
            
            if ($locked === true)
            {
                $diff = $this->_seconds_between($now, $locled_access);
                if ($diff > $this->_control_ban_time)
                {
                    $statement = $this->_db->prepare("UPDATE controls SET locked_access = :locked_access, locked = 0 WHERE (user_ip = :user_ip) AND (uri = :uri)");
                    try
                    {
                        $statement->execute(array(':locked_access' => $now, ':user_ip' => $this->_user_ip, ':uri' => $this->_uri));
                    } catch (PDOException $e)
                    {
                        throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
                    }

                    //return (bool) $statement->rowCount(); //true
                    return true;
                } else
                {
                    $statement = $this->_db->prepare("UPDATE controls SET locked_access = :locked_access WHERE (user_ip = :user_ip) AND (uri = :uri)");

                    try
                    {
                        $statement->execute(array(':locked_access' => $now, ':user_ip' => $this->_user_ip, ':uri' => $this->_uri));
                    } catch (PDOException $e)
                    {
                        throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
                    }

                    //return !((bool) $statement->rowCount());
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

    public function count_requests()
    {
        $this->_load_configuration();
        $statement = $this->_db->prepare("SELECT * FROM controls WHERE (user_ip = :user_ip) AND (uri = :uri)");

        try
        {
            $statement->execute(array(':user_ip' => $this->_user_ip, ':uri' => $this->_uri));
        } catch (PDOException $e)
        {
            throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
        }

        if (!$result = $statement->fetch(PDO::FETCH_OBJ))
        {
            $statement = $this->_db->prepare("INSERT INTO controls (user_ip, uri, last_access, requests, locked, locked_access) VALUES (:user_ip, :uri, :last_access, 1, 0, :last_access)");

            try
            {
                $statement->execute(array(':user_ip' => $this->_user_ip, ':uri' => $this->_uri, ':last_access' => date('Y-m-d H:i:s')));
            } catch (PDOException $e)
            {
                throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
            }
        } else
        {
            $requests = $result->requests;
            $locked_access = $result->locked_access;
            $locked = 0;
            $now = date('Y-m-d H:i:s');
            $diff = $this->_seconds_between($now, $result->last_access);

            if ($diff < $this->_control_request_timeout)
            {
                $requests++;
            } else
            {
                $requests = 1;
            }
            $last_access = date('Y-m-d H:i:s');
            if ($requests >= $this->_control_max_requests)
            {
                $locked = 1;
                $requests = 0;
                $locked_access = $last_access;
            }

            $statement = $this->_db->prepare("UPDATE controls SET last_access = :last_access, requests = :requests, locked = :locked, locked_access = :locked_access WHERE (user_ip = :user_ip) AND (uri = :uri)");

            try
            {
                $statement->execute(array(':last_access' => $last_access, ':requests' => $requests, ':locked' => $locked, ':locked_access' => $locked_access, ':user_ip' => $this->_user_ip, ':uri' => $this->_uri));
            } catch (PDOException $e)
            {
                throw new Antiflood_Exception('There was a problem querying the local SQLite3 database. :error', array(':error' => $e->getMessage()));
            }
        }
    }
    
}