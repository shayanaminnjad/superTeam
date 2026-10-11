<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

use Bga\Games\SuperTeam\Game;

class CaptainPower_6 extends CaptainPowerBase
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            \clienttranslate("Captain 6"),
            \clienttranslate("Move up to 2 spaces when adjacent to an opponent."),
            0,1,0, 1);
    }

    public static function staticId() : int
    {
        return 6;
    }

    public function getId(): int
    {
        return self::staticId();
    }
}