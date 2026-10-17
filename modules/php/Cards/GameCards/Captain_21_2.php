<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\ActionTypes;
use Bga\Games\SuperTeam\BasicTurnLogic;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_21;

class Captain_21_2 extends CaptainCard
{

    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
                \clienttranslate("Captain 3"),
                CardType::Captain,
                \clienttranslate("**Requirement:** Play when adjacent to any opponent. 
**Effect:** Perform an action and move."))
            , $game);
    }

    public function isExecutionPossible(): bool
    {
        return count($this->getPiecesThatCanDoAction()) > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_21::staticId(), 2);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        return [
            $this->enforceAdjacentPieces(...),
            $this->goToCaptainCardPerformerSelectionState(...),
            $this->goToBasicActionState(...),
            $this->makeNextActionForfeitable(...),
            $this->goToFreeMoveWithSelectedPiece(...),
            $this->ifCaptainSelectedGiveMorale(...)
        ];
    }

    private function enforceAdjacentPiecesThatCanDoAnAction() : ExecutionResult
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePieces($this->getPiecesThatCanDoAction());
        return new ExecutionResult(ExecutionAction::Continue);
    }

    private function getPiecesThatCanDoAction() : array
    {
        $res = [];

        $activePlayer = intval($this->game->getActivePlayerId());
        $playerPiecesAdjacentToOpponents = BoardEvaluator::getPlayerPiecesAdjacentToOpponent($this->board, $activePlayer);

        $inactivePlayer = $this->game->getInactivePlayerId();
        $excludedActions = [];
        $excludedActions[ActionTypes::Move->value] = 1;
        foreach($playerPiecesAdjacentToOpponents as $piece)
        {
            if (count(BasicTurnLogic::getActionsDoableByPiece($this->board, $piece, $activePlayer, $inactivePlayer ,$excludedActions)) > 0)
                $res [] = $piece;
        }

        return $res;
    }

    protected function cleanup(): void
    {
    }
}