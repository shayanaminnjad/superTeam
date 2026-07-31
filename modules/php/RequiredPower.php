<?php

namespace Bga\Games\SuperTeam;

class RequiredPower
{
    private const string SKIP_ENABLED_KEY = 'is_skip_enabled';

    public readonly int $distance;
    public readonly array $blockingHexes;

    public function __construct(int $distance, array $blockingHexes) {
        $this->distance = $distance;
        $this->blockingHexes = $blockingHexes;
    }
    public function getTotal() : int
    {
        $distPow = ceil($this->distance / (float)2);
        $distPow = $distPow >= 0 ? $distPow : 0;

        $blockingHexesAddOn = \count($this->blockingHexes);

        $isSkipEnabled = self::isSkipEnabled();
        if ($blockingHexesAddOn > 0 && $isSkipEnabled)
            $blockingHexesAddOn -= 1;

        return  $distPow + $blockingHexesAddOn;
    }

    public static function setSkipEnabled(bool $isEnabled) : void
    {
        DBAdapter::getInstance()->storeGlobalInt(self::SKIP_ENABLED_KEY, $isEnabled ? 1 : 0);
    }

    public static function isSkipEnabled() : bool
    {
        return DBAdapter::getInstance()->getGlobalInt(self::SKIP_ENABLED_KEY) == 1;
    }
}