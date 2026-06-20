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
        //remove two fatigue cards if possible.
        //all coach markers can be re-arranged.
        //player piece setup (this is handled)
        //all cards go to draw deck, and shuffled, then 4 new cards are drawn.
        //change the starting player.

        //TODO: This class must reset tactics in use! since we might end up here from a reward!

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
