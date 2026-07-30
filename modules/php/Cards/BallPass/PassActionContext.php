<?php

namespace Bga\Games\SuperTeam\Cards\BallPass;

use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\HexID;

class PassActionContext
{
    private const string LAST_PASS_RECEIVER_THIS_TURN_POS_KEY = 'last_pass_receiver_this_turn_pos';
    private const string PIECES_PASSED_KEY = 'pieces_passed';

    private DBAdapter $dbAdapter;
    private int $playerId;

    public function __construct(int $playerId)
    {
        $this->dbAdapter = DBAdapter::getInstance();
        $this->playerId = $playerId;
    }

    public function storeLastPassReceiverPosThisTurn(HexID $pos) : void
    {
        $this->dbAdapter->storePlayerTempStr($this->playerId, self::LAST_PASS_RECEIVER_THIS_TURN_POS_KEY, $pos->toJson());
    }

    public function getLastPassReceiverPosThisTurn() : HexID
    {
        $hexJson = $this->dbAdapter->getPlayerTempStr($this->playerId, self::LAST_PASS_RECEIVER_THIS_TURN_POS_KEY);

        if (empty($hexJson))
            return HexID::GetInvalidId();

        return HexID::fromJson($hexJson);
    }

    public function addPiecesPassed(int $id) : void
    {
        $piecesPassedIds = $this->getPiecesPassedIds();
        $piecesPassedIds[] = $id;
        $this->dbAdapter->storeGlobalString(self::PIECES_PASSED_KEY, json_encode($piecesPassedIds));
    }

    public function getPiecesPassedIds() : array
    {
        $piecesPassedIdsJson = $this->dbAdapter->getGlobalString(self::PIECES_PASSED_KEY);

        if (empty($piecesPassedIdsJson))
            return [];

        return json_decode($piecesPassedIdsJson, true);
    }

    public function reset(bool $turnContexts) : void
    {
        if ($turnContexts)
        {
            $this->dbAdapter->storePlayerTempStr($this->playerId, self::LAST_PASS_RECEIVER_THIS_TURN_POS_KEY, HexID::GetInvalidId()->toJson());
        }

        $this->dbAdapter->storeGlobalString(self::PIECES_PASSED_KEY, '');
    }
}