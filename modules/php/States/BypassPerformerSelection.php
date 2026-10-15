<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BypassLogic;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;

class BypassPerformerSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_BYPASS_PERFORMER_SELECTION_STATE,
            name: 'bypassPerformerSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} must choose a player for bypass'),
            descriptionMyTurn: clienttranslate('you must choose a player for bypass'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }
    public function selfGetArgs(): array
    {
        $board = $this->game->getBoard();
        $playerId = intval($this->game->getActivePlayerId());

        $pieceThatCanBypass = BypassLogic::getPiecesThatCanBypass($playerId, $board);

        $hexesWithBypassingPieces = [];
        foreach ($pieceThatCanBypass  as $piece)
        {
            $hexesWithBypassingPieces[] = $piece->getPosition()->convertToArray();
        }

        return ["pieces" => $hexesWithBypassingPieces];
    }


    #[PossibleAction]
    public function actPieceSelected(int $hexColumn, int $hexRow): string
    {
        $hexesWithBypassingPiecesAsArray = self::getArgs()["pieces"];
        $selectedHex = new HexID($hexColumn, $hexRow);

        if (!array_any($hexesWithBypassingPiecesAsArray, fn($v) => HexID::fromArray($v)->equals($selectedHex)))
            throw new \BgaUserException(\clienttranslate("selected invalid piece id for tackling"));

        $board = $this->game->getBoard();
        $playerPiece = null;
        $board->tryGetPlayerPieceInHex($selectedHex, $playerPiece);
        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePieces([$playerPiece]);

        return BypassTargetSelection::class;
    }

    function zombie(int $playerId): string
    {
        $hexes = $this->getArgs()["pieces"];

        if (empty($hexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($hexes);

        return $this->actPieceSelected($hex['hex_column'], $hex['hex_row']);
    }
}