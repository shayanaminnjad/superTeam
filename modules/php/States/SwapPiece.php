<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\NotificationAdapter;
use RuntimeException;

class SwapPiece extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_SWAP_PIECE,
            name: 'swapPiece',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} must select a piece to swap'),
            descriptionMyTurn: clienttranslate('${you} must select a piece to swap'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePieces = $pieceSelectionContext->getSelectablePieceIds();

        if (count($selectablePieces) != 1)
            throw new RuntimeException("there must be exactly one piece in pieceToSwapSelection");

        $board = $this->game->getBoard();

        $piecePosition = $board->getPiece($selectablePieces[0])->getPosition();

        $activePlayer = intval($this->game->getActivePlayerId());

        $adjacentPieces = BoardEvaluator::getAdjacentOpponentPieces($board, $piecePosition, $activePlayer, false);

        $hexesWithPiecesToSwap = [];
        foreach ($adjacentPieces as $piece)
        {
            $hexesWithPiecesToSwap[] = $piece->getPosition()->convertToArray();
        }

        if (DBAdapter::getInstance()->getGlobalBool('forfeitable_captain_action'))
        {
            $this->addClientButton(\clienttranslate("Forfeit"), [$this, "actForfeit"], [],
                new ClientButtonParams(ClientButtonColor::ALERT));
        }

        return ["piecesToSwap" => $hexesWithPiecesToSwap];
    }

    #[PossibleAction]
    public function actPieceSelected(int $hexColumn, int $hexRow): string
    {
        $hexesWithTacklingPiecesAsArray = self::getArgs()["piecesToSwap"];

        $hexes = [];
        foreach ($hexesWithTacklingPiecesAsArray as $hexAsArray)
        {
            $hexes[] = HexID::fromArray($hexAsArray);
        }

        $selectedHex = new HexID($hexColumn, $hexRow);

        if (!array_any($hexes, fn($v) => $selectedHex->equals($v)))
            throw new \BgaUserException(\clienttranslate("selected invalid piece id for swapping"));

        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePieces = $pieceSelectionContext->getSelectablePieceIds();

        if (count($selectablePieces) != 1)
            throw new RuntimeException("there must be exactly one piece in pieceToSwapSelection");

        $board = $this->game->getBoard();
        $piecePosition = $board->getPiece($selectablePieces[0])->getPosition();
        $board->swapHexesContent($selectedHex, $piecePosition , true);

        NotificationAdapter::getInstance()->notifyAll("swapPerformed", '',
            [
                "swappedHexes" => [$selectedHex->convertToArray(), $piecePosition->convertToArray()],
                "ballPosition" => $board->getBallHex()->convertToArray()
            ]);

        $activePlayer = intval($this->game->getActivePlayerId());
        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayer);
    }

    #[PossibleAction]
    public function actForfeit(int $activePlayerId): string
    {
        if (!DBAdapter::getInstance()->getGlobalBool('forfeitable_captain_action'))
            throw new \BgaUserException(\clienttranslate("Forfeit is not possible!"));

        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }

    function zombie(int $playerId): string
    {
        $hexes = $this->getArgs()["piecesToSwap"];

        if (empty($hexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($hexes);

        return $this->actPieceSelected($hex['hex_column'], $hex['hex_row']);
    }
}