<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MovedPieceInfo;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\MoveHexSelectionLogic;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\PlayerSide;

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

        $moveActionContext = new MoveActionContext();
        $selectedPiece = $moveActionContext->getSelectedPieceData();

        $board = $this->game->getBoard();
        $pieceData = $board->getPlayerPieceData($selectedPiece->id);

        if ($pieceData === null)
            throw new \BgaUserException("Invalid piece selected!");

        $maxPossibleMovement = BoardEvaluator::getPieceMaxPossibleMovement($board, $activePlayerId, $pieceData, new PlayerResources($activePlayerId));

        if ($maxPossibleMovement == 0)
            throw new \BgaUserException("piece can't be moved!");

        $moveActionContext = new MoveActionContext();
        $moveType = $moveActionContext->getCurrentMoveType();

        $movesTaken = intval($this->game->getGameStateValue('number_of_moves')); //TODO: move this to MoveActionContext class

        $moveableHexes = MoveHexSelectionLogic::GetAvailableHexes($board, $pieceData->getId(), $moveType, $maxPossibleMovement - $movesTaken);

        $hexesToSend = [];
        foreach ($moveableHexes as $hex)
        {
            $hexesToSend[] = $hex->convertToArray();
        }

        if ($movesTaken > 0 && $this->isMoveConditionMet($activePlayerId, $board, $moveActionContext))
            $this->addClientButton(\clienttranslate('Finish Movement'), [$this, 'actFinishMove'], [],
                new ClientButtonParams(ClientButtonColor::ALERT));

        return ["moveableHexes" => $hexesToSend,
                "selectedPiece" => $selectedPiece->id];
    }

    #[PossibleAction]
    public function actPieceMoved(int $hexColumn, int $hexRow, int $activePlayerId) : string
    {
        //TODO: check if the moved location is valid.

        $moveActionContext = new MoveActionContext();
        $selectedPiece = $moveActionContext->getSelectedPieceData();

        $board = $this->game->getBoard();
        $ballLocation = $board->getBallHex();
        $pieceOldData = $board->getPlayerPieceData($selectedPiece->id);

        $maxMovement = BoardEvaluator::getPieceMaxPossibleMovement($board, $activePlayerId, $pieceOldData, new PlayerResources($activePlayerId));
        $canMoveNearOpponents = PlayerPowers::forPlayer($activePlayerId)->canBallCarrierMoveNearOpponents($pieceOldData);

        $ballMoved = 0;

        if ($ballLocation->equals($pieceOldData->getPosition()))
        {
            $ballMoved = 1;
            $board->setBallLocation(new HexID($hexColumn, $hexRow));
        }

        $numberOfMoves = intval($this->game->getGameStateValue('number_of_moves'));

        $shouldEndMovement = false;

        if ($numberOfMoves + 1 >= $maxMovement)
        {
            $shouldEndMovement = true;
        }

        $spentMorale = 0;
        if(!$pieceOldData->isFresh() && $numberOfMoves === 0) //player has moved an exhausted piece.
        {
            $moveActionContext = new MoveActionContext();

            if ($moveActionContext->doExhaustedNeedMoraleForMovement())
            {
                $playerResources = new PlayerResources($activePlayerId);
                $playerResources->addMorale(-1, false);
                $spentMorale = 1;
            }

            $shouldEndMovement = true;
        }

        if ($ballMoved === 1 && !$canMoveNearOpponents &&
            (BoardEvaluator::doesOpponentExistInNeighbours($board, $pieceOldData->getPosition(), \intval($this->game->getActivePlayerId()), true) ||
            BoardEvaluator::doesOpponentExistInNeighbours($board, new HexID($hexColumn, $hexRow), \intval($this->game->getActivePlayerId()), true)))
        {
            $shouldEndMovement = true;
        }

        $board->movePieceToHex($selectedPiece->id, new HexID($hexColumn, $hexRow));
        $this->game->setGameStateValue('number_of_moves', $numberOfMoves + 1);

        $this->notify->all("movedPiece", \clienttranslate('${player_name} moves a piece'), array(
            "player_name" => $this->game->getActivePlayerName(),
            "movedPiece" => $selectedPiece->id,
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
    public function actFinishMove(int $activePlayerId): string
    {
        $board = $this->game->getBoard();
        $moveActionContext = new MoveActionContext();
        $movesTaken = intval($this->game->getGameStateValue('number_of_moves')); //TODO: move this to MoveActionContext class
        if ($movesTaken > 0 && $this->isMoveConditionMet($activePlayerId, $board, $moveActionContext))
            return MoveFinished::class;
        else
            throw new \BgaUserException("You can't finish movement while you haven't met movement conditions and haven't moved at least once.");
    }

    function zombie(int $playerId): string
    {
        if (intval($this->game->getGameStateValue('number_of_moves')) > 0)
            return MoveFinished::class;

        // captain cards read the moved piece after this state, so the zombie must really move
        $moveableHexes = $this->selfGetArgs()['moveableHexes'];
        if (empty($moveableHexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($moveableHexes);

        return $this->actPieceMoved($hex['hex_column'], $hex['hex_row'], $playerId);
    }

    private function isMoveConditionMet(int $playerId, Board $board, MoveActionContext $moveActionContext) : bool
    {
        $moveType = $moveActionContext->getCurrentMoveType();

        if ($moveType == MoveTypes::FREE)
            return true;

        $movedPieceInfo = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerId);
        $piece = $board->getPiece($movedPieceInfo->id);

        if ($moveType == MoveTypes::BACKWARD)
        {
            return $this->hasPieceMovedBackward($board, $movedPieceInfo, $piece, $playerSide);
        }

        if ($moveType == MoveTypes::FORWARD)
        {
            return $this->hasPieceMovedForward($board, $movedPieceInfo, $piece, $playerSide);
        }

        if ($moveType == MoveTypes::BACKWARD_NEAR_OPPONENT)
        {
            return $this->hasPieceMovedBackward($board, $movedPieceInfo, $piece, $playerSide) &&
                BoardEvaluator::hasAdjacentOpponentPieces($board, $piece->getPosition(), $playerId, true);
        }

        if ($moveType == MoveTypes::FORWARD_NEAR_OPPONENT)
        {
            return $this->hasPieceMovedForward($board, $movedPieceInfo, $piece, $playerSide) &&
                BoardEvaluator::hasAdjacentOpponentPieces($board, $piece->getPosition(), $playerId, true);
        }

        return false;
    }

    private function hasPieceMovedForward(Board $board, MovedPieceInfo $movedPieceInfo, PlayerPiece $piece,PlayerSide $playerSide) : bool
    {
        if ($playerSide == PlayerSide::Left)
        {
            return $movedPieceInfo->initialPosition->getColumn() < $piece->getPosition()->getColumn();
        }
        else
        {
            return $movedPieceInfo->initialPosition->getColumn() > $piece->getPosition()->getColumn();
        }
    }

    private function hasPieceMovedBackward(Board $board, MovedPieceInfo $movedPieceInfo, PlayerPiece $piece, PlayerSide $playerSide) : bool
    {
        if ($playerSide == PlayerSide::Left)
        {
            return $movedPieceInfo->initialPosition->getColumn() > $piece->getPosition()->getColumn();
        }
        else
        {
            return $movedPieceInfo->initialPosition->getColumn() < $piece->getPosition()->getColumn();
        }
    }
}
