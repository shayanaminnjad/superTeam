<?php

namespace Bga\Games\SuperTeam;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\Utility\Logger;

class PassTargetSelectionLogic
{
    public static function getPassTargetsHexes(Board $board, PassTypes $passType, int $playerId, HexId $passStart, PlayerSide $playerSide): array
    {
        $passTargetHexes = [];

        switch ($passType)
        {
            case PassTypes::CROSS:
                $passTargetHexes = self::getSameColumnPassTargets($board, $playerId, $passStart);
                break;
            case PassTypes::BACKWARD:
                $passTargetHexes = self::getBackwardPassTargets($board, $playerId, $playerSide, $passStart);
                break;
            case PassTypes::FORWARD:
                $passTargetHexes = self::getForwardPassTargets($board, $playerId, $playerSide, $passStart);
                break;
            case PassTypes::THROUGH:
                $passTargetHexes = self::getThroughPassTargets($board, $playerId, $playerSide);
                break;
            case PassTypes::NEAR_OPPONENT:
            {
                $playerPieces = $board->getPlayerPiecesByOwner($playerId);

                $passActionContext = new PassActionContext($playerId);
                $passedPiecesIds = $passActionContext->getPiecesPassedIds();

                foreach($playerPieces as $piece)
                    if ($piece->getRole() !== PlayerRole::GoalKeeper->value &&
                        !BoardEvaluator::doesPieceHasTheBall($board, $piece) &&
                        !array_any($passedPiecesIds, fn($passedPieceId) => $piece->getId() == intval($passedPieceId)))
                    {
                        $passTargetHexes[] = $piece->getPosition();
                    }
            }
            break;
        }

        return $passTargetHexes;
    }

    public static function forcePassTargets(Board $board, PassTypes $passType, int $playerId, HexId $passStart, PlayerSide $playerSide): bool
    {
        $passTargetHexes = self::getPassTargetsHexes($board, $passType, $playerId, $passStart, $playerSide);

        if (!empty($passTargetHexes))
        {
            self::storeForcedTargets($playerId, self::getPositionsAsJson($passTargetHexes));
            return true;
        }
        else
        {
            return false;
        }
    }

    private static function getForwardPassTargets(Board $board, int $playerId, PlayerSide $playerSide, HexID $passStart) : array
    {
        $pieces = BoardSpatial::getPlayerOwnedPiecesInFrontColumns($board, $passStart->getColumn(), $playerId, $playerSide);

        return self::getPiecesPositions(self::filterNotPassedPieces($playerId,$pieces));
    }

    private static function getBackwardPassTargets(Board $board, int $playerId, PlayerSide $playerSide, HexID $passStart) : array
    {
        $pieces = BoardSpatial::getPlayerOwnedPiecesInBackwardColumns($board, $passStart->getColumn(), $playerId, $playerSide);
        return self::getPiecesPositions(self::filterNotPassedPieces($playerId,$pieces));
    }
    
    private static function getSameColumnPassTargets(Board $board, int $playerId, HexID $passStart) : array
    {
        $pieces = BoardSpatial::getPlayerOwnedPiecesInTheSameColumn($board, $passStart->getColumn(), $playerId);

        return self::getPiecesPositions(self::filterNotPassedPieces($playerId, $pieces));
    }

    private static function getThroughPassTargets(Board $board, int $playerId, PlayerSide $playerSide) : array
    {
        $targets = [];
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        $passActionContext = new PassActionContext($playerId);
        $piecesPassedIds = $passActionContext->getPiecesPassedIds();

        foreach ($playerPieces as $piece)
        {
            if ($piece->getRole() === PlayerRole::GoalKeeper->value ||
                BoardEvaluator::doesPieceHasTheBall($board, $piece) ||
                array_any($piecesPassedIds, fn($passedPieceId) => $piece->getId() == intval($passedPieceId)))
            {
                continue;
            }

            $targets = array_merge($targets, HexLib::getFrontArc($piece->getPosition(), ($playerSide === PlayerSide::Left) ? 1 : -1));
        }

        return array_filter($targets, fn($hex) => $board->isHexEmpty($hex));
    }

    private static function storeForcedTargets(int $playerId, string $jsonTargets) : void
    {
        DBAdapter::GetInstance()->storePlayerTempStr($playerId, "forced_pass_targets", $jsonTargets);
    }

    private static function getPositionsAsJson(array $positions) : string
    {
        $jsonTargets = [];
        foreach($positions as $pos)
        {
            $jsonTargets[] = $pos->toJson();
        }

        return json_encode($jsonTargets);
    }

    private static function getPiecesPositions(array $pieces) : array
    {
        $positions = [];
        foreach($pieces as $piece)
        {
            $positions[] = $piece->getPosition();
        }

        return $positions;
    }

    private static function filterNotPassedPieces(int $playerId, array $pieces) : array
    {
        $passActionContext = new PassActionContext($playerId);
        $piecesPassedIds = $passActionContext->getPiecesPassedIds();

        return array_filter($pieces, fn($el) => !array_any($piecesPassedIds, fn($passedPieceId)=> $el->getId() == intval($passedPieceId)));
    }
}