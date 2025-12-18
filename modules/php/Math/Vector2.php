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

    public function normalize() : Vector2
    {
        $size = $this->size();

        return new Vector2($this->x / $size, $this->y / $size);
    }

    public function subtract(Vector2 $rhs) : Vector2
    {
        return new Vector2($this->x - $rhs->x, $this->y - $rhs->y);
    }

    public function add(Vector2 $rhs) : Vector2
    {
        return new Vector2($this->x + $rhs->x, $this->y + $rhs->y);
    }

    public function scale(float $scale) : Vector2
    {
        return new Vector2($this->x * $scale, $this->y * $scale);
    }

}