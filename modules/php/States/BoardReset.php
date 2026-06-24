<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardInfo;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerPiecesDBManager;

class BoardReset extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_BOARD_RESET,
            name: "BoardReset",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId): string
    {
        //TODO: This class must reset tactics in use! since we might end up here from a reward!

        Board::resetBoard(true);

        return PlayerPieceSetup::class;
    }
}
