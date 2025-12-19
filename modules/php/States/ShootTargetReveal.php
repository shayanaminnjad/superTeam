<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\DBAdapter;

class ShootTargetReveal extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_SHOOT_TARGET_REVEAL,
            name: 'shootTargetReveal',
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId)
    {
        $board = $this->game->getBoard();
        $defender = $board->getDefender();
        
        $defenderTarget = ShootTargetSelection::getPlayerShootTarget($defender);
        $defenderGoalie = $board->getPlayerGoalie($board->getDefender());
        $board->movePieceToHex($defenderGoalie->getId(), $defenderTarget);
        
        $attacker = $board->getAttacker();
        $attackerTarget = ShootTargetSelection::getPlayerShootTarget($attacker);
        
        $dummyinlineHexes = [];
        $dummyblockingHexes = [];
        $requiredPower = $board->getRequiredPower($board->getBallHex(), $attackerTarget, $board->getDefender(), $dummyinlineHexes, $dummyblockingHexes);
        
        DiceRoll::storeRollInput($activePlayerId, $requiredPower, ShootPerform::class, true);
        MoraleAssignment::storeMoraleAssignData(addslashes(DiceRoll::class), true);

        $db = DBAdapter::getInstance();
        $db->storeSelectedHex($attackerTarget->getColumn(), $attackerTarget->getRow());

        $this->game->notify->all(
            'shootTargetReveal',
            '',
            [
                'goalieHex' => $defenderTarget->convertToArray(),
                'attacker' => $board->getAttacker(),
                'defender' => $board->getDefender(),
                'defenderTarget' => $defenderTarget,
                'attackerTarget' => $attackerTarget,
                'powerRequired' => $requiredPower
            ]
        );

        return ActiveNextGoMorale::class;
    }
}
