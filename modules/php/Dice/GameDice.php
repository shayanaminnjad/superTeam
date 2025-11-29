<?php

namespace Bga\Games\SuperTeam\Dice;

use Bga\Games\SuperTeam\Dice\Dice;

class GameDice
{
    public static function greenDie() : Dice
    {
        static $greenDie = new Dice([3, 4, 2, 2, 3, 1]);

        return $greenDie;
    }

    public static function whiteDie() : Dice
    {
        static $whiteDie = new Dice([2, 0, -1, 1, 1, 1]);

        return $whiteDie;
    }
}