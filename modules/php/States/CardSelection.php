<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\CardReward;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Cards\TacticType;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\PassTargetSelectionLogic;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;
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

    public function selfGetArgs(): array
    {
        $activePlayer = \intval($this->game->getActivePlayerId());

        $board = $this->game->getBoard();
        $goalieHasBall = BoardEvaluator::doesPlayerGoalieHasBall($board, $activePlayer);

        $playableTactics = [];
        if (!$goalieHasBall)
        {
            $playerHand = $this->game->getPlayerHand($activePlayer);
            $cardsInHand = $playerHand->getCardsInHand();
            foreach ($cardsInHand as $cardDef)
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
                } else
                {
                    Logger::logError("card type does not exist in card factory " . $cardDef["type"]);
                }
            }
        }

        $cardsPlayedThisTurn = self::getCardsPlayedThisTurn($this->game);
        $canPass =  $cardsPlayedThisTurn > 0;
        $canPlayCard = $cardsPlayedThisTurn < 3;

        return [
            'canPass' => $canPass,
            'canPlayCard' => $canPlayCard,
            'playableTactics' => $playableTactics,
            'goalieHasBall' => $goalieHasBall
        ];
    }

    #[PossibleAction]
    public function actCardSelected(int $cardId, bool $asBasic): string
    {
        $activePlayer = \intval($this->game->getActivePlayerId());

        $this->game->giveExtraTime($activePlayer); //todo: shouldn't do this unless the card action is completed.

        if (self::getCardsPlayedThisTurn($this->game) >= 3)
            throw new \BgaUserException("You can't play more than 3 cards in a turn!");

        $playerHand = $this->game->getPlayerHand($activePlayer);

        $playerHand->discardCard($cardId);

        $this->game->notify->all("cardPlayed", "card {$cardId} played", [
            'discards' => $playerHand->getDiscardedCards(),
            'cardsInHandCount' => $playerHand->CountCardsInHand()
            ]);

        self::increaseCardsPlayedThisTurnByOne($this->game);

        $board = $this->game->getBoard();
        $goalieHasBall = BoardEvaluator::doesPlayerGoalieHasBall($board, $activePlayer);

        $dbAdapter = DBAdapter::getInstance();
        if ($goalieHasBall)
        {
            $dbAdapter->storeActionReturnState($activePlayer, BeforeCardSelectionReset::class);
            return PassDestSelection::class;
        }
        else if ($asBasic)
        {
            $dbAdapter->storeActionReturnState($activePlayer, BeforeCardSelectionReset::class);
            return PlayerBasicTurn::class;
        }
        else
        {
            $isAttacker = Board::isPlayerAttacker($activePlayer);

            $tacticContext = new TacticContext();

            $cardDef = $playerHand->getCard($cardId);

            $cardFactory = $this->game->getCardFactory();
            $card = $cardFactory->getCard($cardDef["type"]);
            if (!$card->isExecutionPossible())
                throw new \BgaUserException("Invalid card selected for execution, execution impossible");

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

    private static function getCardsPlayedThisTurn(Game $game): int
    {
        return intval($game->getGameStateValue("cardsPlayedThisTurn"));
    }

    public static function undoLastCardPlayed(int $activePlayer, Game $game) : void
    {
        $playerHand = $game->getPlayerHand($activePlayer);
        $cardMoved = $playerHand->moveLastDiscardedCardToHand();
        self::decreaseCardsPlayedThisTurnByOne($game);

        $game->notify->player($game->getInactivePlayerId(), "cardUndo", "", [
            'undoCard' => $cardMoved,
            'cardsInHandCount' => $playerHand->CountCardsInHand()
        ]);
    }

    private static function increaseCardsPlayedThisTurnByOne(Game $game): void
    {
        $cardsPlayedThisTurn = self::getCardsPlayedThisTurn($game);
        $game->setGameStateValue("cardsPlayedThisTurn", $cardsPlayedThisTurn + 1);
    }

    private static function decreaseCardsPlayedThisTurnByOne(Game $game) : void
    {
        $cardsPlayedThisTurn = self::getCardsPlayedThisTurn($game);
        $game->setGameStateValue("cardsPlayedThisTurn", $cardsPlayedThisTurn - 1);
    }
}
