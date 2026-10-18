<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\Card;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MultiCardSelectionContext;
use Bga\Games\SuperTeam\NotificationAdapter;
use Bga\Games\SuperTeam\PermanentPowers\Teams\TeamId;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\States\MultiCardSelectState;
use Bga\Games\SuperTeam\TimeTrack;

class CoachF extends Card
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Coach"),
            CardType::Coach,
            \clienttranslate("**Requirement:** Discard 1-3 cards from your hand. 
**Effect:** Draw the same amount of cards from your deck.")), $game);
    }

    public function isExecutionPossible(): bool
    {
        $playerHand = $this->game->getPlayerHand(intval($this->game->getActivePlayerId()));

        return $playerHand->getCardsInHand() >= 2; //2 since the coach is still in our hand, so we should have at least 1 card more.
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCoachCardType(TeamId::DrawFiveKeepFour->value);
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
        $multiCardSelectionContext->storeSelectableCardsRange(1, 3);
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
            clienttranslate('${player_name} discarded ${cardsPlayed} card(s) to draw ${cardsPlayed} card(s)'),
            [
                'discards' => $playerHand->getDiscardedCards(),
                'player_name' => NotificationAdapter::getInstance()->getPlayerNameById($activePlayer),
                'cardsPlayed' => count($cardsSelected),
            ]);

        $wasShuffled = $playerHand->drawCards(count($cardsSelected));

        $this->game->notify->player($activePlayer, "handRefill", "",
            [
                'playerHand' => $playerHand->getCardsInHand(),
                'wasDiscardShuffled' => $wasShuffled,
            ]);

        if ($wasShuffled)
        {
            DBAdapter::getInstance()->storeGlobalBool("player_hand_shuffled_during_turn", true);

            if (TimeTrack::GetCurrentMinute() != 90)
                TimeTrack::AdvanceTimeTrack(true);
        }

        return new ExecutionResult(ExecutionAction::Success);
    }

    protected function cleanup(): void
    {
    }
}