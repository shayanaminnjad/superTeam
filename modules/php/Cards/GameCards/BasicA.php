<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\BallPass\PassTypes;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\DefensiveReward;
use Bga\Games\SuperTeam\Cards\OffensiveReward;
use Bga\Games\SuperTeam\Cards\TacticCard;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PassTargetSelectionLogic;
use function count;


class BasicA extends TacticCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Basic A",
            CardType::Basic,
            "",
            new CardDefensiveMoves(0, 1, 0, 1, 0),
            new DefensiveReward(1, 0, 0),
            false,
            new OffensiveReward(1, 2, 0),
            false,),
            $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getBasicCardType(0);
    }

    public function getId() : string
    {
        return self::staticId();
    }

    protected function isOffensiveMovePossible(): bool
    {
        $currentPlayer =  \intval($this->game->getCurrentPlayerId());
        $playerHasTheBall = BoardEvaluator::doesPlayerHasTheBall($this->board, $currentPlayer);
        $startHex = $this->board->getBallHex();

        return
            $playerHasTheBall &&
            count(PassTargetSelectionLogic::getPassTargetsHexes($this->board, PassTypes::FORWARD, $currentPlayer, $startHex, $this->game->getPlayerSide($currentPlayer))) > 0;
    }

    protected function getOffensiveCommands(): array
    {
        return [
            $this->forceForwardPassTargets(...),
            $this->goToPassState(...),
            $this->checkPassResult(...)
        ];
    }

    private function forceForwardPassTargets() : ExecutionResult
    {
        $playerId = \intval($this->game->getCurrentPlayerId());
        $startHex = $this->board->getBallHex();

        PassTargetSelectionLogic::forcePassTargets($this->board, PassTypes::FORWARD, $playerId, $startHex, $this->game->getPlayerSide($playerId));
        return new ExecutionResult(ExecutionAction::Continue);
    }

}