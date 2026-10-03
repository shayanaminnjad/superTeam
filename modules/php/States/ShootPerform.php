<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\CardActionDriver;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\NotificationAdapter;
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
            $this->notify->all('shootSuccess', \clienttranslate('${player_name} shoot successfully'), [
                "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId),
            ]);

            $playerResources = new PlayerResources($attacker);

            $playerResources->addOneGoal($this->game, true);

            $cardActionDriver = new CardActionDriver($activePlayerId, $this->game);

            if ($cardActionDriver->isCardInProgress())
            {
                $stateToReturnTo = $cardActionDriver->progress();
                assert($stateToReturnTo == BeforeCardSelectionReset::class);
            }

            DBAdapter::getInstance()->storeGlobalInt('is_restart_after_goal', 1);

            return PassTurn::class;
        } 
        else 
        {
            $selectedHex = ShootTargetSelection::getPlayerShootTarget($attacker);

            $newLocation = $board->getClosetBlockingPieceOrHalfWay($board->getBallHex(), $selectedHex, $this->game->getInactivePlayerId());

            $newLocation = BoardEvaluator::correctPositionIfInsideGoalieArea($board, $newLocation);

            $board->setBallLocation($newLocation);
            $this->notify->all('shootFailed', \clienttranslate('${player_name}\'s shoot failed'), [
                "ballHex" => $newLocation->convertToArray(),
                "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId),
            ]);

            return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
        }
    }
}
