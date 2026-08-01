<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\CardReward;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Cards\TacticType;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\RequiredPower;
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

        $passActionContext = new PassActionContext($activePlayerId);
        $passActionContext->reset(false);

        RequiredPower::setSkipEnabled(false);
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


        $cardsPlayedThisTurn = $this->getCardsPlayedThisTurn();
        $canPass =  $cardsPlayedThisTurn > 0;
        $canPlayCard = $cardsPlayedThisTurn < 3;

        //todo: add the special case for when the goalie has the ball.

        return [
            'canPass' => $canPass,
            'canPlayCard' => $canPlayCard,
            'playableTactics' => $playableTactics
        ];
    }

    #[PossibleAction]
    public function actCardSelected(int $cardId, string $cardType, bool $asBasic): string
    {
        $activePlayer = \intval($this->game->getActivePlayerId());

        $this->game->giveExtraTime($activePlayer);

        if ($this->getCardsPlayedThisTurn() >= 3)
            throw new \BgaUserException("You can't play more than 3 cards in a turn!");

        $playerHand = $this->game->getPlayerHand($activePlayer);

        $playerHand->discardCard($cardId);
        
        $this->game->notify->all("cardPlayed", "card {$cardId} played", [
            'discards' => $playerHand->getDiscardedCards(),
            'cardsInHandCount' => $playerHand->CountCardsInHand()
            ]);

        $this->increaseCardsPlayedThisTurnByOne();

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

    private function getCardsPlayedThisTurn(): int
    {
        return intval($this->game->getGameStateValue("cardsPlayedThisTurn"));
    }

    private function increaseCardsPlayedThisTurnByOne(): void
    {
        $cardsPlayedThisTurn = $this->getCardsPlayedThisTurn();
        $this->game->setGameStateValue("cardsPlayedThisTurn", $cardsPlayedThisTurn + 1);
    }
}
