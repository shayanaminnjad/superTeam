<?php

namespace Bga\Games\SuperTeam\Math;

class MinMax
{
    public readonly float $min;
    public readonly float $max;

    public function __construct(float $min, float $max)
    {
        $this->min = $min;
        $this->max = $max;
    }
}
