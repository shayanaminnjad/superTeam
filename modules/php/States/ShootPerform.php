<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerResources;

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
        $attacker = $board->getAttacker();
        $defender = $board->getDefender();

        $attackerRollResult = DiceRollServices::getRollResult($attacker);
        $defenderRollResult = DiceRollServices::getRollResult($defender);
        $powerRequired = $this->game->getGameStateValue('required_power');

        if ($attackerRollResult->getTotal() >= $defenderRollResult->getTotal() + $powerRequired) 
        {
            $playerResources = new PlayerResources($attacker);

            $playerResources->addOneGoal(true);

            $this->game->activeNextPlayer();

            return BoardReset::class;
        } 
        else 
        {
            $defenderGoalie = $board->getPlayerGoalie($defender);
            $selectedHex = ShootTargetSelection::getPlayerShootTarget($attacker);

            $newLocation = $board->getClosetBlockingPieceOrHalfWay($board->getBallHex(), $selectedHex, $this->game->getInactivePlayerId());
            $board->setBallLocation($newLocation);
            $this->notify->all('shootFailed', "failed shoot", [
                "ballHex" => $newLocation->convertToArray()
            ]);

            return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
        }
    }
}
