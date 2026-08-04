<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;
use Bga\Games\SuperTeam\States\SwapPiece;

class Captain_C_0 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Captain 1",
            CardType::Captain,
            ""), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(2, 0);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    public function isExecutionPossible(): bool
    {
        $playerId = \intval($this->game->GetActivePlayerId());

        return count(BoardEvaluator::getPlayerPiecesAdjacentToOpponent($this->board, $playerId)) > 0;
    }

    protected function getCommands(): array
    {
        return [
            $this->enforceAdjacentPieces(...),
            $this->goToCaptainCardPerformerSelectionState(...),
            $this->goToSwapPieceState(...),
            $this->goToBasicActionState(...),
            $this->ifCaptainGiveMorale(...)
        ];
    }

    protected function cleanup() : void
    {
    }

    private function enforceAdjacentPieces() : ExecutionResult
    {
        $playerId = \intval($this->game->GetActivePlayerId());
        $adjacentPieces = BoardEvaluator::getPlayerPiecesAdjacentToOpponent($this->board, $playerId);

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePieces($adjacentPieces);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    private function goToSwapPieceState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, SwapPiece::class);
    }

    private function ifCaptainGiveMorale() : ExecutionResult
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
}