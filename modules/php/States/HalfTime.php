<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\TimeTrack;

class HalfTime extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_HALF_TIME,
            name: "HalfTime",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId): string
    {
        $this->game->getTeamBoard()->resetAttributes();
        //remove all attributes except goal keeper
        //move all cards to draw deck
        //remove two fatigues
        //change the start player from the first round.

        //TODO: client should be notified and show a half time banner probably!



        //reassign your team squad
        //go to coach marker assign state (but gk can't be re-assigned)
        //shuffle all cards and add four to your hand

        TimeTrack::AdvanceTimeTrack(true);
    }
}