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
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_1;
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\States\SwapPiece;


class Captain_A_2 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Captain 3"),
            CardType::Captain,
            \clienttranslate("**Requirement:** Play when NOT adjacent to any opponent.
**Effect:** Move and swap position with an opponent.")), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_1::staticId(), 2);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    public function isExecutionPossible(): bool
    {
        return count($this->getMoveablePiecesNotAdjacentToOpponents()) > 0;
    }

    protected function getCommands(): array
    {
        return [
            $this->enforceNotAdjacentPieces(...),
            $this->goToFreeMoveState(...),
            $this->makeNextActionForfeitable(...),
            $this->goToSwapStateIfPossible(...),
            $this->ifCaptainMovedGiveMorale(...)
        ];
    }

    protected function cleanup() : void
    {
    }

    private function enforceNotAdjacentPieces() : ExecutionResult
    {
        MovePieceSelectionLogic::storeMoveablePiecesIds($this->getMoveablePiecesNotAdjacentToOpponents());

        return new ExecutionResult(ExecutionAction::Continue);
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

            return new ExecutionResult(ExecutionAction::GoToState, SwapPiece::class);
        }
        else
        {
            return new ExecutionResult(ExecutionAction::Continue);
        }
    }
}