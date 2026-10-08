<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

use Bga\Games\SuperTeam\Game;

class CaptainPower_21 extends CaptainPowerBase
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            \clienttranslate("Captain 21"),
            \clienttranslate("GAIN A MORALE AFTER A SUCCESSFUL DRIBBLE!"),
            0,1,1, 0);
    }

    public static function staticId() : int
    {
        return 21;
    }

    public function getId(): int
    {
        return self::staticId();
    }
}