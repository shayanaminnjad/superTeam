<?php

namespace Bga\Games\SuperTeam;
use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\HexLib\HexLib;
use http\Exception\RuntimeException;

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
                throw new RuntimeException("near opponent is not implemented yet!");
        }

        return $passTargetHexes;
    }

    public static function forcePassTargets(Board $board, PassTypes $passType, int $playerId, HexId $passStart, PlayerSide $playerSide): void
    {
        $passTargetHexes = self::getPassTargetsHexes($board, $passType, $playerId, $passStart, $playerSide);

        if (!empty($passTargetHexes))
        {
            self::storeForcedTargets($playerId, self::getPositionsAsJson($passTargetHexes));
        }
    }

    private static function getForwardPassTargets(Board $board, int $playerId, PlayerSide $playerSide, HexID $passStart) : array
    {
        $pieces = BoardSpatial::getPlayerOwnedPiecesInFrontColumns($board, $passStart->getColumn(), $playerId, $playerSide);
        return self::getPiecesPositions($pieces);
    }

    private static function getBackwardPassTargets(Board $board, int $playerId, PlayerSide $playerSide, HexID $passStart) : array
    {
        $pieces = BoardSpatial::getPlayerOwnedPiecesInBackwardColumns($board, $passStart->getColumn(), $playerId, $playerSide);
        return self::getPiecesPositions($pieces);
    }
    
    private static function getSameColumnPassTargets(Board $board, int $playerId, HexID $passStart) : array
    {
        $pieces = BoardSpatial::getPlayerOwnedPiecesInTheSameColumn($board, $passStart->getColumn(), $playerId);
        return self::getPiecesPositions($pieces);
    }

    private static function getThroughPassTargets(Board $board, int $playerId, PlayerSide $playerSide) : array
    {
        $targets = [];
        $playerPieces = $board->getPlayerPiecesByOwner($playerId);

        foreach ($playerPieces as $piece)
        {
            $pos = $piece->getPosition();

            $hexOneSpaceAhead = null;
            $hexTwoSpacesAhead = null;

            if($playerSide == PlayerSide::Left)
            {
                $hexOneSpaceAhead = new HexID($pos->getColumn() + 1, $pos->getRow());
                $hexTwoSpacesAhead = new HexID($pos->getColumn() + 2, $pos->getRow());
            }
            else
            {
                $hexOneSpaceAhead = new HexID($pos->getColumn() - 1, $pos->getRow());
                $hexTwoSpacesAhead = new HexID($pos->getColumn() - 2, $pos->getRow());
            }

            $offsetExtent = HexLib::getOffsetExtent();
            if ($offsetExtent->isInsideExtent($hexOneSpaceAhead) && !$board->hasPlayerInHex($hexOneSpaceAhead))
            {
                $targets[] = $hexOneSpaceAhead;
            }

            if ($offsetExtent->isInsideExtent($hexTwoSpacesAhead) && !$board->hasPlayerInHex($hexTwoSpacesAhead))
            {
                $targets[] = $hexTwoSpacesAhead;
            }
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
}