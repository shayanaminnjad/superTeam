<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_6;


class Captain_6_2 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
                \clienttranslate("Captain 3"),
                CardType::Captain,
                \clienttranslate("**Requirement:** If you have the ball **inside** the penalty area. 
**Effect:** Shoot and skip a block."))
            , $game);
    }

    public function isExecutionPossible(): bool
    {
        $ballHex = $this->board->getBallHex();

        $piece = null;
        if ($this->board->tryGetPlayerPieceInHex($ballHex, $piece))
        {
            $activePlayerId = $this->game->getActivePlayerId();

            if ($piece->getOwner() === $activePlayerId)
                return true;
        }

        return false;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_6::staticId(), 2);
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
            $this->enableSkip(...),
            $this->goToShootState(...),
            $this->giveMoraleIfCaptainShoot(...)
        ];
    }

    protected function cleanup(): void
    {
    }
}