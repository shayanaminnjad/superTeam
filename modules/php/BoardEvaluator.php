<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;

class BoardEvaluator
{
    public static function getFreshPlayerPieces(Board $board, int $playerId) : array
    {
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        $freshPieces = [];
        foreach($playerPieces as $piece)
        {
            if ($piece->isFresh())
                $freshPieces[] = $piece;
        }

        return $freshPieces;
    }

    public static function getPiecesThatCanTackle(Board $board, int $tacklingPlayer, int $opponentId)
    {
        $res = [];
        $playerPiece = null;

        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPiece))
        {
            assert($playerPiece !== null); //to supress a warning!

            if ($playerPiece->getOwner() !== $tacklingPlayer && $playerPiece->getRole() !== PlayerRole::GoalKeeper->value)
            {
                //Here is a bit confusing, to we want our pieces, so we get opponent pieces regarding opponent Id!
                //getAdjacentOpponentPieces, saves us a call to get inactive player with current implementation, maybe I need to change it.
                $adjacentOpponents = self::getAdjacentOpponentPieces($board, $board->getBallHex(), $opponentId, false);

                foreach($adjacentOpponents as $opponent)
                {
                    if ($opponent->getCanTackle())
                    {
                        $res[] = $opponent;
                    }
                }
            }
        }

        return $res;
    }

    public static function doesPlayerHasTheBall(Board $board, int $playerId): bool
    {
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPiece))
        {
            return $playerPiece->getOwner() == $playerId;
        }

        return false;
    }

    public static function doesPieceHasTheBall(Board $board, PlayerPiece $piece): bool
    {
        return $board->getBallHex()->equals($piece->getPosition());
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
                    if (self::canMoveToAdjacentPosition($board, $playerId, $playerPiece, $opponentPiece->getPosition()))
                    {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function canMoveToAdjacentPosition(Board $board, int $playerId, PlayerPiece $piece, HexID $targetPos): bool
    {
        $distance = (int)HexLib::getOffsetDistance($piece->getPosition(), $targetPos);

        //TODO: if we are adjacent and move to another adjacent position with move forward/backward rule, it is ok.
        if ($distance == 1)
            return false; //already adjacent!

        $pieceMaxMoveDistance = self::getPieceMaxPossibleMovement($board, $playerId, $piece, new PlayerResources($playerId));

        if (($distance - 1) > $pieceMaxMoveDistance)
            return false;

        $hasBall = self::doesPieceHasTheBall($board, $piece);
        return BoardSpatial::canFindPathToTargetAdjacency($board, $piece->getPosition(), $targetPos, $pieceMaxMoveDistance, $hasBall, $playerId);
    }

    public static function getPieceMaxPossibleMovement(Board $board, int $playerId, PlayerPiece $piece, PlayerResources $resources) : int
    {
        $pieceMaxMoveDistance = 2;

        if (!$piece->isFresh())
        {
            $ownerMorale = $resources->getMorale();

            $pieceMaxMoveDistance = $ownerMorale > 0 ? 1 : 0;
        }

        if ($pieceMaxMoveDistance > 1 && self::doesPieceHasTheBall($board, $piece))
        {
            if (BoardEvaluator::doesOpponentExistInNeighbours($board, $piece->getPosition(), $playerId, true))
            {
                $pieceMaxMoveDistance = 1;
            }
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

    public static function getAdjacentOpponentPieces(Board $board, HexID $reference, int $playerId, bool $includeGoalie): array
    {
        $res = [];

        $neighbourHexes = HexLib::getNeighbours(HexLib::offsetToCubic($reference), 1);

        for ($i = 0; $i < \count($neighbourHexes); $i++) {
            $playerPiece = null;
            if ($board->tryGetPlayerPieceInHex(HexLib::cubicToHexID($neighbourHexes[$i]), $playerPiece)) {
                if ($playerPiece && $playerPiece->getOwner() !== $playerId) {
                    if ($playerPiece->getRole() !== PlayerRole::GoalKeeper->value || $includeGoalie) {
                        $res[] = $playerPiece;
                    }
                }
            }
        }

        return $res;
    }

    public static function hasAdjacentOpponentPieces(Board $board, HexID $reference, int $playerId, bool $includeGoalie) : bool
    {
        $neighbourHexes = HexLib::getNeighbours(HexLib::offsetToCubic($reference), 1);

        for ($i = 0; $i < \count($neighbourHexes); $i++) {
            $playerPiece = null;
            if ($board->tryGetPlayerPieceInHex(HexLib::cubicToHexID($neighbourHexes[$i]), $playerPiece)) {
                if ($playerPiece && $playerPiece->getOwner() !== $playerId) {
                    if ($playerPiece->getRole() !== PlayerRole::GoalKeeper->value || $includeGoalie) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function doesOpponentExistInNeighbours(Board $board, HexID $reference, int $currentPlayer, bool $includeGoalie): bool
    {
        return \count(self::getAdjacentOpponentPieces($board, $reference, $currentPlayer, $includeGoalie)) > 0;
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