<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardInfo;
use Bga\Games\SuperTeam\Cards\TacticContext;
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
        $tacticContext = new TacticContext();
        $tacticContext->reset();

        Board::resetBoard(true);

        return PlayerPieceSetup::class;
    }
}
