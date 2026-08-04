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

class TeamATactic1 extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new TacticCardDefinition(
            "Team A",
            CardType::Team,
            "",
            new CardDefensiveMoves(1, 2, 0, 0, 0),
            new DefensiveReward(1, 0, 1),
            true,
            new OffensiveReward(0, 2, 1),
            true,),
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
        return [
            $this->forceForwardPassTargets(...),
            $this->goToPassState(...),
            $this->checkPassResult(...),
            $this->forceThroughPassTargets(...),
            $this->goToPassState(...),
            $this->checkThroughPassResult(...)
        ];
    }

    protected function isOffensiveMovePossible(): bool
    {
        $currentPlayer =  \intval($this->game->GetActivePlayerId());
        $playerHasTheBall = BoardEvaluator::doesPlayerHasTheBall($this->board, $currentPlayer);
        $startHex = $this->board->getBallHex();

        return
            $playerHasTheBall &&
            count(PassTargetSelectionLogic::getPassTargetsHexes($this->board, PassTypes::FORWARD, $currentPlayer, $startHex, $this->game->getPlayerSide($currentPlayer))) > 0;
    }
}