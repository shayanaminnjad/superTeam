<?php

declare(strict_types=1);


namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\Cards\TacticType;
use Bga\Games\SuperTeam\Cards\CardReward;

class TacticRewardResolve extends PlayerBaseState
{
    function __construct(protected Game $game,
    ) {
        parent::__construct(
            $game,
            name: 'tacticRewardResolve',
            id: ST_TACTIC_REWARD_RESOLVE,
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} must play a reward'),
            descriptionMyTurn: clienttranslate('${you} must play a reward'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function onEnteringState(int $activePlayerId)
    {
        $reward = $this->getCardInPlayReward();
        $claimedReward = $this->getRewardClaimed();

        if ($reward->isEmpty() || $this->rewardHasBeenClaimed($reward, $claimedReward))
        {
            return CardSelection::class;
        }

        $this->claimMoraleIfNotClaimed($activePlayerId, $reward, $claimedReward);
    }

    public function selfGetArgs() : array
    {
        $reward = $this->getCardInPlayReward();
        $rewardClaimed = $this->getRewardClaimed();

        $remainingReward = $reward->diff($rewardClaimed);

        if ($remainingReward->action > 0)
        {
            $this->addClientButton(
                \sprintf(_('%dx Action'), $remainingReward->move),
                [$this,'actDoOneAction']);
        }

        if ($remainingReward->move > 0)
        {
            $this->addClientButton(
                \sprintf(_('%dx Move'), $remainingReward->move),
                [$this, 'actDoOneMove']);
        }

        $this->addClientButton(_("Forfeit"), [$this, 'actForfeitReward']);
        
        return [];
    }

    #[PossibleAction]
    public function actForfeitReward() : string
    {
        return CardSelection::class;
    }

    #[PossibleAction]
    public function actDoOneAction(int $activePlayerId) : string
    {
        $cardReward = $this->getCardInPlayReward();
        $claimedReward = $this->getRewardClaimed();

        $rewardRemaining = $cardReward->diff($claimedReward);

        if ($rewardRemaining->action == 0)
            throw new \BgaUserException("Card has no more action rewards");

        $claimedReward->action += 1;
        $this->storeRewardClaimed($claimedReward);

        DBAdapter::getInstance()->storeActionReturnState($activePlayerId, self::class);

        return PlayerBasicTurn::class;
    }
        
    #[PossibleAction]
    public function actDoOneMove(int $activePlayerId) : string
    {
        $cardReward = $this->getCardInPlayReward();
        $claimedReward = $this->getRewardClaimed();

        $rewardRemaining = $cardReward->diff($claimedReward);

        if ($rewardRemaining->move == 0)
            throw new \BgaUserException("Card has no more move rewards");

        $claimedReward->move += 1;
        $this->storeRewardClaimed($claimedReward);

        DBAdapter::getInstance()->storeActionReturnState($activePlayerId, self::class);

        return PieceSelection::class;
    }

    function zombie(int $playerId): string
    {
        return $this->actForfeitReward();
    }

    private function getRewardClaimed() : CardReward
    {
        $tacticContext = new TacticContext();

        return $tacticContext->getRewardUsed();
    }

    private function storeRewardClaimed(CardReward $reward) : void
    {
        $tacticContext = new TacticContext();

        $tacticContext->setRewardUsed($reward);
    }

    private function rewardHasBeenClaimed(CardReward $reward, CardReward $claimedReward) : bool
    {
        return $reward->diff($claimedReward)->isEmpty();
    }

    private function getCardInPlayReward() : CardReward
    {
        $tacticContext = new TacticContext();

        $cardInPlay = $tacticContext->getTacticInPlay();
        assert($cardInPlay >= 0);

        $tacticType = $tacticContext->getTacticType();

        $card = $this->game->getCardFactory()->getCard($cardInPlay);
        $cardDef = $card->getDefinition();

        return $tacticType == TacticType::Offensive ? $cardDef->offensiveReward : $cardDef->defensiveReward;
    }

    private function claimMoraleIfNotClaimed(int $activePlayerId,CardReward $rewardToClaim, CardReward $claimedReward) : void
    {
        if ($rewardToClaim->morale - $claimedReward->morale > 0)
        {
            $playerResources = new PlayerResources($activePlayerId);
            $playerResources->addMorale($rewardToClaim->morale - $claimedReward->morale, true);

            $claimedReward->morale = $rewardToClaim->morale;
            $this->storeRewardClaimed($claimedReward);
        }
    }
}