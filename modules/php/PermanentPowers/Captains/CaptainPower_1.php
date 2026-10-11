<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

use Bga\Games\SuperTeam\Game;

class CaptainPower_1 extends CaptainPowerBase
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            \clienttranslate("Captain 1"),
            \clienttranslate("Move up to 3 spaces without the ball."),
            0,0,1, 1);
    }

    public static function staticId() : int
    {
        return 1;
    }

    public function getId(): int
    {
        return self::staticId();
    }
}