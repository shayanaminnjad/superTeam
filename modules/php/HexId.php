<?php 

namespace Bga\Games\SuperTeam;

class HexID
{
    private int $column;
    private int $row;

    public function __construct(int $column, int $row)
    {
        $this->column = $column;
        $this->row = $row;
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

    public function equals(HexID $rhs) : bool
    {
        return $this->row == $rhs->row && $this->column == $rhs->column;
    }
}