<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Algorithm\BipartiteMatcher;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BoardSpatial;
use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\PassTargetSelectionLogic;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerSide;
use Bga\Games\SuperTeam\States\MultiMove;
use Bga\Games\SuperTeam\States\PassDestSelection;

abstract class TacticCard extends Card
{
    public function __construct(CardDefinition $definition, Game $game)
    {
        parent::__construct($definition, $game);
    }

    abstract protected function getOffensiveCommands() : array;
    abstract protected function isOffensiveMovePossible(): bool;


    final public function isExecutionPossible(): bool
    {
        //This is checked before playing the card, so I know whether I am defender or attacker.
        $activePlayer = \intval($this->game->getActivePlayerId());
        $isAttacker = Board::isPlayerAttacker($activePlayer);

        return ($isAttacker && $this->isOffensiveMovePossible()) || (!$isAttacker && $this->isDefensivePossible());
    }

    final protected function getCommands(): array
    {
        //this step is done after tactic context is filled.
        $tacticContext = new TacticContext();
        $tacticType = $tacticContext->getTacticType();

        if ($tacticType == TacticType::Defensive)
            return $this->getDefensiveCommands();
        else
            return $this->getOffensiveCommands();
    }


    final protected function goToPassState() : ExecutionResult
    {
        DBAdapter::getInstance()->setGameStateValue('lastPassResult', 0);
        return new ExecutionResult(ExecutionAction::GoToState, PassDestSelection::class);
    }

    final protected function checkPassResult() : ExecutionResult
    {
        $passResult = DBAdapter::getInstance()->getGameStateValue("lastPassResult") == 1;
        return new ExecutionResult($passResult ? ExecutionAction::Continue : ExecutionAction::Failure);
    }

