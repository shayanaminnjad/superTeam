<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\States\PlayerBaseState;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Utility\Logger;

class CardSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_CARD_SELECTION,
            name: 'cardSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('actplayer must play a card or pass'),
            descriptionMyTurn: clienttranslate('you must play a card or pass'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $playerHand = $this->game->getPlayerHand(\intval($this->game->getActivePlayerId()));

        $cardsInHand = $playerHand->CountCardsInHand();
        $canPass =  $cardsInHand < PlayerHand::$MAX_CARDS_IN_HAND;
        $canPlayCard = $cardsInHand > 1;

        return [
            'canPass' => $canPass,
            'canPlayCard' => $canPlayCard
        ];
    }

    #[PossibleAction]
    public function actCardSelected(int $cardId, bool $asBasic): string
    {
        $playerHand = $this->game->getPlayerHand(\intval($this->game->getActivePlayerId()));

        if ($playerHand->CountCardsInHand() == 1)
            throw new \BgaUserException("You can't play more than 3 cards in a turn!");

        Logger::logInfo("Card " . $cardId . "selected to be performed as" . ($asBasic ? 'as basic.' : 'as tactic.'));

        $activePlayer = \intval($this->game->getActivePlayerId());
        $playerHand = $this->game->getPlayerHand($activePlayer);

        $playerHand->discardCard($cardId);

        $this->game->notify->all("cardPlayed", "card {$cardId} played", [
            'discards' => $playerHand->getDiscardedCards(),
            'cardsInHandCount' => $playerHand->CountCardsInHand()
        ]);

        //(TODO) this should change, player turn is actually basic card performer.
        if ($asBasic)
            return PlayerTurn::class;
        else
            return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actPass(): string
    {
        return PassTurn::class;
    }

    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie
        return '';
    }
}
