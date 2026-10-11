<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\DBAdapter;
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
        NotificationAdapter::getInstance()->notifyAll('halfTime', '', []);

        $this->game->getTeamBoard()->resetAllPlayersAttributes(TeamAttribute::GK, true);
        $playerHands = $this->game->getPlayerHands();

        foreach($playerHands as $hand)
        {
            $hand->MoveAllCardsToDeck();
        }

        NotificationAdapter::getInstance()->notifyAll('cardReset', '', []);

        foreach($playerHands as $hand)
        {
            $hand->removeUpToTwoFatiguesFromDeck(true);
        }

        DBAdapter::getInstance()->storeGlobalInt('is_restart_after_goal', 0);
        $playerStartedFirstHalf = $this->game->getPlayerStartedFirstHalf();
        $currentStarterPlayer = $this->game->getStarterPlayer();

        if ($playerStartedFirstHalf === $currentStarterPlayer)
            $this->game->swapStarterPlayer();

        if ($this->game->getActivePlayerId() != $this->game->getStarterPlayer())
            $this->game->activeNextPlayer();

        Board::resetBoard(true);

        TimeTrack::AdvanceTimeTrack(true);

        return PlayerPieceSetup::class;
    }
}