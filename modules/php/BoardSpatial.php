<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use function count;

final class BoardSpatial
{
    //piece queries
    public static function getPlayerOwnedPiecesInFrontColumns(Board $board, int $startColumn, int $ownerId, playerSide $playerSide, bool $includeGoalie = false, bool $includeBallCarrier = false): array
    {
        $result = [];

        $playerPieces = $board->getPlayerPiecesByOwner($ownerId);

        foreach($playerPieces as $piece)
        {
            if (($piece->getRole() == PlayerRole::GoalKeeper->value && !$includeGoalie) || (!$includeBallCarrier && BoardEvaluator::doesPieceHasTheBall($board, $piece)))
                continue;

            if ($playerSide == PlayerSide::Left)
            {
                if ($piece->getPosition()->getColumn() > $startColumn)
                {
                    $result[] = $piece;
                }
            }
            else
            {
                if ($piece->getPosition()->getColumn() < $startColumn)
                {
                    $result[] = $piece;
                }
            }
        }

        return $result;
    }

    public static function getPlayerOwnedPiecesInBackwardColumns(Board $board, int $startColumn, int $ownerId, PlayerSide $playerSide, bool $includeGoalie = false, bool $includeBallCarrier = false): array
    {
        $result = [];
        $playerPieces = $board->getPlayerPiecesByOwner($ownerId);

        foreach($playerPieces as $piece)
        {
            if (($piece->getRole() == PlayerRole::GoalKeeper->value && !$includeGoalie) || (!$includeBallCarrier && BoardEvaluator::doesPieceHasTheBall($board, $piece)))
                continue;

            if ($playerSide == PlayerSide::Left)
            {
                if ($piece->getPosition()->getColumn() < $startColumn)
                {
                    $result[] = $piece;
                }
            }
            else
            {
                if ($piece->getPosition()->getColumn() > $startColumn)
                {
                    $result[] = $piece;
                }
            }
        }

        return $result;
    }

    public static function getPlayerOwnedPiecesInTheSameColumn(Board $board, int $column, int $ownerId, bool $includeGoalie = false, bool $includeBallCarrier = false): array
    {
        $result = [];
        $playerPieces = $board->getPlayerPiecesByOwner($ownerId);

        foreach($playerPieces as $piece)
        {
            if (($piece->getRole() == PlayerRole::GoalKeeper->value && !$includeGoalie) || (!$includeBallCarrier && BoardEvaluator::doesPieceHasTheBall($board, $piece)))
                continue;

            if ($piece->getPosition()->getColumn() === $column)
                $result[] = $piece;
        }

        return $result;
    }

    //path finding
    public static function canFindPathToTarget(Board $board, PlayerPiece $movingPiece, callable $targetPredicate): bool
    {
        return count(self::findMoveableHexesToTarget($board, $movingPiece, [$movingPiece->getPosition()], $targetPredicate)) > 0;
    }

    /** @param HexID[] $pathSoFar this movement's path, from the initial position to the piece's current position */
    public static function findMoveableHexesToTarget(Board $board, PlayerPiece $movingPiece, array $pathSoFar, callable $targetPredicate): array
    {
        $immediateMoveableHexes = [];

        $playerId = $movingPiece->getOwner();
        $playerPowers = PlayerPowers::forPlayer($playerId);
        $playerMorale = new PlayerResources($playerId)->getMorale();
        $hasBall = $movingPiece->getPosition()->equals($board->getBallHex());

        $currentNeighbors = HexLib::getNeighbours(HexLib::offsetToCubic($movingPiece->getPosition()), 1);
        foreach ($currentNeighbors as $cubicNeighbor)
        {
            $neighborHex = HexLib::cubicToHexID($cubicNeighbor);

            $pathToTarget = self::exploreMovementPath($board, $neighborHex, $movingPiece, $hasBall, $pathSoFar, $playerMorale, $targetPredicate, $playerPowers);

            if (count($pathToTarget) > 0)
                $immediateMoveableHexes[] = $neighborHex;
        }

        return $immediateMoveableHexes;
    }

