<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;

class BypassLogic
{
    /**
     * @return PlayerPiece[]
     */
    public static function getBypassTargets(Board $board, int $playerId, HexId $playerHex): array
    {
        $adjacentOpponents = BoardEvaluator::getAdjacentOpponentPieces($board, $board->getBallHex(), $playerId, false);

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

        return !$board->hasPlayerInHex($destination);
    }

    public static function getByPassDest(PlayerPiece $opponent, HexID $myLocation) : HexID
    {
        $opponentPos = $opponent->getPosition();
        $dir = HexLib::GetDirectionTo($myLocation, $opponentPos);
        return HexLib::cubicToHexID(HexLIb::offsetToCubic($myLocation)->add($dir->scale(2)));
    }
}