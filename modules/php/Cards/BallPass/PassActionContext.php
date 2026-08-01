<?php

namespace Bga\Games\SuperTeam\Cards\BallPass;

use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\PlayerPiece;

class PassActionContext
{
    private const string LAST_PASS_RECEIVER_THIS_TURN_ID_KEY = 'last_pass_receiver_this_turn_id';
    private const string LAST_PASS_POS_THIS_TURN_KEY = 'last_pass_pos_this_turn';
    private const string PIECES_PASSED_KEY = 'pieces_passed';

    private DBAdapter $dbAdapter;
    private int $playerId;

    public function __construct(int $playerId)
    {
        $this->dbAdapter = DBAdapter::getInstance();
        $this->playerId = $playerId;
    }

    public function storeLastPassReceiverPieceData(PlayerPiece $piece) : void
    {
        $this->dbAdapter->storePlayerTempInt($this->playerId, self::LAST_PASS_RECEIVER_THIS_TURN_ID_KEY, $piece->getId());
        $this->dbAdapter->storePlayerTempStr($this->playerId, self::LAST_PASS_POS_THIS_TURN_KEY, $piece->getPosition()->toJson());
    }

    public function invalidateLastPassReceiverPieceData() : void
    {
        $this->dbAdapter->storePlayerTempInt($this->playerId, self::LAST_PASS_RECEIVER_THIS_TURN_ID_KEY, PlayerPiece::getInvalidPieceId());
        $this->dbAdapter->storePlayerTempStr($this->playerId, self::LAST_PASS_POS_THIS_TURN_KEY, HexID::GetInvalidId()->toJson());
    }

    public function getLastPassReceiverIdThisTurn() : int
    {
        return $this->dbAdapter->getPlayerTempInt($this->playerId, self::LAST_PASS_RECEIVER_THIS_TURN_ID_KEY);
    }

    public function getLastPassReceiverPosThisTurn() : HexID
    {
        $hexJson = $this->dbAdapter->getPlayerTempStr($this->playerId, self::LAST_PASS_POS_THIS_TURN_KEY);

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
            $this->invalidateLastPassReceiverPieceData();
        }

        $this->dbAdapter->storeGlobalString(self::PIECES_PASSED_KEY, '');
    }
}