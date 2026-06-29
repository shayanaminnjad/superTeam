<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardSpatial;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\States\PassDestSelection;


class Basic1 extends Card
{
    public function __construct(Game $game, Board $board)
    {
        parent::__construct(new CardDefinition(
            "Basic A",
            CardType::Basic,
            "",
            new DefensiveReward(1, 0, 0),
            false,
            new OffensiveReward(1, 2, 0),
            false)
            , $board, $game);
    }

    public function isOffensivePossible() : bool
    {
        $board = $this->board;
        $currentPlayer =  $this->game->getCurrentPlayerId();
        $playerHasTheBall = BoardEvaluator::DoesPlayerHasTheBall($board, $currentPlayer);
        $startHex = $board->getBallHex();

        return
            $playerHasTheBall &&
            \count(BoardSpatial::GetPiecesInFrontColumns($this->board, $startHex->getColumn(), $currentPlayer, $this->game->getPlayerSide($currentPlayer))) > 0;
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
        $currentPlayer = \intval($this->game->getCurrentPlayerId());
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
        DBAdapter::GetInstance()->storePlayerTempStr(\intval($this->game->getActivePlayerId()), "forced_pass_targets", "");
    }

    public function isDefensivePossible(): bool
    {
        $predicate = function(PlayerPiece $playerPiece, PlayerPiece $opponentPiece) : bool {
            return $playerPiece->getPosition()->getColumn() < ($opponentPiece->getPosition()->getColumn() - 1);
        };

        $currentPlayer = \intval($this->game->getCurrentPlayerId());



        $canMoveAPieceToAdjacency = BoardEvaluator::canMoveAPieceNearAnOpponentPiece($this->board, $currentPlayer, $predicate);
    }

    protected function getDefensiveCommands(): array
    {
        // TODO: Implement getDeffensiveCommands() method.

        return [];
    }
}