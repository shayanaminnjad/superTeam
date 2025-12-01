<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;

class PieceSelection extends PlayerBaseState
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

        return $this->game->getSelectablePieces(null);
    }

    #[PossibleAction]
    public function actCancel(): string
    {
        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actPieceSelected(int $pieceId) : string
    {
        $selectablePieces = $this->game->getSelectablePieces(null);

        if (!in_array($pieceId, $selectablePieces['selectablePieces'], true) &&
            !in_array($pieceId, $selectablePieces['selectableExhaustedPieces'], true))
        {
            throw new \BgaUserException("Piece Id {$pieceId} is not selectable.");
        }

        $this->game->setGameStateValue('selected_piece', $pieceId);

        return MoveAction::class;
    }

    function zombie(int $playerId): string {
        // the code to run when the player is a Zombie
        return '';
    }
}
