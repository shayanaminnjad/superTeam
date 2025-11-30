<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;
use BgaUserException;

use Bga\Games\SuperTeam\BoardInfo;
use Bga\Games\SuperTeam\PlayerSide;

class PlayerPieceSetup extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PLAYER_PIECE_SETUP,
            name: "playerPieceSetup",
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must place his player pieces'),
            descriptionMyTurn: clienttranslate('${you} must place the indicated piece'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function getArgs(): array
    {
        //get pieces that can be placed.
        $board = $this->game->getBoard();
        $playerPieces = $board->getPlayerPiecesByOwner(intval($this->game->getActivePlayerId()));
        
        $availablePieces = array_filter($playerPieces, function($piece)
        {
            return !$piece->hasValidPosition();
        });
        
        usort($availablePieces, function ($a, $b)
        {
            return $a->getId() <=> $b->getId();
        });
        
        $pieceToPlaceId = 0;
        if (count($availablePieces) > 0)
        {
            $pieceToPlaceId = $availablePieces[0]->getId();
        }
            
        //get available positions
        $playerSide = $this->game->getPlayerSide(intval($this->game->getActivePlayerId()));
        $IsFirstPlayer = $this->game->isCurrentPlayerFirstPlayer();

        $possibleLocations = [];
        //Special case where center is not set by the first player.
        if ($IsFirstPlayer && count($availablePieces) == 1 && !$board->hasPlayerInHex(BoardInfo::$CENTER_POSITION))
        {
            $possibleLocations = [BoardInfo::$CENTER_POSITION->convertToArray()];
        }
        else
        {
            $playerStartingPositions = $playerSide === PlayerSide::Left ? BoardInfo::$LEFT_PLAYER_STARTING_POSITIONS : BoardInfo::$RIGHT_PLAYER_STARTING_POSITIONS;

            if ($IsFirstPlayer) 
            {
                $playerStartingPositions[] = BoardInfo::$CENTER_POSITION;
            }

            for ($i = 0; $i < count($playerStartingPositions); $i++) 
            {
                if ($board->hasPlayerInHex($playerStartingPositions[$i]) == false) 
                {
                    $possibleLocations[] = $playerStartingPositions[$i]->convertToArray();
                }
            }
        }

        return [
            "availableStartingPositions" => $possibleLocations,
            "pieceToPlace"=> $pieceToPlaceId,
        ];
    }

    #[PossibleAction]
    public function actPlacedPiece(int $placedPiece, int $hexColumn, int $hexRow): string
    {
        $playerPieceSetupArgs = $this->getArgs();

        $availablePositions = $playerPieceSetupArgs["availableStartingPositions"];
        $pieceToPlace = $playerPieceSetupArgs["pieceToPlace"];

        $isValidPosition = false;
        for ($i = 0; $i < count($availablePositions); $i++ )
        {
            $currentPosition= $availablePositions[$i];

            if ($currentPosition['hex_column'] === $hexColumn && $currentPosition['hex_row'] === $hexRow)
            {
                $isValidPosition = true;
                break;
            }
        }

        if (!$isValidPosition) 
        {
            throw new BgaUserException("Invalid hex position for placing a piece! (" . $hexColumn . ", " . $hexRow . ")");
        }

        if ($placedPiece !== intval($pieceToPlace))
        {
            throw new BgaUserException("peice placed is not valid! piece to be placed id was " .  $pieceToPlace . ", received " . $placedPiece);
        }

        $this->game->DbQuery("UPDATE `player_pieces` SET `hex_column` = $hexColumn, `hex_row` = $hexRow WHERE `id` = $placedPiece ");

        $playerId = (int)$this->game->getActivePlayerId();

        $this->notify->all("placedPiece", clienttranslate('${player_name} places a piece'), [
            "player_id" => $playerId,
            "player_name" => $this->game->getActivePlayerName(), // remove this line if you uncomment notification decorator
            "placed_piece" => $placedPiece,
            "hex_column" => $hexColumn,
            "hex_row" => $hexRow,
        ]);

        if (!$this->game->isPlayerSetupComplete($playerId))
        {
            return PlayerPieceSetup::class;
        }
        else
        {
            return SetupComplete::class;
        }
    }

    #[PossibleAction]
    public function actDebugDefaultPlacePieces() : string
    {
        $playerId = (int)$this->game->getActivePlayerId();

        while(!$this->game->isPlayerSetupComplete($playerId))
        {

            $playerPieceSetupArgs = $this->getArgs();

            $availablePositions = $playerPieceSetupArgs["availableStartingPositions"];
            $pieceToPlace = $playerPieceSetupArgs["pieceToPlace"];

            $firstAvailablePos = $availablePositions[0];
            $hexColumn = $firstAvailablePos['hex_column'];
            $hexRow = $firstAvailablePos['hex_row'];

            $this->game->DbQuery("UPDATE `player_pieces` SET `hex_column` = $hexColumn, `hex_row` = $hexRow WHERE `id` = $pieceToPlace ");


            $this->notify->all("placedPiece", clienttranslate('${player_name} places a piece'), [
                "player_id" => $playerId,
                "player_name" => $this->game->getActivePlayerName(), // remove this line if you uncomment notification decorator
                "placed_piece" => $pieceToPlace,
                "hex_column" => $hexColumn,
                "hex_row" => $hexRow,
            ]);
        }
    
        return SetupComplete::class;
    }

    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie

        return '';
    }
}

