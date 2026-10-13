<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;
use Bga\Games\SuperTeam\RequiredPower;
use Bga\Games\SuperTeam\States\CaptainCardPerformerSelection;
use Bga\Games\SuperTeam\States\MovePieceSelection;
use Bga\Games\SuperTeam\States\PassDestSelection;
use Bga\Games\SuperTeam\States\PlayerBasicTurn;
use Bga\Games\SuperTeam\States\ShootTargetSelection;

abstract class CaptainCard extends Card
{
    public function __construct(CardDefinition $definition, Game $game)
    {
        parent::__construct($definition, $game);
    }

    protected function giveMorale() : ExecutionResult
    {
        $playerId = \intval($this->game->getActivePlayerId());
        $playerResources = new PlayerResources($playerId);
        $playerResources->addMorale(1, true);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    protected function goToShootState() : ExecutionResult
    {
        $this->game->gamestate->setAllPlayersMultiactive();
        return new ExecutionResult(ExecutionAction::GoToState, ShootTargetSelection::class);
    }

    protected function goToFreeMoveState() : ExecutionResult
    {
        $moveActionContext = new MoveActionContext();
        $moveActionContext->setCurrentMoveType(MoveTypes::FREE);

        return new ExecutionResult(ExecutionAction::GoToState, MovePieceSelection::class);
    }

    protected function ifCaptainMovedGiveMorale() : ExecutionResult
    {
        $moveActionContext = new MoveActionContext();
        $piecesMoved = $moveActionContext->getPiecesMoved();

        if ($this->board->getPiece($piecesMoved[0]->id)->getRole() === PlayerRole::Captain->value)
        {
            $playerId = \intval($this->game->getActivePlayerId());
            $playerResources = new PlayerResources($playerId);
            $playerResources->addMorale(1, true);
        }

        return new ExecutionResult(ExecutionAction::Continue);
    }

    protected function goToCaptainCardPerformerSelectionState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, CaptainCardPerformerSelection::class);
    }

    protected function goToBasicActionState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, PlayerBasicTurn::class);
    }

    protected function makeNextActionForfeitable()
    {
        DBAdapter::getInstance()->storeGlobalBool('forfeitable_captain_action', true);
    }

    protected function enableSkip() : ExecutionResult
    {
        RequiredPower::setSkipEnabled(true);
        return new ExecutionResult(ExecutionAction::Continue);
    }

    protected function goToPassState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, PassDestSelection::class);
    }

    protected function isBallReceiverInsidePenaltyArea() : bool
    {
        $result = false;

        $playerId = intval($this->game->getActivePlayerId());
        $passActionContext = new PassActionContext($playerId);

        $pieceId = $passActionContext->getLastPassReceiverIdThisTurn();

        if (PlayerPiece::getInvalidPieceId() != $pieceId &&
            BoardEvaluator::isInsideOpponentPenaltyArea($playerId, $passActionContext->getLastPassReceiverPosThisTurn()))
        {
            $playerPiece = $this->board->getPlayerPieceData($pieceId);
            if (BoardEvaluator::isInsideOpponentPenaltyArea($playerId, $playerPiece->getPosition()))
            {
                assert($playerPiece->getPosition()->equals($this->board->getBallHex()));

                $result = true;
            }
        }

        return $result;
    }

    protected function isBallReceiverOutsidePenaltyArea() : bool
    {
        $result = false;

        $playerId = intval($this->game->getActivePlayerId());
        $passActionContext = new PassActionContext($playerId);

        $pieceId = $passActionContext->getLastPassReceiverIdThisTurn();

        if (PlayerPiece::getInvalidPieceId() != $pieceId &&
            !BoardEvaluator::isInsideOpponentPenaltyArea($playerId, $passActionContext->getLastPassReceiverPosThisTurn()))
        {
            $playerPiece = $this->board->getPlayerPieceData($pieceId);
            if (!BoardEvaluator::isInsideOpponentPenaltyArea($playerId, $playerPiece->getPosition()))
            {
                assert($playerPiece->getPosition()->equals($this->board->getBallHex()));

                $result = true;
            }
        }

        return $result;
    }

    protected function getPiecesNotAdjacentToOpponents() : array
    {
        $playerId = \intval($this->game->GetActivePlayerId());
        return MovePieceSelectionLogic::extractMoveableNotAdjacentToOpponentPieces($this->board, $playerId);
    }
}