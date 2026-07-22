<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Algorithm\BipartiteMatcher;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BoardSpatial;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\States\MultiMove;

abstract class TacticCard extends Card
{
    public function __construct(CardDefinition $definition, Game $game, Board $board)
    {
        parent::__construct($definition, $board, $game);
    }
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

    abstract function getOffensiveCommands() : array;

    protected function getDefensiveCommands(): array
    {
        return [$this->goToMultiMoveState(...)];
    }

    private function goToMultiMoveState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, MultiMove::class);
    }

    abstract protected function cleanup() : void;

    abstract protected function isOffensiveMovePossible(): bool;


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
}