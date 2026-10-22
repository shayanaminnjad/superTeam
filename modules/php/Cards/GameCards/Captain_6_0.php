<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BasicTurnLogic;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_6;

class Captain_6_0 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
                \clienttranslate("Captain 1"),
                CardType::Captain,
                \clienttranslate("**Requirement:** Play when adjacent to any opponent. 
**Effect:** Swap position with an opponent and either move or perform an action."))
            , $game);
    }

    public function isExecutionPossible(): bool
    {
        return count($this->getPiecesThatCanSwap()) > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_6::staticId(), 0);
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
            $this->enforcePiecesThatCanSwap(...),
            $this->goToCaptainCardPerformerSelectionState(...),
            $this->goToSwapPieceState(...),
            $this->enableMoveInBasic(...),
            $this->makeNextActionForfeitable(...),
            $this->goToBasicActionState(...),
            $this->ifCaptainSelectedGiveMorale(...)
        ];
    }

    protected function cleanup(): void
    {
        BasicTurnLogic::setMoveAvailabilityInBasic(false);
    }

    private function enableMoveInBasic() : ExecutionResult
    {
        BasicTurnLogic::setMoveAvailabilityInBasic(true);
        return new ExecutionResult(ExecutionAction::Continue);
    }
}