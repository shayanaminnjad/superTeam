<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\Card;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;
use Bga\Games\SuperTeam\States\CoachCardResolver;
use Bga\Games\SuperTeam\States\TacticalTurnStateManager;

class CoachB extends Card
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Coach B",
            CardType::Coach,
            ""), $game);
    }

    public function isExecutionPossible(): bool
    {
        $playerId = intval($this->game->getCurrentPlayerId());
        $playerResources = new PlayerResources($playerId);

        return $playerResources->getMorale() > 0 && count($this->getExhaustedMoveablePieces()) > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCoachCardType(1);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    protected function getCommands(): array
    {
        return [
            $this->spendOneMorale(...),
            $this->goToCoachCardState(...),
            $this->finishOrJumpToMove(...)
        ];
    }

    private function goToCoachCardState() : ExecutionResult
    {
        $playerId = intval($this->game->getCurrentPlayerId());
        DBAdapter::getInstance()->storeActionReturnState($playerId, TacticalTurnStateManager::class);

        MovePieceSelectionLogic::storeMoveablePiecesIds($this->getExhaustedMoveablePieces());

        $moveActionContext = new MoveActionContext();
        $moveActionContext->setCurrentMoveType(MoveTypes::FREE);
        $moveActionContext->setExhaustedNeedMoraleForMovement(false);

        return new ExecutionResult(ExecutionAction::GoToState, CoachCardResolver::class);
    }

    private function spendOneMorale() : ExecutionResult
    {
        $playerId = intval($this->game->getCurrentPlayerId());
        $playerResources = new PlayerResources($playerId);
        $playerResources->addMorale(-1, true);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    private function finishOrJumpToMove() : ExecutionResult
    {
        if (count($this->getExhaustedMoveablePieces()) > 0)
        {
            return new ExecutionResult(ExecutionAction::Jump, null, 1);
        }
        else
        {
            return new ExecutionResult(ExecutionAction::Continue);
        }
    }

    protected function cleanup() : void
    {
    }

    private function getExhaustedMoveablePieces() : array
    {
        $playerId = \intval($this->game->getCurrentPlayerId());
        $exhaustedPieces =  BoardEvaluator::getExhaustedPlayerPieces($this->board, $playerId, false);

        return MovePieceSelectionLogic::excludePiecesMoveThisTurn($exhaustedPieces);
    }
}