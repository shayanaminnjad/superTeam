<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

use Bga\Games\SuperTeam\Game;

class CaptainPower_3 extends CaptainPowerBase
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            \clienttranslate("Captain 3"),
            \clienttranslate("On dribble, bypass the opponent."),
            1,0,1, 0);
    }

    public static function staticId() : int
    {
        return 3;
    }
    public function getId(): int
    {
        return self::staticId();
    }
}