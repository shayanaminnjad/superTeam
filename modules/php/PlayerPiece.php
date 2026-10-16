<?php

namespace Bga\Games\SuperTeam;

class PlayerPiece
{
    private readonly array $data;

    private HexID $position;

    private bool $canTackle;

    //======================================================

    public function __construct($playerPieceData)
    {
        $this->data = $playerPieceData;

        $this->position = new HexID($playerPieceData['hex_column'], $playerPieceData['hex_row']);

        $this->canTackle = $playerPieceData['can_tackle'] === '1';
    }

    public function getId(): int
    {
        return $this->data['id'];
    }

    public function getOwner(): int
    {
        return $this->data['owner_id'];
    }

    public function getRole(): int
    {
        return $this->data['piece_role'];
    }

    public function isFresh(): bool
    {
        return $this->data['is_fresh'];
    }

    public function setFreshness(bool $isFresh, bool $withNotify) : void
    {
        $dbAdapter = DBAdapter::getInstance();
        $isFreshValue = $isFresh ? 'TRUE' : 'FALSE';
        $dbAdapter->DbQuery("UPDATE player_pieces SET is_fresh = $isFreshValue WHERE `id` = {$this->getId()}");

        if ($withNotify)
        {
            $notificationAdapter = NotificationAdapter::getInstance();
            if ($isFresh)
            {
                $notificationAdapter->notifyAll("pieceFreshed", "", array(
                    "pieceId" => $this->getId(),
                ));
            }
            else
            {
                $notificationAdapter->notifyAll("pieceExhausted", "", array(
                    "pieceId" => $this->getId(),
                ));
            }
        }
    }

    public function getCanTackle(): bool
    {
        return $this->canTackle;
    }

    public function setCanTackle(bool $canTackle, bool $updateDb)
    {
        if ($this->canTackle === $canTackle)
            return;

        $this->canTackle = $canTackle;

        if ($updateDb) 
        {
            $myId = $this->getId();
            $tackleInput = $canTackle ? 'TRUE' : 'FALSE';
            DBAdapter::getInstance()->DbQuery("UPDATE `player_pieces` SET `can_tackle` = $tackleInput WHERE `id` = $myId");
        }
    }

    public function hasValidPosition(): bool
    {
        return $this->position->getColumn() != -1 && $this->position->getRow() != -1;
    }

    /**
     * @return HexID
     */
    public function getPosition(): HexID
    {
        return $this->position;
    }

    public function setPosition(HexID $hex, bool $updateDb)
    {
        if ($this->position->equals($hex))
            return;

        $this->position = $hex;

        if ($updateDb) 
        {
            $myId = $this->getId();
            DBAdapter::getInstance()->DbQuery("UPDATE `player_pieces` SET `hex_column` = {$hex->getColumn()}, `hex_row` = {$hex->getRow()} WHERE `id` = $myId ");
        }
    }

    public static function getInvalidPieceId() : int
    {
        return -1;
    }
}
