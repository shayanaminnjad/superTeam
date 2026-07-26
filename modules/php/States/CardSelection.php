<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Cards\CardReward;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Cards\TacticType;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\PlayerResources;
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

    public function onEnteringState(int $activePlayerId)
    {
        $tacticContext = new TacticContext();
        $tacticContext->reset();

        $moveContext = new MoveActionContext();
        $moveContext->reset();

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->reset();
    }

    public function selfGetArgs(): array
    {
        $activePlayer = \intval($this->game->getActivePlayerId());
        $playerHand = $this->game->getPlayerHand($activePlayer);

        $playableTactics = [];
        $cardsInHand = $playerHand->getCardsInHand();
        foreach($cardsInHand as $cardDef)
        {
            $cardFactory = $this->game->getCardFactory();

            //TODO: remove this, this is only for the ease of development, should not end up in production code.
            if ($cardFactory->hasCard($cardDef["type"]))
            {
                $card = $cardFactory->getCard($cardDef["type"]);

                if ($card->isExecutionPossible())
                {
                    $playableTactics[] = $card->getId();
                }
            }
            else
            {
                Logger::logError("card type does not exist in card factory " . $cardDef["type"]);
            }
        }

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
        //TODO: probalby I should give the active player extra time per card.

        $activePlayer = \intval($this->game->getActivePlayerId());

        $playerHand = $this->game->getPlayerHand($activePlayer);

        if ($playerHand->CountCardsInHand() == 1)
            throw new \BgaUserException("You can't play more than 3 cards in a turn!");

        $playerHand = $this->game->getPlayerHand($activePlayer);
        
        $playerHand->discardCard($cardId);
        
        $this->game->notify->all("cardPlayed", "card {$cardId} played", [
            'discards' => $playerHand->getDiscardedCards(),
            'cardsInHandCount' => $playerHand->CountCardsInHand()
            ]);
            

        //TODO: save last played card and send it to the player, or give the cards an index indicating
        //the order of discard.
        $dbAdapter = DBAdapter::getInstance();
        if ($asBasic)
        {
            $dbAdapter->storeActionReturnState($activePlayer, CardSelection::class);
            return PlayerBasicTurn::class;
        }
        else
        {
            $isAttacker = Board::isPlayerAttacker($activePlayer);

            $tacticContext = new TacticContext();

            $cardDef = $playerHand->getCard($cardId);

            $tacticContext->setTacticInPlay($cardDef['type']);
            $tacticContext->setTacticProgress(0);
            $tacticContext->setRewardUsed(CardReward::empty());
            $tacticContext->setTacticType($isAttacker ? TacticType::Offensive : TacticType::Defensive);

            if ($tacticContext->getTacticType() == TacticType::Defensive)
            {
                $moveActionContext = new MoveActionContext();
                $moveActionContext->setBallInitialLocation($this->game->getBoard()->getBallHex());

                $playerResource = new PlayerResources($activePlayer);
                $moveActionContext->setMoraleInitialAmount($playerResource->getMorale());
            }

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
