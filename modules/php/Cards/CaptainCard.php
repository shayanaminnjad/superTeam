<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\BasicTurnLogic;
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
use Bga\Games\SuperTeam\States\BypassTargetSelection;
use Bga\Games\SuperTeam\States\CaptainCardPerformerSelection;
use Bga\Games\SuperTeam\States\MoveAction;
use Bga\Games\SuperTeam\States\MovePieceSelection;
use Bga\Games\SuperTeam\States\PassDestSelection;
use Bga\Games\SuperTeam\States\PlayerBasicTurn;
use Bga\Games\SuperTeam\States\PushingPieceSelection;
use Bga\Games\SuperTeam\States\PushTargetSelection;
use Bga\Games\SuperTeam\States\ShootTargetSelection;
use Bga\Games\SuperTeam\States\SwapPiece;

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

    protected function giveMoraleIfCaptainPassed() : ExecutionResult
    {
        $playerId = intval($this->game->getActivePlayerId());
        $passActionContext = new PassActionContext($playerId);

        $piecesPassedIds = $passActionContext->getPiecesPassedIds();
        assert(count($piecesPassedIds) ==1);

        if ($this->board->getPiece($piecesPassedIds[0])->getRole() == PlayerRole::Captain->value)
        {
            $playerId = \intval($this->game->GetActivePlayerId());
            $playerResources = new PlayerResources($playerId);
            $playerResources->addMorale(1, true);
        }

        return new ExecutionResult(ExecutionAction::Success);
    }

    protected function goToShootState() : ExecutionResult
    {
        ShootTargetSelection::resetTargets($this->game);
        $this->game->gamestate->setAllPlayersMultiactive();
        return new ExecutionResult(ExecutionAction::GoToState, ShootTargetSelection::class);
    }

    protected function goToFreeMoveState() : ExecutionResult
    {
        $moveActionContext = new MoveActionContext();
        $moveActionContext->setCurrentMoveType(MoveTypes::FREE);

        return new ExecutionResult(ExecutionAction::GoToState, MovePieceSelection::class);
    }

    // Second part move: the performer is already known, so piece selection is skipped.
    protected function goToFreeMoveWithSelectedPiece() : ExecutionResult
    {
        $selectablePieces = (new PieceSelectionContext())->getSelectablePieceIds();

        if (count($selectablePieces) != 1)
            return $this->goToFreeMoveState();

        $playerId = \intval($this->game->getActivePlayerId());
        $piece = $this->board->getPiece($selectablePieces[0]);

        if (!MovePieceSelectionLogic::trySelectPieceForFreeMove($this->board, $piece, $playerId))
            return new ExecutionResult(ExecutionAction::Continue);

        return new ExecutionResult(ExecutionAction::GoToState, MoveAction::class);
    }

    protected function goToByPassState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, ByPassTargetSelection::class);
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

    protected function ifCaptainSelectedGiveMorale() : ExecutionResult
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePieces = $pieceSelectionContext->getSelectablePieceIds();

        assert(count($selectablePieces) == 1);

        if ($this->board->getPiece($selectablePieces[0])->getRole() == PlayerRole::Captain->value)
        {
            $playerId = \intval($this->game->GetActivePlayerId());
            $playerResources = new PlayerResources($playerId);
            $playerResources->addMorale(1, true);
        }

        return new ExecutionResult(ExecutionAction::Success);
    }

    protected function goToCaptainCardPerformerSelectionState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, CaptainCardPerformerSelection::class);
    }

    protected function goToBasicActionState() : ExecutionResult
    {
        $selectablePieces = (new PieceSelectionContext())->getSelectablePieceIds();

        if (count($selectablePieces) == 1)
        {
            $playerId = \intval($this->game->getActivePlayerId());
            $actions = BasicTurnLogic::getActionsDoableByPiece($this->board, $this->board->getPiece($selectablePieces[0]),
                $playerId, $this->game->getInactivePlayerId());

            if (empty($actions))
                return new ExecutionResult(ExecutionAction::Continue);
        }

        return new ExecutionResult(ExecutionAction::GoToState, PlayerBasicTurn::class);
    }

    protected function goToPushState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, PushingPieceSelection::class);
    }

    protected function goToSwapPieceState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, SwapPiece::class);
    }

    protected function goToMovePieceSelection() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, MovePieceSelection::class);
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

    protected function enforceAdjacentPieces() : ExecutionResult
    {
        $playerId = \intval($this->game->GetActivePlayerId());
        $adjacentPieces = BoardEvaluator::getPlayerPiecesAdjacentToOpponent($this->board, $playerId);

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePieces($adjacentPieces);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    protected function giveMoraleIfCaptainShoot() : ExecutionResult
    {
        $shootingPieceId = DBAdapter::getInstance()->getGlobalInt('last_player_who_shoot');
        $piece = $this->board->getPiece($shootingPieceId);

        if ($piece->getRole() === PlayerRole::Captain->value)
        {
            $playerResources = new PlayerResources(intval($this->game->getActivePlayerId()));
            $playerResources->addMorale(1, true);
        }

        return new ExecutionResult(ExecutionAction::Continue);
    }
}