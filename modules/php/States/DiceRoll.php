<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Dice\GameDice;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Math\Math;
use Bga\Games\SuperTeam\MoraleAssignContext;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\PlayerRole;
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

        $activePlayerRollResult = null;
        $rollNotifData = [];
        foreach ($playersToRollFor as $player) {
            $relatedAttribute = $player == $activePlayerId ? $rollData->selfAttribute : $rollData->otherAttribute;
            $rollResult = $this->rollForPlayer($player, $relatedAttribute, $rollData->rollingPieceIds[$player]);
            DiceRollServices::storeRollResult($player, $rollResult);
            if($player === $activePlayerId)
                $activePlayerRollResult = $rollResult;
            $rollResultData = $rollResult->getData();
            $rollNotifData[$player] = $rollResultData;
        }

        $playerPower = PlayerPowers::forPlayer($activePlayerId);
        $hasReroll = false;
        if ($playerPower->reRollNegativeDiceOncePerTurn() && DiceRollServices::getDiceRerolledThisTurn() === false)
        {
            $whiteDiceRolled = $activePlayerRollResult->getWhiteDice();

            if (array_any($whiteDiceRolled, fn(int $face) => $face === -1))
                $hasReroll = true;
        }

        $attacker = Board::getAttacker();

        $blockingPieces = Math::clamp($rollData->skipEnabled ? $rollData->blockingPieces - 1 : $rollData->blockingPieces, 0, $rollData->blockingPieces);

        $notifData = [];
        $notifData["rollResult"] = $rollNotifData;
        $notifData["distance"] = $rollData->distance;
        $notifData["blockingPieces"] = $blockingPieces;
        $notifData["skipEnabledPlayerId"] = ($rollData->skipEnabled ? $activePlayerId : -1);
        $notifData["requiredPower"] = $rollData->requiredPower;
        $notifData["attacker"] = $attacker;
        $notifData["hasReroll"] = $hasReroll;
        $notifData["diceLog"] = "";
        $notifData["actionName"] = $rollData->actionName;
        if ($rollData->selfAttribute == TeamAttribute::Pass) {
            $notifData["passDest"] = DBAdapter::getInstance()->getSelectedHexAsHexID()->convertToArray();
        }
        $notifData["i18n"] = ["actionName"];
        //History logs (F5) drop every arg that is not in the message, so the client can rebuild ${diceLog}.
        $notifData["preserve"] = ["rollResult", "distance", "blockingPieces", "requiredPower", "actionName", "skipEnabledPlayerId"];

        $this->notify->all("diceRolled", \clienttranslate('${diceLog}'), $notifData);

        if ($hasReroll)
        {
            return DiceReRollState::class;
        }
        else
        {
            assert(strlen($rollData->finishTransition) > 0, "no transition is stored for dice roll finish");
            return $rollData->finishTransition;
        }
    }

    private function rollForPlayer(int $playerId, int $activePlayerId, TeamAttribute $relatedAttribute, int $rollingPieceId): DiceRollResult
    {
        $whiteDice = [];
        if ($relatedAttribute != TeamAttribute::None)
        {
            $teamBoard = $this->game->getTeamBoard();
            $attrCount = $teamBoard->getAttribute($playerId, $relatedAttribute);

            if ($activePlayerId == $playerId && DBAdapter::getInstance()->getGlobalBool('has_extra_die_for_whole_turn'))
                $attrCount += 1;

            for($i = 0; $i < $attrCount; $i++)
            {
                $whiteDice[]= GameDice::whiteDie()->roll();
            }
        }

        $dieRes = GameDice::greenDie()->roll();
        $moraleAssignContext = new MoraleAssignContext();
        $assignedMorale = $moraleAssignContext->getMoraleAssigned($playerId);

        $captainStars = 0;
        $rollingPiece = $this->game->getBoard()->getPiece($rollingPieceId);
        if ($this->game->isAdvancedMode() && $rollingPiece->getRole() == PlayerRole::Captain->value)
            $captainStars = $this->game->getPlayerCaptainPower($playerId)?->getPowerFor($relatedAttribute) ?? 0;

        return new DiceRollResult($assignedMorale, $dieRes, $whiteDice, $captainStars);
    }
}

class DiceRollData
{
    public string $actionName;
    public bool $rollForBoth;
    public int $requiredPower;
    public string $finishTransition;
    public int $distance;
    public int $blockingPieces;
    public bool $skipEnabled;
    public TeamAttribute $selfAttribute;
    public TeamAttribute $otherAttribute;
    /** @var array<int, int> */
    public array $rollingPieceIds;

