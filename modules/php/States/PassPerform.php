<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BoardInfo;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\GameStats;
use Bga\Games\SuperTeam\MoraleAssignContext;
use Bga\Games\SuperTeam\RequiredPower;
use Bga\Games\SuperTeam\StatAction;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\NotificationAdapter;

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

        $isPassSuccessful = $diceRes->getTotal() >= $powerRequired;
        GameStats::recordDiceRoll($this->game, $activePlayerId, $diceRes);
        GameStats::recordAction($this->game, $activePlayerId, StatAction::Pass, $isPassSuccessful);

        if ($isPassSuccessful)
        {
            DBAdapter::getInstance()->setGameStateValue('lastPassResult', 1);

            $board->setBallLocation($selectedHex);
            $this->notify->all('successfulPass', \clienttranslate('${player_name} passed successfully.'), [
                "ballHex" => $selectedHex->convertToArray(),
                "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId),
            ]);


            if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPiece))
            {
                $passActionContext->storeLastPassReceiverPieceData($playerPiece);
            }
            else
            {
                $passActionContext->invalidateLastPassReceiverPieceData();
            }
        }
        else
        {
            DBAdapter::getInstance()->setGameStateValue('lastPassResult', 0);

            $newLocation = $board->getClosetBlockingPieceOrHalfWay($board->getBallHex(), $selectedHex, $this->game->getInactivePlayerId());

            $newLocation = BoardEvaluator::correctPositionIfInsideGoalieArea($board, $newLocation);

            $board->setBallLocation($newLocation);
            NotificationAdapter::getInstance()->notifyAll('failedPass', \clienttranslate('${player_name}\'s pass failed'),
                [
                    "ballHex" => $newLocation->convertToArray(),
                    "player_name" => NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId)
            ]);

            $passActionContext->invalidateLastPassReceiverPieceData();
        }

        RequiredPower::setSkipEnabled(false);

        $opponent_player_id = (int) $this->game->getPlayerAfter($activePlayerId);
        $moraleAssignContext = new MoraleAssignContext();
        $moraleAssignContext->reset([$activePlayerId, $opponent_player_id]);

        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }
}
