<?php

declare(strict_types=1);


namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Cards\TacticType;
use Bga\Games\SuperTeam\Cards\ExecutionAction;

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

    function onEnteringState(int $activePlayerId)
    {
        $dbAdapter = DBAdapter::getInstance();

        $cardInPlay = $dbAdapter->getGlobalString("tactic_in_play");
        assert($cardInPlay >= 0);

        $tacticProgress = $dbAdapter->getGlobalInt("tactic_progress");
        assert($tacticProgress >= 0, "tactic progress can't be negative!");

        //TODO: tactic type can be inferred there is no need to store it.
        $tacticType = $dbAdapter->getGlobalInt("tactic_type");
        assert($tacticType >= 0 && $tacticType <=1, "tactic type should be either 0 or 1");

        $card = $this->game->getCardFactory()->getCard($cardInPlay);

        $result = $card->execute(TacticType::from($tacticType), $tacticProgress);

        if ($result->action == ExecutionAction::GoToState)
        {
            $dbAdapter->storeActionReturnState($activePlayerId, self::class);
            $dbAdapter->storeGlobalInt("tactic_progress", $result->index);
            return $result->requestedState;
        }
        else
        {
            //TODO: apply fatigue too.
            
            if ($result->action == ExecutionAction::Success) 
            {
                return TacticRewardResolve::class;
            } 
            else if ($result->action == ExecutionAction::Failure) 
            {
                $this->cleanup();
                return CardSelection::class;
            }
        }
    }

    private function cleanup()
    {
        $dbAdapter = DBAdapter::getInstance();

        $dbAdapter->storeGlobalString("tactic_in_play", "");
        $dbAdapter->storeGlobalInt("tactic_progress", -1);
    }
}