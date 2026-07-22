<?php

declare(strict_types=1);


namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\TacticContext;
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
        $tacticContext = new TacticContext();

        $cardInPlay = $tacticContext->getTacticInPlay();
        assert($cardInPlay >= 0);

        $tacticProgress = $tacticContext->getTacticProgress();
        assert($tacticProgress >= 0, "tactic progress can't be negative!");

        $tacticType = $tacticContext->getTacticType();

        $card = $this->game->getCardFactory()->getCard($cardInPlay);

        $result = $card->execute($tacticProgress);

        if ($result->action == ExecutionAction::GoToState)
        {
            $dbAdapter = DBAdapter::getInstance();
            $dbAdapter->storeActionReturnState($activePlayerId, self::class);
            $tacticContext->setTacticProgress( $result->index);

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
                return CardSelection::class;
            }
        }
    }
}