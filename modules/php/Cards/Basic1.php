<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Algorithm\BipartiteMatcher;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardSpatial;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\States\MultiMove;
use Bga\Games\SuperTeam\States\PassDestSelection;
use function count;
use function intval;


class Basic1 extends Card
{
    public function __construct(Game $game, Board $board)
    {
        parent::__construct(new CardDefinition(
            "Basic A",
            CardType::Basic,
            "",
            new CardDefensiveMoves(0, 1, 0, 1, 0),
            new DefensiveReward(1, 0, 0),
            false,
            new OffensiveReward(1, 2, 0),
            false,)
            , $board, $game);
    }

    public function isOffensivePossible() : bool
    {
        $board = $this->board;
        $currentPlayer =  $this->game->getCurrentPlayerId();
        $playerHasTheBall = BoardEvaluator::doesPlayerHasTheBall($board, $currentPlayer);
        $startHex = $board->getBallHex();

        return
            $playerHasTheBall &&
            count(BoardSpatial::GetPiecesInFrontColumns($this->board, $startHex->getColumn(), $currentPlayer, $this->game->getPlayerSide($currentPlayer))) > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getBasicCardType(0);
    }

    public function getId() : string
    {
        return self::staticId();
    }

    protected function getOffensiveCommands() : array
    {
        return [
            $this->forceForwardPassTargets(...),
            $this->goToPassState(...),
            $this->checkPassResult(...)
        ];
    }

    private function forceForwardPassTargets() : ExecutionResult
    {
        $startHex = $this->board->getBallHex();
        $currentPlayer = intval($this->game->getCurrentPlayerId());
        $piecesForward = BoardSpatial::GetPiecesInFrontColumns($this->board, $startHex->getColumn(), $currentPlayer, $this->game->getPlayerSide($currentPlayer));

        $forcedTargets = [];
        foreach($piecesForward as $piece)
        {
            $forcedTargets[] = $piece->getPosition()->toJson();
        }

        DBAdapter::GetInstance()->storePlayerTempStr($currentPlayer, "forced_pass_targets", json_encode($forcedTargets));

        return new ExecutionResult(ExecutionAction::Continue);
    }

    private function goToPassState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, PassDestSelection::class);
    }

    private function checkPassResult() : ExecutionResult
    {
        $passResult = DBAdapter::getInstance()->getGameStateValue("lastPassResult") == 1;
        return new ExecutionResult($passResult ? ExecutionAction::Continue : ExecutionAction::Failure);
    }

    protected function cleanup() : void
    {
        DBAdapter::GetInstance()->storePlayerTempStr(intval($this->game->getActivePlayerId()), "forced_pass_targets", "");
    }

    public function isDefensivePossible(): bool
    {
        $piecesAvailableMoves = $this->getPiecesPossibleMoves();
        $demand = $this->getDefinition()->cardDefensiveMoves->toArray();

        return (new BipartiteMatcher())->canSatisfyDemand($piecesAvailableMoves, $demand);
    }

    protected function getDefensiveCommands(): array
    {
        //we don't want this function at all! all moves are resolved the same.
        return [
            $this->goToMultiMoveState(...)];
    }

    private function goToMultiMoveState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, MultiMove::class);
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