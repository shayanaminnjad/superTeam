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
            descriptionMyTurn: clienttranslate('${you} must move the selected piece'),
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
            throw new \BgaUserException(\clienttranslate("Invalid piece selected!"));

        $maxPossibleMovement = BoardEvaluator::getPieceMaxPossibleMovement($board, $activePlayerId, $pieceData, $selectedPiece->getPath(), new PlayerResources($activePlayerId));

        if ($maxPossibleMovement == 0)
            throw new \BgaUserException(\clienttranslate("piece can't be moved!"));

        $moveActionContext = new MoveActionContext();
        $moveType = $moveActionContext->getCurrentMoveType();

        $movesTaken = intval($this->game->getGameStateValue('number_of_moves'));

        $moveableHexes = MoveHexSelectionLogic::GetAvailableHexes($board, $pieceData->getId(), $moveType);

        $hexesToSend = [];
        foreach ($moveableHexes as $hex)
        {
            $hexesToSend[] = $hex->convertToArray();
        }

        if ($movesTaken == 0 && DBAdapter::getInstance()->getGlobalBool('forfeitable_captain_action'))
        {
            $this->addClientButton(\clienttranslate("Forfeit"), [$this, "actForfeit"], [], new ClientButtonParams(ClientButtonColor::ALERT));
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
        $destination = new HexID($hexColumn, $hexRow);
        $moveActionContext = new MoveActionContext();
        $board = $this->game->getBoard();

        $moveableHexes = MoveHexSelectionLogic::GetAvailableHexes($board, $moveActionContext->getSelectedPieceData()->id, $moveActionContext->getCurrentMoveType());
        if (!array_any($moveableHexes, fn(HexID $hex) => $hex->equals($destination)))
            throw new \BgaUserException(\clienttranslate("The selected hex is not reachable!"));

        $moveActionContext->addSelectedPieceMove($destination);
        $selectedPiece = $moveActionContext->getSelectedPieceData();

        $ballLocation = $board->getBallHex();
        $pieceOldData = $board->getPlayerPieceData($selectedPiece->id);
        // movePieceToHex updates $pieceOldData in place
        $oldPosition = $pieceOldData->getPosition();

        $ballMoved = 0;

        if ($ballLocation->equals($oldPosition))
        {
            $ballMoved = 1;
            $board->setBallLocation(new HexID($hexColumn, $hexRow));
        }

        $oldNumberOfMoves = intval($this->game->getGameStateValue('number_of_moves'));

        $board->movePieceToHex($selectedPiece->id, new HexID($hexColumn, $hexRow));
        $this->game->setGameStateValue('number_of_moves', $oldNumberOfMoves + 1);

        // no near opponent clamp here: a ball picked up next to an opponent must not end the move,
        // the carried ball case is handled by the $ballMoved check below
        $playerPowers = PlayerPowers::forPlayer($activePlayerId);
        $maxMovement = $playerPowers->maxMovement($pieceOldData, BoardEvaluator::doesPieceHasTheBall($board, $pieceOldData),
            $selectedPiece->getPath(), new PlayerResources($activePlayerId)->getMorale());
        $canMoveNearOpponents = $playerPowers->canBallCarrierMoveNearOpponents($pieceOldData);

        $shouldEndMovement = false;

        if ($oldNumberOfMoves + 1 >= $maxMovement)
        {
            $shouldEndMovement = true;
        }

        $spentMorale = 0;
        if(!$pieceOldData->isFresh() && $oldNumberOfMoves === 0) //player has moved an exhausted piece.
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
            (BoardEvaluator::doesOpponentExistInNeighbours($board, $oldPosition, \intval($this->game->getActivePlayerId()), true) ||
            BoardEvaluator::doesOpponentExistInNeighbours($board, new HexID($hexColumn, $hexRow), \intval($this->game->getActivePlayerId()), true)))
        {
            $shouldEndMovement = true;
        }

        $this->notify->all("movedPiece", "", array(
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
        $movesTaken = intval($this->game->getGameStateValue('number_of_moves'));
        if ($movesTaken > 0 && $this->isMoveConditionMet($activePlayerId, $board, $moveActionContext))
            return MoveFinished::class;
        else
            throw new \BgaUserException(\clienttranslate("You can't finish movement while you haven't met movement conditions and haven't moved at least once."));
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
