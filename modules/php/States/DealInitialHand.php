<?php

namespace Bga\Games\SuperTeam\States;


use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Game;

class DealInitialHand extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_DEAL_INITIAL_HAND,
            name: 'dealInitialHand',
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function onEnteringState()
    {
        $playerHands = $this->game->getPlayerHands();

        foreach($playerHands as &$playerHand)
        {
            $wasDiscardShuffled = $playerHand->drawToFour();

            $this->game->notify->player($playerHand->getOwner(), "handRefill", "",
                [
                    'playerHand' => $playerHand->getCardsInHand(),
                    'wasDiscardShuffled' => $wasDiscardShuffled,
                ]);
        }

        return CardSelection::class;
    }
}