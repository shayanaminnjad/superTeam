<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\NotificationAdapter;

class PerformDribble extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PERFORM_DRIBBLE,
            name: "performDribble",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId):string
    {
        $board = $this->game->getBoard();

        $attackerRollRes = DiceRollServices::getRollResult($board->getAttacker());
        $defenderRollRes = DiceRollServices::getRollResult($board->getDefender());

        if ($attackerRollRes->getTotal() >= $defenderRollRes->getTotal())
        {
            $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();
            $ballOldHex = $board->getBallHex();
            $board->swapHexesContent($ballOldHex, $selectedHex, true);

            $this->game->notify->all("dribblePerformed", \clienttranslate('${player_name} dribbled successfully'), [
                "swappedHexes" => [$selectedHex->convertToArray(), $ballOldHex->convertToArray()],
                "ballPosition" => $board->getBallHex()->convertToArray(),
                "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId)
            ]);
        }
        else
        {
            $this->notify->all('dribbleFailed', \clienttranslate('${player_name}\'s dribble failed'), [
                "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId),
            ]);
        }

        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }
}
