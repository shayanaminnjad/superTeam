<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Dice\GameDice;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Dice\DiceRollResult;

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
        $rollData = $this->getRollData();

        $playersToRollFor = [$activePlayerId];

        if ($rollData->rollForBoth) {
            $playersToRollFor[] = $this->game->getInactivePlayerId();
        }

        $notifData = [];
        foreach ($playersToRollFor as $player) 
        {
            $rollResult = $this->rollForPlayer($player);
            $this->storeRollResult($player, $rollResult);
            $notifData[$player] = $rollResult->getData();
        }

        $notifData["requiredPower"] = $rollData->requiredPower;

        //IMMEDIATE: I should check whether I should roll for both players or only a single player, then save the result below per player.
        $this->notify->all("diceRolled", \clienttranslate('diceRolled'), $notifData);

        assert(strlen($rollData->finishTransition) > 0, "no transition is stored for dice roll finish");

        return $rollData->finishTransition;
    }

    private function rollForPlayer(int $playerId): DiceRollResult
    {
        //TODO: Determine number of white dice to roll, and roll them!
        $dieRes = GameDice::greenDie()->roll();
        $assignedMorale = MoraleAssignment::getMoraleAssigned($playerId);

        return new DiceRollResult($assignedMorale, $dieRes, []);
    }

    //TODO: move this to a class named dice roll serivces.
    private function storeRollResult(int $player_id, DiceRollResult $rollRes)
    {
        DBAdapter::getInstance()->storePlayerTempStr($player_id, 'dice_result', $rollRes->toJson());
    }

    public static function getRollResult(int $player_id): DiceRollResult
    {
        $json = DBAdapter::getInstance()->getPlayerTempStr($player_id, 'dice_result');

        $result = new DiceRollResult();
        $result->fromJson($json);

        return $result;
    }

    public static function storeRollData(int $requiredPower, string $finishTransition, bool $bothPlayers)
    {
        //TODO: I should package this into a single json entry!
        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->setGameStateValue('required_power', $requiredPower);
        $dbAdapter->storeGlobalString('dice_roll_transition', $finishTransition);
        $dbAdapter->setGameStateValue("shouldRollForBoth", $bothPlayers ? 1 : 0);
    }

    private function getRollData(): DiceRollData
    {
        return new DiceRollData();
    }
}

class DiceRollData
{
    public bool $rollForBoth;
    public int $requiredPower;
    public string $finishTransition;

    public function __construct()
    {
        $dbAdapter = DBAdapter::getInstance();
        $this->requiredPower = $dbAdapter->getGameStateValue('required_power');
        $this->finishTransition = $dbAdapter->getGlobalString('dice_roll_transition');
        $this->rollForBoth = $dbAdapter->getGameStateValue("shouldRollForBoth") === 1;
    }
}
