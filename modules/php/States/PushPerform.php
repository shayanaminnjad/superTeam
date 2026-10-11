<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BoardSpatial;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;

class PushPerform extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PUSH_PERFORM,
            name: 'pushPerform',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is choosing a push destination'),
            descriptionMyTurn: clienttranslate('you must choose push destination'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }
    public function selfGetArgs(): array
    {
        $board = $this->game->getBoard();

        $selectedPushTarget = DBAdapter::getInstance()->getSelectedHexAsHexID();

        $playerPiece = null;
        $board->tryGetPlayerPieceInHex($selectedPushTarget, $playerPiece);

        $pushHexes = BoardSpatial::findImmediateMoveableNeighbours($board, $playerPiece);

        return ["pushHexes" => $pushHexes];
    }

    #[PossibleAction]
    public function actTargetSelected(int $hexColumn, int $hexRow): string
    {
        $board = $this->game->getBoard();
        $selectedHex = new HexID($hexColumn, $hexRow);

        //todo: check whether the hex is selectable at all!
        if (!BoardSpatial::isHexMoveable($board, $selectedHex))
            throw new \BgaUserException(clienttranslate("Invalid hex selected."));

        $selectedPushTarget = DBAdapter::getInstance()->getSelectedHexAsHexID();
        $playerPiece = null;
        $board->tryGetPlayerPieceInHex($selectedPushTarget, $playerPiece);
        $board->movePieceToHex($playerPiece->getId(), $selectedHex);

        $ballMoved = false;
        if ($board->getBallHex()->equals($selectedPushTarget))
        {
            $ballMoved = true;
            $board->setBallLocation($selectedHex);
        }

        $this->notify->all("movedPiece", \clienttranslate('${player_name} pushes a piece'), array(
            "player_name" => $this->game->getActivePlayerName(),
            "movedPiece" => $playerPiece->id,
            "hex_column" => $hexColumn,
            "hex_row" => $hexRow,
            "ballMoved" => $ballMoved,
            "spentMorale"=> 0
        ));

        $activePlayer = \intval($this->game->getActivePlayerId());
        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayer);
    }

    function zombie(int $playerId): string
    {
        $hexes = $this->getArgs()["pushHexes"];

        if (empty($hexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($hexes);

        return $this->actTargetSelected($hex['hex_column'], $hex['hex_row']);
    }
}