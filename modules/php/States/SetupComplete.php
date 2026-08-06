<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;

class SetupComplete extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_SETUP_COMPLETE,
            name: "setupComplete",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId)
    {
        $this->game->giveExtraTime($activePlayerId);
               
        $playerId = intval($this->game->activeNextPlayer());

        if (!$this->game->getBoard()->doAllPiecesHaveValidPosition($playerId))
        {
            return PlayerPieceSetup::class;
        } 
        else
        {
            $isRestartAfterGoal = DBAdapter::getInstance()->getGlobalInt('is_restart_after_goal') == 1;

            $this->game->getBoard()->updateAttackerAndDefender();

            if ($isRestartAfterGoal)
            {
                DBAdapter::getInstance()->storeGlobalInt('is_restart_after_goal', 0);
                return BeforeCardSelectionReset::class;
            }
            else
            {
                $this->gamestate->setAllPlayersMultiactive();
                return TeamAttributeAssignment::class;
            }
        }
    }
}
