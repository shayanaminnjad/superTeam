<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\NotificationAdapter;

class PlayerResources
{
    private int $playerId;
    private int $morale;

    //======================================================

    public function __construct(int $currentPlayerId)
    {
        $this->playerId = $currentPlayerId;

        $dbAdapter = DBAdapter::getInstance();

        $playerMorale = $dbAdapter->getUniqueValueFromDB(
            "SELECT `resource_morale` FROM `player` WHERE `player_id` = {$currentPlayerId}"
        );

        $this->morale = \intval($playerMorale);
        assert($this->morale >= 0, "morale must be always equal or greater than 0!");
    }

    public function getMorale() : int
    {
        return $this->morale;
    }

    public function setMorale(int $amount, bool $notify) 
    {
        $dbAdapter = DBAdapter::getInstance();

        $this->morale = $amount;

        $dbAdapter->DbQuery(
            "UPDATE `player` SET `resource_morale` = {$amount} WHERE `player_id` = {$this->playerId}"
        );

        if ($notify) 
        {
            NotificationAdapter::getInstance()->notifyAll(
                "moraleChanged",
                "morale changed to {$amount}",
                ["amount" => $amount, "playerId" => $this->playerId]
            );
        }
    }

    public function addMorale(int $amount, bool $notify)
    {
        $this->setMorale($this->getMorale() + $amount, $notify);
    }
}
