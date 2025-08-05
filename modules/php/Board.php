<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;
use Exception;

class Board
{
    private array $playerPieces;

    private HexID $ballPosition;

    //======================================================

    public function __construct($piecesData, HexId $ballPosition)
    {
        for ($i = 0; $i < count($piecesData); $i++)
        {
            $this->playerPieces[] = new PlayerPiece($piecesData[$i]);
        }

        $this->ballPosition = $ballPosition;
    }

    public function getBallHex() : HexID
    {
        return $this->ballPosition;   
    }

    public function hasPlayerInHex(HexID $hexID) : bool
    {
        for ($i = 0; $i < count($this->playerPieces); $i++)
        {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getPosition()->equals($hexID))
            {
                return true;
            }
        }

        return false;
    }

    public function tryGetPlayerPieceInHex(HexID $hexID, ?PlayerPiece &$outPlayerPiece) : bool
    {
        $found = false;
        $outPlayerPiece = null;

        for ($i = 0; $i < count($this->playerPieces); $i++)
        {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getPosition()->equals($hexID))
            {
                $outPlayerPiece = $currentPiece;
                $found = true;
                break;
            }
        }

        return $found;
    }

    public function getPlayerPiecesInForwardColumns(int $startColumn, $ownerId) : array //This should be of type playerPiece
    {
        $result = [];

        for ($i = 0; $i < count($this->playerPieces); $i++)
        {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getOwner() === $ownerId && $currentPiece->getPosition()->getColumn() > $startColumn)
            {
                $result[] = $currentPiece;
                break;
            }
        }

        return $result;
    }

    public function getPlayerPiecesInBackwardColumns(int $startColumn, $ownerId) : array
    {
        $result = [];

        for ($i = 0; $i < count($this->playerPieces); $i++)
        {
            $currentPiece = $this->playerPieces[$i];
            $pieceCol = $currentPiece->getPosition()->getColumn();

            if ($currentPiece->getOwner() === $ownerId &&  $currentPiece->hasValidPosition() && $pieceCol < $startColumn)
            {
                $result[] = $currentPiece;
                break;
            }
        }

        return $result;
    }

    public function getPlayerPiecesInTheSameColumn(int $column, $ownerId) : array
    {
        $result = [];

        for ($i = 0; $i < count($this->playerPieces); $i++)
        {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getOwner() === $ownerId && $currentPiece->getPosition()->getColumn() === $column)
            {
                $result[] = $currentPiece;
                break;
            }
        }

        return $result;
    }

    public function getPlayerPiecesByOwner(int $owner) : array
    {
        $result = [];

        for ($i = 0; $i < count($this->playerPieces); $i++)
        {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getOwner() === $owner)
            {
                $result[] = $currentPiece;
            }
        }

        return $result;
    }
}