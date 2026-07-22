<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\Math\HexInfo;

class Board
{
    private array $playerPieces;

    private HexID $ballPosition;

    private DBAdapter $dbadapter;

    //======================================================

    public function __construct(array $piecesData, HexId $ballPosition, DBAdapter $dBAdapter)
    {
        for ($i = 0; $i < \count($piecesData); $i++) {
            $this->playerPieces[] = new PlayerPiece($piecesData[$i]);
        }

        $this->ballPosition = $ballPosition;
        $this->dbadapter = $dBAdapter;
    }

    public function setBallLocation(HexID $location) : void
    {
        $this->dbadapter->DbQuery("UPDATE ball SET `hex_column` = {$location->getColumn()}, `hex_row` = {$location->getRow()} ");

        $this->ballPosition = $location;

        $this->updateAttackerAndDefender();
    }

    public function getBallHex(): HexID
    {
        return $this->ballPosition;
    }

    public function getPieces(): array
    {
        return $this->playerPieces;
    }

    public function getPiece(int $id) : PlayerPiece | null
    {
        foreach($this->playerPieces as $piece)
        {
            if ($piece->getId() == $id)
                return $piece;
        }

        return null;
    }

    public function hasPlayerInHex(HexID $hexID): bool
    {
        for ($i = 0; $i < \count($this->playerPieces); $i++) {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getPosition()->equals($hexID)) {
                return true;
            }
        }

