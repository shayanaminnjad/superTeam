<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\UndoStack;
use Bga\Games\SuperTeam\UndoStepAction;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\MoraleAssignContext;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\Math\Math;
use Bga\Games\SuperTeam\DBAdapter;

class MoraleAssignment extends GameState
{
    use UndoStepAction;

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_MORALE_ASSIGMENT,
            name: 'moraleAssignment',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is assigning ${moraleIcon} for ${actionName}'),
            descriptionMyTurn: clienttranslate('${you} must assign ${moraleIcon} for ${actionName} (Max ${maxMoraleAssignable})'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function getArgs(int $activePlayerId): array
    {
        $playerResources = new PlayerResources($activePlayerId);

        $maxMoralePerAction = PlayerPowers::forPlayer($activePlayerId)->maxMoraleAssignable();

        $maxMoraleAssignable = Math::clamp($playerResources->getMorale(), 0, $maxMoralePerAction);

        $requiredPower = $this->game->getGameStateValue('required_power');

        $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();

        $attacker = Board::getAttacker();
        $shootTarget = ShootTargetSelection::getPlayerShootTarget($attacker);
        $isShooting = $shootTarget->isValid();


        $moraleDataToSend = $this->prepareMoraleAssignDataToSend(new MoraleAssignContext(), -1);

        return [
            'actionName' => DiceRollServices::getCurrentActionName(),
            'i18n' => ['actionName'],
            'moraleIcon' => 'morale',
            'maxMoraleAssignable' => $maxMoraleAssignable,
            'requiredPower' => $requiredPower,
            'attacker' => $attacker,
            'selectedDestination' => $selectedHex->convertToArray(),
            'isShooting' => $isShooting,
            'moraleAssigned' => $moraleDataToSend,
            'clientButtons' => UndoStack::getClientButtons($this->game),
        ];
    }

    #[PossibleAction]
    public function actMoraleAssigned(int $amount, int $activePlayerId): string
    {
        $maxMoraleAssignable = $this->getArgs($activePlayerId)['maxMoraleAssignable'];

        if ($amount > $maxMoraleAssignable || $amount < 0) {
            throw new \BgaUserException(\clienttranslate('${amount} morale assigned, max morale assignable is ${maxMorale}'), 100, ['amount' => $amount, 'maxMorale' => $maxMoraleAssignable]);
        }

        $moraleAssignContext = new MoraleAssignContext();
        $moraleAssignmentRemaining = $moraleAssignContext->getMoraleAssignRemaining();
        $moraleAssignContext->storeMoraleAssignRemaining( $moraleAssignmentRemaining - 1);

        if ($amount > 0)
        {
            $playerResources = new PlayerResources($activePlayerId);
            $playerResources->addMorale(-$amount, true);
        }

        $activePlayerId = intval($this->game->getActivePlayerId());
        $moraleAssignContext->storeMoraleAssigned($activePlayerId, $amount);

        $this->notifyMoraleAssigned($moraleAssignContext, $activePlayerId);

        if ($moraleAssignmentRemaining - 1 > 0) {
            return ActiveNextGoMorale::class;
        } else {
            $finishTransition = $moraleAssignContext->getMoraleAssignTransition();
            assert(strlen($finishTransition) > 0, "no transition is stored for morale assign finish");

            return $finishTransition;
        }
    }

    function zombie(int $playerId): string
    {
        return $this->actMoraleAssigned(0, $playerId);
    }

    private function notifyMoraleAssigned(MoraleAssignContext $moraleAssignContext, int $activePlayerId): void
    {
        $moraleAssignedNotifData = $this->prepareMoraleAssignDataToSend($moraleAssignContext, $activePlayerId);

        $this->game->notify->all("moraleAssigned", "", $moraleAssignedNotifData);
    }

    private function prepareMoraleAssignDataToSend(MoraleAssignContext $moraleAssignContext, int $activePlayerId) : array
    {
        $moralePiecesIds = $moraleAssignContext->getMoraleAssignPiecesIds();

        $moraleAssignData = [];

        foreach ($moralePiecesIds as [$playerId, $pieceId])
        {
            if ($activePlayerId == -1 || $activePlayerId == $playerId)
            {
                $moraleAssignData[$playerId] = [
                    $pieceId,
                    $moraleAssignContext->getMoraleAssigned($playerId)
                ];
            }
        }

        return $moraleAssignData;
    }
}
