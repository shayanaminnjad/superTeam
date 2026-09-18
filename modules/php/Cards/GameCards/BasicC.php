<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\DefensiveReward;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\OffensiveReward;
use Bga\Games\SuperTeam\Cards\TacticCard;
use Bga\Games\SuperTeam\Cards\TacticCardDefinition;
use Bga\Games\SuperTeam\Game;

class BasicC extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new TacticCardDefinition(
            "Basic C",
            CardType::Basic,
            "",
            new CardDefensiveMoves(0, 1, 1, 0, 0),
            new DefensiveReward(1, 0, 0),
            false,
            [PassTypes::NEAR_OPPONENT],
            new OffensiveReward(1, 2, 0),
            false,),
            $game);
    }
    public static function staticId() : string
    {
        return CardTypeHelper::getBasicCardType(2);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    function getOffensiveCommands(): array
    {
        return [
            $this->forceNearOpponentTargets(...),
            $this->goToPassState(...),
            $this->checkPassResult(...)
        ];
    }
}