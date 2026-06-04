<?php

namespace Bga\Games\SuperTeam\Cards;

class CardFactory
{
    /** @var array<int, callable(): Card> */
    private array $registry = [];

    public function registerCard(int $id, callable $cardCreator)
    {
        $this->registry[$id] = $cardCreator;
    }

    public function getCard(int $id)
    {
        if (!isset($this->registery[$id]))
            throw new \RuntimeException("Card not found: $id");

        return ($this->registry[$id])();
    }
}