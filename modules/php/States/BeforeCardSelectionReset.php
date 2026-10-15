<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\MoraleAssignContext;
use Bga\Games\SuperTeam\PassTargetSelectionLogic;
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\RequiredPower;

class BeforeCardSelectionReset extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_BEFORE_CARD_SELECTION_RESET,
            name: 'beforeCardSelectionReset',
            type: StateType::GAME,
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function onEnteringState(int $activePlayerId) : string
    {
        if (CardSelection::getWasLastActionUndone() === false)
            $this->game->giveExtraTime($activePlayerId);

        CardSelection::setWasLastActionUndone(false);

        $tacticContext = new TacticContext();
        $tacticContext->reset();

        $moveContext = new MoveActionContext();
        $moveContext->reset();

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->reset();

        $passActionContext = new PassActionContext($activePlayerId);
        $passActionContext->reset(false);

        PassTargetSelectionLogic::reset($activePlayerId);

        $board = $this->game->getBoard();
        $lastPassReceiverId = $passActionContext->getLastPassReceiverIdThisTurn();
        if(PlayerPiece::getInvalidPieceId() != $lastPassReceiverId)
        {
            $ballHex = $board->getBallHex();
            $outPlayer = null;
            if ($board->tryGetPlayerPieceInHex($ballHex, $outPlayer))
            {
                if ($outPlayer->getId() != $lastPassReceiverId)
                {
                    $passActionContext->invalidateLastPassReceiverPieceData();
                }
            }
        }

        $this->game->setGameStateValue('number_of_moves', 0);

        DBAdapter::getInstance()->storePlayerTempStr($activePlayerId, 'shootTarget', HexID::GetInvalidId()->toJson());

        RequiredPower::setSkipEnabled(false);

        $moraleAssignContext = new MoraleAssignContext();
        $opponent_player_id = (int) $this->game->getPlayerAfter($activePlayerId);
        $moraleAssignContext->reset([$activePlayerId, $opponent_player_id]);

        DBAdapter::getInstance()->storeGlobalBool('forfeitable_captain_action', false);
        DBAdapter::getInstance()->storeGlobalBool('can_move_in_basic_turn', false);

        return CardSelection::class;
    }
}