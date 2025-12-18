<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Dice\GameDice;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\RequiredPower;
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
        $rollData = $this->getRollData($activePlayerId);

        $playersToRollFor = [$activePlayerId];

        if ($rollData->rollForBoth) {
            $playersToRollFor[] = $this->game->getInactivePlayerId();
        }

        $rollNotifData = [];
        foreach ($playersToRollFor as $player) {
            $rollResult = $this->rollForPlayer($player);
            $this->storeRollResult($player, $rollResult);
            $rollResultData = $rollResult->getData();
            $rollNotifData[$player] = $rollResultData;
        }
        
        $notifData = [];
        $notifData["rollResult"] = $rollNotifData;
        $notifData["distance"] = $rollData->distance;
        $notifData["blockingPieces"] = $rollData->blockingPieces;
        $notifData["requiredPower"] = $rollData->requiredPower;

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

    public static function storeRollInput(int $playerId, RequiredPower $requiredPower, string $finishTransition, bool $bothPlayers)
    {
        $rollData = new DiceRollData($requiredPower->getTotal(), $finishTransition, $requiredPower->distance, \count($requiredPower->blockingHexes), $bothPlayers);

        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->setGameStateValue('required_power', $requiredPower->getTotal());
        $dbAdapter->storePlayerTempStr($playerId, "dice_roll_input", $rollData->toJson());
    }

    private function getRollData(int $playerId): DiceRollData
    {
        $json = DBAdapter::getInstance()->getPlayerTempStr($playerId, "dice_roll_input");
        $rollData = new DiceRollData();
        $rollData->fromJson($json);

        return $rollData;
    }
}

class DiceRollData
{
    public bool $rollForBoth;
    public int $requiredPower;
    public string $finishTransition;
    public int $distance;
    public int $blockingPieces;

    public function __construct(int $requiredPower = 0, string $finishTransition = '', int $distance = 0, int $blockingPieces = 0, bool $rollForBoth = false)
    {
        $this->requiredPower = $requiredPower;
        $this->finishTransition = $finishTransition;
        $this->rollForBoth = $rollForBoth;
        $this->distance = $distance;
        $this->blockingPieces = $blockingPieces;
    }

    public function toJson(): string
    {
        $result = [
            "rollForBoth" => $this->rollForBoth,
            "requiredPower" => $this->requiredPower,
            "distance" => $this->distance,
            "blockingPieces" => $this->blockingPieces,
            "finishTransition" => $this->finishTransition
        ];

        return json_encode($result);
    }

    public function fromJson($json)
    {
        $data = json_decode($json, true);

        assert(is_array($data), "Invalid json format passed!");

        $this->rollForBoth = $data["rollForBoth"];
        $this->requiredPower = $data["requiredPower"];
        $this->distance = $data["distance"];
        $this->blockingPieces = $data["blockingPieces"];
        $this->finishTransition = $data["finishTransition"];
    }
}
