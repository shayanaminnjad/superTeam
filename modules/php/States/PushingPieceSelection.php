<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;

class PushingPieceSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PUSHING_PIECE_SELECTION,
            name: 'pushingPieceSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is choosing a piece for push'),
            descriptionMyTurn: clienttranslate('${you} must choose a piece for push'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function onEnteringState()
    {
        //in extremely rare cases there might not be a valid dest to push the piece to!
        //this will result in a blink in front, however it is almost impossible to happen!
        if (!$this->getPiecesThatCanPush())
        {
            $activePlayer = \intval($this->game->getActivePlayerId());
            return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayer);
        }
    }

    public function selfGetArgs(): array
    {
        $piecesHexArray = $this->getPiecesThatCanPush();

        return ["pieces" => $piecesHexArray];
    }

    #[PossibleAction]
    public function actTargetSelected(int $hexColumn, int $hexRow): string
    {
        $board = $this->game->getBoard();
        $selectedHex = new HexID($hexColumn, $hexRow);

        $hexesArray = $this->getArgs()["pieces"];
        if (!array_any($hexesArray, fn($hex) => HexID::fromArray($hex)->equals($selectedHex)))
            throw new \BgaUserException(clienttranslate("Invalid piece selected for push!"));

        $selectedPiece = null;
        $board->tryGetPlayerPieceInHex($selectedHex, $selectedPiece);
        //push only happens through captain cards, so this is ok to be here.
        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePiecesIds([$selectedPiece->getId()]);

        DBAdapter::getInstance()->storeSelectedHex($selectedHex);

        return PushTargetSelection::class;
    }

    function zombie(int $playerId): string
    {
        $hexes = $this->getArgs()["pieces"];

        if (empty($hexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($hexes);

        return $this->actTargetSelected($hex['hex_column'], $hex['hex_row']);
    }

    /**
     * @return HexID[]
     */
    private function getPiecesThatCanPush() : array
    {
        $board = $this->game->getBoard();
        $activePlayer = \intval($this->game->getActivePlayerId());

        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePieces = $pieceSelectionContext->getSelectablePieceIds();

        $piecesAdjacentToOpponents = BoardEvaluator::getPlayerPiecesAdjacentToOpponent($board, $activePlayer, false);
        $piecesHexArray = [];
        foreach ($piecesAdjacentToOpponents as $playerPiece)
        {
            if (empty($selectablePieces) || array_any($selectablePieces, fn($piece) => $piece === $playerPiece->getId()))
            {
                if (count(BoardEvaluator::getPushableOpponentPieces($board, $playerPiece, $activePlayer)) > 0)
                {
                    $piecesHexArray[] = $playerPiece->getPosition()->convertToArray();
                }
            }
        }

        return $piecesHexArray;
    }
}