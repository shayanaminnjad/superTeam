<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\NotificationAdapter;
use Bga\Games\SuperTeam\TeamAttribute;
use Bga\Games\SuperTeam\TimeTrack;
use Bga\Games\SuperTeam\Utility\Logger;

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

    function onEnteringState(): string
    {
        $this->game->getTeamBoard()->resetAllPlayersAttributes(TeamAttribute::GK, true);
        $playerHands = $this->game->getPlayerHands();

        foreach($playerHands as $hand)
        {
            $hand->MoveAllCardsToDeck();

            NotificationAdapter::getInstance()->notifyPlayer($hand->getOwner(), 'cardReset', '',[]);
        }

        //TODO: remove two fatigues

        $this->game->swapStarterPlayer();

        //TODO: client should be notified and show a half time banner probably!

        Board::resetBoard(true);

        TimeTrack::AdvanceTimeTrack(true);

        return PlayerPieceSetup::class;

        //shuffle all cards and add four to your hand
    }
}