<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_4;
use Bga\Games\SuperTeam\States\MovePieceSelection;
use Bga\Games\SuperTeam\States\TacticalTurnStateManager;

class Captain_4_1 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
                \clienttranslate("Captain 2"),
                CardType::Captain,
                \clienttranslate("**Requirement:** Play when NOT adjacent to any opponent.
**Effect:** Move and perform an action."))
            , $game);
    }
    public function isExecutionPossible(): bool
    {
        return count($this->getMoveablePiecesNotAdjacentToOpponents()) > 0;
    }


    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_4::staticId(), 1);
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
            $this->prepareMoveContext(...),
            $this->goToMovePieceSelection(...),
            $this->enforceLastPieceSelected(...),
            $this->makeNextActionForfeitable(...),
            $this->goToBasicActionState(...),
            $this->ifCaptainMovedGiveMorale(...)
        ];
    }

    private function prepareMoveContext() : ExecutionResult
    {
        $playerId = intval($this->game->GetActivePlayerId());
        DBAdapter::getInstance()->storeActionReturnState($playerId, TacticalTurnStateManager::class);

        MovePieceSelectionLogic::storeMoveablePiecesIds($this->getMoveablePiecesNotAdjacentToOpponents());

        $moveActionContext = new MoveActionContext();
        $moveActionContext->setCurrentMoveType(MoveTypes::FREE);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    private function enforceLastPieceSelected() : ExecutionResult
    {
        $moveActionContext = new MoveActionContext();
        $piecesMoved = $moveActionContext->getPiecesMoved();

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePiecesIds([$piecesMoved[count($piecesMoved) - 1]->id]);

        return new ExecutionResult(ExecutionAction::Continue);
    }
    protected function cleanup(): void
    {
    }
}