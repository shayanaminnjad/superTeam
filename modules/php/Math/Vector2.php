<?php

namespace Bga\Games\SuperTeam\Math;

class Vector2
{
    public int $x;

    public int $y;

    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function size() : float
    {
        return sqrt($this->x * $this->x + $this->y * $this->y);
    }

    public function toString():string
    {
        return '(' . $this->x . ',' . $this->y .')';
    }

    //============================

    public static function subtract(Vector2 $lhs, Vector2 $rhs) : Vector2
    {
        return new Vector2($lhs->x - $rhs->x, $lhs->y - $rhs->y);
    }

    public static function add(Vector2 $lhs, Vector2 $rhs) : Vector2
    {
        return new Vector2($lhs->x + $rhs->x, $lhs->y + $rhs->y);
    }

}