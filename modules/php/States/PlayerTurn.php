<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;

class PlayerTurn extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            name: 'playerTurn',
            id: ST_PLAYER_TURN,
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} must choose an action'),
            descriptionMyTurn: clienttranslate('${you} must choose an action'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $pieceSelectableForMove = $this->game->getSelectablePieces(null);

        if (count($pieceSelectableForMove['selectablePieces']) > 0 || count($pieceSelectableForMove['selectableExhaustedPieces']) > 0) 
        {
            $this->addClientButton('move', [$this, 'actMove']);
        }

        $board = $this->game->getBoard();
        $playerPieceWithBall = null;
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPieceWithBall)) 
        {
            if ($playerPieceWithBall->getOwner() === intval($this->game->getActivePlayerId())) 
            {
                $this->addClientButton('pass ball', [$this, 'actPassBall']);
            }
        }

        return [];
    }

    #[PossibleAction]
    public function actMove(): string
    {
        return PieceSelection::class;
    }

    #[PossibleAction]
    public function actPassBall() : string
    {
        return PassDestSelection::class;
    }

    #[PossibleAction]
    public function actDebugAddMorale(int $amount)
    {
        if ($amount >= 0) {
            $this->game->setCurrentPlayerMorale($amount, true);
        }
    }

    #[PossibleAction]
    public function actDebugMoveBall(int $column, int $row)
    {
        $selectedHex = new HexID($column, $row);
        $this->game->getBoard()->setBallLocation($selectedHex);

        $this->notify->all('successfulPass', "successful pass", [
            "ballHex" => $selectedHex->convertToArray()
        ]);
    }


    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie
        return '';
    }
}
