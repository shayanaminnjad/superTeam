<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;

class MovePieceSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            name: "pieceSelection",
            id: ST_PIECE_SELECTION,
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is choosing a piece'),
            descriptionMyTurn: clienttranslate('${you} must choose a piece'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $this->addClientButton('cancel', [$this, 'actCancel']);

        $moveActionContext = new MoveActionContext();
        $moveablePiecesIds = $moveActionContext->getMoveablePiecesIds();

        $board = $this->game->getBoard();

        $freshPieceIDs = [];
        $exhaustedSelectablePieceIds = [];
        foreach($moveablePiecesIds as $moveablePieceId)
        {
            $pieceData = $board->getPlayerPieceData($moveablePieceId);

            if ($pieceData->isFresh())
                $freshPieceIDs[] = $pieceData->getId();
            else
                $exhaustedSelectablePieceIds[] = $pieceData->getId();
        }

        return [
            'selectablePieces' => $freshPieceIDs,
            'selectableExhaustedPieces' => $exhaustedSelectablePieceIds
        ];
    }

    #[PossibleAction]
    public function actCancel(int $activePlayerId): string
    {
        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }

    #[PossibleAction]
    public function actPieceSelected(int $pieceId) : string
    {
        $moveActionContext = new MoveActionContext();
        $moveablePiecesIds = $moveActionContext->getMoveablePiecesIds();

        if (!in_array($pieceId, $moveablePiecesIds, true))
            throw new \BgaUserException("Piece Id {$pieceId} is not selectable.");

        $moveContext = new MoveActionContext();
        $moveContext->addPieceMoved($pieceId);

        $this->game->setGameStateValue('selected_piece', $pieceId);

        return MoveAction::class;
    }

    function zombie(int $playerId): string
    {
        return $this->actCancel();
    }
}
