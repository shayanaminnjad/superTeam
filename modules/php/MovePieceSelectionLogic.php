<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;

final class MovePieceSelectionLogic
{
    public static function enforceMovePieceSelectionConstraints(Board $board, int $playerId, MoveTypes $moveType, bool $excludePreviouslyMoved = false) : bool
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

        self::storeMoveablePiecesIds($moveablePieces);

        return \count($moveablePieces) > 0;
    }

    public static function extractFreeMoveMoveablePieces(Board $board, int $playerId) : array
    {
        $res = [];

        $playerResources = new PlayerResources($playerId);
        $playerMorale = $playerResources->getMorale();
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);
        foreach($playerPieces as $piece)
        {
            if ($piece->getRole() == PlayerRole::GoalKeeper->value)
                continue;

            if (!$piece->isFresh() && $playerMorale == 0)
                continue;

            $moveableHexes = BoardSpatial::getMoveableHexes($board, $piece->getPosition(),1, false, $playerId);

            if (count($moveableHexes) > 0)
                $res[] = $piece;
        }

        return $res;
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

        $resources = new PlayerResources($playerId);
        $side = Board::getPlayerSide($playerId);

        foreach ($board->getPlayerPiecesByOwner($playerId) as $piece)
        {
            if ($piece->getRole() == PlayerRole::GoalKeeper->value)
                continue;

            $position = $piece->getPosition();

            $maxMoves = BoardEvaluator::getPieceMaxPossibleMovement($board, $playerId, $piece, $resources);

            //hasBall is set to false, cause a move with ball also can at least one movement, so it is a moveable piece anyway.
            $moveableHexes = BoardSpatial::getMoveableHexes($board, $position, $maxMoves,false, $playerId);

            foreach ($moveableHexes as $hex)
            {
                if ($isValidDestination === null || $isValidDestination($position->getColumn(), $hex, $side))
                {
                    $res[] = $piece;
                    break;
                }
            }
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

    private static function excludePiecesMoveThisTurn(array $moveablePieces) : array
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

    private static function storeMoveablePiecesIds(array $pieces) : void
    {
        $moveActionContext = new MoveActionContext();

        $moveActionContext->storeMoveablePieces($pieces);
    }
}