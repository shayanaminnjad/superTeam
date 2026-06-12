<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;

class MoveAction extends PlayerBaseState
{
    function __construct(
        Game $game,
    ) {
        parent::__construct(
            game: $game,
            name: 'moveAction',
            id: ST_MOVE_ACTION,
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is moving a piece'),
            descriptionMyTurn: clienttranslate('move the selected piece'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $selectedPiece = intval($this->game->getGameStateValue('selected_piece'));

        assert($selectedPiece != -1, "move action must have a valid piece selected, before it gets called!");

        $moveableHexes = $this->game->getBoard()->getMoveableHexesByPiece($selectedPiece);

        $numberOfMoves = intval($this->game->getGameStateValue('number_of_moves'));

        if ($numberOfMoves > 0)
            $this->addClientButton('Finish Movement', [$this, 'actFinishMove']);

        return ["moveableHexes" => $moveableHexes,
                "selectedPiece" => $selectedPiece];
    }

    #[PossibleAction]
    public function actPieceMoved(int $hexColumn, int $hexRow, int $activePlayerId) : string
    {
        //TODO: check if the moved location is valid.

        $selectedPiece = intval($this->game->getGameStateValue('selected_piece'));
        
        $board = $this->game->getBoard();
        $ballLocation = $board->getBallHex();
        $pieceOldData = $this->game->getObjectFromDB("SELECT `hex_column`, `hex_row`, is_fresh FROM `player_pieces` WHERE `id` = $selectedPiece");
        $oldPieceHexRow = intval($pieceOldData['hex_row']);
        $oldPieceHexCol = intval($pieceOldData['hex_column']);

        $ballMoved = 0;

        if ($ballLocation->getColumn() === $oldPieceHexCol && $ballLocation->getRow() === $oldPieceHexRow)
        {
            $ballMoved = 1;
            $board->setBallLocation(new HexID($hexColumn, $hexRow));
            //TODO: this will probably change attacker and defender (I am not sure however if it happens right now?)
        }

        $isFresh = boolval($pieceOldData['is_fresh']);
        $numberOfMoves = intval($this->game->getGameStateValue('number_of_moves'));

        $shouldEndMovement = false;

        if ($numberOfMoves === 1)
        {
            $shouldEndMovement = true;
        }

        $spentMorale = 0;
        if(!$isFresh && $numberOfMoves === 0) //player has moved an exhusted piece.
        {
            $playerResources = new PlayerResources($activePlayerId);
            $playerResources->addMorale(-1, false);

            $spentMorale = 1;
            $shouldEndMovement = true;
        }


        if ($ballMoved === 1 &&
            ($board->doesOpponentExistInNeighbours(new HexID($oldPieceHexCol, $oldPieceHexRow), \intval($this->game->getActivePlayerId()), true) ||
            $board->doesOpponentExistInNeighbours(new HexID($hexColumn, $hexRow), \intval($this->game->getActivePlayerId()), true)))
        {
            $shouldEndMovement = true;
        }

        $board->movePieceToHex($selectedPiece, new HexID($hexColumn, $hexRow));
        $this->game->setGameStateValue('number_of_moves', $numberOfMoves + 1);

        $this->notify->all("movedPiece", \clienttranslate('${player_name} moves a piece'), array(
            "player_name" => $this->game->getActivePlayerName(),
            "movedPiece" => $selectedPiece,
            "hex_column" => $hexColumn,
            "hex_row" => $hexRow,
            "ballMoved" => $ballMoved,
            "spentMorale"=> $spentMorale
        ));

        if ($shouldEndMovement)
        {
            return MoveFinished::class;
        }
        else
        {
            return self::class;
        }
    }

    #[PossibleAction]
    public function actFinishMove(): string
    {
        return MoveFinished::class;
    }

    function zombie(int $playerId): string {
        // the code to run when the player is a Zombie
        return '';
    }
}
