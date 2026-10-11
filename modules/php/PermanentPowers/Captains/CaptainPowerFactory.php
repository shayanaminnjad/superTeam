<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

use Bga\Games\SuperTeam\Game;

class CaptainPowerFactory
{
    public static function GetCaptainPower(string $captainId, Game $game) : ?CaptainPowerBase
    {
        if ($captainId == CaptainPower_1::staticId())
            return new CaptainPower_1($game);
        else if ($captainId == CaptainPower_3::staticId())
            return new CaptainPower_3($game);
        else if ($captainId == CaptainPower_4::staticId())
            return new CaptainPower_4($game);
        else if ($captainId == CaptainPower_6::staticId())
            return new CaptainPower_6($game);
        else if ($captainId == CaptainPower_14::staticId())
            return new CaptainPower_14($game);
        else if ($captainId == CaptainPower_21::staticId())
            return new CaptainPower_21($game);
        else
            return null;
    }
}