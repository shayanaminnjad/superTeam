<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardSpatial;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\States\PassDestSelection;
use Bga\Games\SuperTeam\Cards\CardType;


class Basic1 extends Card
{
    public function __construct(Game $game, Board $board)
    {
        parent::__construct(new CardDefinition(
            "Basic 1",
            CardType::Basic,
            "",
            new CardReward(1, 0, 0),
            false,
            new CardReward(1, 2, 0),
            false)
            , $board, $game);
    }

    public function isOffensivePossible() : bool
    {
        $startHex = $this->board->getBallHex();
        $currentPlayer = $this->game->getCurrentPlayerId();

        return \count(BoardSpatial::GetPiecesInFrontColumns($this->board, $startHex->getColumn(), $currentPlayer)) > 0;
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
        $currentPlayer = $this->game->getCurrentPlayerId();
        $piecesForward = BoardSpatial::GetPiecesInFrontColumns($this->board, $startHex->getColumn(), $currentPlayer);

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
        // TODO: Implement isDefensivePossible() method.
    }

    protected function getDeffensiveCommands(): array
    {
        // TODO: Implement getDeffensiveCommands() method.
    }
}