<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\DBAdapter;

class PerformTackle extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PERFORM_TACKLE,
            name: 'performTackle',
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId)
    {
        $board = $this->game->getBoard();

        $attackerRollRes = DiceRoll::getRollResult($board->getAttacker());
        $defenderRollRes = DiceRoll::getRollResult($board->getDefender());

        if ($defenderRollRes->getTotal() > $attackerRollRes->getTotal()) {
            $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();
            $board->swapHexesContent($board->getBallHex(), $selectedHex, swapBall: false);

            $this->game->notify->all("tacklePerformed", '', [
                "swappedHexes" => [$selectedHex->convertToArray(), $board->getBallHex()->convertToArray()]
            ]);
        }
        else
        {
            $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();
            
            $piece = null;
            $board->tryGetPlayerPieceInHex($selectedHex, $piece);

            assert($piece != null);

            $piece->setCanTackle(false, true);
        }

        return PlayerTurn::class;
    }
}
