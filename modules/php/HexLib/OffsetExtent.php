<?php

namespace Bga\Games\SuperTeam\HexLib;

use Bga\Games\SuperTeam\HexID;

class OffsetExtent
{
    private int $cMin;
    private int $cMax;
    private int $rEvenMin;
    private int $rOddMin;
    private int $rEvenMax;
    private int $rOddMax;

    public function __construct(int $cMin, int $cMax, int $rOddMin, int $rOddMax, int $rEvenMin, int $rEvenMax)
    {
        $this->cMin = $cMin;
        $this->cMax = $cMax;
        $this->rEvenMin = $rEvenMin;
        $this->rOddMin = $rOddMin;
        $this->rEvenMax = $rEvenMax;
        $this->rOddMax = $rOddMax;
    }

    public function isInsideExtent(HexID $hexID) : bool
    {
        $column = $hexID->getColumn();
        $row = $hexID->getRow();

        $isEvenColumn = ($column & 1) === 0;

        return  $column <= $this->cMax && $column >= $this->cMin &&
                $row <= ($isEvenColumn ? $this->rEvenMax : $this->rOddMax) &&
                $row >= ($isEvenColumn ? $this->rEvenMin : $this->rOddMin);
    }
}