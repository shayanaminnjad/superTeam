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

class TeamCTactic1 extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new TacticCardDefinition(
            \clienttranslate("Team A"),
            CardType::Team,
            \clienttranslate("**Offensive**
**Forward & Through Pass:** Pass the ball to a piece who is at least one row ahead of the current ball carrier. Then pass the ball to a space 1 or 2 spaces ahead of another piece. 
---
**Defensive**
**Forward Move x2:** Move one of your pieces forward.
**Backward Move:** Move one of your pieces backward. 
"),
            new CardDefensiveMoves(0, 2, 1, 0, 0),
            new DefensiveReward(1, 0, 1),
            true,
            [PassTypes::FORWARD, PassTypes::THROUGH],
            new OffensiveReward(2, 2, 0),
            true,),
            $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getTeamCardType(2,0);
    }

    public function getId(): string
    {
        return static::staticId();
    }
}