        return false;
    }

    public function tryGetPlayerPieceInHex(HexID $hexID, ?PlayerPiece &$outPlayerPiece): bool
    {
        $found = false;
        $outPlayerPiece = null;

        for ($i = 0; $i < \count($this->playerPieces); $i++) {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getPosition()->equals($hexID)) {
                $outPlayerPiece = $currentPiece;
                $found = true;
                break;
            }
        }

        return $found;
    }

    public function getPlayerGoalie(int $ownerId): ?PlayerPiece
    {
        foreach ($this->playerPieces as $piece) {
            if ($piece->getOwner() === $ownerId && $piece->getRole() === PlayerRole::GoalKeeper->value)
                return $piece;
        }

        assert(false, "player must have a goalie!");

        return null;
    }

    public function getPlayerPiecesByOwner(int $owner): array
    {
        $result = [];

        for ($i = 0; $i < \count($this->playerPieces); $i++) {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getOwner() === $owner) {
                $result[] = $currentPiece;
            }
        }

        return $result;
    }

    public function getPlayerPieceData(int $pieceId): ?PlayerPiece
    {
        return array_find($this->playerPieces, fn($x) => $pieceId === $x->getId());
    }

    public function swapHexesContent(HexID $lhs, HexID $rhs, bool $swapBall) : void
    {
        $lhsPlayerPiece = null;
        $this->tryGetPlayerPieceInHex($lhs, $lhsPlayerPiece);
        assert($lhsPlayerPiece !== null, "swap hex content needs hexes with piece!");
        $lhsPos = $lhsPlayerPiece->getPosition();

        $rhsPlayerPiece = null;
        $this->tryGetPlayerPieceInHex($rhs, $rhsPlayerPiece);
        assert($rhsPlayerPiece !== null, "swap hex content needs hexes with piece!");
        $rhsPos = $rhsPlayerPiece->getPosition();

        $this->dbadapter->DbQuery(PlayerPiecesDBManager::swapPiecesQuery(
            $lhsPlayerPiece->getId(),
            $rhsPos->getRow(),
            $rhsPos->getColumn(),
            $rhsPlayerPiece->getId(),
            $lhsPos->getRow(),
            $lhsPos->getColumn()
        ));

        $lhsPlayerPiece->setPosition($rhsPos, false);
        $rhsPlayerPiece->setPosition($lhsPos, false);

        if ($swapBall) 
        {
            if ($this->getBallHex()->equals($lhs)) 
            {
                $this->setBallLocation($rhs);
            } 
            else if ($this->getBallHex()->equals($rhs)) 
            {
                $this->setBallLocation($lhs);
            }
            else 
            {
                assert(false, "when swap ball is enabled, ball should be in one of the hexes!");
            }
        }
        else
        {
            $this->updateAttackerAndDefender();
        }
    }

    public function doAllPiecesHaveValidPosition(int $playerId) : bool
    {
        $res = true;

        $playerPieces = $this->getPlayerPiecesByOwner($playerId);

        for ($i = 0; $i < count($playerPieces); $i++) {
            if (!$playerPieces[$i]->hasValidPosition()) {
                $res = false;
                break;
            }
        }

        return $res;
    }

    public static function resetBoard(bool $withNotify) : void
    {
        DBAdapter::getInstance()->DbQuery(PlayerPiecesDBManager::resetPiecesDataQuery());
        $ballPos = BoardInfo::$CENTER_POSITION;
        DBAdapter::getInstance()->DbQuery("UPDATE `ball` SET `hex_column`={$ballPos->getColumn()}, `hex_row`={$ballPos->getRow()}");

        if ($withNotify)
        {
            NotificationAdapter::getInstance()->notifyAll(
                "resetBoard",
                "",
                [
                    "ballHex" => $ballPos->convertToArray(),
                    "goalieRow" => 5]);
        }
    }

    public static function isPlayerAttacker(int $playerId) : bool
    {
        $attackerId = DBAdapter::getInstance()->getGameStateValue(('attacker'));

        return $playerId == $attackerId;
    }

    public static function getPlayerSide(int $playerId) : PlayerSide
    {
        $dbAdapter = DBAdapter::getInstance();
        $userInLeftID = intval($dbAdapter->getGameStateValue('user_in_left_id'));

        if ($userInLeftID === $playerId)
            return PlayerSide::Left;
        else
            return PlayerSide::Right;
    }

    //TODO: move these two to a helper class.
    public function getClosetBlockingPieceOrHalfWay(HexID $start, HexID $end, int $opponentId): HexID
    {
        $res = null;

        $opponentPieces = $this->getPlayerPiecesByOwner($opponentId);
        $opponentPieceLocations = [];

        for ($i = 0; $i < count($opponentPieces); $i++) {
            $opponentPieceLocations[] = $opponentPieces[$i]->getPosition();
        }

        $outBlockingHexes = HexLib::getHexesInPath($start, $end, HexLib::GetHexInfo()->size * 0.5, $opponentPieceLocations);

        if (\count($outBlockingHexes) > 0) {
            $smallestDist = PHP_FLOAT_MAX;
            foreach ($outBlockingHexes as $blockingHex) {
                $blockingHexDist = HexLib::getOffsetDistance($start, $blockingHex);

                if ($blockingHexDist < $smallestDist) {
                    $smallestDist = $blockingHexDist;
                    $res = $blockingHex;
                }
            }
        } else {
            $hexesInLine = HexLib::getHexesInLine($start, $end);
            $res = $hexesInLine[ceil((float)\count($hexesInLine) / 2.0)];
        }

        return $res;
    }

    public function getRequiredPower(HexID $start, HexID $end, int $opponentId, array &$outLineHexes, array &$outBlockingHexes): RequiredPower
    {
        $distance = HexLib::getOffsetDistance($start, $end);
        $opponentPieces = $this->getPlayerPiecesByOwner($opponentId);
        $opponentPieceLocations = [];

        for ($i = 0; $i < \count($opponentPieces); $i++) {
            $opponentPieceLocations[] = $opponentPieces[$i]->getPosition();
        }

        $outBlockingHexes = HexLib::getHexesInPath($start, $end, HexLib::GetHexInfo()->size * 0.5, $opponentPieceLocations);

        return new RequiredPower($distance, $outBlockingHexes);
    }

    public function getAttacker(): int
    {
        return  $this->dbadapter->getGameStateValue('attacker');
    }

    public function getDefender(): int
    {
        return $this->dbadapter->getGameStateValue('defender');
    }

    public function updateAttackerAndDefender(): void
    {
        $playerPiece = null;

        if ($this->tryGetPlayerPieceInHex($this->getBallHex(), $playerPiece)) {
            $attackerId = $this->dbadapter->getGameStateValue('attacker');

            if ($playerPiece && $playerPiece->getOwner() !== $attackerId) {
                $defenderId = $this->dbadapter->getGameStateValue('defender');

                $this->dbadapter->setGameStateValue('attacker', $defenderId);
                $this->dbadapter->setGameStateValue('defender', $attackerId);
            }
        }
    }

    public function movePieceToHex(int $pieceId, HexID $hex) : void
    {
        $piece = $this->getPlayerPieceData($pieceId);
        $piece->setPosition($hex, true);
        
        $this->updateAttackerAndDefender();
    }
}
