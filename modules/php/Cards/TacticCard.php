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
use Bga\Games\SuperTeam\PlayerRole;
use Bga\Games\SuperTeam\PlayerSide;
use Bga\Games\SuperTeam\States\MultiMove;
use Bga\Games\SuperTeam\States\PassDestSelection;

abstract class TacticCard extends Card
{
    private TacticCardDefinition $tacticCardDefinition;
    public function __construct(TacticCardDefinition $definition, Game $game)
    {
        $this->tacticCardDefinition = $definition;

        parent::__construct($definition, $game);
    }

    //TODO: I don't need to implement this per class! the algorithm is the same for all of them, just the parameters change!
    abstract protected function getOffensiveCommands() : array;

    final protected function isOffensiveMovePossible(): bool
    {
        $currentPlayer =  \intval($this->game->GetActivePlayerId());
        $playerHasTheBall = BoardEvaluator::doesPlayerHasTheBall($this->board, $currentPlayer);
        $startHex = $this->board->getBallHex();

        return
            $playerHasTheBall &&
            PassTargetSelectionLogic::isPassSequencePossible(
                $this->board,
                $this->tacticCardDefinition->offensivePassSequence,
                $currentPlayer,
                $startHex,
                $this->game->getPlayerSide($currentPlayer));
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

    final public function hasFatigue() : bool
    {
        $tacticContext = new TacticContext();
        $tacticType = $tacticContext->getTacticType();

        if ($tacticType == TacticType::Defensive)
            return $this->definition->defenseHasFatigue;
        else
            return $this->definition->offenseHasFatigue;
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
        $playerId = \intval($this->game->GetActivePlayerId());
        $startHex = $this->board->getBallHex();

        $forced = PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::THROUGH, $playerId, $startHex, $this->game->getPlayerSide($playerId));

        if ($forced)
            return new ExecutionResult(ExecutionAction::Continue);
        else
            return new ExecutionResult(ExecutionAction::Failure);
    }

    final protected function forceForwardPassTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->GetActivePlayerId());
        $startHex = $this->board->getBallHex();

        $forced = PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::FORWARD, $playerId, $startHex, $this->game->getPlayerSide($playerId));

        if ($forced)
            return new ExecutionResult(ExecutionAction::Continue);
        else
            return new ExecutionResult(ExecutionAction::Failure);
    }

    final protected function forceBackwardPassTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->GetActivePlayerId());
        $startHex = $this->board->getBallHex();

        $forced = PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::BACKWARD, $playerId, $startHex, $this->game->getPlayerSide($playerId));

        if ($forced)
            return new ExecutionResult(ExecutionAction::Continue);
        else
            return new ExecutionResult(ExecutionAction::Failure);
    }

    final protected function forceNearOpponentTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->GetActivePlayerId());
        $startHex = $this->board->getBallHex();

        $forced = PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::NEAR_OPPONENT, $playerId, $startHex, $this->game->getPlayerSide($playerId));

        if ($forced)
            return new ExecutionResult(ExecutionAction::Continue);
        else
            return new ExecutionResult(ExecutionAction::Failure);
    }

    final protected function forceCrossPassTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->GetActivePlayerId());
        $startHex = $this->board->getBallHex();

        $forced = PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::CROSS, $playerId, $startHex, $this->game->getPlayerSide($playerId));

        if ($forced)
            return new ExecutionResult(ExecutionAction::Continue);
        else
            return new ExecutionResult(ExecutionAction::Failure);
    }

    final protected function checkThroughPassResult() : ExecutionResult
    {
        $passResult = false;

        $playerId = \intval($this->game->GetActivePlayerId());
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
        $playerId = \intval($this->game->GetActivePlayerId());
        $piecesAvailableMoves = $this->getPiecesPossibleMoves();
        $demand = $this->getDefinition()->cardDefensiveMoves->toArray();
        $tiredPiecesIds = $this->getTiredPiecesIds();
        $playerResources = new PlayerResources($playerId);

        return (new BipartiteMatcher())->canSatisfyDemand($piecesAvailableMoves, $tiredPiecesIds, $playerResources->getMorale(), $demand);
    }

    private function getPiecesPossibleMoves() : array
    {
        $playerId = intval($this->game->GetActivePlayerId());
        $playerPieces = $this->board->getPlayerPiecesByOwner($playerId);
        $resources = new PlayerResources($playerId);

        $defensiveMoves = $this->definition->cardDefensiveMoves;
        $piecesAvailableMoves = [];

        $playerSide = Board::getPlayerSide($playerId);

        foreach ($playerPieces as $playerPiece)
        {
            if ($playerPiece->getRole() === PlayerRole::GoalKeeper->value)
                continue;

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

    /**
     * @return array<int>
     */
    private function getTiredPiecesIds() : array
    {
        $result = [];

        $playerId = intval($this->game->GetActivePlayerId());
        $playerPieces = $this->board->getPlayerPiecesByOwner($playerId);
        $tiredPieces = array_filter($playerPieces, fn ($piece) => $piece->isFresh() === false && $piece->getRole() != PlayerRole::GoalKeeper->value);

        foreach($tiredPieces as $piece)
            $result[] = $piece->getId();

        return $result;
    }

    protected function cleanup() : void
    {
        DBAdapter::GetInstance()->storePlayerTempStr(intval($this->game->getActivePlayerId()), "forced_pass_targets", "");
    }
}
