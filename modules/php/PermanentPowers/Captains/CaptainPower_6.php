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
            \clienttranslate("MOVE UP TO TWO SPACES WHEN ADJACENT TO AN OPPONENT!"),
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