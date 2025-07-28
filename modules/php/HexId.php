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
}