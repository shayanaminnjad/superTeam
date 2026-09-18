<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
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
            $assignData = new MoraleAssignData();

            DBAdapter::getInstance()->setGameStateValue('moraleAssignRemaining', $assignData->moraleAssignRemaining - 1);
            DBAdapter::getInstance()->storePlayerTempInt(intval($this->game->getActivePlayerId()), 'assigned_morale', 0);

            if ($assignData->moraleAssignRemaining - 1 > 0) {
                return ActiveNextGoMorale::class;
            } else {
                assert(strlen($assignData->finishTransition) > 0, "no transition is stored for morale assign finish");

                return $assignData->finishTransition;
            }
        }
    }
}