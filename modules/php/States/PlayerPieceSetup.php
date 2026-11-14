<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerPieceSetupLogic;
use Bga\Games\SuperTeam\PlayerRole;
use Bga\Games\SuperTeam\Utility\Logger;
use BgaUserException;

class PlayerPieceSetup extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PLAYER_PIECE_SETUP,
            name: "playerPieceSetup",
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} ${dynamicTitle}'),
            descriptionMyTurn: clienttranslate('${you} ${dynamicTitle}'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $activePlayerId = intval($this->game->getActivePlayerId());
        $setupLogic = new PlayerPieceSetupLogic($this->game->getBoard());
        $notPlacedPieces = $setupLogic->getNotPlacedPieces($activePlayerId);
        $piecesLeft = count($notPlacedPieces);

        $pieceToPlaceId = -1;
        if ($piecesLeft > 0)
        {
            $pieceToPlaceId = $notPlacedPieces[0]->getId();
        }

        $playerSide = $this->game->getPlayerSide(intval($this->game->getActivePlayerId()));
        $isFirstPlayer = $this->game->isCurrentPlayerFirstPlayer();

        $possibleLocations =[];
        if ($piecesLeft > 0)
            $possibleLocations = $setupLogic->getAvailablePlacingPositions($playerSide, $isFirstPlayer, $piecesLeft);

        if ($piecesLeft < (PlayerPieceSetupLogic::PIECES_PER_PLAYER - 1)) //minus goalkeeper
        {
            $this->addClientButton('Restart', [$this, 'actRestart'], [],
                new ClientButtonParams(ClientButtonColor::ALERT));
        }

        $canConfirm = $this->game->getBoard()->doAllPiecesHaveValidPosition($activePlayerId);
        $this->addClientButton('Confirm', [$this, 'actConfirm'], [],
            new ClientButtonParams(ClientButtonColor::PRIMARY, '', !$canConfirm));

        if ($pieceToPlaceId === -1)
        {
            $pieceType = "none";
            $dynamicTitle = \clienttranslate('must confirm the piece placement');
        }
        else
        {
            $pieceType = $this->game->getBoard()->getPiece($pieceToPlaceId)->getRole() === PlayerRole::Captain->value
                ? "captain"
                : "regular";

            $dynamicTitle = sprintf(
                \clienttranslate('must place the [%1$s] piece (%2$d left)'),
                $pieceType,
                $piecesLeft
            );
        }

        return [
            "availableStartingPositions" => $possibleLocations,
            "pieceToPlace"  => $pieceToPlaceId,
            "pieceType"     => $pieceType,
            "dynamicTitle"  => $dynamicTitle,
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
            throw new BgaUserException(\clienttranslate('Invalid hex position for placing a piece! (${column}, ${row})'), 100, ['column' => $hexColumn, 'row' => $hexRow]);
        }

        if ($placedPiece !== intval($pieceToPlace))
        {
            throw new BgaUserException(\clienttranslate('Piece placed is not valid! Piece to be placed id was ${expected}, received ${received}'), 100, ['expected' => $pieceToPlace, 'received' => $placedPiece]);
        }

        $this->game->DbQuery("UPDATE `player_pieces` SET `hex_column` = $hexColumn, `hex_row` = $hexRow WHERE `id` = $placedPiece ");

        $playerId = (int)$this->game->getActivePlayerId();

        $this->notify->all("placedPiece", '', [
            "player_id" => $playerId,
            "player_name" => $this->game->getActivePlayerName(), // remove this line if you uncomment notification decorator
            "placed_piece" => $placedPiece,
            "hex_column" => $hexColumn,
            "hex_row" => $hexRow,
        ]);

        return PlayerPieceSetup::class;
    }

    #[PossibleAction]
    public function actConfirm(int $activePlayerId): string
    {
        if (!$this->game->getBoard()->doAllPiecesHaveValidPosition($activePlayerId))
            throw new BgaUserException(\clienttranslate('Confirm is not possible! there are pieces not placed.'));

        return SetupComplete::class;
    }
    #[PossibleAction]
    public function actRestart(int $activePlayerId) : string
    {
        Board::resetPlayerPieces($activePlayerId, true);

        return PlayerPieceSetup::class;
    }

    function zombie(int $playerId): string
    {
        $playerPieceSetup = new PlayerPieceSetupLogic($this->game->getBoard());
        $playerPieceSetup->defaultSetup($this->game, $playerId);

        return SetupComplete::class;
    }
}