<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\States\PlayerBaseState;
use Bga\Games\SuperTeam\Game;

class CardSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PLAY_CARD,
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

        if ($playerHand->CountCardsInHand() < PlayerHand::$MAX_CARDS_IN_HAND) //has played a card
        {
            $this->addClientButton("pass", [$this, 'actPass']);
        }

        return [];
    }

    #[PossibleAction]
    public function actCardSelected(int $cardId, bool $asBasic): string
    {
        return '';
    }

    #[PossibleAction]
    public function actPass() : string
    {
        //refresh hand to four
        return '';
    }

    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie
        return '';
    }
}
