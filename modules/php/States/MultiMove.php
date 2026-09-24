<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;


use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
use Bga\Games\SuperTeam\NotificationAdapter;
use Bga\Games\SuperTeam\PlayerResources;

class MultiMove extends PlayerBaseState
{
    function __construct(
        Game $game,
    ) {
        parent::__construct(
            game: $game,
            name: 'multiMove',
            id: ST_MULTI_MOVE,
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} should choose a move'),
            descriptionMyTurn: clienttranslate('${actplayer} chooses a move'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function onEnteringState()
    {
        //fixme: this actually happens after self get args, change it, since we need it before!
        if ($this->getRemainingMoves(new MoveActionContext())->isEmpty())
            return TacticalTurnStateManager::class;
    }

    public function selfGetArgs(): array
    {
        $moveActionContext = new MoveActionContext();
        $movesRemainingArr = $this->getRemainingMoves($moveActionContext)->toArray();

        foreach($movesRemainingArr as $key => $value)
        {
            if ($value > 0)
            {
                $this->addClientButton("{$value} [$key]", [$this, 'actMoveChosen'], ['key' => $key]);
            }
        }

        if (!$this->hasPerformedAnyMoves($moveActionContext))
        {
            $this->addClientButton("Undo", [$this, 'actUndo'], [], new ClientButtonParams(ClientButtonColor::ALERT));
        }

        return [];
    }

    #[PossibleAction]
    public function actMoveChosen(string $key)
    {
        $moveType = MoveTypes::tryFrom($key);
        if ($moveType === null)
            throw new \BgaUserException("invalid key sent `$key`");

        $movesRemaining = $this->getRemainingMoves(new MoveActionContext());

        if ($movesRemaining->getMoves($moveType) <= 0)
            throw new \BgaUserException("invalid move selected `$key`");

        $moveActionContext = new MoveActionContext();

        $movesPerformed = $moveActionContext->getMovesPerformed();
        $movesPerformed->increaseMoveByOne($moveType);
        $moveActionContext->storeMovesPerformed($movesPerformed);

        $playerId = \intval($this->game->getActivePlayerId());

        $anyPieceEnforced = MovePieceSelectionLogic::enforceMovePieceSelectionConstraints($this->game->getBoard(), $playerId, $moveType, true);

        if (!$anyPieceEnforced)
            throw new \BgaUserException("Invalid move selected! no piece can be moved with that criteria!");

        DBAdapter::getInstance()->storeActionReturnState($playerId, self::class);

        $moveActionContext->setCurrentMoveType($moveType);

        return MovePieceSelection::class;
    }

    #[PossibleAction]
    public function actUndo(int $activePlayerId) : string
    {
        /*
        $tacticContext = new TacticContext();
        $tacticInPlay = $tacticContext->getTacticInPlay();

        $hand = $this->game->getPlayerHand($activePlayerId);
        $hand->drawCard($tacticInPlay);

        $this->notify->player($activePlayerId, "handRefill", "",
            [
                'playerHand' => $hand->getCardsInHand(),
                'wasDiscardShuffled' => false,
            ]);

        $moveActionContext = new MoveActionContext();
        $ballPos = $moveActionContext->getBallInitialLocation();
        $board = $this->game->getBoard();
        $board->setBallLocation($ballPos);

        $piecesMoved = $moveActionContext->getPiecesMoved();

        $pieceMovedToSend = [];
        $freshPieces = [];
        foreach($piecesMoved as $pieceMoved)
        {
            $board->movePieceToHex($pieceMoved->id, $pieceMoved->initialPosition);
            $pieceMovedToSend[] = ["pieceId" => $pieceMoved->id, "position" => $pieceMoved->initialPosition->convertToArray()];
            if ($pieceMoved->wasFresh)
            {
                $freshPieces [] = $pieceMoved->id;
                $board->getPiece($pieceMoved->id)->setFreshness(true, false);
            }
        }

        $moraleBeforeTactic = $moveActionContext->getMoraleInitialAmount();
        $playerResources = new PlayerResources($activePlayerId);
        $playerResources->setMorale($moraleBeforeTactic, true);

        NotificationAdapter::getInstance()->notifyAll("undoMove", "undoMoves",
        [
            "ballHex" => $board->getBallHex(),
            "piecesToHexes" => $pieceMovedToSend,
            "freshPieces" => $freshPieces
        ]);*/

        if (!$this->hasPerformedAnyMoves(new MoveActionContext()))
        {
            $activePlayerId = \intval($this->game->getActivePlayerId());
            CardSelection::undoLastCardPlayed($activePlayerId, $this->game);

            return BeforeCardSelectionReset::class;
        }
        else
        {
            throw new \BgaUserException("undo is not possible after first move!");
        }
    }

    private function getRemainingMoves(MoveActionContext $defensiveMoveContext) : CardDefensiveMoves
    {
        $tacticContext = new TacticContext();

        $cardInPlay = $tacticContext->getTacticInPlay();
        $card = $this->game->getCardFactory()->getCard($cardInPlay);

        $cardDefensiveMoves = $card->getDefinition()->cardDefensiveMoves;

        $movesPerformedSoFar = $defensiveMoveContext->getMovesPerformed();

        return $cardDefensiveMoves->diff($movesPerformedSoFar);
    }

    private function hasPerformedAnyMoves(MoveActionContext $defensiveMoveContext) : bool
    {
        $movesPerformedSoFar = $defensiveMoveContext->getMovesPerformed();

        return !$movesPerformedSoFar->isEmpty();
    }

    function zombie(int $playerId): string
    {
        return '';
    }
}