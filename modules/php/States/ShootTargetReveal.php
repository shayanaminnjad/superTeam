<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\HexID;

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

        $db = DBAdapter::getInstance();
        $defenderTarget = $db->getGameStateValue("defenderShootTarget");

        $defenderGoalie = $board->getPlayerGoalie($board->getDefender());
        $defenderGoalieOldPos = $defenderGoalie->getPosition();
        $goalieNewPos = new HexID($defenderGoalieOldPos->getColumn(), 5 + $defenderTarget);
        $board->movePieceToHex($defenderGoalie->getId(), $goalieNewPos);

        $attackerTarget = $db->getGameStateValue("attackerShootTarget");
        $attackerTargetHex = new HexID($goalieNewPos->getColumn(), 5 + $attackerTarget);

        $dummyinlineHexes = [];
        $dummyblockingHexes = [];
        $requiredPower = $board->getRequiredPower($board->getBallHex(), $attackerTargetHex, $board->getDefender(), $dummyinlineHexes, $dummyblockingHexes);

        DiceRoll::storeRollData($requiredPower, addslashes(ShootPerform::class), true);
        MoraleAssignment::storeMoraleAssignData(addslashes(DiceRoll::class), true);
        $db->storeSelectedHex($attackerTargetHex->getColumn(), $attackerTargetHex->getRow());

        $this->game->notify->all(
            'shootTargetReveal',
            '',
            [
                'goalieHex' => $goalieNewPos->convertToArray(),
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
