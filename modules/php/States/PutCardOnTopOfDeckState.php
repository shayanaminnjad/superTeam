<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MultiCardSelectionContext;
use Bga\Games\SuperTeam\NotificationAdapter;

class PutCardOnTopOfDeckState extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PUT_CARD_ON_TOP_OF_DECK_STATE,
            name: 'putCardOnTopOfDeck',
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId): string
    {
        $multiCardSelectionContext = new MultiCardSelectionContext($activePlayerId);
        $cardsSelected = $multiCardSelectionContext->getCardsSelected();

        assert(count($cardsSelected) == 1);

        $playerHand = $this->game->getPlayerHand($activePlayerId);
        $playerHand->putCardOnTopOfDrawDeck($cardsSelected[0]);

        NotificationAdapter::getInstance()->notifyPlayer($activePlayerId, "cardPutOnTopOfDeck","",
            [
                'movedCard' => $cardsSelected[0]
            ]);

        $this->game->notify->all("deckCountChanged", "", [[$activePlayerId, $playerHand->CountCardsInDeck()]]);

        $multiCardSelectionContext->reset();

        return CardSelection::class;
    }
}