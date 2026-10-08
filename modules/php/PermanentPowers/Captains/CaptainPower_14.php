<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

use Bga\Games\SuperTeam\Game;

class CaptainPower_14 extends CaptainPowerBase
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            \clienttranslate("Captain 14"),
            \clienttranslate("SKIP BLOCKS WHEN PASSING THE BALL!"),
            1,1,0, 0);
    }

    public static function staticId() : int
    {
        return 14;
    }

    public function getId(): int
    {
        return self::staticId();
    }
}