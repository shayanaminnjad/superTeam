<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;
use function count;
use function intval;

class PlayerBasicTurn extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            name: 'playerBasicTurn',
            id: ST_PLAYER_BASIC_TURN,
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} must choose an action'),
            descriptionMyTurn: clienttranslate('${you} must choose an action'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePiecesIds = $pieceSelectionContext->getSelectablePieceIds();

        if (DBAdapter::getInstance()->getGlobalBool('forfeitable_captain_action'))
        {
            $this->addClientButton(\clienttranslate("Forfeit"), [$this, "actForfeit"], [], new ClientButtonParams(ClientButtonColor::ALERT));
        }

        if (count($selectablePiecesIds) == 0)
        {
            $this->addActionsDoableByAllPieces();
        }
        else
        {
            $this->addActionsDoableByPiece($selectablePiecesIds[0]); //this only happens in captain actions
        }

        $activePlayer = \intval($this->game->getActivePlayerId());
        $actionReturnState = DBAdapter::getInstance()->getPlayerActionReturnState($activePlayer);
        if ($actionReturnState === BeforeCardSelectionReset::class)
        {
            $this->addClientButton(\clienttranslate("Undo"), [$this, "actUndo"], [], new ClientButtonParams(ClientButtonColor::ALERT));
        }

        return [];
    }

    #[PossibleAction]
    public function actMove(int $activePlayerId): string
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePiecesIds = $pieceSelectionContext->getSelectablePieceIds();

        if (count($selectablePiecesIds) == 0) //if no piece is already select, then enforce.
        {
            MovePieceSelectionLogic::enforceMovePieceSelectionConstraints($this->game->getBoard(), $activePlayerId, MoveTypes::FREE);
        }

        $moveActionContext = new MoveActionContext();
        $moveActionContext->setCurrentMoveType(MoveTypes::FREE);

        return MovePieceSelection::class;
    }

    #[PossibleAction]
    public function actPassBall() : string
    {
        return PassDestSelection::class;
    }
    
    #[PossibleAction]
    public function actShootBall() : string
    {
        $this->game->giveExtraTime($this->game->getInactivePlayerId());

        $this->gamestate->setAllPlayersMultiactive();
        return ShootTargetSelection::class;
    }

    #[PossibleAction]
    public function actDribble() : string
    {
        return DribbleTargetSelection::class;
    }

    #[PossibleAction]
    public function actTackle() : string
    {
        return TacklePerformerSelection::class;
    }

    #[PossibleAction]
    public function actPass(int $activePlayerId) : string
    {
        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }

    #[PossibleAction]
    public function actUndo(): string
    {
        $activePlayer = \intval($this->game->getActivePlayerId());
        $actionReturnState = DBAdapter::getInstance()->getPlayerActionReturnState($activePlayer);
        if ($actionReturnState === BeforeCardSelectionReset::class)
        {
            CardSelection::undoLastCardPlayed($activePlayer, $this->game);
            return $actionReturnState;
        }
        else
        {
            throw new \BgaUserException("Undo is not possible, invalid starting state!");
        }
    }

    #[PossibleAction]
    public function actForfeit(int $activePlayerId): string
    {
        if (!DBAdapter::getInstance()->getGlobalBool('forfeitable_captain_action'))
            throw new \BgaUserException("Forfeit is not possible!");

        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }

    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie
        return '';
    }

    private function addActionsDoableByAllPieces() : void
    {
        $board = $this->game->getBoard();
        $activePlayerId = intval($this->game->getActivePlayerId());
        $pieceSelectableForMove = MovePieceSelectionLogic::extractFreeMoveMoveablePieces($board, $activePlayerId);

        $playerPieceWithBall = null;
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPieceWithBall))
        {
            if ($playerPieceWithBall->getOwner() === $activePlayerId)
            {
                $this->addClientButton(\clienttranslate('Pass ball'), [$this, 'actPassBall']);

                if ($playerPieceWithBall->getRole() !== PlayerRole::GoalKeeper->value)
                {
                    $this->addClientButton(\clienttranslate('Shoot'), [$this, 'actShootBall']);

                    if (BoardEvaluator::doesOpponentExistInNeighbours($board, $board->getBallHex(), $activePlayerId, false))
                    {
                        $this->addClientButton(\clienttranslate('Dribble'), [$this, 'actDribble']);
                    }
                }
            }
            else //so opponent has the ball!
            {
                $anyoneCanTackle = count(BoardEvaluator::getPiecesThatCanTackle($board, intval($this->game->getActivePlayerId()), $this->game->getInactivePlayerId())) > 0;

                if ($anyoneCanTackle)
                {
                    $this->addClientButton(\clienttranslate('Tackle'), [$this, 'actTackle']);
                }
            }
        }

        if (count($pieceSelectableForMove) > 0 &&
            ($playerPieceWithBall == null || $playerPieceWithBall->getOwner() !== $activePlayerId || $playerPieceWithBall->getRole() !== PlayerRole::GoalKeeper->value))
        {
            $this->addClientButton(\clienttranslate('Move'), [$this, 'actMove']);
        }
    }

    //todo: this can be the only logic for this class!
    private function addActionsDoableByPiece(int $pieceId) : void
    {
        $board = $this->game->getBoard();
        $activePlayerId = intval($this->game->getActivePlayerId());
        $piece = $board->getPiece($pieceId);

        $playerPieceWithBall = null;
        if($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPieceWithBall))
        {
            if ($playerPieceWithBall->getId() === $pieceId)
            {
                $this->addClientButton(\clienttranslate('Pass ball'), [$this, 'actPassBall']);
                $this->addClientButton(\clienttranslate('Shoot'), [$this, 'actShootBall']);

                if (BoardEvaluator::doesOpponentExistInNeighbours($board, $board->getBallHex(), $activePlayerId, false))
                {
                    $this->addClientButton(\clienttranslate('Dribble'), [$this, 'actDribble']);
                }
            }
            else
            {
                $piecesThatCanTackle = BoardEvaluator::getPiecesThatCanTackle($board, intval($this->game->getActivePlayerId()), $this->game->getInactivePlayerId());

                if (array_any($piecesThatCanTackle, fn($v)=> $v->getId() === $pieceId))
                {
                    $this->addClientButton(\clienttranslate('Tackle'), [$this, 'actTackle']);
                }
            }
        }

        $playerResources = new PlayerResources($activePlayerId);
        if (MovePieceSelectionLogic::canPieceFreeMove($board, $activePlayerId, $piece, $playerResources->getMorale()))
            $this->addClientButton(\clienttranslate('Move'), [$this, 'actMove']);

        if (count($this->getClientButtons()) == 0)
        {
            $this->addClientButton(\clienttranslate('Pass'), [$this, 'actPass'], [], new ClientButtonParams(ClientButtonColor::ALERT));
        }
    }
}
