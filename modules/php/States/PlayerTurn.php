<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;

enum GameActions: int
{
    case Unknown = 0;
    case Move = 1;
    case FinishMove = 2;
    case PassBall = 3;
    case Cancel = 4;
    case Confirm = 5;
}

class PlayerTurn extends GameState
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

    public function getArgs(): array
    {
        //TODO: each of these actions must be an object, with predicate, and name and id.

        $possibleActions = [];

        $pieceSelectableForMove = $this->game->getSelectablePieces(null);

        if (count($pieceSelectableForMove['selectablePieces']) > 0 || count($pieceSelectableForMove['selectableExhaustedPieces']) > 0) {
            $possibleActions[] = ['name' => 'move', 'id' => GameActions::Move];
        }

        $board = $this->game->getBoard();
        $playerPieceWithBall = null;
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPieceWithBall)) {
            if ($playerPieceWithBall->getOwner() === intval($this->game->getActivePlayerId())) {
                $possibleActions[] = ['name' => 'pass ball', 'id' => GameActions::PassBall];
            }
        }

        return ['possibleActions' => $possibleActions];
    }

    #[PossibleAction]
    public function actActionSelected(int $actionId): string
    {
        // TODO: a list of handlers is more proper, however it is ok right now.
        switch ($actionId) {
            case GameActions::Move->value:
                return PieceSelection::class;
            case GameActions::PassBall->value:
                return PassDestSelection::class;
            default:
                throw new \BgaUserException("invalid id passed");
        }
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
