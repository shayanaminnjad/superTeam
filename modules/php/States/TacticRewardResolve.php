<?php

declare(strict_types=1);


namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;

use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\PlayerPiece;
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
        $activePlayerId = intval($this->game->getActivePlayerId());

        if ($this->isChoosingExtraReward())
        {
            $this->addClientButton(\clienttranslate('[move]'), [$this, 'actExtraMove']);
            $this->addClientButton(\clienttranslate('[morale]'), [$this, 'actGetOneMorale']);
            $this->addClientButton(\clienttranslate('Back'), [$this, 'actBack']);
        }
        else
        {
            $reward = $this->getCardInPlayReward();
            $rewardClaimed = $this->getRewardClaimed();

            $remainingReward = $reward->diff($rewardClaimed);

            if ($remainingReward->action > 0)
            {
                $this->addClientButton(
                    \sprintf(\clienttranslate('%d [action]'), $remainingReward->action),
                    [$this, 'actDoOneAction']);
            }

            if ($remainingReward->move > 0)
            {
                $this->addClientButton(
                    \sprintf(\clienttranslate('%d [move]'), $remainingReward->move),
                    [$this, 'actDoOneMove']);
            }

            $board = $this->game->getBoard();
            $tacticContext = new TacticContext();
            if ($tacticContext->hasExtraReward($board, $activePlayerId) && !$tacticContext->isExtraRewardClaimed())
            {
                $this->addClientButton(\clienttranslate('[move]/[morale]'), [$this, 'actExtraRewardChoose']);
            }

            $this->addClientButton(\clienttranslate("Forfeit"), [$this, 'actForfeitReward'], [],
                new ClientButtonParams(ClientButtonColor::ALERT));
        }
        
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
            throw new \BgaUserException(\clienttranslate("Card has no more action rewards"));

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
            throw new \BgaUserException(\clienttranslate("Card has no more move rewards"));

        $claimedReward->move += 1;
        $this->storeRewardClaimed($claimedReward);

        DBAdapter::getInstance()->storeActionReturnState($activePlayerId, BeforeRewardResolve::class);

        MovePieceSelectionLogic::enforceMovePieceSelectionConstraints($this->game->getBoard(), $activePlayerId, MoveTypes::FREE);

        $moveActionContext = new MoveActionContext();
        $moveActionContext->setCurrentMoveType(MoveTypes::FREE);

        return MovePieceSelection::class;
    }

    #[PossibleAction]
    public function actBack() : string
    {
        $this->setIsChoosingExtraReward(false);
        return self::class;
    }

    #[PossibleAction]
    public function actExtraRewardChoose(int $activePlayerId) : string
    {
        $board = $this->game->getBoard();

        $tacticContext = new TacticContext();
        if ($tacticContext->hasExtraReward($board, $activePlayerId) && !$tacticContext->isExtraRewardClaimed())
        {
            $this->setIsChoosingExtraReward(true);
            return self::class;
        }
        else
            throw new \BgaUserException(\clienttranslate("You cannot get extra reward now!"));
    }

    #[PossibleAction]
    public function actGetOneMorale(int $activePlayerId) : string
    {
        $board = $this->game->getBoard();

        $tacticContext = new TacticContext();
        if ($tacticContext->hasExtraReward($board, $activePlayerId) && !$tacticContext->isExtraRewardClaimed())
        {
            $tacticContext->setIsExtraMoveMoraleRewardUsed(true);
            $this->setIsChoosingExtraReward(false);
            $playerResources = new PlayerResources($activePlayerId);
            $playerResources->addMorale(1, true);
        }
        else
            throw new \BgaUserException(\clienttranslate("You cannot get extra reward now!"));

        return self::class;
    }

    #[PossibleAction]
    public function actExtraMove(int $activePlayerId) : string
    {
        $board = $this->game->getBoard();

        $tacticContext = new TacticContext();
        if ($tacticContext->hasExtraReward($board, $activePlayerId) && !$tacticContext->isExtraRewardClaimed())
        {
            $tacticContext->setIsExtraMoveMoraleRewardUsed(true);
            $this->setIsChoosingExtraReward(false);
        }
        else
            throw new \BgaUserException(\clienttranslate("You cannot get extra reward now!"));

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

    private function isChoosingExtraReward() : bool
    {
        return DBAdapter::getInstance()->getGlobalBool('choosing_extra_reward');
    }

    private function setIsChoosingExtraReward(bool $isChoosing) : void
    {
        DBAdapter::getInstance()->storeGlobalBool('choosing_extra_reward', $isChoosing);
    }
}