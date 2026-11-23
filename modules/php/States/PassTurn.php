<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\NotificationAdapter;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\PlayerPiecesDBManager;
use Bga\Games\SuperTeam\TimeTrack;
use Bga\Games\SuperTeam\UndoStack;

class PassTurn extends GameState
{
    function __construct(
        protected Game $game,
    )
    {
        parent::__construct(
            $game,
            id: ST_PASS_TURN,
            name: 'passTurn',
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: true,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId)
    {
        UndoStack::clear();

        //(sha) this happens if we have given the second player a final turn.
        if ($this->isLastTurn($activePlayerId, TimeTrack::GetTimeTrackPosition()))
            return ST_END_GAME;

        $playerHand = $this->game->getPlayerHand($activePlayerId);

        $wasDiscardShuffled = $playerHand->drawToAmount(PlayerPowers::forPlayer($activePlayerId)->drawFiveKeepFour() ? 5 : PlayerHand::$DEFAULT_DRAW_AMOUNT);

        $this->game->notify->player($activePlayerId, "handRefill", "",
            [
                'playerHand' => $playerHand->getCardsInHand(),
                'wasDiscardShuffled' => $wasDiscardShuffled,
            ]);

        $this->game->notify->all("deckCountChanged", "", [[$activePlayerId, $playerHand->CountCardsInDeck()]]);

        if ($wasDiscardShuffled)
        {
            $this->game->notify->all("discardShuffled", "", ["playerId" => $activePlayerId]);
        }

        DiceRollServices::storeDiceRerolledThisTurn(false);

        $this->game->giveExtraTime($activePlayerId);
        $nextPlayer = $this->game->activeNextPlayer();

        $this->game->DbQuery(PlayerPiecesDBManager::refreshPiecesQuery($nextPlayer));
        $this->game->notify->all("piecesRefreshed", "", ["playerId" => $nextPlayer]);

        $passActionContext = new PassActionContext($activePlayerId);
        $passActionContext->reset(true);

        $this->game->setGameStateValue("cardsPlayedThisTurn", 0);
        DBAdapter::getInstance()->storeGlobalBool('has_extra_die_for_whole_turn', false);

        $isRestartAfterGoal = DBAdapter::getInstance()->getGlobalInt('is_restart_after_goal');

        if ($isRestartAfterGoal)
        {
            if ($this->game->getStarterPlayer() != $nextPlayer)
                $this->game->swapStarterPlayer();
        }

        $wasPlayerHandShuffledDuringTurn = DBAdapter::getInstance()->getGlobalBool("player_hand_shuffled_during_turn");
        DBAdapter::getInstance()->storeGlobalBool("player_hand_shuffled_during_turn", false);
        if ($wasDiscardShuffled || $wasPlayerHandShuffledDuringTurn)
        {
            $timeTrackPosition = TimeTrack::GetTimeTrackPosition();
            if (!$wasPlayerHandShuffledDuringTurn && !TimeTrack::IsHalfTime($timeTrackPosition))
            {
                $timeTrackPosition = TimeTrack::AdvanceTimeTrack(true);
            }

            if ($this->isHalfTime($activePlayerId, $timeTrackPosition))
            {
                return HalfTime::class;
            }
            else if ($this->isLastTurn($activePlayerId, $timeTrackPosition))
            {
                return ST_END_GAME;
            }
            else
            {
                if (TimeTrack::IsGameFinished($timeTrackPosition))
                {
                    $this->game->setGameStateValue("isFinalTurn", 1);
                    $this->game->notify->all("finalTurnStarted", "", []);
                }
                else if (TimeTrack::IsHalfTime($timeTrackPosition))
                {
                    $this->game->setGameStateValue("isHalfTimeNext", 1);
                    $this->game->notify->all("halfTimeNext", "", []);
                }

                if ($isRestartAfterGoal)
                {
                    return BoardReset::class;
                } else
                {
                    return BeforeCardSelectionReset::class;
                }
            }
        } else
        {
            if ($this->isHalfTime($activePlayerId, TimeTrack::GetTimeTrackPosition()))
            {
                return HalfTime::class;
            }
            else if ($isRestartAfterGoal)
            {
                return BoardReset::class;
            }
            else
            {
                return BeforeCardSelectionReset::class;
            }
        }
    }

    /**
     * @param int $activePlayerId
     * @return bool
     */
    public function isLastTurn(int $activePlayerId, int $timeTrackPos): bool
    {
        //(sha) we want to make sure that both players play the same amount of turns. Amir wanted the player who had started the first half, to be the one
        //who finished the game in his turn.
        return TimeTrack::IsGameFinished($timeTrackPos) && $activePlayerId === $this->game->getPlayerStartedFirstHalf();
    }

    public function isHalfTime(int $activePlayerId, int $timeTrackPos): bool
    {
        //(sha) we want to make sure that both players play the same amount of turns.
        return TimeTrack::IsHalfTime($timeTrackPos) && $activePlayerId !== $this->game->getPlayerStartedFirstHalf();
    }
}
 