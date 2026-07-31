<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Game;

class Captain_C_1 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Captain 2",
            CardType::Captain,
            ""), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(2, 1);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    public function isExecutionPossible(): bool
    {
        return false;
        // TODO: Implement isExecutionPossible() method.
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        return [];
        // TODO: Implement getCommands() method.
    }

    protected function cleanup() : void
    {
        // TODO: Implement cleanup() method.
    }
}