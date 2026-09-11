<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
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
        $pieceSelectionContext = new PieceSelectionContext();
        $moveablePiecesIds = $pieceSelectionContext->getSelectablePieceIds();

        $board = $this->game->getBoard();

        $freshPieceIDs = [];
        $exhaustedSelectablePieceIds = [];

        foreach ($moveablePiecesIds as $moveablePieceId)
        {
            $pieceData = $board->getPlayerPieceData($moveablePieceId);

            if ($pieceData->isFresh())
                $freshPieceIDs[] = $pieceData->getId();
            else
                $exhaustedSelectablePieceIds[] = $pieceData->getId();
        }

        $moveActionContext = new MoveActionContext();

        return [
            'selectablePieces' => $freshPieceIDs,
            'selectableExhaustedPieces' => $exhaustedSelectablePieceIds,
            'showMorale' => $moveActionContext->doExhaustedNeedMoraleForMovement()
        ];
    }

    #[PossibleAction]
    public function actCancel(int $activePlayerId): string
    {
        //todo: if we want to support cancel, and if this is a multi move, then we must update moves that has been done!
        //probably a better architecture here is to late commit that move has been done.
        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }

    #[PossibleAction]
    public function actPieceSelected(int $pieceId) : string
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $moveablePiecesIds = $pieceSelectionContext->getSelectablePieceIds();

        if (!in_array($pieceId, $moveablePiecesIds, true))
            throw new \BgaUserException("Piece Id {$pieceId} is not selectable.");

        $board = $this->game->getBoard();
        $moveActionContext = new MoveActionContext();
        $moveActionContext->setSelectedPieceData($board->getPlayerPieceData($pieceId));

        return MoveAction::class;
    }

    function zombie(int $playerId): string
    {
        return $this->actCancel();
    }
}
