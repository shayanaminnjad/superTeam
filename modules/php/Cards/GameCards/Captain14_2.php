<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_14;

class Captain14_2 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Captain 3"),
            CardType::Captain,
            \clienttranslate("**Requirement:** Play after receiving the ball outside the Penalty Area.
**Effect:** Shoot and skip a block.")), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_14::staticId(), 2);
    }

    public function getId(): string
    {
        return self::staticId();
    }
    public function isExecutionPossible(): bool
    {
        return $this->isBallReceiverOutsidePenaltyArea();
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        return [
            $this->enableSkip(...),
            $this->goToShootState(...),
            $this->giveMoraleIfCaptainShoot(...)
        ];
    }

    protected function cleanup(): void
    {
    }
}