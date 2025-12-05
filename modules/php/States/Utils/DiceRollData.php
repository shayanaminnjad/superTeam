<?php

namespace Bga\Games\SuperTeam\States\Utils;
use Bga\Games\SuperTeam\DBAdapter;

class DiceRollData
{
    public static int $assignedMorale;
    public static int $requiredPower;
    public static string $finishTransition;

    public static function store(int $requiredPower, string $finishTransition): void
    {
        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->setGameStateValue('required_power', $requiredPower);
        $dbAdapter->storeGlobalString('dice_roll_transition', $finishTransition);
    }

    public static function load()
    {
        $dbAdapter = DBAdapter::getInstance();
        self::$assignedMorale = $dbAdapter->getGameStateValue("assigned_morale");
        self::$requiredPower = $dbAdapter->getGameStateValue('required_power');
        self::$finishTransition = $dbAdapter->getGlobalString('dice_roll_transition');
    }
}