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
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_3;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;
use Bga\Games\SuperTeam\States\SwapPiece;

class Captain_C_0 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Captain 1"),
            CardType::Captain,
            \clienttranslate("**Requirement:** Play when adjacent to any opponent. 
**Effect:** Swap position with an opponent and perform an action.")), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_3::staticId(), 0);
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
            $this->makeNextActionForfeitable(...),
            $this->goToBasicActionState(...),
            $this->ifCaptainSelectedGiveMorale(...)
        ];
    }

    protected function cleanup() : void
    {
    }

    private function goToSwapPieceState() : ExecutionResult
    {
        return new ExecutionResult(ExecutionAction::GoToState, SwapPiece::class);
    }
}