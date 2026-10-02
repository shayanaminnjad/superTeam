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

class TeamBTactic3 extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new TacticCardDefinition(
            \clienttranslate("Team C"),
            CardType::Team,
            \clienttranslate("**Offensive**
**Backward & Forward Through Pass:** Pass the ball to a piece who is at least one row behind of the current ball carrier. Then pass the ball to a space 1 or 2 spaces ahead of another piece. 
---
**Defensive**
**Forward Move x2:** Move one of your pieces forward.
**Free Move:** Move one of your pieces in any direction. 
"),
            new CardDefensiveMoves(1, 2, 0, 0, 0),
            new DefensiveReward(1, 0, 1),
            true,
            [PassTypes::BACKWARD, PassTypes::THROUGH],
            new OffensiveReward(0, 2, 1),
            true,),
            $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getTeamCardType(1,2);
    }
    public function getId(): string
    {
        return self::staticId();
    }
}