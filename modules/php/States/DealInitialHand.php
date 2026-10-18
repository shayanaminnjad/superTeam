<?php

namespace Bga\Games\SuperTeam\States;


use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;

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

        $playerDecksSizes = [];
        foreach($playerHands as &$playerHand)
        {
            $playerHand->shuffleDeck();

            $wasDiscardShuffled = $playerHand->drawToAmount(PlayerPowers::forPlayer($playerHand->getOwner())->drawFiveKeepFour() ? 5 : PlayerHand::$DEFAULT_DRAW_AMOUNT);

            $this->game->notify->player($playerHand->getOwner(), "handRefill", "",
                [
                    'playerHand' => $playerHand->getCardsInHand(),
                    'wasDiscardShuffled' => $wasDiscardShuffled,
                ]);

            $playerDecksSizes [] = [$playerHand->getOwner(), $playerHand->CountCardsInDeck()];
        }

        $this->game->notify->all("deckCountChanged", "", $playerDecksSizes);

        return BeforeCardSelectionReset::class;
    }
}