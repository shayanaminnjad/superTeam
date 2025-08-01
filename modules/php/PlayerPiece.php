<?php

namespace Bga\Games\SuperTeam;

class PlayerPiece
{
    private readonly array $data;

    private readonly HexID $position;

    //======================================================

    public function __construct($playerPieceData)
    {
        $this->data = $playerPieceData;

        $this->position = new HexID($playerPieceData['hex_column'], $playerPieceData['hex_row']);
    }

    public function getId() : int
    {
        return$this->data['id'];
    }

    public function getOwner() : int
    {
        return $this->data['owner_id'];
    }

    public function getRole() : int
    {
        return $this->data['piece_role'];
    }

    public function isFresh() : bool
    {
        return $this->data['is_fresh'];
    }

    public function hasValidPosition() : bool
    {
        return $this->position->getColumn() != -1 && $this->position->getRow() != -1;
    }

    public function getPosition() : HexID
    {
        return $this->position;
    }
}