<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\UndoPlayedCard;

class CaptainCardPerformerSelection extends PlayerBaseState
{
    use UndoPlayedCard;

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_CAPTAIN_CARD_PERFORMER_SELECTION,
            name: 'captainCardPerformerSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} must select a piece to perform captain card'),
            descriptionMyTurn: clienttranslate('${you} must select a piece to perform captain card'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePieces = $pieceSelectionContext->getSelectablePieceIds();

        $this->addUndoButtonIfPlayedCardUndoable();

        return ["piecesToSelect" => $selectablePieces];
    }

    #[PossibleAction]
    public function actPieceSelected(int $pieceId)
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePieces = $pieceSelectionContext->getSelectablePieceIds();

        if (!array_any($selectablePieces, fn($piece) => $piece == $pieceId))
            throw new \BgaUserException(\clienttranslate("Invalid piece selected"));

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePiecesIds([$pieceId]);

        return TacticalTurnStateManager::class;
    }

    function zombie(int $playerId): string
    {
        $selectablePieces = (new PieceSelectionContext())->getSelectablePieceIds();

        if (empty($selectablePieces))
            return BeforeCardSelectionReset::class;

        return $this->actPieceSelected($this->getRandomZombieChoice($selectablePieces));
    }
}