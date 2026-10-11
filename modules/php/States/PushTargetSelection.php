<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BoardSpatial;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;

class PushTargetSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PUSH_TARGET_SELECTION,
            name: 'pushTargetSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is choosing push target'),
            descriptionMyTurn: clienttranslate('you must choose push target'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }
    public function selfGetArgs(): array
    {
        $board = $this->game->getBoard();
        $activePlayer = \intval($this->game->getActivePlayerId());

        $adjacentOpponents = BoardEvaluator::getAdjacentOpponentPieces($board, $board->getBallHex(), $activePlayer, false);
        $pushablePiecesArray = [];
        foreach ($adjacentOpponents as $opponent)
        {
            if (count (BoardSpatial::findImmediateMoveableNeighbours($board, $opponent)) > 0)
                $pushablePiecesArray[] = $opponent->getPosition()->convertToArray();
        }

        return ["pushablePieces" => $pushablePiecesArray];
    }

    #[PossibleAction]
    public function actTargetSelected(int $hexColumn, int $hexRow): string
    {
        $board = $this->game->getBoard();
        $activePlayer = \intval($this->game->getActivePlayerId());
        $selectedHex = new HexID($hexColumn, $hexRow);

        $opponentPiece = null;
        if (!$board->tryGetPlayerPieceInHex($selectedHex, $opponentPiece) || $opponentPiece->getOwner() === $activePlayer)
            throw new \BgaUserException(clienttranslate("Invalid piece selected for push!"));

        DBAdapter::getInstance()->storeSelectedHex($selectedHex);

        return PushDestinationSelection::class;
    }

    function zombie(int $playerId): string
    {
        $hexes = $this->getArgs()["pushablePieces"];

        if (empty($hexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($hexes);

        return $this->actTargetSelected($hex['hex_column'], $hex['hex_row']);
    }
}