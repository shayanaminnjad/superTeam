<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BypassLogic;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\GameStats;
use Bga\Games\SuperTeam\HexID;
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

        if (BypassLogic::doesBypassRequiresDiceRoll())
        {
            $attackerRollRes = DiceRollServices::getRollResult($board->getAttacker());
            $defenderRollRes = DiceRollServices::getRollResult($board->getDefender());

            $isBypassSuccessful = $attackerRollRes->getTotal() >= $defenderRollRes->getTotal();
            GameStats::recordDiceRoll($this->game, $board->getAttacker(), $attackerRollRes);
            GameStats::recordDiceRoll($this->game, $board->getDefender(), $defenderRollRes);

            if ($isBypassSuccessful)
            {
                $this->doBypass($board, $activePlayerId);
            } else
            {
                $this->game->notify->all("bypassFailed", \clienttranslate('${player_name}\'s bypass failed'), [
                    "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId)
                ]);
            }
        }
        else
        {
            $this->doBypass($board, $activePlayerId);
        }

        BypassLogic::setBypassRollRequirement(false);

        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }

    private function doBypass(Board $board, int $activePlayerId) : void
    {
        $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();
        $playerInHex = null;

        $board->tryGetPlayerPieceInHex($selectedHex, $playerInHex);

        $bypassingPieceHex = $this->getBypassingPieceHex($board);
        $destinationHex = BypassLogic::getByPassDest($playerInHex, $bypassingPieceHex);

        $activePlayerPiece = null;
        $board->tryGetPlayerPieceInHex($bypassingPieceHex, $activePlayerPiece);
        $board->movePieceToHex($activePlayerPiece->getId(), $destinationHex);

        $shouldMoveBall = $board->getBallHex()->equals($bypassingPieceHex);
        if ($shouldMoveBall)
        {
            $board->setBallLocation($destinationHex);
        }

        GameStats::recordBypass($this->game, $activePlayerId);

        $this->game->notify->all("bypassPerformed", \clienttranslate('${player_name} bypassed successfully'), [
            "movedPiece" => $activePlayerPiece->getId(),
            "destHex" => $destinationHex->convertToArray(),
            "movedBall" => $shouldMoveBall,
            "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId)
        ]);
    }

    private function getBypassingPieceHex(Board $board) : HexID
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePieceIds = $pieceSelectionContext->getSelectablePieceIds();

        if (count($selectablePieceIds) === 0)
        {
            return $board->getBallHex();
        }
        else //happens with captain
        {
            return $board->getPiece($selectablePieceIds[0])->getPosition();
        }
    }
}