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
use Bga\Games\SuperTeam\States\MoveAction;
use Bga\Games\SuperTeam\States\TacticalTurnStateManager;
use Bga\Games\SuperTeam\Utility\Logger;

class CoachA extends Card
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Coach A",
            CardType::Coach,
            \clienttranslate("**Requirement:** Pay 1 Morale.
**Effect:** Move all Ready pieces up to 2 spaces.")), $game);
    }

    public function isExecutionPossible(): bool
    {
        $playerId = intval($this->game->GetActivePlayerId());
        $playerResources = new PlayerResources($playerId);

        return $playerResources->getMorale() > 0 && count($this->getFreshMoveablePieces()) > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCoachCardType(0);
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
        $playerId = intval($this->game->GetActivePlayerId());
        DBAdapter::getInstance()->storeActionReturnState($playerId, TacticalTurnStateManager::class);

        MovePieceSelectionLogic::storeMoveablePiecesIds($this->getFreshMoveablePieces());

        $moveActionContext = new MoveActionContext();
        $moveActionContext->setCurrentMoveType(MoveTypes::FREE);


        return new ExecutionResult(ExecutionAction::GoToState, CoachCardResolver::class);
    }

    private function spendOneMorale() : ExecutionResult
    {
        $playerId = intval($this->game->GetActivePlayerId());
        $playerResources = new PlayerResources($playerId);
        $playerResources->addMorale(-1, true);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    private function finishOrJumpToMove() : ExecutionResult
    {
        if (count($this->getFreshMoveablePieces()) > 0)
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

    private function getFreshMoveablePieces() : array
    {
        $playerId = \intval($this->game->GetActivePlayerId());
        $freshPieces =  BoardEvaluator::getFreshPlayerPieces($this->board, $playerId, false);

        return MovePieceSelectionLogic::excludePiecesMoveThisTurn($freshPieces);
    }
}