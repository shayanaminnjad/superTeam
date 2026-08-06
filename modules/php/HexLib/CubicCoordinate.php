<?php

namespace Bga\Games\SuperTeam\HexLib;

use Bga\Games\SuperTeam\HexID;

class CubicCoordinate
{
    private float $q;
    private float $r;
    private float $s;

    public function __construct(float $q, float $r, float $s)
    {
        $this->q = $q;
        $this->r = $r;
        $this->s = $s;
    }

    public function getQ() : float
    {
        return $this->q;
    }

    public function getR() : float
    {
        return $this->r;
    }

    public function getS() : float
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

    public function toString() : string
    {
        return "(" . $this->q . ", " . $this->r . ", " . $this->s . ")";
    }

    //====================================

    public static function distance(CubicCoordinate $start,CubicCoordinate $end) : float
    {
        $diff = $end->subtract($start);

        return (abs($diff->q) + abs($diff->r) + abs($diff->s)) * 0.5;
    }
}