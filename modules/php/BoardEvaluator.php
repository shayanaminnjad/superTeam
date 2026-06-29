<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;

class BoardEvaluator
{
    public static function DoesPlayerHasTheBall(Board $board, int $playerId): bool
    {
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPiece))
        {
            return $playerPiece->getOwner() == $playerId;
        }

        return false;
    }

    public static function hasOpponentPieceInHex(Board $board, HexID $hex, int $askingPlayerId): bool
    {
        $playerPiece = null;
        if ($board->tryGetPlayerPieceInHex($hex, $playerPiece))
            if ($playerPiece->getOwner() != $askingPlayerId)
                return true;

        return false;
    }

    public static function canMoveAPieceNearAnOpponentPiece(Board $board, int $playerId, callable $opponentPredicate) : bool
    {
        $playerPieces = self::getPlayerPieces($board, $playerId);
        $opponentPieces = self::getOpponentPieces($board, $playerId);

        foreach ($playerPieces as $playerPiece)
        {
            foreach ($opponentPieces as $opponentPiece)
            {
                if ($opponentPredicate($playerPiece, $opponentPiece))
                {
                    if (self::canMoveToAdjacentPosition($board, $playerPiece, $opponentPiece->getPosition()))
                    {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function canMoveToAdjacentPosition(Board $board, PlayerPiece $piece, HexID $targetPos): bool
    {
        $distance = (int)HexLib::getOffsetDistance($piece->getPosition(), $targetPos);

        //TODO: if we are adjacent and move to another adjacent position with move forward/backward rule, it is ok.
        if ($distance == 1)
            return false; //already adjacent!

        $pieceMaxMoveDistance = 2;

        $resources = new PlayerResources($piece->getOwner());
        if (!$piece->isFresh())
        {
            $ownerMorale = $resources->getMorale();

            $pieceMaxMoveDistance = $ownerMorale > 0 ? 1 : 0;
        }

        if (($distance - 1) > $pieceMaxMoveDistance)
            return false;

        return BoardSpatial::canFindPathToTargetAdjacency($board, $piece->getPosition(), $targetPos, $pieceMaxMoveDistance);
    }

    public static function countPiecesThatCanBeMovedForward($board, int $playerId): int
    {
        $playerPieces = self::getPlayerPieces($board, $playerId);

        foreach ($playerPieces as $playerPiece)
        {

        }
    }

    private static function getPlayerPieces(Board $board, int $ownerId) : array
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

    private static function getOpponentPieces(Board $board, int $ownerId) : array
    {
        $result = [];

        $pieces = $board->getPieces();

        foreach ($pieces as $piece)
        {
            if ($piece->getOwner() != $ownerId)
            {
                $result[] = $piece;
            }
        }

        return $result;
    }
}