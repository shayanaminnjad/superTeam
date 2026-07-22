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

class TeamATactic1 extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Team A",
            CardType::Team,
            "",
            new CardDefensiveMoves(1, 2, 0, 0, 0),
            new DefensiveReward(1, 0, 1),
            false,
            new OffensiveReward(1, 2, 0),
            false,),
            $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getTeamCardType(0,0);
    }

    public function getId(): string
    {
        return static::staticId();
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