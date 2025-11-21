<?php 

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\Math\Vector2;

class HexID
{
    private int $column;
    private int $row;
    private Vector2 $cartesian;

    public function __construct(int $column, int $row)
    {
        $this->column = $column;
        $this->row = $row;

        $this->cartesian = HexLib::HexIDToCartesian($this);
    }

    public function getRow() : int
    {
        return $this->row;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function convertToArray() : array
    {
        return ['hex_column'=> $this->column, 'hex_row' =>$this->row];
    }

    public function asCartesian() : Vector2
    {
        return $this->cartesian;
    }

    public function equals(HexID $rhs) : bool
    {
        return $this->row == $rhs->row && $this->column == $rhs->column;
    }

    public function toString() : string
    {
        return "({$this->row}, {$this->column})";
    }
}