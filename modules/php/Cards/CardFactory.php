<?php

namespace Bga\Games\SuperTeam\Cards;
use Bga\Games\SuperTeam\Utility\Logger;

class CardFactory
{
    /** @var array<int, callable(): Card> */
    private array $registry = [];

    public function registerCard(string $id, callable $cardCreator)
    {
        Logger::logInfo("Card " . $id . "registered");

        $this->registry[$id] = $cardCreator;
    }

    public function getCard(string $id) : Card
    {
        if (!isset($this->registry[$id]))
            throw new \RuntimeException("Card not found: $id");

        return ($this->registry[$id])();
    }

    public function hasCard(string $id) : bool
    {
        return isset($this->registry[$id]);
    }
}