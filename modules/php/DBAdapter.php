<?php

namespace Bga\Games\SuperTeam;

class DBAdapter
{
    private $getListFn;
    private $getOneFn;
    private $getUniqueFn;
    private $queryFn;

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new DBAdapter();
        }

        return self::$instance;
    }

    private function __construct() {}

    public function init(callable $getListFn, callable $getOneFn, callable $getUnique, callable $execFn)
    {
        $this->getListFn = $getListFn;
        $this->getOneFn  = $getOneFn;
        $this->queryFn   = $execFn;
        $this->getUniqueFn = $getUnique;
    }

    public function initGlobals(): void
    {
        $row = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM st_globals");

        assert($row == 0, "init globals shouldn't be called more than once!");

        if ($row == 0) {
            $this->DbQuery("INSERT INTO st_globals () VALUES ()");
        }
    }

    public function getUniqueValueFromDB($sql)
    {
        return ($this->getUniqueFn)($sql);
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

    public function storeGlobalString(string $entry, string $value)
    {
        $this->DbQuery("UPDATE `globals` SET `$entry` = `$value`");
    }

    public function getGlobalString(string $entry): string
    {
        $row = $this->getObjectFromDB("SELECT `$entry` FROM `st_globals`");

        return $row[$entry];
    }
}
