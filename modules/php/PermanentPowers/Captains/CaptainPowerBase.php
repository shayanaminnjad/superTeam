<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

use Bga\Games\SuperTeam\Game;

abstract class CaptainPowerBase
{
    public function __construct(
        Game $game,
        private readonly string $title,
        private readonly string $tooltip,
        private readonly int $passPower,
        private readonly int $shootPower,
        private readonly int $dribblePower,
        private readonly int $tacklePower)
    {}

    abstract public function getId() : int;

    final public function getTitle() : string
    {
        return $this->title;
    }

    final public function getTooltip() : string
    {
        return $this->tooltip;
    }

    final public function getPassPower(): int
    {
        return $this->passPower;
    }

    final public function getShootPower() : int
    {
        return $this->shootPower;
    }

    final public function getDribblePower(): int
    {
        return $this->dribblePower;
    }

    final public function getTacklePower(): int
    {
        return $this->tacklePower;
    }
}