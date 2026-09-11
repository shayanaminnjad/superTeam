<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\States\BeforeCardSelectionReset;
use Bga\Games\SuperTeam\States\BeforeRewardResolve;
use Bga\Games\SuperTeam\States\CardSelection;
use Bga\Games\SuperTeam\States\TacticalTurnStateManager;
use Bga\Games\SuperTeam\States\TacticRewardResolve;

class CardActionDriver
{
    private int $activePlayerId;
    private Game $game;

    public function __construct(int $activePlayerId, Game $game)
    {
        $this->activePlayerId = $activePlayerId;
        $this->game = $game;
    }

    public function progress() : string
    {
        $tacticContext = new TacticContext();

        $cardInPlay = $tacticContext->getTacticInPlay();
        assert($cardInPlay >= 0);

        $tacticProgress = $tacticContext->getTacticProgress();
        assert($tacticProgress >= 0, "tactic progress can't be negative!");

        //it means this is second time we are entering here, so the player is commited.
        $tacticContext->setTacticCommited($tacticProgress > 0 );

        $card = $this->game->getCardFactory()->getCard($cardInPlay);

        $result = $card->execute($tacticProgress);

        if ($result->action == ExecutionAction::GoToState)
        {
            $dbAdapter = DBAdapter::getInstance();
            $dbAdapter->storeActionReturnState($this->activePlayerId, TacticalTurnStateManager::class);
            $tacticContext->setTacticProgress( $result->index);

            return $result->requestedState;
        }
        else
        {
            //TODO: this part must be part of the class instructions! no need to know about the cards here!
            if ($result->action == ExecutionAction::Success && $card instanceof TacticCard)
            {
                if ($card->hasFatigue())
                {
                    $playerHand = $this->game->getPlayerHand($this->activePlayerId);
                    $playerHand->drawFatigue();
                    $playerHand->notifyPlayerOfHandRefill();
                }

                return BeforeRewardResolve::class;
            }
            else
            {
                return BeforeCardSelectionReset::class;
            }
        }
    }

    public function isCardInProgress() : bool
    {
        $tacticContext = new TacticContext();

        return $tacticContext->getTacticInPlay() >= 0;
    }
}