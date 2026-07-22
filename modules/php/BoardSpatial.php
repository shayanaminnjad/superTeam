<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;
use function count;

final class BoardSpatial
{
    public static function getPlayerOwnedPiecesInFrontColumns(Board $board, int $startColumn, int $ownerId, playerSide $playerSide): array
    {
        $result = [];

        $playerPieces = $board->getPlayerPiecesByOwner($ownerId);

        foreach($playerPieces as $piece)
        {
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

    public static function getPlayerOwnedPiecesInBackwardColumns(Board $board, int $startColumn, int $ownerId, PlayerSide $playerSide): array
    {
        $result = [];
        $playerPieces = $board->getPlayerPiecesByOwner($ownerId);

        foreach($playerPieces as $piece)
        {
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

    public static function getPlayerOwnedPiecesInTheSameColumn(Board $board, int $column, int $ownerId): array
    {
        $result = [];
        $playerPieces = $board->getPlayerPiecesByOwner($ownerId);

        foreach($playerPieces as $piece)
        {
            if ($piece->getPosition()->getColumn() === $column)
                $result[] = $piece;
        }

        return $result;
    }

    public static function canFindPathToTarget(Board $board, HexID $startPos, int $maxMoveDist, bool $hasBall, int $playerId, callable $targetPredicate): bool
    {
        $queue = [];
        $head = 0;
        $visited = [];

        $queue[] = [new GraphNode($startPos, null), 0];
        $visited[$startPos->getId()] = true;

        while ($head < count($queue))
        {
            [$currentNode, $currentDepth] = $queue[$head++];

            if (!$startPos->equals($currentNode->node) && $targetPredicate($board, $currentNode->node,  $playerId))
            {
                return true;
            }

            if ($currentDepth == $maxMoveDist)
                continue;

            $currentNeighbors = HexLib::getNeighbours(HexLib::offsetToCubic($currentNode->node), 1);

            if (self::shouldPieceEndMovement($board, $currentNeighbors, $currentDepth, $hasBall, $playerId))
                continue;

            foreach ($currentNeighbors as $cubicNeighbor)
            {
                $neighborHex = HexLib::cubicToHexID($cubicNeighbor);
                if (!isset($visited[$neighborHex->getId()]) && self::isHexMoveable($board, $neighborHex))
                {
                    $queue[] = [new GraphNode($neighborHex, $currentNode), $currentDepth + 1];
                    $visited[$neighborHex->getId()] = true;
                }
            }
        }

        return false;
    }

    public static function findMoveableHexesToTarget(Board $board, HexID $startPos, int $maxMoveDist, bool $hasBall, int $playerId, callable $targetPredicate): array
    {
        $res = [];

        $validTargetsCache = [];

        $queue = [];
        $head = 0;
        $visited = [];

        $queue[] = [new GraphNode($startPos, null), 0];
        $visited[$startPos->getId()] = true;

        while ($head < count($queue))
        {
            [$currentNode, $currentDepth] = $queue[$head++];

            //TODO: account for when we start movement and we are near opponent. (this should be handled in piece selection, is it handled?)
            if (!$startPos->equals($currentNode->node))
            {
                if ($targetPredicate($board, $currentNode->node,  $playerId))
                {
                    $validTargetsCache[] = $currentNode->node;
                    $startAdjacentNode = self::TraverseToAdjacent($currentNode);

                    if (!array_any($res, fn(HexID $foundHex) => $startAdjacentNode->node->equals($foundHex)))
                        $res[] = $startAdjacentNode->node;
                }
            }

            if ($currentDepth == $maxMoveDist)
                continue;

            $currentNeighbors = HexLib::getNeighbours(HexLib::offsetToCubic($currentNode->node), 1);

            if (self::shouldPieceEndMovement($board, $currentNeighbors, $currentDepth, $hasBall, $playerId))
                continue;

            foreach ($currentNeighbors as $cubicNeighbor)
            {
                $neighborHex = HexLib::cubicToHexID($cubicNeighbor);
                if (!isset($visited[$neighborHex->getId()]))
                {
                    if (self::isHexMoveable($board, $neighborHex))
                    {
                        $queue[] = [new GraphNode($neighborHex, $currentNode), $currentDepth + 1];
                        $visited[$neighborHex->getId()] = true;
                    }
                }
                else if (array_any($validTargetsCache, fn($target) => $target->equals($neighborHex)))
                {
                    $startAdjacentNode = self::TraverseToAdjacent($currentNode);

                    if (!array_any($res, fn(HexID $foundHex) => $startAdjacentNode->node->equals($foundHex)))
                        $res[] = $startAdjacentNode->node;
                }
            }
        }

        return $res;
    }

    public static function findPathToTarget(Board $board, HexID $startPos, HexID $targetHex, int $maxMoveDist, bool $hasBall, int $playerId): array
    {
        if ($targetHex->equals($startPos))
            return [];

        $shortestPath = [];
        $queue = [];
        $head = 0;
        $visited = [];

        $queue[] = [new GraphNode($startPos, null), 0];
        $visited[$startPos->getId()] = true;

        while ($head < count($queue))
        {
            [$currentNode, $currentDepth] = $queue[$head++];

            if ($currentNode->node->equals($targetHex))
            {
                $shortestPath[] = $currentNode->node;
                $parentNode = $currentNode->parent;

                while($parentNode->parent != null) //we don't want to have root node.
                {
                    $shortestPath[] = $parentNode->node;
                    $parentNode = $parentNode->parent;
                }

                break;
            }

            if ($currentDepth == $maxMoveDist)
                continue;

            $currentNeighbors = HexLib::getNeighbours(HexLib::offsetToCubic($currentNode->node), 1);

            if (self::shouldPieceEndMovement($board, $currentNeighbors, $currentDepth, $hasBall, $playerId))
                continue;

            foreach ($currentNeighbors as $cubicNeighbor)
            {
                $neighborHex = HexLib::cubicToHexID($cubicNeighbor);
                if (!isset($visited[$neighborHex->getId()]) && self::isHexMoveable($board, $neighborHex))
                {
                    $queue[] = [new GraphNode($neighborHex, $currentNode), $currentDepth + 1];
                    $visited[$neighborHex->getId()] = true;
                }
            }
        }

        return array_reverse($shortestPath);
    }

    public static function getMoveableHexes(Board $board, HexID $startPos, int $maxMoveDist, bool $hasBall, int $playerId) : array
    {
        $res = [];
        $queue = [];
        $head = 0;
        $visited = [];

        $queue[] = [$startPos, 0];
        $visited[$startPos->getId()] = true;

        while ($head < count($queue))
        {
            [$currentHex, $currentDepth] = $queue[$head++];

            if ($currentDepth == $maxMoveDist)
                continue;

            $currentNeighbors = HexLib::getNeighbours(HexLib::offsetToCubic($currentHex), 1);

            if (self::shouldPieceEndMovement($board, $currentNeighbors, $currentDepth, $hasBall, $playerId))
                continue;

            foreach($currentNeighbors as $cubicNeighbor)
            {
                $neighborHex = HexLib::cubicToHexID($cubicNeighbor);
                if (!isset($visited[$neighborHex->getId()]) && self::isHexMoveable($board, $neighborHex))
                {
                    $res[] = $neighborHex;
                    $queue[] = [$neighborHex, $currentDepth + 1];
                    $visited[$neighborHex->getId()] = true;
                }
            }
        }

        return $res;
    }

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

    private static function shouldPieceEndMovement(Board $board, array $neighbors, int $movesSofar, bool $hasBall, int $playerId) : bool
    {
        $shouldStop = false;
        if ($hasBall && $movesSofar > 0)
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

    private static function TraverseToAdjacent(GraphNode $currentNode): GraphNode
    {
        $startAdjacentNode = $currentNode;
        $parentNode = $startAdjacentNode->parent;

        while ($parentNode->parent != null) //we don't want to have root node.
        {
            $startAdjacentNode = $parentNode;
            $parentNode = $startAdjacentNode->parent;
        }
        return $startAdjacentNode;
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