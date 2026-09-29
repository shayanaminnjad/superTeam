<?php

namespace Bga\Games\SuperTeam;

class PlayerPieceSetupLogic
{
    public const int PIECES_PER_PLAYER = 6;
    private Board $board;

    public function __construct(Board $board)
    {
        $this->board = $board;
    }

    public function getNotPlacedPieces(int $playerId) : array
    {
        $playerPieces = $this->board->getPlayerPiecesByOwner($playerId);

        $availablePieces = array_filter($playerPieces, function($piece)
        {
            return !$piece->hasValidPosition();
        });

        usort($availablePieces, function ($a, $b)
        {
            return $a->getId() <=> $b->getId();
        });

        return $availablePieces;
    }

    public function getAvailablePlacingPositions(PlayerSide $playerSide, bool $isFirstPlayer, int $piecesRemaining) : array
    {
        //Special case where center is not set by the first player.
        if ($isFirstPlayer && $piecesRemaining == 1 && !$this->board->hasPlayerInHex(BoardInfo::$CENTER_POSITION))
        {
            $possibleLocations = [BoardInfo::$CENTER_POSITION->convertToArray()];
        }
        else
        {
            $playerStartingPositions = $playerSide === PlayerSide::Left ? BoardInfo::$LEFT_PLAYER_STARTING_POSITIONS : BoardInfo::$RIGHT_PLAYER_STARTING_POSITIONS;

            if ($isFirstPlayer)
            {
                $playerStartingPositions[] = BoardInfo::$CENTER_POSITION;
            }

            for ($i = 0; $i < count($playerStartingPositions); $i++)
            {
                if ($this->board->hasPlayerInHex($playerStartingPositions[$i]) == false)
                {
                    $possibleLocations[] = $playerStartingPositions[$i]->convertToArray();
                }
            }
        }

        return $possibleLocations;
    }

    public function defaultSetup(Game $game, int $activePlayerId) : void
    {
        $playerSide = $game->getPlayerSide($activePlayerId);
        $isFirstPlayer = $game->isCurrentPlayerFirstPlayer();

        while (!$this->board->doAllPiecesHaveValidPosition($activePlayerId))
        {
            $piecesToPlace = $this->getNotPlacedPieces($activePlayerId);
            $availablePositions = $this->getAvailablePlacingPositions($playerSide, $isFirstPlayer, count($piecesToPlace));

            $positionToPlace = $availablePositions[0];
            $piece = &$piecesToPlace[0];
            $piece->setPosition(HexID::fromArray($positionToPlace), true);

            $game->notify->all("placedPiece", "", [
                "player_id" => $activePlayerId,
                "placed_piece" => $piece->getId(),
                "hex_column" => $positionToPlace['hex_column'],
                "hex_row" => $positionToPlace['hex_row'],
            ]);
        }
    }
}