    public function __construct(string $actionName, int $requiredPower, string $finishTransition, int $distance, int $blockingPieces, bool $passEnabled, TeamAttribute $selfAttribute, TeamAttribute $otherAttribute, array $rollingPieceIds, bool $rollForBoth = false)
    {
        $this->actionName = $actionName;
        $this->rollingPieceIds = $rollingPieceIds;
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
            "actionName" => $this->actionName,
            "rollForBoth" => $this->rollForBoth,
            "requiredPower" => $this->requiredPower,
            "distance" => $this->distance,
            "blockingPieces" => $this->blockingPieces,
            "passEnabled" => $this->skipEnabled,
            "finishTransition" => $this->finishTransition,
            "selfAttribute" => $this->selfAttribute->value,
            "otherAttribute" => $this->otherAttribute->value,
            "rollingPieceIds" => $this->rollingPieceIds
        ];

        return json_encode($result);
    }

    public static function fromJson($json)
    {
        $data = json_decode($json, true);

        assert(\is_array($data), "Invalid json format passed!");

        $actionName = $data["actionName"];
        $rollForBoth = $data["rollForBoth"];
        $requiredPower = $data["requiredPower"];
        $distance = $data["distance"];
        $blockingPieces = $data["blockingPieces"];
        $passEnabled = $data["passEnabled"];
        $finishTransition = $data["finishTransition"];
        $selfAttribute = TeamAttribute::from($data["selfAttribute"]);
        $otherAttribute = TeamAttribute::from($data["otherAttribute"]);
        $rollingPieceIds = $data["rollingPieceIds"];

        return new DiceRollData($actionName, $requiredPower, $finishTransition, $distance, $blockingPieces, $passEnabled, $selfAttribute, $otherAttribute, $rollingPieceIds, $rollForBoth);
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

    /** @param array<int, int> $rollingPieceIds player id => id of the piece that rolls for that player */
    public static function storeRollInput(string $actionName, int $playerId, RequiredPower $requiredPower, string $finishTransition, TeamAttribute $selfAttribute, TeamAttribute $otherAttribute, bool $bothPlayers, array $rollingPieceIds)
    {
        $rollData = new DiceRollData($actionName, $requiredPower->getTotal(), $finishTransition, $requiredPower->distance, \count($requiredPower->blockingHexes), RequiredPower::isSkipEnabled(), $selfAttribute, $otherAttribute, $rollingPieceIds, $bothPlayers);

        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->setGameStateValue('required_power', $requiredPower->getTotal());
        // global, because the opponent may be the active player during morale assignment
        $dbAdapter->storeGlobalString('roll_action_name', addslashes($actionName));
        $dbAdapter->storePlayerTempStr($playerId, "dice_roll_input", $rollData->toJson());
    }

    public static function getCurrentActionName(): string
    {
        return DBAdapter::getInstance()->getGlobalString('roll_action_name');
    }

    public static function getRollData(int $playerId): DiceRollData
    {
        $json = DBAdapter::getInstance()->getPlayerTempStr($playerId, "dice_roll_input");
        return DiceRollData::fromJson($json);
    }

    public static function storeDiceRerolledThisTurn(bool $diceRerolled) : void
    {
        DBAdapter::getInstance()->storeGlobalBool('dice_rerolled_this_turn', $diceRerolled);
    }

    public static function getDiceRerolledThisTurn() : bool
    {
        return DBAdapter::getInstance()->getGlobalBool('dice_rerolled_this_turn');
    }

    public static function getClientData(Game $game, int $activePlayerId): array
    {
        $rollData = self::getRollData($activePlayerId);

        $playersToRollFor = [$activePlayerId];
        if ($rollData->rollForBoth) {
            $playersToRollFor[] = $game->getInactivePlayerId();
        }

        $rollResults = [];
        foreach ($playersToRollFor as $playerId) {
            $rollResults[$playerId] = self::getRollResult($playerId)->getData();
        }

        $data = [
            "rollResult"          => $rollResults,
            "distance"            => $rollData->distance,
            "blockingPieces"      => $rollData->blockingPieces,
            "skipEnabledPlayerId" => $rollData->skipEnabled ? $activePlayerId : -1,
            "requiredPower"       => $rollData->requiredPower,
            "attacker"            => Board::getAttacker(),
            "actionName"          => $rollData->actionName,
            "rollForBoth"         => $rollData->rollForBoth,
            "selfAttribute"       => $rollData->selfAttribute->value,
            "otherAttribute"      => $rollData->otherAttribute->value,
            "rollingPieceIds"     => $rollData->rollingPieceIds,
        ];

        if ($rollData->selfAttribute == TeamAttribute::Pass) {
            $data["passDest"] = DBAdapter::getInstance()->getSelectedHexAsHexID()->convertToArray();
        }

        return $data;
    }
}