<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\DBAdapter;

class Board
{
    private array $playerPieces;

    private HexID $ballPosition;

    private DBAdapter $dbadapter;

    //======================================================

    public function __construct(array $piecesData, HexId $ballPosition, DBAdapter $dBAdapter)
    {
        for ($i = 0; $i < count($piecesData); $i++) {
            $this->playerPieces[] = new PlayerPiece($piecesData[$i]);
        }

        $this->ballPosition = $ballPosition;
        $this->dbadapter = $dBAdapter;
    }

    public function setBallLocation(HexID $location)
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

    public function hasPlayerInHex(HexID $hexID): bool
    {
        for ($i = 0; $i < count($this->playerPieces); $i++) {
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

        for ($i = 0; $i < count($this->playerPieces); $i++) {
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

    public function getPlayerPiecesInForwardColumns(int $startColumn, $ownerId): array //This should be of type playerPiece
    {
        $result = [];

        for ($i = 0; $i < count($this->playerPieces); $i++) {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getOwner() === $ownerId && $currentPiece->getPosition()->getColumn() > $startColumn) {
                $result[] = $currentPiece;
                break;
            }
        }

        return $result;
    }

    public function getPlayerPiecesInBackwardColumns(int $startColumn, $ownerId): array
    {
        $result = [];

        for ($i = 0; $i < count($this->playerPieces); $i++) {
            $currentPiece = $this->playerPieces[$i];
            $pieceCol = $currentPiece->getPosition()->getColumn();

            if ($currentPiece->getOwner() === $ownerId &&  $currentPiece->hasValidPosition() && $pieceCol < $startColumn) {
                $result[] = $currentPiece;
                break;
            }
        }

        return $result;
    }

    public function getPlayerPiecesInTheSameColumn(int $column, $ownerId): array
    {
        $result = [];

        for ($i = 0; $i < count($this->playerPieces); $i++) {
            $currentPiece = $this->playerPieces[$i];

            if ($currentPiece->getOwner() === $ownerId && $currentPiece->getPosition()->getColumn() === $column) {
                $result[] = $currentPiece;
                break;
            }
        }

        return $result;
    }

    public function getPlayerPiecesByOwner(int $owner): array
    {
        $result = [];

        for ($i = 0; $i < count($this->playerPieces); $i++) {
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

    public function getMoveableHexesByPiece(int $pieceId): array
    {
        $result = [];

        $playerPiece = $this->getPlayerPieceData($pieceId);

        $piecePosition = $playerPiece->getPosition();

        $neighbourHexes = HexLib::getNeighbours(HexLib::offsetToCubic($piecePosition), 1);

        for ($i = 0; $i < count($neighbourHexes); $i++) {
            $hexId = HexLib::cubicToHexID($neighbourHexes[$i]);

            //TODO: I might be able to show this by red!
            if ($this->canMoveToHex($hexId)) {
                $result[] = $hexId->convertToArray();
            }
        }

        return $result;
    }

    public function doesOpponentExistInNeighbours(HexID $reference, int $currentPlayer): bool
    {
        $neighbourHexes = HexLib::getNeighbours(HexLib::offsetToCubic($reference), 1);

        for ($i = 0; $i < count($neighbourHexes); $i++) {
            $playerPiece = null;
            if ($this->tryGetPlayerPieceInHex(HexLib::cubicToHexID($neighbourHexes[$i]), $playerPiece)) {
                if ($playerPiece && $playerPiece->getOwner() != $currentPlayer) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getClosetBlockingPieceOrHalfWay(HexID $start, HexID $end, int $opponentId): HexID
    {
        $res = null;

        $opponentPieces = $this->getPlayerPiecesByOwner($opponentId);
        $opponentPieceLocations = [];

        for ($i = 0; $i < count($opponentPieces); $i++) {
            $opponentPieceLocations[] = $opponentPieces[$i]->getPosition();
        }

        $outBlockingHexes = HexLib::getHexesInPath($start, $end, 1.2, $opponentPieceLocations);

        if (count($outBlockingHexes) > 0) {
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
            $res = $hexesInLine[ceil((float)count($hexesInLine) / 2.0)];
        }

        return $res;
    }

    public function getRequiredPower(HexID $start, HexID $end, int $opponentId, array &$outLineHexes, array &$outBlockingHexes): int
    {
        $distance = HexLib::getOffsetDistance($start, $end);
        $opponentPieces = $this->getPlayerPiecesByOwner($opponentId);
        $opponentPieceLocations = [];

        for ($i = 0; $i < count($opponentPieces); $i++) {
            $opponentPieceLocations[] = $opponentPieces[$i]->getPosition();
        }

        $outBlockingHexes = HexLib::getHexesInPath($start, $end, 1.2, $opponentPieceLocations);

        return ceil($distance / (float)2) + count($outBlockingHexes);
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

    public function movePieceToHex(int $pieceId, HexID $hex)
    {
        $this->dbadapter->DbQuery("UPDATE `player_pieces` SET `hex_column` = {$hex->getColumn()}, `hex_row` = {$hex->getRow()} WHERE `id` = $pieceId ");
        $piece = $this->getPlayerPieceData($pieceId);
        $piece->setPosition($hex);
    }

    private function canMoveToHex(HexID $hexID): bool
    {
        return !$this->hasPlayerInHex($hexID) && !$this->isGoalieSpecificSpace($hexID);
    }

    private function isGoalieSpecificSpace(HexID $hexID): bool
    {
        if ($hexID->getColumn() === 0 || $hexID->getColumn() === 16)
            if ($hexID->getRow() >= 4 && $hexID->getRow() <= 6)
                return true;

        return false;
    }
}
