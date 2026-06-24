<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerPieceSetupLogic;
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
        $setupLogic = new PlayerPieceSetupLogic($this->game->getBoard());
        $notPlacedPieces = $setupLogic->getNotPlacedPieces(intval($this->game->getActivePlayerId()));

        $pieceToPlaceId = 0;
        if (count($notPlacedPieces) > 0)
        {
            $pieceToPlaceId = $notPlacedPieces[0]->getId();
        }
            
        $playerSide = $this->game->getPlayerSide(intval($this->game->getActivePlayerId()));
        $isFirstPlayer = $this->game->isCurrentPlayerFirstPlayer();

        $possibleLocations = $setupLogic->getAvailablePlacingPositions($playerSide, $isFirstPlayer, count($notPlacedPieces));

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

        if (!$this->game->getBoard()->doAllPiecesHaveValidPosition($playerId))
        {
            return PlayerPieceSetup::class;
        }
        else
        {
            return SetupComplete::class;
        }
    }

    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie

        return '';
    }
}

