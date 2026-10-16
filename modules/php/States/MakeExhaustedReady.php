<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;

class MakeExhaustedReady extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_READY_EXHAUSTED_PLAYER,
            name: 'readyExhaustedPlayer',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is selecting a piece to refresh'),
            descriptionMyTurn: clienttranslate('${you} must select a piece to refresh'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $board = $this->game->getBoard();
        $playerId = $this->game->GetActivePlayerId();

        $exhaustedPieces = BoardEvaluator::getExhaustedPlayerPieces($board, $playerId, false);

        $pieceLocations = [];
        foreach($exhaustedPieces as $piece)
        {
            $pieceLocations[] = $piece->getPosition()->convertToArray();
        }

        return ['exhaustedPieces' => $pieceLocations];
    }

    #[PossibleAction]
    public function actTargetSelected(int $hexColumn, int $hexRow): string
    {
        $exhaustedPiecesPosition = $this->getArgs()["exhaustedPieces"];

        $selectedHex = new HexID($hexColumn, $hexRow);
        if (!array_any($exhaustedPiecesPosition, fn($posArray) => HexID::fromArray($posArray)->equals($selectedHex)))
            throw new \BgaUserException(clienttranslate("invalid piece selected!"));

        $playerPiece = null;
        $this->game->getBoard()->tryGetPlayerPieceInHex($selectedHex, $playerPiece);

        $playerPiece->setFreshness(true, true);

        return BeforeCardSelectionReset::class;
    }

    function zombie(int $playerId): string
    {
        $hexes = $this->getArgs()["exhaustedPieces"];

        if (empty($hexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($hexes);

        return $this->actTargetSelected($hex['hex_column'], $hex['hex_row']);
    }
}