<?php

declare(strict_types=1);


namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
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

    public function selfGetArgs() : array
    {
        $reward = $this->getCardInPlayReward();
        $rewardClaimed = $this->getRewardClaimed();

        $remainingReward = $reward->diff($rewardClaimed);

        if ($remainingReward->action > 0)
        {
            $this->addClientButton(
                \sprintf(_('%dx Action'), $remainingReward->action),
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
        return BeforeCardSelectionReset::class;
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

        DBAdapter::getInstance()->storeActionReturnState($activePlayerId, BeforeRewardResolve::class);

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePiecesIds([]);

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

        DBAdapter::getInstance()->storeActionReturnState($activePlayerId, BeforeRewardResolve::class);

        MovePieceSelectionLogic::enforceMovePieceSelectionConstraints($this->game->getBoard(), $activePlayerId, MoveTypes::FREE);

        $moveActionContext = new MoveActionContext();
        $moveActionContext->setCurrentMoveType(MoveTypes::FREE);

        return MovePieceSelection::class;
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

    private function getCardInPlayReward() : CardReward
    {
        $tacticContext = new TacticContext();
        return $tacticContext->getCardInPlayReward($this->game);
    }
}