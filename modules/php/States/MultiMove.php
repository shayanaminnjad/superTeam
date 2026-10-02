<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;


use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Cards\TacticCard;
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
                $this->addClientButton(_("{$value} [$key]"), [$this, 'actMoveChosen'], ['key' => $key]);
            }
        }

        if (!$this->hasPerformedAnyMoves($moveActionContext))
        {
            $this->addClientButton(\clienttranslate("Undo"), [$this, 'actUndo'], [], new ClientButtonParams(ClientButtonColor::ALERT));
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

        $this->keepOnlyPiecesThatLeaveCardCompletable($moveActionContext);

        DBAdapter::getInstance()->storeActionReturnState($playerId, self::class);

        $moveActionContext->setCurrentMoveType($moveType);

        return MovePieceSelection::class;
    }

    #[PossibleAction]
    public function actUndo(int $activePlayerId) : string
    {
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
        $card = $this->getTacticCardInPlay();

        $cardDefensiveMoves = $card->getDefinition()->cardDefensiveMoves;

        $movesPerformedSoFar = $defensiveMoveContext->getMovesPerformed();

        return $cardDefensiveMoves->diff($movesPerformedSoFar);
    }

    private function getTacticCardInPlay() : TacticCard
    {
        $tacticContext = new TacticContext();

        return $this->game->getCardFactory()->getCard($tacticContext->getTacticInPlay());
    }

    private function keepOnlyPiecesThatLeaveCardCompletable(MoveActionContext $moveActionContext) : void
    {
        $card = $this->getTacticCardInPlay();
        $remainingDemand = $this->getRemainingMoves($moveActionContext);
        $alreadyMovedIds = array_map(fn($p) => $p->id, $moveActionContext->getPiecesMoved());

        $pieceSelectionContext = new PieceSelectionContext();
        $candidateIds = $pieceSelectionContext->getSelectablePieceIds();

        $feasibleIds = array_values(array_filter($candidateIds,
            fn($id) => $card->canPieceSatisfyRemainingDemand($id, $remainingDemand, $alreadyMovedIds)));

        if (empty($feasibleIds))
            throw new \BgaUserException("No piece can be selected here without making the rest of the card impossible to complete!");

        $pieceSelectionContext->storeSelectablePiecesIds($feasibleIds);
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