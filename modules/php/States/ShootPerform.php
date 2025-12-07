<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Game;

class ShootPerform extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_SHOOT_PERFORM,
            name: 'shootPerform',
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId)
    {
        $board = $this->game->getBoard();

        $attackerRollResult = DiceRoll::getRollResult($board->getAttacker());
        $defenderRollResult = DiceRoll::getRollResult($board->getDefender());
        $powerRequired = $this->game->getGameStateValue('required_power');

        if ($attackerRollResult->getTotal() >= $defenderRollResult->getTotal() + $powerRequired) 
        {
            $playerGoals = $this->game->AddOneGoal($board->getAttacker());

            $this->notify->all('goalScored', 'goal scored!', array(
                "playerId" => $board->getAttacker(),
                "goals" => $playerGoals
            ));

            $this->game->activeNextPlayer();

            return playerTurn::class;
        } 
        else 
        {
            $defenderGoalie = $board->getPlayerGoalie($board->getDefender());
            $selectedHex = ShootTargetSelection::getShootTargetHex( $defenderGoalie->getPosition()->getColumn(),ShootTargetSelection::getAttackShootTarget());

            $newLocation = $board->getClosetBlockingPieceOrHalfWay($board->getBallHex(), $selectedHex, $this->game->getInactivePlayerId());
            $board->setBallLocation($newLocation);
            $this->notify->all('shootFailed', "failed shoot", [
                "ballHex" => $newLocation->convertToArray()
            ]);

            return playerTurn::class;
        }
    }
}
