<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Game;

class Captain_C_2 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Captain 3",
            CardType::Captain,
            ""), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(2, 2);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    public function isExecutionPossible(): bool
    {
        // TODO: Implement isExecutionPossible() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        // TODO: Implement getCommands() method.

        return [];
    }

    protected function cleanup() : void
    {
        // TODO: Implement cleanup() method.
    }
}