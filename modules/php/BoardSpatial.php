<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\Utility\Logger;
use Couchbase\Role;
use function count;

final class BoardSpatial
{
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

    public static function canFindPathToTarget(Board $board, HexID $startPos, int $maxMoveDist, bool $hasBall, int $playerId, callable $targetPredicate): bool
    {
        $stopNearOpponents = self::stopsNearOpponents($board, $startPos, $hasBall);

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

            if (self::shouldPieceEndMovement($board, $currentNeighbors, $currentDepth, $stopNearOpponents, $playerId))
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
        $stopNearOpponents = self::stopsNearOpponents($board, $startPos, $hasBall);

        $queue = [];
        $head = 0;
        $visited = [];
        $nodeRegistry = []; // hexId => GraphNode, one canonical instance per hex

        $startNode = new MultiParentGraphNode($startPos, null, 0);
        $nodeRegistry[$startPos->getId()] = $startNode;
        $queue[] = $startNode;
        $visited[$startPos->getId()] = true;

        $targetNodes = [];

        while ($head < count($queue))
        {
            $currentNode = $queue[$head++];

            if (!$startPos->equals($currentNode->node) && $targetPredicate($board, $currentNode->node, $playerId))
            {
                $targetNodes[] = $currentNode;
            }

            if ($currentNode->depth == $maxMoveDist)
                continue;

            $currentNeighbors = HexLib::getNeighbours(HexLib::offsetToCubic($currentNode->node), 1);

            if (self::shouldPieceEndMovement($board, $currentNeighbors, $currentNode->depth, $stopNearOpponents, $playerId))
                continue;

            foreach ($currentNeighbors as $cubicNeighbor)
            {
                $neighborHex = HexLib::cubicToHexID($cubicNeighbor);
                $neighborId = $neighborHex->getId();

                if (!isset($visited[$neighborId]))
                {
                    if (self::isHexMoveable($board, $neighborHex))
                    {
                        $neighborNode = new MultiParentGraphNode($neighborHex, $currentNode, $currentNode->depth + 1);
                        $nodeRegistry[$neighborId] = $neighborNode;
                        $queue[] = $neighborNode;
                        $visited[$neighborId] = true;
                    }
                }
                else
                {
                    $existingNode = $nodeRegistry[$neighborId];

                    if ($existingNode->depth > $currentNode->depth)
                    {
                        if (!array_any($existingNode->parents, fn($p) => $p->node->equals($currentNode->node)))
                            $existingNode->parents[] = $currentNode;
                    }
                }
            }
        }

        $res = [];
        $inRes = [];
        $visited = [];
        foreach ($targetNodes as $targetNode)
            self::markAncestors($targetNode, $startPos, $visited, $inRes, $res);

        return $res;
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

    public static function findPathToTarget(Board $board, HexID $startPos, HexID $targetHex, int $maxMoveDist, bool $hasBall, int $playerId): array
    {
        $stopNearOpponents = self::stopsNearOpponents($board, $startPos, $hasBall);

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

            if (self::shouldPieceEndMovement($board, $currentNeighbors, $currentDepth, $stopNearOpponents, $playerId))
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
        $stopNearOpponents = self::stopsNearOpponents($board, $startPos, $hasBall);

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

            if (self::shouldPieceEndMovement($board, $currentNeighbors, $currentDepth, $stopNearOpponents, $playerId))
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

    private static function stopsNearOpponents(Board $board, HexID $startPos, bool $hasBall) : bool
    {
        return $hasBall;
        //todo: add player powers here.
    }

    private static function shouldPieceEndMovement(Board $board, array $neighbors, int $movesSofar, bool $stopNearOpponents, int $playerId) : bool
    {
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
