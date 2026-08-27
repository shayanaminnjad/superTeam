<?php 

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\Math\Vector2;

class HexID
{
    private int $column;
    private int $row;
    private Vector2 $cartesian;
    private static ?HexId $invalidId = null;

    public function __construct(int $column, int $row)
    {
        $this->column = $column;
        $this->row = $row;

        $this->cartesian = HexLib::HexIDToCartesian($this);
    }

    public static function GetInvalidId(): HexID
    {
        return self::$invalidId ??= new HexId(-1, -1);
    }

    public function isValid() : bool
    {
        return !self::GetInvalidId()->equals($this);
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

    public function toJson() : string
    {
        return json_encode($this->convertToArray());
    }

    public static function fromArray(array $arrayHexId) : HexID
    {
        return new HexID($arrayHexId['hex_column'], $arrayHexId['hex_row']);
    }

    public static function fromJson(string $json):HexID
    {
        $res = json_decode($json, true);

        return new HexID($res['hex_column'], $res['hex_row']);
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

    public function getId() : int
    {
        return $this->row * 10 + $this->column;
    }
}