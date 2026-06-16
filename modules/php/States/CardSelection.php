<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Utility\Logger;
use Bga\Games\SuperTeam\Cards\TacticType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;

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

        $playableTactics = [];
        $playableTactics[] = CardTypeHelper::getBasicCardType(0);
        /*
        $cardsInHand = $playerHand->getCardsInHand();
        foreach($cardsInHand as $cardDef)
        {
            $card = $this->game->getCardFactory()->getCard($cardDef["type"]);

            if ($card->isOffensivePossible())
            {
                $playableTactics[] = $card->getId();
            }
        }*/

        $cardsInHand = $playerHand->CountCardsInHand();
        $canPass =  $cardsInHand < PlayerHand::$MAX_CARDS_IN_HAND;
        $canPlayCard = $cardsInHand > 1;

        return [
            'canPass' => $canPass,
            'canPlayCard' => $canPlayCard,
            'playableTactics' => $playableTactics
        ];
    }

    #[PossibleAction]
    public function actCardSelected(int $cardId, string $cardType, bool $asBasic): string
    {
        $playerHand = $this->game->getPlayerHand(\intval($this->game->getActivePlayerId()));

        $cardDef = $playerHand->getCard($cardId);

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
            
            
            if ($asBasic)
                return PlayerBasicTurn::class;
            else
            {
                $dbAdapter = DBAdapter::getInstance();
                //TODO: encapsulate this logic
                $dbAdapter->storeGlobalString('tactic_in_play', $cardDef['type']);
                //TODO: I should check whether I need to store this or infer it.
                //the edge case is pass forward, I am not sure if you become defender at that moment or not.
                $dbAdapter->storeGlobalInt('tactic_type', TacticType::Offensive->value); 
                $dbAdapter->storeGlobalInt("tactic_progress", 0);
                $dbAdapter->storeGlobalInt("tactic_reward_in_progress", 0);
                $dbAdapter->storeGlobalString("rewards_used", "");

                return TacticalTurnStateManager::class;
            }
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
