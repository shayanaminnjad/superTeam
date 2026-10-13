<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Algorithm\BipartiteMatcher;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BoardSpatial;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
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

    private function forceFeasiblePassTargetsForType(PassTypes $passType) : ExecutionResult
    {
        $playerId = \intval($this->game->GetActivePlayerId());
        $startHex = $this->board->getBallHex();
        $playerSide = $this->game->getPlayerSide($playerId);

        $passesDoneSoFar = \count((new PassActionContext($playerId))->getPiecesPassedIds());
        $remainingAfterThis = \array_slice($this->tacticCardDefinition->offensivePassSequence, $passesDoneSoFar + 1);

        $forced = PassTargetSelectionLogic::forceFeasiblePassTargets($this->board, $passType, $remainingAfterThis, $playerId, $startHex, $playerSide);

        if ($forced)
            return new ExecutionResult(ExecutionAction::Continue);
        else
            return new ExecutionResult(ExecutionAction::Failure);
    }

    //FIXME: why I do this? I can simply just check the pass result from the dice?
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

    private function getOffensiveCommands(): array
    {
        $commands = [];

        foreach ($this->tacticCardDefinition->offensivePassSequence as $passType)
        {
            $commands[] = fn() => $this->forceFeasiblePassTargetsForType($passType);
            $commands[] = $this->goToPassState(...);
            $commands[] = PassTargetSelectionLogic::isThroughPassFamily($passType) ? $this->checkThroughPassResult(...) : $this->checkPassResult(...);
        }

        return $commands;
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
        return $this->getPiecesPossibleMovesForDemand($this->definition->cardDefensiveMoves);
    }

    public function getPiecesPossibleMovesForDemand(CardDefensiveMoves $demand, array $excludedPieceIds = []) : array
    {
        $playerId = intval($this->game->GetActivePlayerId());
        $playerPieces = $this->board->getPlayerPiecesByOwner($playerId);
        $resources = new PlayerResources($playerId);

        $defensiveMoves = $demand;
        $piecesAvailableMoves = [];

        $playerSide = Board::getPlayerSide($playerId);

        foreach ($playerPieces as $playerPiece)
        {
            if ($playerPiece->getRole() === PlayerRole::GoalKeeper->value)
                continue;

            if (array_any($excludedPieceIds, fn($id) => $id === $playerPiece->getId()))
                continue;

            $playerPieceId = $playerPiece->getId();
            if ($defensiveMoves->getMoves(MoveTypes::FREE) > 0)
            {
                if (BoardSpatial::canFindPathToTarget($this->board, $playerPiece, fn($board, $hex, $playerId) => true))
                {
                    $piecesAvailableMoves[$playerPieceId][] = MoveTypes::FREE->value;
                }
            }
            if ($defensiveMoves->getMoves(MoveTypes::BACKWARD) > 0)
            {
                if (BoardSpatial::canFindPathToTarget($this->board, $playerPiece,
                    fn($board, $hex, $playerId) => BoardSpatial::isColumnInPieceBackward($playerPiece->getPosition()->getColumn(), $hex->getColumn(), $playerSide)))
                {
                    $piecesAvailableMoves[$playerPieceId][] = MoveTypes::BACKWARD->value;
                }
            }
            if ($defensiveMoves->getMoves(MoveTypes::FORWARD) > 0)
            {
                if (BoardSpatial::canFindPathToTarget($this->board, $playerPiece,
                    fn($board, $hex, $playerId) => BoardSpatial::isColumnInPieceForward($playerPiece->getPosition()->getColumn(), $hex->getColumn(), $playerSide)))
                {
                    $piecesAvailableMoves[$playerPieceId][] = MoveTypes::FORWARD->value;

                }
            }
            if ($defensiveMoves->getMoves(MoveTypes::BACKWARD_NEAR_OPPONENT) > 0)
            {
                if (BoardSpatial::canFindPathToTarget($this->board, $playerPiece,
                    fn($board, $hex, $playerId) => BoardSpatial::isColumnInPieceBackward($playerPiece->getPosition()->getColumn(), $hex->getColumn(), $playerSide) &&
                        BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)))
                {
                    $piecesAvailableMoves[$playerPieceId][] = MoveTypes::BACKWARD_NEAR_OPPONENT->value;

                }
            }
            if ($defensiveMoves->getMoves(MoveTypes::FORWARD_NEAR_OPPONENT) > 0)
            {
                if (BoardSpatial::canFindPathToTarget($this->board, $playerPiece,
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
        return $this->getTiredPiecesIdsExcluding([]);
    }

    /**
     * @return array<int>
     */
    public function getTiredPiecesIdsExcluding(array $excludedPieceIds) : array
    {
        $result = [];

        $playerId = intval($this->game->GetActivePlayerId());
        $playerPieces = $this->board->getPlayerPiecesByOwner($playerId);
        $tiredPieces = array_filter($playerPieces, fn ($piece) =>
            $piece->isFresh() === false &&
            $piece->getRole() != PlayerRole::GoalKeeper->value &&
            !array_any($excludedPieceIds, fn($id) => $id === $piece->getId()));

        foreach($tiredPieces as $piece)
            $result[] = $piece->getId();

        return $result;
    }

    /**
     * Checks whether, after assigning $pieceId to satisfy one unit of demand (already reflected
     * by $remainingDemand having been decremented by the caller), the rest of the player's pieces
     * can still satisfy what remains. Mirrors isDefensivePossible() on the reduced problem.
     */
    public function canPieceSatisfyRemainingDemand(int $pieceId, CardDefensiveMoves $remainingDemand, array $alreadyMovedPieceIds) : bool
    {
        $playerId = intval($this->game->GetActivePlayerId());
        $excluded = [...$alreadyMovedPieceIds, $pieceId];

        $demandArray = $remainingDemand->toArray();
        if (array_all($demandArray, fn($amount) => $amount <= 0))
            return true;

        $playerResources = new PlayerResources($playerId);
        $moraleAvailable = $playerResources->getMorale();

        $piece = $this->board->getPiece($pieceId);
        if (!$piece->isFresh())
            $moraleAvailable -= 1;

        if ($moraleAvailable < 0)
            return false;

        $workerCapabilities = $this->getPiecesPossibleMovesForDemand($remainingDemand, $excluded);
        $tiredPiecesIds = $this->getTiredPiecesIdsExcluding($excluded);

        return (new BipartiteMatcher())->canSatisfyDemand($workerCapabilities, $tiredPiecesIds, $moraleAvailable, $demandArray);
    }

    protected function cleanup() : void
    {
        DBAdapter::GetInstance()->storePlayerTempStr(intval($this->game->getActivePlayerId()), "forced_pass_targets", "");
    }
}
