<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\DefensiveReward;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\OffensiveReward;
use Bga\Games\SuperTeam\Cards\TacticCard;
use Bga\Games\SuperTeam\Cards\TacticCardDefinition;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PassTargetSelectionLogic;

class TeamATactic3 extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new TacticCardDefinition(
            "Team C",
            CardType::Team,
            \clienttranslate("**Offensive**
**Cross & Forward Pass:** Pass the ball to a piece who is at the same row as the current ball carrier. Then pass the ball to another piece who is at least one row ahead of that piece. 
---
**Defensive**
**Forward Move x2:** Move one of your pieces forward.
**Backward Move:** Move one of your pieces backward.
"),
            new CardDefensiveMoves(0, 2, 1, 0, 0),
            new DefensiveReward(1, 0, 1),
            true,
            [PassTypes::CROSS, PassTypes::FORWARD],
            new OffensiveReward(2, 2, 0),
            true,),
            $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getTeamCardType(0,2);
    }
    public function getId(): string
    {
        return self::staticId();
    }
}