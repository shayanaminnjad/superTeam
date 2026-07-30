<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;
use Bga\Games\SuperTeam\States\MovePieceSelection;
use Bga\Games\SuperTeam\States\ShootTargetSelection;

abstract class CaptainCard extends Card
{
    public function __construct(CardDefinition $definition, Game $game)
    {
        parent::__construct($definition, $game);
    }

    protected function giveMorale() : ExecutionResult
    {
        $playerId = \intval($this->game->getCurrentPlayerId());
        $playerResources = new PlayerResources($playerId);
        $playerResources->addMorale(1, true);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    protected function goToShootState() : ExecutionResult
    {
        $this->game->gamestate->setAllPlayersMultiactive();
        return new ExecutionResult(ExecutionAction::GoToState, ShootTargetSelection::class);
    }

    protected function goToMoveState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, MovePieceSelection::class);
    }

    protected function ifCaptainMovedGiveMorale() : ExecutionResult
    {
        $moveActionContext = new MoveActionContext();
        $piecesMoved = $moveActionContext->getPiecesMoved();

        if ($this->board->getPiece($piecesMoved[0]->id)->getRole() === PlayerRole::Captain->value)
        {
            $playerId = \intval($this->game->getCurrentPlayerId());
            $playerResources = new PlayerResources($playerId);
            $playerResources->addMorale(1, true);
        }

        return new ExecutionResult(ExecutionAction::Continue);
    }
}