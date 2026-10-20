<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_4;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;

class Captain_4_2 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
                \clienttranslate("Captain 3"),
                CardType::Captain,
                \clienttranslate("**Requirement:** Play after receiving the ball outside the Penalty Area. 
**Effect:** Gain 1 Morale and Shoot."))
            , $game);
    }

    public function isExecutionPossible(): bool
    {
        return $this->isBallReceiverOutsidePenaltyArea();
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_4::staticId(), 2);
    }
    public function getId(): string
    {
        return self::staticId();
    }

    protected function getCommands(): array
    {
        return [
            $this->enforceBallHolderAsPerformer(...),
            $this->goToCaptainCardPerformerSelectionState(...),
            $this->giveMorale(...),
            $this->goToShootState(...),
            $this->giveMoraleIfCaptainShoot(...)
        ];
    }

    protected function cleanup(): void
    {
    }
}