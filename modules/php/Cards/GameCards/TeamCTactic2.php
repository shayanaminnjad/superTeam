<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\DefensiveReward;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\OffensiveReward;
use Bga\Games\SuperTeam\Cards\TacticCard;
use Bga\Games\SuperTeam\Cards\TacticCardDefinition;
use Bga\Games\SuperTeam\Game;

class TeamCTactic2 extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new TacticCardDefinition(
            \clienttranslate("Team B"),
            CardType::Team,
            \clienttranslate("**Offensive**
**Cross & Through Pass:** Pass the ball to a piece who is at the same row as the current ball carrier. Then pass the ball to a space 1 or 2 spaces ahead of the same piece. 
---
**Defensive**
**Backward Move x2::** Move one of your pieces backward.
**Forward Move:** Move one of your pieces forward. 
"),
            new CardDefensiveMoves(0, 1, 2, 0, 0),
            new DefensiveReward(1, 0, 1),
            true,
            [PassTypes::CROSS, PassTypes::THROUGH_PREVIOUS],
            new OffensiveReward(1, 2, 0),
            true,),
            $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getTeamCardType(2,1);
    }

    public function getId(): string
    {
        return static::staticId();
    }
}