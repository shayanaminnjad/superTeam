<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\BasicTurnLogic;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\CardReward;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\GameStats;
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
            description: clienttranslate('${actplayer} must play a card or pass'),
            descriptionMyTurn: clienttranslate('${you} must play a card or pass'),
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
                $card = $cardFactory->getCard($cardDef["type"]);
                if ($card->isExecutionPossible())
                {
                    $playableTactics[] = $card->getId();
                }
            }
        }

        $cardsPlayedThisTurn = self::getCardsPlayedThisTurn($this->game);
        $canPass =  $cardsPlayedThisTurn > 0;
        $canPlayCard = $cardsPlayedThisTurn < 3;

        return [
            'canPass' => $canPass,
            'canPlayCard' => $canPlayCard,
            'goalieHasBall' => $goalieHasBall,
            // card types from the hand, so only the active player may receive them
            '_private' => [
                $activePlayer => ['playableTactics' => $playableTactics],
            ],
        ];
    }

    #[PossibleAction]
    public function actCardSelected(int $cardId, bool $asBasic): string
    {
        $activePlayer = \intval($this->game->getActivePlayerId());

        if (self::getCardsPlayedThisTurn($this->game) >= 3)
            throw new \BgaUserException(\clienttranslate("You can't play more than 3 cards in a turn!"));

        $playerHand = $this->game->getPlayerHand($activePlayer);

        $playerHand->discardCard($cardId);

        $playedCardType = $playerHand->getCard($cardId)['type'];

        $this->game->notify->all("cardPlayed", \clienttranslate('${player_name} plays ${cardLog}'), [
            'player_id' => $activePlayer,
            'player_name' => $this->game->getPlayerNameById($activePlayer),
            'cardType' => $playedCardType,
            'cardLog' => '',
            'activePlayer' => $activePlayer,
            'discards' => $playerHand->getDiscardedCards(),
            'cardsInDeckCount' => $playerHand->CountCardsInDeck(),
            'preserve' => ['cardType'],
            ]);

        self::increaseCardsPlayedThisTurnByOne($this->game);

        $board = $this->game->getBoard();
        $goalieHasBall = BoardEvaluator::doesPlayerGoalieHasBall($board, $activePlayer);

        GameStats::recordCardPlayed($this->game, $activePlayer, $playedCardType, $asBasic || $goalieHasBall);

        $dbAdapter = DBAdapter::getInstance();
        if ($goalieHasBall)
        {
            $dbAdapter->storeActionReturnState($activePlayer, BeforeCardSelectionReset::class);
            return PassDestSelection::class;
        }
        else if ($asBasic)
        {
            $dbAdapter->storeActionReturnState($activePlayer, BeforeCardSelectionReset::class);
            BasicTurnLogic::setMoveAvailabilityInBasic(true);
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
                throw new \BgaUserException(\clienttranslate("Invalid card selected for execution, execution impossible"));

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
        $cardsInHand = $this->game->getPlayerHand($playerId)->getCardsInHand();

        if (self::getCardsPlayedThisTurn($this->game) > 0 || empty($cardsInHand))
            return $this->actPass();

        $cardIds = array_map(fn($card) => intval($card['id']), array_values($cardsInHand));

        return $this->actCardSelected($this->getRandomZombieChoice($cardIds), true);
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
        GameStats::undoCardPlayed($game, $activePlayer);
        BasicTurnLogic::setMoveAvailabilityInBasic(false);

        $game->notify->all("cardUndo", \clienttranslate('${player_name} undoes playing ${cardLog}'), [
            'player_id' => $activePlayer,
            'player_name' => $game->getPlayerNameById($activePlayer),
            'cardType' => $cardMoved['type'],
            'cardLog' => '',
            'undoCard' => $cardMoved,
            'cardsInDeckCount' => $playerHand->CountCardsInDeck(),
            'preserve' => ['cardType'],
        ]);

        self::setWasLastActionUndone(true);
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

    public static function getWasLastActionUndone() : bool
    {
        return DBAdapter::getInstance()->getGlobalBool('was_last_action_undone');
    }

    public static function setWasLastActionUndone(bool $wasLastActionUndone) : void
    {
        DBAdapter::getInstance()->storeGlobalBool('was_last_action_undone', $wasLastActionUndone);
    }
}
