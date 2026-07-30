<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\HexID;

class PassPerform extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PASS_PERFORM,
            name: 'passPerform',
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    function onEnteringState(int $activePlayerId) : string
    {
        $powerRequired = $this->game->getGameStateValue('required_power');
        
        $selectedHex = DBAdapter::getInstance()->getSelectedHexAsHexID();
        
        $diceRes = DiceRollServices::getRollResult($activePlayerId);
        $board = $this->game->getBoard();

        $passActionContext = new PassActionContext($activePlayerId);

        $playerPiece = null;
        $board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPiece);
        assert($playerPiece != null, "Passing piece can't be null!");
        $passActionContext->addPiecesPassed($playerPiece->getId());

        if ($diceRes->getTotal() >= $powerRequired)
        {
            DBAdapter::getInstance()->setGameStateValue('lastPassResult', 1);

            $board->setBallLocation($selectedHex);
            $this->notify->all('successfulPass', "successful pass", [
                "ballHex" => $selectedHex->convertToArray()
            ]);


            if ($board->hasPlayerInHex($board->getBallHex()))
            {
                $passActionContext->storeLastPassReceiverPosThisTurn($selectedHex);
            }
            else
            {
                $passActionContext->storeLastPassReceiverPosThisTurn(HexID::GetInvalidId());
            }
        }
        else
        {
            DBAdapter::getInstance()->setGameStateValue('lastPassResult', 0);

            $newLocation = $board->getClosetBlockingPieceOrHalfWay($board->getBallHex(), $selectedHex, $this->game->getInactivePlayerId());
            $board->setBallLocation($newLocation);
            $this->notify->all('failedPass', "failed pass", [
                "ballHex" => $newLocation->convertToArray()
            ]);

            $passActionContext->storeLastPassReceiverPosThisTurn(HexID::GetInvalidId());
        }

        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }
}
