<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\BasicTurnLogic;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\CardReward;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
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
            description: clienttranslate('${actplayer} must ${selectionTitle} ${cardCounter}'),
            descriptionMyTurn: clienttranslate('${you} must ${selectionTitle} ${cardCounter}'),
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
        $canPass = self::canPass($this->game, $activePlayer);
        $canPlayCard = $cardsPlayedThisTurn < 3;

        if ($canPlayCard && $canPass)
        {
            $title = clienttranslate('play a card or end turn');
            $counter = '(' . ($cardsPlayedThisTurn + 1) . '/3)';
        }
        else if ($canPlayCard && !$canPass)
        {
            $title = clienttranslate('play a card');
            $counter = '(' . ($cardsPlayedThisTurn + 1) . '/3)';
        }
        else
        {
            $title = clienttranslate('end turn');
            $counter = '';
        }

        return [
            'canPass' => $canPass,
            'canPlayCard' => $canPlayCard,
            'goalieHasBall' => $goalieHasBall,
            'selectionTitle' => $title,
            'cardCounter' => $counter,
            'i18n' => ['selectionTitle'],
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

        if (!$playerHand->isCardInHand($cardId))
            throw new \BgaUserException(\clienttranslate("That card is not in your hand!"));

        $board = $this->game->getBoard();
        $goalieHasBall = BoardEvaluator::doesPlayerGoalieHasBall($board, $activePlayer);

        // checked before the discard: some cards count the rest of the hand
        if (!$asBasic && !$goalieHasBall)
        {
            $tactic = $this->game->getCardFactory()->getCard($playerHand->getCard($cardId)['type']);
            if (!$tactic->isExecutionPossible())
                throw new \BgaUserException(\clienttranslate("Invalid card selected for execution, execution impossible"));
        }

        $playerHand->discardCard($cardId);
        $playedCardType = $playerHand->getCard($cardId)['type'];

        $message = "";
        if($goalieHasBall)
            $message = \clienttranslate('${player_name} plays ${cardLog} as Goalie pass.');
        else if ($playedCardType === CardTypeHelper::getFatigueCardType())
            $message = \clienttranslate('${player_name} plays ${cardLog}');
        else if ($asBasic)
            $message = \clienttranslate('${player_name} plays ${cardLog} as Basic.');
        else
            $message = \clienttranslate('${player_name} plays ${cardLog} as Skill.');

        $this->game->notify->all("cardPlayed", $message, [
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

        DBAdapter::getInstance()->storeGlobalInt('morale_before_card_play', (new PlayerResources($activePlayer))->getMorale());

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

            $tacticContext->setTacticInPlay($playedCardType);
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
        if (!self::canPass($this->game, \intval($this->game->getActivePlayerId())))
            throw new \BgaUserException(\clienttranslate("You must play at least one card before passing!"));

        return PassTurn::class;
    }

    // an empty hand can never play a card, so passing must stay possible
    private static function canPass(Game $game, int $playerId): bool
    {
        return self::getCardsPlayedThisTurn($game) > 0 || empty($game->getPlayerHand($playerId)->getCardsInHand());
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

    // captain and coach cards can be taken back until their first piece or card selection is made
    public static function isPlayedCardUndoable(Game $game, int $playerId): bool
    {
        if ((new TacticContext())->GetTacticCommited())
            return false;

        if (DBAdapter::getInstance()->getPlayerActionReturnState($playerId) !== TacticalTurnStateManager::class)
            return false;

        $cardType = self::getCardInPlayType($game);

        return $cardType === CardType::Captain || $cardType === CardType::Coach;
    }

    private static function getCardInPlayType(Game $game): ?CardType
    {
        $cardInPlay = (new TacticContext())->getTacticInPlay();

        return empty($cardInPlay) ? null : $game->getCardFactory()->getCard($cardInPlay)->getDefinition()->type;
    }

    public static function undoPlayedCard(Game $game, int $playerId): string
    {
        if (!self::isPlayedCardUndoable($game, $playerId))
            throw new \BgaUserException(\clienttranslate("You cannot undo this action!"));

        self::undoLastCardPlayed($playerId, $game);
        self::restoreMoraleBeforeCardPlay($game, $playerId);

        return BeforeCardSelectionReset::class;
    }

    // the card may have been paid for or rewarded with morale before the first selection
    private static function restoreMoraleBeforeCardPlay(Game $game, int $playerId): void
    {
        $playerResources = new PlayerResources($playerId);
        $difference = DBAdapter::getInstance()->getGlobalInt('morale_before_card_play') - $playerResources->getMorale();

        if ($difference === 0)
            return;

        $playerResources->addMorale($difference, true);
        GameStats::undoMoraleChange($game, $playerId, abs($difference));
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
