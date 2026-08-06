<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\Game;
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
        $tacticContext = new TacticContext();
        $tacticContext->reset();

        $moveContext = new MoveActionContext();
        $moveContext->reset();

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->reset();

        $passActionContext = new PassActionContext($activePlayerId);
        $passActionContext->reset(false);

        RequiredPower::setSkipEnabled(false);

        return CardSelection::class;
    }
}