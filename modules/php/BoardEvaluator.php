<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\HexLib\HexLib;

class BoardEvaluator
{
    public static function getFreshPlayerPieces(Board $board, int $playerId, bool $includeGoalie = false) : array
    {
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        $freshPieces = [];
        foreach($playerPieces as $piece)
        {
            if ($piece->getRole() == PlayerRole::GoalKeeper->value && !$includeGoalie)
                continue;

            if ($piece->isFresh())
                $freshPieces[] = $piece;
        }

        return $freshPieces;
    }

    public static function getExhaustedPlayerPieces(Board $board, int $playerId, bool $includeGoalie = false) : array
    {
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        $freshPieces = [];
        foreach($playerPieces as $piece)
        {
            if ($piece->getRole() == PlayerRole::GoalKeeper->value && !$includeGoalie)
                continue;

            if (!$piece->isFresh())
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

    public static function getPieceMaxPossibleMovement(Board $board, int $playerId, PlayerPiece $piece, PlayerResources $resources) : int
    {
        $pieceMaxMoveDistance = 2;

        if (!$piece->isFresh())
        {
            $moveActionContext = new MoveActionContext();
            if ($moveActionContext->doExhaustedNeedMoraleForMovement())
            {
                $ownerMorale = $resources->getMorale();

                $pieceMaxMoveDistance = $ownerMorale > 0 ? 1 : 0;
            }
            else
            {
                $pieceMaxMoveDistance = 1;
            }
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

    public static function doesPlayerGoalieHasBall(Board $board, int $playerId): bool
    {
        $playerPiece = null;

        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPiece))
        {
            if ($playerPiece->getOwner() === $playerId && $playerPiece->getRole() === PlayerRole::GoalKeeper->value)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @return PlayerPiece[]
     */
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

    public static function getPlayerPiecesNotAdjacentToOpponent(Board $board, int $playerId, bool $includeGoalie = false): array
    {
        $result = [];

        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        foreach($playerPieces as $piece)
        {
            if (!$includeGoalie && $piece->getRole() == PlayerRole::GoalKeeper->value)
                continue;

            if (!self::hasAdjacentOpponentPieces($board, $piece->getPosition(), $playerId, true))
                $result[] = $piece;
        }

        return $result;
    }

    public static function correctPositionIfInsideGoalieArea(Board $board, HexID $position) : HexID
    {
        $goalieSpecificLocations = BoardInfo::$GOALIE_SPECIFIC_LOCATIONS;

        if (array_any($goalieSpecificLocations, fn($el) => $position->equals($el)))
        {
            $relatedGoaliePlayerId = null;
            if ($position->getColumn() == 0)
            {
                $relatedGoaliePlayerId = intval(DBAdapter::getInstance()->getGameStateValue('user_in_left_id'));
            }
            else
            {
                $relatedGoaliePlayerId = intval(DBAdapter::getInstance()->getGameStateValue('user_in_right_id'));
            }


            return $board->getPlayerGoalie($relatedGoaliePlayerId)->getPosition();
        }

        return $position;
    }

    /**
     * @return PlayerPiece[]
     */
    public static function getPlayerPiecesAdjacentToOpponent(Board $board, int $playerId, bool $includeGoalie = false): array
    {
        $result = [];

        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        foreach($playerPieces as $piece)
        {
            if (!$includeGoalie && $piece->getRole() == PlayerRole::GoalKeeper->value)
                continue;

            if (self::hasAdjacentOpponentPieces($board, $piece->getPosition(), $playerId, true))
                $result[] = $piece;
        }

        return $result;
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

    public static function isInsideOpponentPenaltyArea(int $playerId, HexID $hexToCheck) : bool
    {
        $playerSide = Board::getPlayerSide($playerId);
        $column = $hexToCheck->getColumn();
        $row = $hexToCheck->getRow();

        $minColumn = -1;
        $maxColumn = -1;

        if ($playerSide == PlayerSide::Left)
        {
            $minColumn = 14;
            $maxColumn = 16;
        }
        else
        {
            $minColumn = 0;
            $maxColumn = 2;
        }

        if ($column >= $minColumn && $column <= $maxColumn)
        {
            if ($column % 2 == 0)
            {
                return $row >= 2 && $row <= 8;
            } else
            {
                return $row >= 2 && $row <= 7;
            }
        }

        return false;
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