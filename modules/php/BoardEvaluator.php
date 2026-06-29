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
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);
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

        $pieceMaxMoveDistance = self::getPieceMaxPossibleMovement($board, $piece);

        if (($distance - 1) > $pieceMaxMoveDistance)
            return false;

        return BoardSpatial::canFindPathToTargetAdjacency($board, $piece->getPosition(), $targetPos, $pieceMaxMoveDistance);
    }

    public static function getPieceMaxPossibleMovement(Board $board, PlayerPiece $piece) : int
    {
        $pieceMaxMoveDistance = 2;

        if (!$piece->isFresh())
        {
            $resources = new PlayerResources($piece->getOwner());

            $ownerMorale = $resources->getMorale();

            $pieceMaxMoveDistance = $ownerMorale > 0 ? 1 : 0;
        }

        return $pieceMaxMoveDistance;
    }

    public static function countPiecesThatCanBeMovedForward(Board $board, int $playerId): int
    {
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        foreach ($playerPieces as $playerPiece)
        {

        }

        //todo; implement this:

        return 0;
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