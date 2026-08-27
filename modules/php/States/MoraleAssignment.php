<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\Math\Math;
use Bga\Games\SuperTeam\DBAdapter;

class MoraleAssignment extends GameState
{
    private const MAX_MORALE_PER_ACTION = 3;

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_MORALE_ASSIGMENT,
            name: 'moraleAssignment',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is assigning morale'),
            descriptionMyTurn: clienttranslate('assign morale (Max ${maxMoraleAssignable})'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function getArgs(int $activePlayerId): array
    {
        $playerResources = new PlayerResources($activePlayerId);

        $maxMoraleAssignable = Math::clamp($playerResources->getMorale(), 0, self::MAX_MORALE_PER_ACTION);

        $requiredPower = $this->game->getGameStateValue('required_power');

        $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();

        $attacker = Board::getAttacker();
        $shootTarget = ShootTargetSelection::getPlayerShootTarget($attacker);
        $isShooting = $shootTarget->isValid();

        return [
            'maxMoraleAssignable' => $maxMoraleAssignable,
            'requiredPower' => $requiredPower,
            'attacker' => $attacker,
            'selectedDestination' => $selectedHex->convertToArray(),
            'isShooting' => $isShooting
        ]; //TODO: this probably must be injected here, as not all actions have a destination!
    }

    #[PossibleAction]
    public function actMoraleAssigned(int $amount, int $activePlayerId): string
    {
        $maxMoraleAssignable = $this->getArgs($activePlayerId)['maxMoraleAssignable'];

        if ($amount > $maxMoraleAssignable || $amount < 0) {
            throw new \BgaUserException("$amount morale assigned, max morale assignable is $maxMoraleAssignable");
        }

        $assignData = $this->getAssignmentData();

        DBAdapter::getInstance()->setGameStateValue('moraleAssignRemaining', $assignData->moraleAssignRemaining - 1);

        $playerResources = new PlayerResources($activePlayerId);
        $playerResources->addMorale(-$amount, true);

        //TODO: this is an important data to the current turn player! make sure that it is shown to him boldly!

        DBAdapter::getInstance()->storePlayerTempInt(intval($this->game->getActivePlayerId()), 'assigned_morale', $amount);

        if ($assignData->moraleAssignRemaining - 1 > 0) {
            return ActiveNextGoMorale::class;
        } else {
            assert(strlen($assignData->finishTransition) > 0, "no transition is stored for morale assign finish");

            return $assignData->finishTransition;
        }
    }

    function zombie(int $playerId): string
    {
        return $this->actMoraleAssigned(0, $playerId);
    }
    
    //TODO: these function can be inside another file named morale assign services
    public static function storeMoraleAssignData(string $finishTransition, bool $bothPlayers) : void
    {
        //I can package all these into a single json entry!
        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->setGameStateValue('moraleAssignRemaining', $bothPlayers ? 2 : 1);
        $dbAdapter->storeGlobalString('morale_assign_transition', addslashes($finishTransition));
    }

    public static function getMoraleAssigned(int $playerId): int
    {
        return DBAdapter::getInstance()->getPlayerTempInt($playerId, 'assigned_morale');
    }

    private function getAssignmentData(): MoraleAssignData
    {
        return new MoraleAssignData();
    }
}

class MoraleAssignData
{
    public int $moraleAssignRemaining;
    public int $requiredPower;
    public string $finishTransition;

    public function __construct()
    {
        $dbAdapter = DBAdapter::getInstance();
        $this->moraleAssignRemaining = $dbAdapter->getGameStateValue("moraleAssignRemaining");
        $this->requiredPower = $dbAdapter->getGameStateValue('required_power');
        $this->finishTransition = $dbAdapter->getGlobalString('morale_assign_transition');
    }
}