    private static function exploreMovementPath(Board $board, HexID $currentPos, PlayerPiece $movingPiece, bool $hasBall, array $pathSoFar, int $morale, callable $targetPredicate, PlayerPowers $powers) : array
    {
        if (!self::isHexMoveable($board, $currentPos))
            return [];

        $playerId = $movingPiece->getOwner();
        $pathSoFar[] = $currentPos;
        $movesNumber = count($pathSoFar) - 1;

        // checked with this step in the path, so a step that cancels a path based bonus (side rows) is rejected
        if ($powers->maxMovement($movingPiece, $hasBall, $pathSoFar, $morale) < $movesNumber)
            return [];

        if ($targetPredicate($board, $currentPos, $playerId))
            return $pathSoFar;

        // only a ball carried during this step stops the piece, a ball picked up here does not
        $stopNearOpponents = self::stopsNearOpponents($movingPiece, $hasBall, $powers);

        $previousPos = $pathSoFar[count($pathSoFar) - 2];
        $previousNeighbours = HexLib::getNeighbours(HexLib::offsetToCubic($previousPos), 1);
        if (self::shouldPieceEndMovement($board, $movingPiece, $previousNeighbours, $movesNumber, $stopNearOpponents, $playerId))
            return [];

        $currentNeighbors = HexLib::getNeighbours(HexLib::offsetToCubic($currentPos), 1);
        if (self::shouldPieceEndMovement($board, $movingPiece, $currentNeighbors, $movesNumber, $stopNearOpponents, $playerId))
            return [];

        $hasBall = $hasBall || $currentPos->equals($board->getBallHex());

        if ($powers->maxMovement($movingPiece, $hasBall, $pathSoFar, $morale) <= $movesNumber)
            return [];

        foreach($currentNeighbors as $cubicNeighbor)
        {
            $neighborHex = HexLib::cubicToHexID($cubicNeighbor);

            $pathToTarget = self::exploreMovementPath($board, $neighborHex, $movingPiece, $hasBall, $pathSoFar, $morale, $targetPredicate, $powers);

            if (count($pathToTarget) > 0)
                return $pathToTarget;
        }

        return [];
    }

    /** @return HexID[]*/
    public static function findImmediateMoveableNeighbours(Board $board, PlayerPiece $piece) : array
    {
        $immediateMoveableHexes = [];

        $currentNeighbors = HexLib::getNeighbours(HexLib::offsetToCubic($piece->getPosition()), 1);
        foreach ($currentNeighbors as $cubicNeighbor)
        {
            $neighborHex = HexLib::cubicToHexID($cubicNeighbor);

            if (self::isHexMoveable($board, $neighborHex))
                $immediateMoveableHexes[] = $neighborHex;
        }

        return $immediateMoveableHexes;
    }

    //general movement rules
    public static function isHexMoveable(Board $board, HexID $hexID): bool
    {
        return !$board->hasPlayerInHex($hexID) && !self::isGoalieSpecificSpace($hexID);
    }

    public static function isGoalieSpecificSpace(HexID $hexID): bool
    {
        if ($hexID->getColumn() === 0 || $hexID->getColumn() === 16)
            if ($hexID->getRow() >= 4 && $hexID->getRow() <= 6)
                return true;

        return false;
    }

    public static function isColumnInPieceForward(int $pieceColumn, int $column, PlayerSide $playerSide): bool
    {
        return $playerSide == PlayerSide::Left && $column > $pieceColumn ||
            $playerSide == PlayerSide::Right && $column < $pieceColumn;
    }

    public static function isColumnInPieceBackward(int $pieceColumn, int $column, PlayerSide $playerSide): bool
    {
        return $playerSide == PlayerSide::Left && $column < $pieceColumn ||
            $playerSide == PlayerSide::Right && $column > $pieceColumn;
    }

    //private
    private static function stopsNearOpponents(PlayerPiece $movingPiece, bool $hasBall, PlayerPowers $powers) : bool
    {
        return $hasBall && !$powers->canBallCarrierMoveNearOpponents($movingPiece);
    }

    private static function shouldPieceEndMovement(Board $board, PlayerPiece $movingPiece, array $neighbors, int $movesSofar, bool $stopNearOpponents, int $playerId) : bool
    {
        if(!$movingPiece->isFresh() && $movesSofar >= 1)
            return true;

        $shouldStop = false;
        if ($stopNearOpponents && $movesSofar > 0)
        {
            foreach ($neighbors as $cubicNeighbor)
            {
                if (BoardEvaluator::hasOpponentPieceInHex($board, HexLib::cubicToHexID($cubicNeighbor), $playerId))
                {
                    $shouldStop = true;
                    break;
                }
            }
        }

        return $shouldStop;
    }

    private static function markAncestors(MultiParentGraphNode $node, HexID $startPos, array &$visited, array &$inRes, array &$res): void
    {
        $id = $node->node->getId();
        if (isset($visited[$id]))
            return;
        $visited[$id] = true;

        foreach ($node->parents as $parent)
        {
            if ($startPos->equals($parent->node))
            {
                if (!isset($inRes[$id]))
                {
                    $inRes[$id] = true;
                    $res[] = $node->node;
                }
            }
            else
            {
                self::markAncestors($parent, $startPos, $visited, $inRes, $res);
            }
        }
    }
}

readonly class GraphNode
{
    public function __construct(HexID $node, ?GraphNode $parent)
    {
        $this->node = $node;
        $this->parent = $parent;
    }

    public HexID $node;
    public ?GraphNode $parent;
}

class MultiParentGraphNode
{
    public function __construct(HexID $node, ?MultiParentGraphNode $parent, int $depth)
    {
        $this->node = $node;

        if ($parent == null)
            $this->parents = [];
        else
            $this->parents = [$parent];

        $this->depth = $depth;
    }

    public int $depth;
    public HexID $node;
    public array $parents;
}
