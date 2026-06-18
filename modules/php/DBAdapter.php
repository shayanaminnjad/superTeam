<?php

namespace Bga\Games\SuperTeam;

class DBAdapter
{
    private $getListFn;
    private $getOneFn;
    private $getUniqueFn;
    private $queryFn;
    private $collectionFn;
    private $gameStateSetter;
    private $gameStateGetter;

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new DBAdapter();
        }

        return self::$instance;
    }

    private function __construct() {}

    public function init(callable $getListFn, callable $getOneFn, callable $getUnique, callable $execFn, callable $collectionFn, callable $gameStateStter, callable $gameStateGetter)
    {
        $this->getListFn = $getListFn;
        $this->getOneFn  = $getOneFn;
        $this->queryFn   = $execFn;
        $this->getUniqueFn = $getUnique;
        $this->gameStateSetter = $gameStateStter;
        $this->gameStateGetter = $gameStateGetter;
        $this->collectionFn = $collectionFn;
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

    public function getCollectionFromDB(string $sql)
    {
        return ($this->collectionFn)($sql);
    }

    public function setGameStateValue(string $name, int $value): void
    {
        ($this->gameStateSetter)($name, $value);
    }

    public function getGameStateValue(string $name): int
    {
        return ($this->gameStateGetter)($name);
    }


    //TODO: These shouldn't be here, these are utils on data base.
    //This must be a single json entry!
    public function storeSelectedHex(int $hexColumn, int $hexRow)
    {
        $this->DbQuery("UPDATE `st_globals` SET `selected_hex_column` = $hexColumn, `selected_hex_row` = $hexRow");
    }

    public function getSelectedHex(): array
    {
        //These has to merged into a single json field!
        return $this->getObjectFromDB("SELECT selected_hex_column, selected_hex_row FROM st_globals");
    }

    public function getSelectedHexAsHexID(): HexID
    {
        $selectedHexArray = $this->getSelectedHex();
        return new HexID(\intval($selectedHexArray['selected_hex_column']), intval($selectedHexArray['selected_hex_row']));
    }

    public function storePlayerTempStr(int $playerId, string $entry, string $value): void
    {
        $value = addslashes($value);
        $this->DbQuery("UPDATE `player_temp_data` SET `$entry` = '$value' WHERE `player_id` = $playerId");
    }

    public function storePlayerTempInt(int $playerId, string $entry, int $value): void
    {
        $this->DbQuery("UPDATE `player_temp_data` SET `$entry` = $value WHERE `player_id` = $playerId");
    }

    public function getPlayerTempStr(int $playerId, string $entry): string
    {
        $row = $this->getObjectFromDB("SELECT `$entry` FROM `player_temp_data` WHERE `player_id` = $playerId");
        return $row[$entry];
    }

    public function getPlayerTempInt(int $playerId, string $entry): int
    {
        $row = $this->getObjectFromDB("SELECT `$entry` FROM `player_temp_data` WHERE `player_id` = $playerId");
        return (int)$row[$entry];
    }

    public function storeGlobalString(string $entry, string $value)
    {
        $this->DbQuery("UPDATE `st_globals` SET `$entry` = '$value'");
    }


    public function getGlobalString(string $entry): string
    {
        $row = $this->getObjectFromDB("SELECT `$entry` FROM `st_globals`");

        return $row[$entry];
    }

    public function storeGlobalInt(string $entry, int $value) : void
    {
        $this->DbQuery("UPDATE `st_globals` SET `$entry` = $value");
    }

    public function getGlobalInt(string $entry): int
    {
        $row = $this->getObjectFromDB("SELECT `$entry` FROM `st_globals`");

        return $row[$entry];
    }

    public function storeActionReturnState(int $playerId, string $class) : void
    {
        $this->storePlayerTempStr($playerId, 'action_return_state', $class);
    }

    public function getPlayerActionReturnState(int $playerId): string
    {
        return $this->getPlayerTempStr($playerId, 'action_return_state');
    }
}
