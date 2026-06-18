<?php

declare(strict_types=1);


namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\Cards\TacticType;
use Bga\Games\SuperTeam\Cards\CardReward;
use Bga\Games\SuperTeam\States\CardSelection;

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
            $this->cleanup();

            return CardSelection::class;
        }

        $this->claimMoraleIfNotClaimed($activePlayerId, $reward->morale, $claimedReward->morale);
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
        $this->cleanup();

        return CardSelection::class;
    }

    #[PossibleAction]
    public function actDoOneAction(int $activePlayerId) : string
    {
        //TODO: store the usage
        DBAdapter::getInstance()->storeActionReturnState($activePlayerId, addslashes(self::class));

        return PlayerBasicTurn::class;
    }
        
    #[PossibleAction]
    public function actDoOneMove(int $activePlayerId) : string
    {
        //TODO: store the usage!
        DBAdapter::getInstance()->storeActionReturnState($activePlayerId, addslashes(self::class));

        return PieceSelection::class;
    }

    function zombie(int $playerId): string
    {
        return $this->actForfeitReward();
    }

    private function cleanup() : void
    {
        $dbAdapter = DBAdapter::getInstance();

        $dbAdapter->storeGlobalString("tactic_in_play", "");
        $dbAdapter->storeGlobalInt("tactic_progress", -1);
        $dbAdapter->storeGlobalInt("tactic_reward_in_progress", 0);
        $dbAdapter->storeGlobalString("rewards_used", "");
    }

    private function getRewardClaimed() : CardReward
    {
        $dbAdapter = DBAdapter::getInstance();

        $rewardJson = $dbAdapter->getGlobalString("rewards_used");

        if ($rewardJson === null || $rewardJson === '')
            return CardReward::empty();
        else
            return CardReward::fromJson($rewardJson);
    }

    private function rewardHasBeenClaimed(CardReward $reward, CardReward $claimedReward) : bool
    {
        return $reward->diff($claimedReward)->isEmpty();
    }

    private function getCardInPlayReward() : CardReward
    {
        $dbAdapter = DBAdapter::getInstance();

        $cardInPlay = $dbAdapter->getGlobalString("tactic_in_play");
        assert($cardInPlay >= 0);

        //TODO: tactic type can be inferred there is no need to store it.
        $tacticType = $dbAdapter->getGlobalInt("tactic_type");
        assert($tacticType >= 0 && $tacticType <=1, "tactic type should be either 0 or 1");

        $card = $this->game->getCardFactory()->getCard($cardInPlay);
        $cardDef = $card->getDefinition();

        return TacticType::from($tacticType) == TacticType::Offensive ? $cardDef->offensiveReward : $cardDef->defensiveReward;
    }

    private function claimMoraleIfNotClaimed(int $activePlayerId,int $amount, int $claimedMorale)
    {
        if ($amount - $claimedMorale > 0)
        {
            $playerResources = new PlayerResources($activePlayerId);

            $playerResources->addMorale($amount - $claimedMorale, true);
        }
    }
}