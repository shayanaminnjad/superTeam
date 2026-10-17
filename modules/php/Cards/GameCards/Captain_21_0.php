<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BypassLogic;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_21;

class Captain_21_0 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
                \clienttranslate("Captain 1"),
                CardType::Captain,
                \clienttranslate("**Requirement:** Play when adjacent to any opponent.  
**Effect:** Bypass an opponent."))
            , $game);
    }

    public function isExecutionPossible(): bool
    {
        $activePlayerId = intval($this->game->getActivePlayerId());
        return count(BypassLogic::getPiecesThatCanBypass($activePlayerId, $this->board)) > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_21::staticId(), 0);
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
            $this->enforcePiecesThatCanBypass(...),
            $this->goToCaptainCardPerformerSelectionState(...),
            $this->goToByPassState(...),
            $this->ifCaptainSelectedGiveMorale(...)
        ];
    }

    private function enforcePiecesThatCanBypass() : ExecutionResult
    {
        $activePlayerId = intval($this->game->getActivePlayerId());
        $piecesThatCanBypass = BypassLogic::getPiecesThatCanBypass($activePlayerId, $this->board);

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePieces($piecesThatCanBypass);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    protected function cleanup(): void
    {
    }
}