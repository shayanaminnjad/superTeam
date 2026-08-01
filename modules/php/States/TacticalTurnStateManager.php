<?php

declare(strict_types=1);


namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\CardActionDriver;
use Bga\Games\SuperTeam\Game;

//TODO: This is really card Action tunStateManager
class TacticalTurnStateManager extends GameState
{
    function __construct(protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_TACTICAL_TURN_STATE_MANAGER,
            name: "tacticalTurnStateManager",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId) : string
    {
        $cardActionDriver = new CardActionDriver($activePlayerId, $this->game);
        return $cardActionDriver->progress();
    }
}