<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Game;

class MoveFinished extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_MOVE_FINISHED,
            name: "moveFinished",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId): string
    {
        $selectedPiece = $this->game->getGameStateValue('selected_piece');
        $this->game->DbQuery("UPDATE player_pieces SET is_fresh = FALSE  WHERE `id` = $selectedPiece");

        $this->game->setGameStateValue('selected_piece', -1);
        $this->game->setGameStateValue('number_of_moves', 0);

        $this->notify->all("pieceExhausted", \clienttranslate('piece was exhausted'), array(
            "pieceId" => $selectedPiece,
        ));

        return CardSelection::class;
    }
}
