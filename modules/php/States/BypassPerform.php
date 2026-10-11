<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BypassLogic;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\NotificationAdapter;

class BypassPerform extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_BYPASS_PERFORM,
            name: "BypassPerform",
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId): string
    {
        $board = $this->game->getBoard();

        $attackerRollRes = DiceRollServices::getRollResult($board->getAttacker());
        $defenderRollRes = DiceRollServices::getRollResult($board->getDefender());

        if ($attackerRollRes->getTotal() >= $defenderRollRes->getTotal())
        {
            $board = $this->game->getBoard();

            $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();
            $playerInHex = null;

            if (!$board->tryGetPlayerPieceInHex($selectedHex, $playerInHex))
                throw new \BgaUserException(\clienttranslate("no player in ball hex!"));

            if ($playerInHex->getOwner() === $activePlayerId)
                throw new \BgaUserException(\clienttranslate("you cannot by pass your own piece!"));

            $destinationHex = BypassLogic::getByPassDest($playerInHex, $board->getBallHex());

            if ($board->hasPlayerInHex($destinationHex))
                throw new \BgaUserException(\clienttranslate("Impossible bypass! destination hex is blocked by another piece!"));

            $activePlayerPiece = null;
            $board->tryGetPlayerPieceInHex($board->getBallHex(), $activePlayerPiece);

            if ($activePlayerPiece === null)
                throw new \BgaUserException(\clienttranslate("Impossible bypass! you don't have the ball"));

            $board->movePieceToHex($activePlayerPiece->getId(), $destinationHex);
            $board->setBallLocation($destinationHex);

            $this->game->notify->all("bypassPerformed", \clienttranslate('${player_name} bypassed successfully'), [
                "movedPiece" => $activePlayerPiece->getId(),
                "destHex" => $destinationHex->convertToArray(),
                "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId)
            ]);
        }
        else
        {
            $this->game->notify->all("bypassFailed", \clienttranslate('${player_name}\'s bypass failed'), [
                "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId)
            ]);
        }

        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }
}