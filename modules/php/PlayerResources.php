<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\NotificationAdapter;

class PlayerResources
{
    private DBAdapter $dbAdapter;
    private int $playerId;

    //======================================================

    public function __construct(int $currentPlayerId)
    {
        $this->dbAdapter = DBAdapter::getInstance();
        $this->playerId = $currentPlayerId;
    }

    public function getMorale() : int
    {
        $playerMorale = $this->dbAdapter->getUniqueValueFromDB(
            "SELECT `resource_morale` FROM `player` WHERE `player_id` = {$this->playerId}"
        );

        return \intval($playerMorale);
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

    public function getGoals() : int
    {
        $goalsScored = $this->getUniqueValueFromDB(
            "SELECT `goals_scored` FROM `player` WHERE `player_id` = {$this->playerId}"
        );

        return $goalsScored = intval($goalsScored);
    }

    public function addOneGoal(bool $notify)
    {
        $currentGoals = getGoals();
        $currentGoals += 1;

        $this->DbQuery(
            "UPDATE `player` SET `goals_scored` = {$currentGoals} WHERE player_id = {$this->playerId}"
        );

        if ($notify)
        {
            $this->notify->all('goalScored', 'goal scored!', array(
                "playerId" => $this->playerId,
                "goals" => $currentGoals
            ));
        }
    }
}
