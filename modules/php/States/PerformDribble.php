<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;

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

        $attackerRollRes = DiceRoll::getRollResult($board->getAttacker());
        $defenderRollRes = DiceRoll::getRollResult($board->getDefender());

        if ($attackerRollRes->getTotal() >= $defenderRollRes->getTotal())
        {
            $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();
            $ballOldHex = $board->getBallHex();
            $board->swapHexesContent($ballOldHex, $selectedHex, true);

            $this->game->notify->all("dribblePerformed", '', [
                "swappedHexes" => [$selectedHex->convertToArray(), $ballOldHex->convertToArray()]
            ]);
        }

        return PlayerTurn::class;
    }
}
