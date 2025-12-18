<?php

namespace Bga\Games\SuperTeam;

class RequiredPower
{
    public readonly int $distance;
    public readonly array $blockingHexes;

    public function __construct(int $distance, array $blockingHexes) {
        $this->distance = $distance;
        $this->blockingHexes = $blockingHexes;
    }
    public function getTotal() : int
    {
        $distPow = ceil($this->distance / (float)2);
        $distPow = $distPow >= 0 ? $distPow : 0;

        return  $distPow + \count($this->blockingHexes);
    }
}