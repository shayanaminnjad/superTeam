<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\DefensiveReward;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\OffensiveReward;
use Bga\Games\SuperTeam\Cards\TacticCard;
use Bga\Games\SuperTeam\Game;

class BasicD extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Basic D",
            CardType::Basic,
            "",
            new CardDefensiveMoves(2, 0, 0, 0, 0),
            new DefensiveReward(0, 0, 0),
            false,
            new OffensiveReward(1, 2, 0),
            false,),
            $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getBasicCardType(3);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    function getOffensiveCommands(): array
    {
        // TODO: Implement getOffensiveCommands() method.

        return [];
    }

    protected function cleanup(): void
    {
        // TODO: Implement cleanup() method.
    }

    protected function isOffensiveMovePossible(): bool
    {
        // TODO: Implement isOffensiveMovePossible() method.

        return false;
    }
}