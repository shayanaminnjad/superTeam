<?php

namespace Bga\Games\SuperTeam\HexLib;

use Bga\Games\SuperTeam\HexID;

class CubicCoordinate
{
    private int $q;
    private int $r;
    private int $s;

    public function __construct(int $q, int $r, int $s)
    {
        $this->q = $q;
        $this->r = $r;
        $this->s = $s;
    }

    public function getQ() : int
    {
        return $this->q;
    }

    public function getR() : int
    {
        return $this->r;
    }

    public function getS() : int
    {
        return $this->s;
    }

    public function add($rhs) : CubicCoordinate
    {
        return new CubicCoordinate($this->q + $rhs->q, $this->r + $rhs->r, $this->s + $rhs->s);
    }

    public function subtract($rhs) : CubicCoordinate
    {
        return new CubicCoordinate($this->q - $rhs->q, $this->r - $rhs->r, $this->s - $rhs->s);
    }

    public function scale(int $factor):CubicCoordinate
    {
        return new CubicCoordinate($this->q * $factor, $this->r * $factor, $this->s * $factor);
    }

    //====================================

    public static function distance(CubicCoordinate $start,CubicCoordinate $end) : int
    {
        $diff = $end->subtract($start);

        return (abs($diff->q) + abs($diff->r) + abs($diff->s)) * 0.5;
    }
}