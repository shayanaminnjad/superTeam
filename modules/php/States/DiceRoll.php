<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Dice\GameDice;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\MoraleAssignContext;
use Bga\Games\SuperTeam\RequiredPower;
use Bga\Games\SuperTeam\Dice\DiceRollResult;
use Bga\Games\SuperTeam\TeamAttribute;

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
        $rollData = DiceRollServices::getRollData($activePlayerId);

        $playersToRollFor = [$activePlayerId];

        if ($rollData->rollForBoth) {
            $playersToRollFor[] = $this->game->getInactivePlayerId();
        }

        $rollNotifData = [];
        foreach ($playersToRollFor as $player) {
            $relatedAttribute = $player == $activePlayerId ? $rollData->selfAttribute : $rollData->otherAttribute;
            $rollResult = $this->rollForPlayer($player, $relatedAttribute);
            DiceRollServices::storeRollResult($player, $rollResult);
            $rollResultData = $rollResult->getData();
            $rollNotifData[$player] = $rollResultData;
        }
        
        $notifData = [];
        $notifData["rollResult"] = $rollNotifData;
        $notifData["distance"] = $rollData->distance;
        $notifData["blockingPieces"] = $rollData->blockingPieces;
        $notifData["skipEnabledPlayerId"] = ($rollData->skipEnabled ? $activePlayerId : -1);
        $notifData["requiredPower"] = $rollData->requiredPower;
        $notifData["attacker"] = Board::getAttacker();

        $this->notify->all("diceRolled", \clienttranslate('diceRolled'), $notifData);

        assert(strlen($rollData->finishTransition) > 0, "no transition is stored for dice roll finish");

        return $rollData->finishTransition;
    }

    private function rollForPlayer(int $playerId, TeamAttribute $relatedAttribute): DiceRollResult
    {
        $whiteDice = [];
        if ($relatedAttribute != TeamAttribute::None)
        {
            $teamBoard = $this->game->getTeamBoard();
            $attrCount = $teamBoard->getAttribute($playerId, $relatedAttribute);

            for($i = 0; $i < $attrCount; $i++)
            {
                $whiteDice[]= GameDice::whiteDie()->roll();
            }
        }

        $dieRes = GameDice::greenDie()->roll();
        $moraleAssignContext = new MoraleAssignContext();
        $assignedMorale = $moraleAssignContext->getMoraleAssigned($playerId);

        return new DiceRollResult($assignedMorale, $dieRes, $whiteDice);
    }
}

class DiceRollData
{
    public bool $rollForBoth;
    public int $requiredPower;
    public string $finishTransition;
    public int $distance;
    public int $blockingPieces;
    public bool $skipEnabled;
    public TeamAttribute $selfAttribute;
    public TeamAttribute $otherAttribute;

    public function __construct(int $requiredPower, string $finishTransition, int $distance, int $blockingPieces, bool $passEnabled, TeamAttribute $selfAttribute, TeamAttribute $otherAttribute, bool $rollForBoth = false)
    {
        $this->requiredPower = $requiredPower;
        $this->finishTransition = $finishTransition;
        $this->rollForBoth = $rollForBoth;
        $this->distance = $distance;
        $this->blockingPieces = $blockingPieces;
        $this->selfAttribute = $selfAttribute;
        $this->otherAttribute = $otherAttribute;
        $this->skipEnabled = $passEnabled;
    }

    public function toJson(): string
    {
        $result = [
            "rollForBoth" => $this->rollForBoth,
            "requiredPower" => $this->requiredPower,
            "distance" => $this->distance,
            "blockingPieces" => $this->blockingPieces,
            "passEnabled" => $this->skipEnabled,
            "finishTransition" => $this->finishTransition,
            "selfAttribute" => $this->selfAttribute->value,
            "otherAttribute" => $this->otherAttribute->value
        ];

        return json_encode($result);
    }

    public static function fromJson($json)
    {
        $data = json_decode($json, true);

        assert(\is_array($data), "Invalid json format passed!");

        $rollForBoth = $data["rollForBoth"];
        $requiredPower = $data["requiredPower"];
        $distance = $data["distance"];
        $blockingPieces = $data["blockingPieces"];
        $passEnabled = $data["passEnabled"];
        $finishTransition = $data["finishTransition"];
        $selfAttribute = TeamAttribute::from($data["selfAttribute"]);
        $otherAttribute = TeamAttribute::from($data["otherAttribute"]);

        return new DiceRollData($requiredPower, $finishTransition, $distance, $blockingPieces, $passEnabled, $selfAttribute, $otherAttribute, $rollForBoth);
    }
}

class DiceRollServices
{
    public static function storeRollResult(int $player_id, DiceRollResult $rollRes)
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

    public static function storeRollInput(int $playerId, RequiredPower $requiredPower, string $finishTransition, TeamAttribute $selfAttribute, TeamAttribute $otherAttribute, bool $bothPlayers)
    {
        $rollData = new DiceRollData($requiredPower->getTotal(), $finishTransition, $requiredPower->distance, \count($requiredPower->blockingHexes), RequiredPower::isSkipEnabled(), $selfAttribute, $otherAttribute, $bothPlayers);

        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->setGameStateValue('required_power', $requiredPower->getTotal());
        $dbAdapter->storePlayerTempStr($playerId, "dice_roll_input", $rollData->toJson());
    }

    public static function getRollData(int $playerId): DiceRollData
    {
        $json = DBAdapter::getInstance()->getPlayerTempStr($playerId, "dice_roll_input");
        return DiceRollData::fromJson($json);
    }
}