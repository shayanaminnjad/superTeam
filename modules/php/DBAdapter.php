<?php

namespace Bga\Games\SuperTeam;

class DBAdapter
{
    private $getListFn;
    private $getOneFn;
    private $queryFn;

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null)
        {
            self::$instance = new DBAdapter();
        }

        return self::$instance;
    }

    private function __construct(){}

    public function init(callable $getListFn, callable $getOneFn, callable $execFn) 
    {
        $this->getListFn = $getListFn;
        $this->getOneFn  = $getOneFn;
        $this->queryFn   = $execFn;
    }

    public function getObjectListFromDB($sql) 
    {
        return ($this->getListFn)($sql);
    }

    public function getObjectFromDB($sql) 
    {
        return ($this->getOneFn)($sql);
    }

    public function DbQuery($sql) 
    {
        return ($this->queryFn)($sql);
    }
}