<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
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
        
        DBAdapter::getInstance()->DbQuery(PlayerPiecesDBManager::resetPiecesDataQuery());
        $ballPos = BoardInfo::$CENTER_POSITION;
        DBAdapter::getInstance()->DbQuery("UPDATE `ball` SET `hex_column`={$ballPos->getColumn()}, `hex_row`={$ballPos->getRow()}");

        $this->game->notify->all(
            "resetBoard",
            "",
            ["ballHex" => $ballPos->convertToArray(),
                   "goalieRow" => 5]);

        return PlayerPieceSetup::class;
    }
}