    final protected function forceThroughPassTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->getCurrentPlayerId());
        $startHex = $this->board->getBallHex();

        PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::THROUGH, $playerId, $startHex, $this->game->getPlayerSide($playerId));
        return new ExecutionResult(ExecutionAction::Continue);
    }

    final protected function forceForwardPassTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->getCurrentPlayerId());
        $startHex = $this->board->getBallHex();

        PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::FORWARD, $playerId, $startHex, $this->game->getPlayerSide($playerId));
        return new ExecutionResult(ExecutionAction::Continue);
    }

    final protected function forceBackwardPassTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->getCurrentPlayerId());
        $startHex = $this->board->getBallHex();

        PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::BACKWARD, $playerId, $startHex, $this->game->getPlayerSide($playerId));
        return new ExecutionResult(ExecutionAction::Continue);
    }

    final protected function forceNearOpponentTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->getCurrentPlayerId());
        $startHex = $this->board->getBallHex();

        PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::NEAR_OPPONENT, $playerId, $startHex, $this->game->getPlayerSide($playerId));
        return new ExecutionResult(ExecutionAction::Continue);
    }

    final protected function forceCrossPassTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->getCurrentPlayerId());
        $startHex = $this->board->getBallHex();

        PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::CROSS, $playerId, $startHex, $this->game->getPlayerSide($playerId));
        return new ExecutionResult(ExecutionAction::Continue);
    }

    final protected function checkThroughPassResult() : ExecutionResult
    {
        $passResult = false;

        $playerId = \intval($this->game->getCurrentPlayerId());
        $ballHex = $this->board->getBallHex();

        $playerPieces = $this->board->getPlayerPiecesByOwner($playerId);
        $playerSideSign = $this->game->getPlayerSide($playerId) === PlayerSide::Left ? 1 : -1;

        for($i = 0; $i < count($playerPieces) && $passResult == false; ++$i)
        {
            $piece = $playerPieces[$i];
            $frontHexes = HexLib::getFrontArc($piece->getPosition(), $playerSideSign);

            foreach($frontHexes as $frontHex)
            {
                if ($ballHex->equals($frontHex))
                {
                    $passResult = true;
                    break;
                }
            }
        }

        return new ExecutionResult($passResult ? ExecutionAction::Continue : ExecutionAction::Failure);
    }

    private function getDefensiveCommands(): array
    {
        return [$this->goToMultiMoveState(...)];
    }

    private function goToMultiMoveState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, MultiMove::class);
    }

    private function isDefensivePossible(): bool
    {
        $piecesAvailableMoves = $this->getPiecesPossibleMoves();
        $demand = $this->getDefinition()->cardDefensiveMoves->toArray();

        return (new BipartiteMatcher())->canSatisfyDemand($piecesAvailableMoves, $demand);
    }

    private function getPiecesPossibleMoves() : array
    {
        $playerId = intval($this->game->getCurrentPlayerId());
        $playerPieces = $this->board->getPlayerPiecesByOwner($playerId);
        $resources = new PlayerResources($playerId);

        $defensiveMoves = $this->definition->cardDefensiveMoves;
        $piecesAvailableMoves = [];

        $playerSide = Board::getPlayerSide($playerId);

        foreach ($playerPieces as $playerPiece)
        {
            $playerPieceId = $playerPiece->getId();
            $pieceHasBall = BoardEvaluator::doesPieceHasTheBall($this->board, $playerPiece);
            $maxMoves = BoardEvaluator::getPieceMaxPossibleMovement($this->board, $playerId, $playerPiece, $resources);

            if ($defensiveMoves->getMoves(MoveTypes::FREE) > 0)
            {
                if (count(BoardSpatial::getMoveableHexes($this->board, $playerPiece->getPosition(),  $maxMoves, $pieceHasBall, $playerId)) > 0)
                {
                    $piecesAvailableMoves[$playerPieceId][] = MoveTypes::FREE->value;
                }
            }
            if ($defensiveMoves->getMoves(MoveTypes::BACKWARD) > 0)
            {
                if (BoardSpatial::canFindPathToTarget($this->board, $playerPiece->getPosition(), $maxMoves, $pieceHasBall, $playerId,
                    fn($board, $hex, $playerId) => BoardSpatial::isColumnInPieceBackward($playerPiece->getPosition()->getColumn(), $hex->getColumn(), $playerSide)))
                {
                    $piecesAvailableMoves[$playerPieceId][] = MoveTypes::BACKWARD->value;
                }
            }
            if ($defensiveMoves->getMoves(MoveTypes::FORWARD) > 0)
            {
                if (BoardSpatial::canFindPathToTarget($this->board, $playerPiece->getPosition(), $maxMoves, $pieceHasBall, $playerId,
                    fn($board, $hex, $playerId) => BoardSpatial::isColumnInPieceForward($playerPiece->getPosition()->getColumn(), $hex->getColumn(), $playerSide)))
                {
                    $piecesAvailableMoves[$playerPieceId][] = MoveTypes::FORWARD->value;

                }
            }
            if ($defensiveMoves->getMoves(MoveTypes::BACKWARD_NEAR_OPPONENT) > 0)
            {
                if (BoardSpatial::canFindPathToTarget($this->board, $playerPiece->getPosition(), $maxMoves, $pieceHasBall, $playerId,
                    fn($board, $hex, $playerId) => BoardSpatial::isColumnInPieceBackward($playerPiece->getPosition()->getColumn(), $hex->getColumn(), $playerSide) &&
                        BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)))
                {
                    $piecesAvailableMoves[$playerPieceId][] = MoveTypes::BACKWARD_NEAR_OPPONENT->value;

                }
            }
            if ($defensiveMoves->getMoves(MoveTypes::FORWARD_NEAR_OPPONENT) > 0)
            {
                if (BoardSpatial::canFindPathToTarget($this->board, $playerPiece->getPosition(), $maxMoves, $pieceHasBall, $playerId,
                    fn($board, $hex, $playerId) => BoardSpatial::isColumnInPieceForward($playerPiece->getPosition()->getColumn(), $hex->getColumn(), $playerSide) &&
                        BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)))
                {
                    $piecesAvailableMoves[$playerPieceId][] = MoveTypes::FORWARD_NEAR_OPPONENT->value;
                }
            }
        }

        return $piecesAvailableMoves;
    }

    protected function cleanup() : void
    {
        DBAdapter::GetInstance()->storePlayerTempStr(intval($this->game->getActivePlayerId()), "forced_pass_targets", "");
    }
}
