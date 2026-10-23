<?php

namespace Bga\Games\SuperTeam;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\HexLib\HexLib;
use RuntimeException;

class PassTargetSelectionLogic
{
    public static function getFeasiblePassTargetsHexes(Board $board, PassTypes $passType, array $remainingPassTypesAfterThis, int $playerId, HexId $passStart, PlayerSide $playerSide): array
    {
        $passTargetHexes = [];

        $passActionContext = new PassActionContext($playerId);
        $piecesPassedIds = $passActionContext->getPiecesPassedIds();
        $piecesPassed = array_map(fn($id) => $board->getPiece(intval($id)), $piecesPassedIds);

        $passTargetPieces = self::getPassTargetPieces($board, $passType, $playerId, $passStart, $playerSide, $piecesPassed);
        if ($passType == PassTypes::THROUGH_PREVIOUS)
        {
            $feasiblePieces = $passTargetPieces;
        }
        else
        {
            $notPassedPieces = self::filterNotPassedPieces($playerId, $passTargetPieces);
            $feasiblePieces = self::filterFeasibleContinuations($board, $playerId, $playerSide, $remainingPassTypesAfterThis, $notPassedPieces);
        }

        if (self::isThroughPassFamily($passType))
        {
            foreach ($feasiblePieces as $piece)
            {
                $arcHexes = HexLib::getFrontArc($piece->getPosition(), ($playerSide === PlayerSide::Left) ? 1 : -1);
                $passTargetHexes = array_merge($passTargetHexes, $arcHexes);
            }

            $passTargetHexes = array_values(array_filter($passTargetHexes, fn($hex) => $board->isHexEmpty($hex)));
        }
        else
        {
            $passTargetHexes = self::getPiecesPositions($feasiblePieces);
        }

        return $passTargetHexes;
    }

    private static function filterFeasibleContinuations(Board $board, int $playerId, PlayerSide $playerSide, array $remainingPassTypes, array $candidatePieces) : array
    {
        $piecesPassedSoFarIds = (new PassActionContext($playerId))->getPiecesPassedIds();
        $piecesPassedSoFar = array_map(fn($id) => $board->getPiece(intval($id)), $piecesPassedSoFarIds);

        return array_values(array_filter($candidatePieces, function($candidate) use ($board, $remainingPassTypes, $playerId, $playerSide, $piecesPassedSoFar) {
            return self::doIsPassSequencePossible($board, $remainingPassTypes, $playerId, $candidate, $playerSide, [...$piecesPassedSoFar, $candidate]);
        }));
    }

    public static function getPassTargetPieces(Board $board, PassTypes $passType, int $playerId, HexID $passStart, PlayerSide $playerSide, array $piecesPassedSoFar) : array
    {
        switch ($passType)
        {
            case PassTypes::CROSS:
                return self::getSameColumnPassTargets($board, $playerId, $passStart);
            case PassTypes::BACKWARD:
                return self::getBackwardPassTargets($board, $playerId, $playerSide, $passStart);
            case PassTypes::FORWARD:
                return self::getForwardPassTargets($board, $playerId, $playerSide, $passStart);
            case PassTypes::THROUGH:
                return self::getThroughPassTargets($board, $playerId);
            case PassTypes::THROUGH_BACKWARD:
                return self::getBackwardThroughPassTargets($board, $playerId, $playerSide, $passStart);
            case PassTypes::THROUGH_FORWARD:
                return self::getForwardThroughPassTargets($board, $playerId, $playerSide, $passStart);
            case PassTypes::THROUGH_PREVIOUS:
                return [$piecesPassedSoFar[count($piecesPassedSoFar) - 1]];
            case PassTypes::NEAR_OPPONENT:
                return self::getNearOpponentPassTargets($board, $passStart, $playerId);
            case PassTypes::BACKWARD_NEAR_OPPONENT:
                return self::getBackwardNearOpponentPassTargets($board, $passStart, $playerId, $playerSide);
            case PassTypes::CROSS_NEAR_OPPONENT:
                return self::getCrossNearOpponentsTargets($board, $passStart, $playerId);
            case PassTypes::FORWARD_NEAR_OPPONENT_TO_NEAR_OPPONENT:
                return self::getForwardNearOpponentToNearOpponent($board, $passStart, $playerId, $playerSide);
            default:
                throw new RuntimeException("Unknown pass type! have you updated the game with an expansion?");
        }
    }

