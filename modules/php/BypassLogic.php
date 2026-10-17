<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;

class BypassLogic
{
    private const string BYPASS_REQUIRES_DICE_ROLL_KEY = 'bypass_requires_dice_roll';

    /**
     * @return PlayerPiece[]
     */
    public static function getPiecesThatCanBypass(int $ownerPlayer, Board $board) : array
    {
        $res = [];

        $ownerPieces = $board->getPlayerPiecesByOwner($ownerPlayer);

        foreach($ownerPieces as $piece)
        {
            if (count(self::getBypassTargets($board, $ownerPlayer, $piece->getPosition())) > 0)
                $res[] = $piece;
        }

        return $res;
    }

    /**
     * @return PlayerPiece[]
     */
    public static function getBypassTargets(Board $board, int $playerId, HexId $playerHex): array
    {
        $adjacentOpponents = BoardEvaluator::getAdjacentOpponentPieces($board, $playerHex, $playerId, false);

        $bypassableOpponents = [];
        foreach ($adjacentOpponents as $opponent) {
            if (self::canBeBypassed($board, $opponent, $playerHex))
            {
                $bypassableOpponents[] = $opponent;
            }
        }

        return $bypassableOpponents;
    }

    public static function canBeBypassed(Board $board, PlayerPiece $opponent, HexID $myLocation): bool
    {
        $destination = self::getByPassDest($opponent, $myLocation);

        if (!HexLib::getOffsetExtent()->isInsideExtent($destination))
            return false;

        return !$board->hasPlayerInHex($destination);
    }

    public static function getByPassDest(PlayerPiece $opponent, HexID $myLocation) : HexID
    {
        $opponentPos = $opponent->getPosition();
        $dir = HexLib::GetDirectionTo($myLocation, $opponentPos);
        return HexLib::cubicToHexID(HexLIb::offsetToCubic($myLocation)->add($dir->scale(2)));
    }

    public static function doesBypassRequiresDiceRoll() : bool
    {
        return DBAdapter::getInstance()->getGlobalBool(self::BYPASS_REQUIRES_DICE_ROLL_KEY);
    }

    public static function setBypassRollRequirement(bool $requiresDice) : void
    {
        DBAdapter::getInstance()->storeGlobalBool(self::BYPASS_REQUIRES_DICE_ROLL_KEY, $requiresDice);
    }
}