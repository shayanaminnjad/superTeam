<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_21;

class Captain_21_1 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
                \clienttranslate("Captain 2"),
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
        return CardTypeHelper::getCaptainCardType(CaptainPower_21::staticId(), 1);
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
            $this->giveMorale(...),
            $this->goToShootState(...),
            $this->giveMoraleIfCaptainShoot(...)
        ];
    }

    protected function cleanup(): void
    {
    }
}