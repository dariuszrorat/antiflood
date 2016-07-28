<?php

defined('SYSPATH') or die('No direct script access.');

abstract class Kohana_Antiflood_Nosql extends Antiflood
{
    /**
     * @var  string the antiflood control directory
     */
    protected $_control_lock_key;
    protected $_control_db_key;

    /**
     * @var  Nosql client
     */

    protected $_client = null;

    protected function _load_configuration()
    {
        parent::_load_configuration();

        $this->_control_db_key = 'db_' . sha1($this->_control_key);
        $this->_control_lock_key = 'lock_' . sha1($this->_control_key);
        return;
    }

    /**
     * Check if user locked
     *
     * @return  bool
     */
    public function check()
    {
        $serialized = $this->_client->get($this->_control_lock_key);
        if ($serialized !== null)
        {
            $data = unserialize($serialized);
            $now = time();
            $diff = $now - $data['time'];
            if ($diff > $this->_control_ban_time)
            {
                $this->_client->del($this->_control_lock_key);
                return true;
            } else
            {
                $data['time'] = $now;
                $this->_client->set($this->_control_lock_key, serialize($data));
                return false;
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
        $control = null;
        $request_count = 0;

        $serialized = $this->_client->get($this->_control_db_key);
        $control = ($serialized !== null) ? unserialize($serialized) : null;

        $now = time();
        if ($control !== null)
        {
            if ($now - $control["time"] < $this->_control_request_timeout)
            {
                $control["count"] ++;
            } else
            {
                $control["count"] = 1;
            }
        } else
        {
            $control["count"] = 1;
        }
        $control["time"] = $now;

        if ($control["count"] >= $this->_control_max_requests)
        {
            $this->_client->set($this->_control_lock_key, serialize(
                            array(
                                'key' => $this->_control_key,
                                'time' => $now)));
            $control["count"] = 0;
        }
        $request_count = $control["count"];

        $this->_client->set($this->_control_db_key, serialize($control));
        return $request_count;
    }

    /**
     * Delete current antiflood control method
     *
     * @return  void
     */

    public function delete()
    {
        $this->_client->del($this->_control_db_key);
        $this->_client->del($this->_control_lock_key);
        return;
    }

    /**
     * Delete all antiflood controls method
     *
     * @return  void
     */

    public function delete_all()
    {
        return;
    }

}
