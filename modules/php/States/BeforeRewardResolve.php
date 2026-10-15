<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BasicTurnLogic;
use Bga\Games\SuperTeam\Cards\CardReward;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerPiecesDBManager;
use Bga\Games\SuperTeam\PlayerResources;

class BeforeRewardResolve extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_BEFORE_REWARD_RESOLVE,
            name: "beforeRewardResolve",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId)
    {
        $tacticContext = new TacticContext();
        $reward = $tacticContext->getCardInPlayReward($this->game);
        $claimedReward = $tacticContext->getRewardUsed();

        $this->claimMoraleIfNotClaimed($activePlayerId, $reward, $claimedReward, $tacticContext);

        $board = $this->game->getBoard();
        $extraRemaining = $tacticContext->hasExtraReward($board, $activePlayerId) && !$tacticContext->isExtraRewardClaimed();
        $inactivePlayerId = $this->game->getInactivePlayerId();
        if (($reward->isEmpty() && !$extraRemaining) ||
            ($this->rewardHasBeenClaimed($reward, $claimedReward) && !$extraRemaining) ||
            ($this->isOnlyRewardRemainingAction($reward, $claimedReward, $extraRemaining) && !BasicTurnLogic::isAnyActionAvailable($board, $activePlayerId, $inactivePlayerId)))
        {
            return BeforeCardSelectionReset::class;
        }
        else
        {
            return TacticRewardResolve::class;
        }
    }

    private function claimMoraleIfNotClaimed(int $activePlayerId,CardReward $rewardToClaim, CardReward $claimedReward, TacticContext $tacticContext) : void
    {
        if ($rewardToClaim->morale - $claimedReward->morale > 0)
        {
            $playerResources = new PlayerResources($activePlayerId);
            $playerResources->addMorale($rewardToClaim->morale - $claimedReward->morale, true);

            $claimedReward->morale = $rewardToClaim->morale;
            $tacticContext->setRewardUsed($claimedReward);
        }
    }

    private function rewardHasBeenClaimed(CardReward $reward, CardReward $claimedReward) : bool
    {
        return $reward->diff($claimedReward)->isEmpty();
    }

    private function isOnlyRewardRemainingAction(CardReward $reward, CardReward $claimedReward, bool $extraRemaining) : bool
    {
        $remainingReward = $reward->diff($claimedReward);
        return $remainingReward->action > 0 && $remainingReward->move == 0 && $remainingReward->morale == 0 && !$extraRemaining;
    }
}