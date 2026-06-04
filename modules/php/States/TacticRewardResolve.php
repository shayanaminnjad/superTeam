<?php

declare(strict_types=1);


namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Cards\TacticType;
use Bga\Games\SuperTeam\Cards\CardReward;

class TacticRewardResolve extends GameState
{
    function __construct(protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_TACTIC_REWARD_RESOLVE,
            name: "tacticRewardResolve",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId)
    {
        $dbAdapter = DBAdapter::getInstance();

        $cardInPlay = $dbAdapter->getGlobalInt("tactic_in_play");
        assert($cardInPlay >= 0);

        $tacticType = $dbAdapter->getGlobalInt("tactic_type");
        assert($tacticType >= 0 && $tacticType <=1, "tactic type should be either 0 or 1");

        $card = $this->game->getCardFactory()->getCard($cardInPlay);
        $cardDef = $card->getDefinition();

        $reward = TacticType::from($tacticType) == TacticType::Offensive ? $cardDef->offensiveReward : $cardDef->defensiveReward;

        if (!$this->hasReward($reward)){}
            //TODO: skip to next turn.


        //has claimed morale
        //if not give morale and save
        //then send to the player all the actions he can take.
        //so this must be essentially a player turn state!

        //TODO: apply fatigue too.
    }

    private function cleanup()
    {
        $dbAdapter = DBAdapter::getInstance();

        $dbAdapter->storeGlobalInt("tactic_in_play", -1);
        $dbAdapter->storeGlobalInt("tactic_progress", -1);
        $dbAdapter->storeGlobalInt("tactic_reward_in_progress", 0);
        $dbAdapter->storeGlobalString("rewards_used", "");
    }

    private function hasReward(CardReward $reward) : bool
    {
        return $reward->morale > 0 || $reward->action > 0|| $reward->move > 0;
    }
}