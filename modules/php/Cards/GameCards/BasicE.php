<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\DefensiveReward;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\OffensiveReward;
use Bga\Games\SuperTeam\Cards\TacticCard;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PassTargetSelectionLogic;

class BasicE extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Basic E",
            CardType::Basic,
            "",
            new CardDefensiveMoves(0, 1, 1, 0, 0),
            new DefensiveReward(1, 0, 0),
            false,
            new OffensiveReward(1, 2, 0),
            false,),
            $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getBasicCardType(4);
    }
    public function getId(): string
    {
        return static::staticId();
    }
    protected function isOffensiveMovePossible(): bool
    {
        $currentPlayer =  \intval($this->game->getCurrentPlayerId());
        $playerHasTheBall = BoardEvaluator::doesPlayerHasTheBall($this->board, $currentPlayer);
        $startHex = $this->board->getBallHex();

        return
            $playerHasTheBall &&
            count(PassTargetSelectionLogic::getPassTargetsHexes($this->board, PassTypes::THROUGH, $currentPlayer, $startHex, $this->game->getPlayerSide($currentPlayer))) > 0;
    }

    protected function getOffensiveCommands(): array
    {
        //TODO: simply checking pass result here won't do the job, since this is a through ball, it might fail to get to the desired location, but still be in front of a piece
        return [
            $this->forceThroughPassTargets(...),
            $this->goToPassState(...),
            $this->checkPassResult(...)
        ];
    }
}