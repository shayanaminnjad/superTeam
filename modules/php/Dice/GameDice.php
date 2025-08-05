<?php

namespace Bga\Games\SuperTeam\GameDice;

use Bga\Games\SuperTeam\Dice\Dice;

class GameDice
{
    public static function greenDie() : Dice
    {
        //TODO: populate it with actual data!
        static $greenDie = new Dice([4, 3, 2, 0, 1, 2]);

        return $greenDie;
    }

    public static function whiteDie() : Dice
    {
        //TODO: populate it with actual data!
        static $whiteDie = new Dice([2, 1, -1, 0, 1, -1]);

        return $whiteDie;
    }
}