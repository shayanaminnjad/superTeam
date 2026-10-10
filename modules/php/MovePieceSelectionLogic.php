<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;

final class MovePieceSelectionLogic
{
    public static function enforceMovePieceSelectionConstraints(Board $board, int $playerId, MoveTypes $moveType, bool $excludePreviouslyMoved = false) : bool
    {
        $moveablePieces = self::routeDataToTypeExtraction($moveType, $board, $playerId, $excludePreviouslyMoved);

        self::storeMoveablePiecesIds($moveablePieces);

        return \count($moveablePieces) > 0;
    }
    
    public static function HasPieceToMove(Board $board, int $playerId, MoveTypes $moveType, bool $excludePreviouslyMoved = false) : bool
    {
        $moveablePieces = self::routeDataToTypeExtraction($moveType, $board, $playerId, $excludePreviouslyMoved);

        return \count($moveablePieces) > 0;
    }

    public static function extractFreeMoveMoveablePieces(Board $board, int $playerId) : array
    {
        $res = [];

        $playerResources = new PlayerResources($playerId);
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);
        foreach($playerPieces as $piece)
        {
            if (self::canPieceFreeMove($board, $piece, $playerResources->getMorale()))
                $res[] = $piece;
        }

        return $res;
    }

    public static function canPieceFreeMove(Board $board, PlayerPiece $piece, int $playerMorale) : bool
    {
        if ($piece->getRole() == PlayerRole::GoalKeeper->value)
            return false;

        if (!$piece->isFresh() && $playerMorale == 0)
            return false;

        return BoardSpatial::canFindPathToTarget($board, $piece, fn($board, $hex, $playerId) => true);
    }

    public static function extractForwardMoveablePieces(Board $board, int $playerId) : array
    {
        return self::extractMoveablePieces($board, $playerId,
            fn(int $pieceColumn, HexID $hex, PlayerSide $side) =>
            self::isColumnInPieceForward($pieceColumn, $hex->getColumn(), $side));
    }

    public static function extractBackwardMoveablePieces(Board $board, int $playerId) : array
    {
        return self::extractMoveablePieces($board, $playerId,
            fn (int $pieceColumn, HexID $hex, PlayerSide $side) =>
            self::isColumnInPieceBackward($pieceColumn, $hex->getColumn(), $side)
        );
    }

    public static function extractForwardNearOpponentPieces(Board $board, int $playerId) : array
    {
        return self::extractMoveablePieces($board, $playerId,
            fn (int $pieceColumn, HexID $hex, PlayerSide $side) =>
                self::isColumnInPieceForward($pieceColumn, $hex->getColumn(), $side)
                && BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)
        );
    }

    public static function extractMoveableNotAdjacentToOpponentPieces(Board $board, int $playerId) : array
    {
        $res = [];

        $moveablePieces = self::extractMoveablePieces($board, $playerId, fn(int $pieceColumn, HexID $hex, PlayerSide $side) => true);

        foreach ($moveablePieces as $piece)
        {
            if (!BoardEvaluator::hasAdjacentOpponentPieces($board, $piece->getPosition(), $playerId, false))
                $res[] = $piece;
        }

        return $res;
    }

    private static function extractBackwardNearOpponentPieces(Board $board, int $playerId) : array
    {
        return self::extractMoveablePieces($board, $playerId,
            fn (int $pieceColumn, HexID $hex, PlayerSide $side) =>
                self::isColumnInPieceBackward($pieceColumn, $hex->getColumn(), $side)
                && BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)
        );
    }

    private static function extractMoveablePieces(Board $board, int $playerId, callable $isValidDestination): array
    {
        $res = [];

        $side = Board::getPlayerSide($playerId);

        foreach ($board->getPlayerPiecesByOwner($playerId) as $piece)
        {
            if ($piece->getRole() == PlayerRole::GoalKeeper->value)
                continue;

            $foundPath = BoardSpatial::canFindPathToTarget($board, $piece,
                fn($board, $hex, $playerId) => $isValidDestination === null || $isValidDestination($piece->getPosition()->getColumn(), $hex, $side));

            if ($foundPath)
                $res[] = $piece;
        }

        return $res;
    }

    private static function isColumnInPieceForward(int $pieceColumn, int $column, PlayerSide $playerSide) : bool
    {
        return $playerSide == PlayerSide::Left && $column > $pieceColumn ||
            $playerSide == PlayerSide::Right && $column < $pieceColumn;
    }

    private static function isColumnInPieceBackward(int $pieceColumn, int $column, PlayerSide $playerSide) : bool
    {
        return $playerSide == PlayerSide::Left && $column < $pieceColumn ||
        $playerSide == PlayerSide::Right && $column > $pieceColumn;
    }

    public static function excludePiecesMoveThisTurn(array $moveablePieces) : array
    {
        $res = [];

        $defensiveMoveContext = new MoveActionContext();
        $piecesMoved = $defensiveMoveContext->getPiecesMoved();

        foreach($moveablePieces as $piece)
        {
            if (!array_any($piecesMoved, fn($pieceMoved) => $pieceMoved->id === $piece->getId()))
            {
                $res[] = $piece;
            }
        }

        return $res;
    }

    public static function storeMoveablePiecesIds(array $pieces) : void
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePieces($pieces);
    }

    /**
     * @param MoveTypes $moveType
     * @param Board $board
     * @param int $playerId
     * @param bool $excludePreviouslyMoved
     * @return array
     * @throws \BgaUserException
     */
    public static function routeDataToTypeExtraction(MoveTypes $moveType, Board $board, int $playerId, bool $excludePreviouslyMoved): array
    {
        $moveablePieces = [];

        switch ($moveType)
        {
            case MoveTypes::BACKWARD:
                $moveablePieces = self::extractBackwardMoveablePieces($board, $playerId);
                break;
            case MoveTypes::FORWARD:
                $moveablePieces = self::extractForwardMoveablePieces($board, $playerId);
                break;
            case MoveTypes::FREE:
                $moveablePieces = self::extractFreeMoveMoveablePieces($board, $playerId);
                break;
            case MoveTypes::FORWARD_NEAR_OPPONENT:
                $moveablePieces = self::extractForwardNearOpponentPieces($board, $playerId);
                break;
            case MoveTypes::BACKWARD_NEAR_OPPONENT:
                $moveablePieces = self::extractBackwardNearOpponentPieces($board, $playerId);
                break;
            default:
                throw new \BgaUserException("Invalid move type!");
        }

        if ($excludePreviouslyMoved)
            $moveablePieces = self::excludePiecesMoveThisTurn($moveablePieces);

        return $moveablePieces;
    }
}