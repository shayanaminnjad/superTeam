<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\States\MovePieceSelection;
use Bga\Games\SuperTeam\States\PieceToSwapSelection;
use Bga\Games\SuperTeam\States\TacklePerformerSelection;


class Captain_A_2 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Captain 3",
            CardType::Captain,
            ""), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(0, 2);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    public function isExecutionPossible(): bool
    {
        $candidates = $this->getMoveableNotAdjacentToOpponentPieces();

        return count($candidates) > 0;
    }

    protected function getCommands(): array
    {
        return [
            $this->enforceNotAdjacentPieces(...),
            $this->goToFreeMoveState(...),
            $this->goToSwapStateIfPossible(...),
            $this->ifCaptainMovedGiveMorale(...)
        ];
    }

    protected function cleanup()
    {
    }

    private function enforceNotAdjacentPieces() : ExecutionResult
    {
        $candidates = $this->getMoveableNotAdjacentToOpponentPieces();

        MovePieceSelectionLogic::storeMoveablePiecesIds($candidates);

        return new ExecutionResult(ExecutionAction::Continue);
    }


    /**
     * @return PlayerPiece[]
     */
    private function getMoveableNotAdjacentToOpponentPieces(): array
    {
        $playerId = intval($this->game->getActivePlayerId());

        $freeMoveablePieces = MovePieceSelectionLogic::extractFreeMoveMoveablePieces($this->board, $playerId);
        $playerPiecesNotAdjacent = BoardEvaluator::getPlayerPiecesNotAdjacentToOpponent($this->board, $playerId);

        return array_uintersect($freeMoveablePieces, $playerPiecesNotAdjacent, fn ($a, $b) => $a->getId() <=> $b->getId());
    }

    private function goToSwapStateIfPossible() : ExecutionResult
    {
        $moveActionContext = new MoveActionContext();

        $piecesMoved = $moveActionContext->getPiecesMoved();
        $movedPiecePosition = $this->board->getPiece($piecesMoved[0]->id)->getPosition();

        $playerId = intval($this->game->getActivePlayerId());

        if (BoardEvaluator::hasAdjacentOpponentPieces($this->board, $movedPiecePosition, $playerId, false))
        {
            $pieceSelectionContext = new PieceSelectionContext();
            $pieceSelectionContext->storeSelectablePiecesIds([$piecesMoved[0]->id]);

            return new ExecutionResult(ExecutionAction::GoToState, PieceToSwapSelection::class);
        }
        else
        {
            return new ExecutionResult(ExecutionAction::Continue);
        }
    }
}