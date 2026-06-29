<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;
use function count;

final class BoardSpatial
{
    public static function GetPiecesInFrontColumns(Board $board, int $startColumn, int $ownerId, playerSide $playerSide): array
    {
        $result = [];

        $playerPieces = BoardSpatial::getPlayerPieces($board, $ownerId);

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

    public static function canFindPathToTargetAdjacency(Board $board, HexID $startPos, HexID $targetHex, int $maxMoveDist, bool $hasBall, int $playerId): bool
    {
        $queue = [];
        $head = 0;
        $visited = [];

        $queue[] = [$startPos, 0];
        $visited[$startPos->getId()] = true;

        while ($head < count($queue))
        {
            [$currentHex, $currentDepth] = $queue[$head++];

            $currentDistance = HexLib::getOffsetDistance($currentHex, $targetHex);

            if ($currentDistance == 1)
                return true;

            if ($currentDepth == $maxMoveDist)
                continue;

            $currentNeighbors = HexLib::getNeighbours(HexLib::offsetToCubic($currentHex), 1);

            if (self::shouldPieceEndMovement($board, $currentNeighbors, $currentDepth, $hasBall, $playerId))
                continue;

            foreach($currentNeighbors as $cubicNeighbor)
            {
                $neighborHex = HexLib::cubicToHexID($cubicNeighbor);
                if (!isset($visited[$neighborHex->getId()]) && !$board->hasPlayerInHex($neighborHex))
                {
                    $queue[] = [$neighborHex, $currentDepth + 1];
                    $visited[$neighborHex->getId()] = true;
                }
            }
        }

        return false;
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
                if (!isset($visited[$neighborHex->getId()]) && !$board->hasPlayerInHex($neighborHex))
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
                if (!isset($visited[$neighborHex->getId()]) && !$board->hasPlayerInHex($neighborHex))
                {
                    $res[] = $neighborHex;
                    $queue[] = [$neighborHex, $currentDepth + 1];
                    $visited[$neighborHex->getId()] = true;
                }
            }
        }

        return $res;
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

}

readonly class GraphNode
{
    public function __construct(HexID $node, ?GraphNode $parent)
    {
        $this->node = $node;
        $this->parent = $parent;
    }

    public HexID $node;
    public GraphNode $parent;
}