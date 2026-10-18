<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\Card;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MultiCardSelectionContext;
use Bga\Games\SuperTeam\NotificationAdapter;
use Bga\Games\SuperTeam\PermanentPowers\Teams\TeamId;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\States\MultiCardSelectState;

class CoachD extends Card
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Coach"),
            CardType::Coach,
            \clienttranslate("**Requirement:** Discard 1 or 2 cards from your hand. 
**Effect:** Gain 1 Morale for each card discarded.")), $game);
    }

    public function isExecutionPossible(): bool
    {
        $playerHand = $this->game->getPlayerHand(intval($this->game->getActivePlayerId()));

        return $playerHand->getCardsInHand() >= 2; //2 since the coach is still in our hand, so we should have at least 1 card more.
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCoachCardType(TeamId::SpendFourOnBoost->value);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        return [
            $this->goToDiscardCard(...),
            $this->discardCardsThenGiveMorale(...)
        ];
    }

    private function goToDiscardCard() : ExecutionResult
    {
        $multiCardSelectionContext = new MultiCardSelectionContext(intval($this->game->getActivePlayerId()));
        $multiCardSelectionContext->storeSelectableCardsRange(1, 2);
        return new ExecutionResult(ExecutionAction::GoToState, MultiCardSelectState::class);
    }

    private function discardCardsThenGiveMorale() : ExecutionResult
    {
        $activePlayer = intval($this->game->getActivePlayerId());
        $multiCardSelectionContext = new MultiCardSelectionContext($activePlayer);
        $cardsSelected = $multiCardSelectionContext->getCardsSelected();

        $playerHand = $this->game->getPlayerHand($activePlayer);

        foreach ($cardsSelected as $cardId)
        {
            $playerHand->discardCard($cardId);
        }

        NotificationAdapter::getInstance()->notifyAll("cardsDiscarded",
            clienttranslate('${player_name} discarded ${cardsPlayed} card(s) to gain ${cardsPlayed} morale(s)'),
            [
                'discards' => $playerHand->getDiscardedCards(),
                'player_name' => NotificationAdapter::getInstance()->getPlayerNameById($activePlayer),
                'cardsPlayed' => count($cardsSelected),
            ]);

        $playerResources = new PlayerResources($activePlayer);
        $playerResources->addMorale(count($cardsSelected), true);

        return new ExecutionResult(ExecutionAction::Success);
    }

    protected function cleanup(): void
    {
    }
}