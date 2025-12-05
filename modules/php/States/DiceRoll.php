<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Dice\GameDice;
use Bga\Games\SuperTeam\States\Utils\DiceRollData;

class DiceRoll extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_DICE_ROLL,
            name: 'diceRoll',
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId): string
    {
        //TODO: Determine number of white dice to roll, and roll them!

        DiceRollData::load();

        $dieRes = GameDice::greenDie()->roll();
        $assignedMorale = DiceRollData::$assignedMorale;

        $this->game->setGameStateValue('dice_result', $dieRes + $assignedMorale);
        $this->game->setGameStateValue('assigned_morale', 0);

        $requiredPower = DiceRollData::$requiredPower;

        $this->notify->all("diceRolled", \clienttranslate('diceRolled'), array(
            "greenDie" => $dieRes,
            "assignedMorale" => $assignedMorale,
            "whiteDice" => [],
            "requiredPower" => $requiredPower
        ));

        assert(strlen(DiceRollData::$finishTransition) > 0, "no transition is stored for dice roll finish");

        return DiceRollData::$finishTransition;
    }
}
