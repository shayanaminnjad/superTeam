<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MoraleAssignContext;
use Bga\Games\SuperTeam\PlayerResources;

class BeforeMoraleAssignment extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_BEFORE_MORALE_ASSIGMENT,
            name: "beforeMoraleAssignment",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId)
    {
        $playerResources = new PlayerResources($activePlayerId);

        if ($playerResources->getMorale() > 0)
        {
            return MoraleAssignment::class;
        }
        else
        {
            $moraleAssignContext = new MoraleAssignContext();
            $moraleAssignRemaining = $moraleAssignContext->getMoraleAssignRemaining();

            $moraleAssignContext->storeMoraleAssignRemaining($moraleAssignRemaining - 1);
            $moraleAssignContext->storeMoraleAssigned(intval($this->game->getActivePlayerId()), 0);

            if ($moraleAssignRemaining - 1 > 0) {
                return ActiveNextGoMorale::class;
            } else {
                $finishTransition = $moraleAssignContext->getMoraleAssignTransition();
                assert(strlen($finishTransition) > 0, "no transition is stored for morale assign finish");

                return $finishTransition;
            }
        }
    }
}