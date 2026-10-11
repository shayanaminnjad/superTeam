<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

use Bga\Games\SuperTeam\Game;

class CaptainPower_4 extends CaptainPowerBase
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            \clienttranslate("Captain 4"),
            \clienttranslate("Gain an extra movement or Morale for any successful tactic this captain is involved in."),
            1,0,0, 1);
    }

    public static function staticId() : int
    {
        return 4;
    }

    public function getId(): int
    {
        return self::staticId();
    }
}