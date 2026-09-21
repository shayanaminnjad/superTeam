<?php

namespace Bga\Games\SuperTeam;

class MoraleAssignContext
{
    private const string ASSIGNED_MORALE = 'assigned_morale';
    private const string MORALE_ASSIGN_REMAINING = 'moraleAssignRemaining';
    private const string MORALE_ASSIGN_TRANSITION = 'morale_assign_transition';
    private const string MORALE_PLAYER_TO_PIECE_MAP = 'morale_player_to_piece_map';

    public function storeMoraleStateData(string $finishTransition, bool $bothPlayers) : void
    {
        $this->storeMoraleAssignRemaining( $bothPlayers ? 2 : 1);
        DBAdapter::getInstance()->storeGlobalString(self::MORALE_ASSIGN_TRANSITION, addslashes($finishTransition));
    }

    public function storeMoraleAssignRemaining($amount) : void
    {
        DBAdapter::getInstance()->setGameStateValue(self::MORALE_ASSIGN_REMAINING, $amount);
    }

    public function getMoraleAssignRemaining() : int
    {
        return DBAdapter::getInstance()->getGameStateValue(self::MORALE_ASSIGN_REMAINING);
    }

    public function getMoraleAssignTransition() : string
    {
        return DBAdapter::getInstance()->getGlobalString(self::MORALE_ASSIGN_TRANSITION);
    }

    public function storeMoraleAssignPieceIds(array $playerIdToPiece) : void
    {
        DBAdapter::getInstance()->storeGlobalString(self::MORALE_PLAYER_TO_PIECE_MAP, json_encode($playerIdToPiece));
    }

    public function getMoraleAssignPiecesIds() : array
    {
        $moralePlayerToPieceStr = DBAdapter::getInstance()->getGlobalString(self::MORALE_PLAYER_TO_PIECE_MAP);

        if (empty($moralePlayerToPieceStr))
            return [];
        else
            return json_decode($moralePlayerToPieceStr, true);
    }

    public function getMoraleAssigned(int $playerId): int
    {
        return DBAdapter::getInstance()->getPlayerTempInt($playerId, self::ASSIGNED_MORALE);
    }

    public function storeMoraleAssigned(int $playerId, int $amount) : void
    {
        DBAdapter::getInstance()->storePlayerTempInt($playerId, self::ASSIGNED_MORALE, $amount);
    }

    public function reset(array $players) : void
    {
        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->setGameStateValue(self::MORALE_ASSIGN_REMAINING, 0);
        $dbAdapter->storeGlobalString(self::MORALE_ASSIGN_TRANSITION, '');
        $dbAdapter->storeGlobalString(self::MORALE_PLAYER_TO_PIECE_MAP, '');

        foreach ($players as $playerId)
            $dbAdapter->storePlayerTempInt($playerId, self::ASSIGNED_MORALE, 0);
    }
}