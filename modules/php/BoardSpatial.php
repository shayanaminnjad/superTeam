<?php

namespace Bga\Games\SuperTeam;

final class BoardSpatial
{
    public static function GetPiecesInFrontColumns(Board $board, int $startColumn, int $ownerId): array
    {
        $result = [];

        $playerPieces = BoardSpatial::GetPlayerPieces($board, $ownerId);

        foreach($playerPieces as $piece)
        {
            if ($piece->getPosition()->getColumn() > $startColumn) 
            {
                $result[] = $piece;
            }
        }

        return $result;
    }

    private static function GetPlayerPieces(Board $board, int $ownerId) : array
    {
        $result = [];

        $pieces = $board->getPieces();

        foreach ($pieces as $piece)
        {
            if ($piece->getOwner() == $ownerId)
            {
                $result[] = $piece;
            }
        }

        return $result;
    }
}