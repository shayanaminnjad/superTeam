<?php

namespace Bga\Games\SuperTeam\Math;

class HexInfo
{
    public readonly float $size;
    public readonly float $inRadius;
    public readonly float $horizontalSpacing;
    public readonly float $verticalGap;
    public readonly float $oddOffset;

    public function __construct(
        float $size,
        float $inRadius,
        float $horizontalSpacing,
        float $verticalGap,
        float $oddOffset
    ) {
        $this->size = $size;
        $this->inRadius = $inRadius;
        $this->horizontalSpacing = $horizontalSpacing;
        $this->verticalGap = $verticalGap;
        $this->oddOffset = $oddOffset;
    }
}
