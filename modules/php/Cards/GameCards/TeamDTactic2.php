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
use Bga\Games\SuperTeam\PermanentPowers\Teams\TeamId;

class TeamDTactic2 extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new TacticCardDefinition(
            \clienttranslate("Team B"),
            CardType::Team,
            \clienttranslate("**Offensive**
**Forward Pressure Pass:** Pass the ball to a piece who is at least one row ahead of the current ball carrier, when an opponent is adjacent to both pieces. 
---
**Defensive**
**Backward Move x2:** Move one of your pieces backward.
**Forward Move:** Move one of your pieces forward. 
"),
            new CardDefensiveMoves(0, 1, 2, 0, 0),
            new DefensiveReward(1, 0, 1),
            true,
            [PassTypes::FORWARD_NEAR_OPPONENT_TO_NEAR_OPPONENT],
            new OffensiveReward(2, 2, 0),
            true,),
            $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getTeamCardType(TeamId::SpendFourOnBoost->value,1);
    }

    public function getId(): string
    {
        return static::staticId();
    }
}