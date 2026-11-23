<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;

class PushTargetSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PUSH_TARGET_SELECTION,
            name: 'pushTargetSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is choosing push target'),
            descriptionMyTurn: clienttranslate('${you} must choose push target'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }
    public function selfGetArgs(): array
    {
        $board = $this->game->getBoard();
        $activePlayer = \intval($this->game->getActivePlayerId());

        $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();
        $selectedPiece = null;
        if (!$board->tryGetPlayerPieceInHex($selectedHex, $selectedPiece) || $selectedPiece->getOwner() !== $activePlayer)
            throw new \BgaUserException(clienttranslate("Invalid piece selected for push!"));

        $pushablePiecesArray = [];
        foreach (BoardEvaluator::getPushableOpponentPieces($board, $selectedPiece, $activePlayer) as $opponent)
        {
            $pushablePiecesArray[] = $opponent->getPosition()->convertToArray();
        }

        if (DBAdapter::getInstance()->getGlobalBool('forfeitable_captain_action'))
        {
            $this->addClientButton(\clienttranslate("Forfeit"), [$this, "actForfeit"], [], new ClientButtonParams(ClientButtonColor::ALERT));
        }

        return ["pushablePieces" => $pushablePiecesArray];
    }

    #[PossibleAction]
    public function actTargetSelected(int $hexColumn, int $hexRow): string
    {
        $this->saveUndoCheckpoint();

        $board = $this->game->getBoard();
        $activePlayer = \intval($this->game->getActivePlayerId());
        $selectedHex = new HexID($hexColumn, $hexRow);

        $opponentPiece = null;
        if (!$board->tryGetPlayerPieceInHex($selectedHex, $opponentPiece) || $opponentPiece->getOwner() === $activePlayer)
            throw new \BgaUserException(clienttranslate("Invalid piece selected for push!"));

        DBAdapter::getInstance()->storeSelectedHex($selectedHex);

        return PushDestinationSelection::class;
    }

    #[PossibleAction]
    public function actForfeit(int $activePlayerId) : string
    {
        $this->saveUndoCheckpoint();

        if (!DBAdapter::getInstance()->getGlobalBool('forfeitable_captain_action'))
            throw new \BgaUserException(\clienttranslate("Forfeit is not possible!"));

        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }

    function zombie(int $playerId): string
    {
        $hexes = $this->getArgs()["pushablePieces"];

        if (empty($hexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($hexes);

        return $this->actTargetSelected($hex['hex_column'], $hex['hex_row']);
    }
}