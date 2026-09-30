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

    public function setMorale(int $amount, bool $notify) : void
    {
        $oldMorale = $this->getMorale();

        $this->dbAdapter->DbQuery(
            "UPDATE `player` SET `resource_morale` = {$amount} WHERE `player_id` = {$this->playerId}"
        );

        if ($notify) 
        {
            $message = $amount > $oldMorale ?
                \clienttranslate('${player_name} gained ${absDiff} morale(s).') :
                \clienttranslate('${player_name} spent ${absDiff} morale(s).');

            NotificationAdapter::getInstance()->notifyAll(
                "moraleChanged",
                $message,
                [
                    "amount" => $amount,
                    "playerId" => $this->playerId,
                    "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($this->playerId),
                    "absDiff" => abs($amount - $oldMorale)
                ]);
        }
    }

    public function addMorale(int $amount, bool $notify) : void
    {
        if ($amount == 0)
            return;

        $this->setMorale($this->getMorale() + $amount, $notify);
    }

    public function getGoals() : int
    {
        $goalsScored = $this->dbAdapter->getUniqueValueFromDB(
            "SELECT `goals_scored` FROM `player` WHERE `player_id` = {$this->playerId}"
        );

        return intval($goalsScored);
    }

    public function addOneGoal(bool $notify) : void
    {
        $currentGoals = $this->getGoals();
        $currentGoals += 1;

        $this->dbAdapter->DbQuery(
            "UPDATE `player` SET `goals_scored` = {$currentGoals}, `player_score` = {$currentGoals}
                WHERE player_id = {$this->playerId}");

        if ($notify)
        {
            NotificationAdapter::getInstance()->notifyAll('goalScored', \clienttranslate('${playerName} scored a goal!'), array(
                "playerId" => $this->playerId,
                "playerName" => NotificationAdapter::getInstance()->getPlayerNameById($this->playerId),
                "goals" => $currentGoals,
                "score" => $currentGoals
            ));
        }
    }
}