    public static function forceFeasiblePassTargets(Board $board, PassTypes $passType, array $remainingPassTypesAfterThis, int $playerId, HexId $passStart, PlayerSide $playerSide): bool
    {
        $passTargetHexes = self::getFeasiblePassTargetsHexes($board, $passType, $remainingPassTypesAfterThis, $playerId, $passStart, $playerSide);

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

    public static function isPassSequencePossible(Board $board, array $passTypes, int $playerId, HexId $passStart, PlayerSide $playerSide): bool
    {
        $playerPiece = null;
        if ($board->tryGetPlayerPieceInHex($passStart, $playerPiece))
        {
            return self::doIsPassSequencePossible($board, $passTypes, $playerId, $playerPiece, $playerSide, []);
        }

        return false;
    }

    public static function getValidPassTargets(Board $board, int $currentPlayer, ?callable $additionalCondition): array
    {
        $forcedTargetsJson = DBAdapter::getInstance()->getPlayerTempStr($currentPlayer, "forced_pass_targets");
        $forcedTargetsArray = json_decode($forcedTargetsJson, true);

        if (!\is_array($forcedTargetsArray))
            $forcedTargetsArray = [];

        $forcedPositions = [];

        foreach($forcedTargetsArray as $forcedTarget)
        {
            $forcedPositions[] = HexID::fromJson($forcedTarget)->convertToArray();
        }

        $allPieces = $board->getPieces();

        $blockedSpaces = [];

        foreach ($allPieces as $piece) {
            if ($piece->getOwner() !== $currentPlayer && $piece->getRole() !== PlayerRole::GoalKeeper->value && (!$additionalCondition || $additionalCondition($piece))) {
                $piecePos = $piece->getPosition();
                $blockedSpaces[] = $piecePos->convertToArray();
            }
        }

        foreach (BoardInfo::$GOALIE_SPECIFIC_LOCATIONS as $goalieLocation) {
                $blockedSpaces[] = $goalieLocation->convertToArray();
        }

        $hexWithBall = $board->getBallHex();

        return [
            "hexWithBall" => $hexWithBall->convertToArray(),
            "blockedSpaces" => $blockedSpaces,
            "forcedPositions" => $forcedPositions
        ];
    }

    public static function reset(int $playerId) : void
    {
        DBAdapter::GetInstance()->storePlayerTempStr($playerId, "forced_pass_targets", "");
    }

    private static function doIsPassSequencePossible(Board $board, array $passTypes, int $playerId, PlayerPiece $passingPiece, PlayerSide $playerSide, array $playerPiecesPassed): bool
    {
        if (empty($passTypes))
            return true;

        $passType = $passTypes[0];
        $remainingPassTypes = array_slice($passTypes, 1);

        $playerPiecesPassed[] = $passingPiece;

        $targetPieces = self::getPassTargetPieces($board, $passType, $playerId, $passingPiece->getPosition(), $playerSide, $playerPiecesPassed);

        if ($passType == PassTypes::THROUGH_PREVIOUS)
        {
            $piecesNotPassed = $targetPieces;
        }
        else
        {
            $piecesNotPassed = array_filter($targetPieces, function ($target) use ($playerPiecesPassed)
            {
                return !array_any($playerPiecesPassed, fn($passed) => $passed->getId() === $target->getId());
            });
        }

        if (empty($piecesNotPassed))
            return false;

        foreach($piecesNotPassed as $piece)
        {
            $res = self::doIsPassSequencePossible($board, $remainingPassTypes, $playerId, $piece, $playerSide, [...$playerPiecesPassed]);

            if ($res)
                return true;
        }

        return false;
    }

    private static function getForwardPassTargets(Board $board, int $playerId, PlayerSide $playerSide, HexID $passStart) : array
    {
        return BoardSpatial::getPlayerOwnedPiecesInFrontColumns($board, $passStart->getColumn(), $playerId, $playerSide);
    }

    private static function getBackwardPassTargets(Board $board, int $playerId, PlayerSide $playerSide, HexID $passStart) : array
    {
        return BoardSpatial::getPlayerOwnedPiecesInBackwardColumns($board, $passStart->getColumn(), $playerId, $playerSide);
    }
    
    private static function getSameColumnPassTargets(Board $board, int $playerId, HexID $passStart) : array
    {
        return BoardSpatial::getPlayerOwnedPiecesInTheSameColumn($board, $passStart->getColumn(), $playerId);
    }

    private static function getThroughPassTargets(Board $board, int $playerId) : array
    {
        $targets = [];
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        foreach ($playerPieces as $piece)
        {
            if ($piece->getRole() === PlayerRole::GoalKeeper->value ||
                BoardEvaluator::doesPieceHasTheBall($board, $piece))
            {
                continue;
            }

            $targets[] = $piece;
        }

        return $targets;
    }

    private static function getBackwardThroughPassTargets(Board $board, int $playerId, PlayerSide $playerSide, HexID $passStart) : array
    {
        $throughPassTargets = self::getThroughPassTargets($board, $playerId);
        $forwardPassTargets = self::getBackwardPassTargets($board, $playerId, $playerSide, $passStart);

        return self::getCandidatesIntersection($forwardPassTargets, $throughPassTargets);
    }

    private static function getForwardThroughPassTargets(Board $board, int $playerId, PlayerSide $playerSide, HexID $passStart) : array
    {
        $throughPassTargets = self::getThroughPassTargets($board, $playerId);
        $forwardPassTargets = self::getForwardPassTargets($board, $playerId, $playerSide, $passStart);

        return self::getCandidatesIntersection($forwardPassTargets, $throughPassTargets);
    }

    private static function getNearOpponentPassTargets(Board $board, HexID $passStart, int $playerId) : array
    {
        if (!BoardEvaluator::doesOpponentExistInNeighbours($board, $passStart, $playerId, true))
            return [];

        $passTargetPieces = [];
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        foreach ($playerPieces as $piece)
        {
            if ($piece->getRole() !== PlayerRole::GoalKeeper->value &&
                !BoardEvaluator::doesPieceHasTheBall($board, $piece))
            {
                $passTargetPieces[] = $piece;
            }
        }
        return $passTargetPieces;
    }


    private static function getBackwardNearOpponentPassTargets(Board $board, HexID $passStart, int $playerId, PlayerSide $playerSide) : array
    {
        $nearOpponentPassTargets = self::getNearOpponentPassTargets($board, $passStart, $playerId);
        $backwardPassTargets = self::getBackwardPassTargets($board, $playerId, $playerSide, $passStart);

        return self::getCandidatesIntersection($backwardPassTargets, $nearOpponentPassTargets);
    }

    private static function getCrossNearOpponentsTargets(Board $board, HexID $passStart, int $playerId) : array
    {
        $nearOpponentPassTargets = self::getNearOpponentPassTargets($board, $passStart, $playerId);
        $crossPassTargets = self::getSameColumnPassTargets($board, $playerId, $passStart);

        return self::getCandidatesIntersection($nearOpponentPassTargets, $crossPassTargets);
    }

    private static function getForwardNearOpponentToNearOpponent(Board $board, HexID $passStart, int $playerId, PlayerSide $playerSide) : array
    {
        $nearOpponentPassTargets = self::getNearOpponentPassTargets($board, $passStart, $playerId);
        $forwardPassTargets = self::getForwardPassTargets($board, $playerId, $playerSide, $passStart);

        $forwardNearOpponentTargets = self::getCandidatesIntersection($nearOpponentPassTargets, $forwardPassTargets);

        $targets = [];
        foreach($forwardNearOpponentTargets as $piece)
        {
            if (count(BoardEvaluator::getAdjacentOpponentPieces($board, $piece->getPosition(), $playerId, false)) > 0)
                $targets[] = $piece;
        }

        return $targets;
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

    public static function isThroughPassFamily(PassTypes $passType) : bool
    {
        return $passType === PassTypes::THROUGH_BACKWARD || $passType === PassTypes::THROUGH_FORWARD ||
            $passType === PassTypes::THROUGH || $passType === PassTypes::THROUGH_PREVIOUS;
    }

    private static function getCandidatesIntersection(array $first, array $second) : array
    {
        $firstIds = array_map(
            fn(PlayerPiece $piece) => $piece->getId(),
            $first
        );

        return array_filter(
            $second,
            fn(PlayerPiece $piece) => in_array($piece->getId(), $firstIds, true)
        );
    }
}