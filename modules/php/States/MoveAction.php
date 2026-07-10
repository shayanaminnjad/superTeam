<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BoardSpatial;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
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
        $activePlayerId = \intval($this->game->getActivePlayerId());

        $selectedPiece = intval($this->game->getGameStateValue('selected_piece'));

        assert($selectedPiece != -1, "move action must have a valid piece selected, before it gets called!");

        $board = $this->game->getBoard();
        $pieceData = $board->getPlayerPieceData($selectedPiece);

        if ($pieceData === null)
            throw new \BgaUserException("Invalid piece selected!");

        $maxPossibleMovement = BoardEvaluator::getPieceMaxPossibleMovement($board, $activePlayerId, $pieceData, new PlayerResources($activePlayerId));

        if ($maxPossibleMovement == 0)
            throw new \BgaUserException("piece can't be moved!");

        $moveActionContext = new MoveActionContext();
        $moveType = $moveActionContext->getCurrentMoveType();

        $pieceHasTheBall = ($board->getBallHex()->equals($pieceData->getPosition()));
        $moveableHexes = BoardSpatial::getMoveableHexes($board, $pieceData->getPosition(), 1, $pieceHasTheBall, $activePlayerId);

        $hexesToSend = [];
        foreach ($moveableHexes as $hex)
        {
            $hexesToSend[] = $hex->convertToArray();
        }

        $numberOfMoves = intval($this->game->getGameStateValue('number_of_moves'));

        if ($numberOfMoves > 0)
            $this->addClientButton('Finish Movement', [$this, 'actFinishMove']);

        return ["moveableHexes" => $hexesToSend,
                "selectedPiece" => $selectedPiece];
    }

    #[PossibleAction]
    public function actPieceMoved(int $hexColumn, int $hexRow, int $activePlayerId) : string
    {
        //TODO: check if the moved location is valid.

        $selectedPiece = intval($this->game->getGameStateValue('selected_piece'));
        
        $board = $this->game->getBoard();
        $ballLocation = $board->getBallHex();
        $pieceOldData = $board->getPlayerPieceData($selectedPiece);

        $ballMoved = 0;

        if ($ballLocation->equals($pieceOldData->getPosition()))
        {
            $ballMoved = 1;
            $board->setBallLocation(new HexID($hexColumn, $hexRow));
        }

        $numberOfMoves = intval($this->game->getGameStateValue('number_of_moves'));

        $shouldEndMovement = false;

        if ($numberOfMoves === 1)
        {
            $shouldEndMovement = true;
        }

        $spentMorale = 0;
        if(!$pieceOldData->isFresh() && $numberOfMoves === 0) //player has moved an exhausted piece.
        {
            $playerResources = new PlayerResources($activePlayerId);
            $playerResources->addMorale(-1, false);

            $spentMorale = 1;
            $shouldEndMovement = true;
        }

        if ($ballMoved === 1 &&
            (BoardEvaluator::doesOpponentExistInNeighbours($board, $pieceOldData->getPosition(), \intval($this->game->getActivePlayerId()), true) ||
            BoardEvaluator::doesOpponentExistInNeighbours($board, new HexID($hexColumn, $hexRow), \intval($this->game->getActivePlayerId()), true)))
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
