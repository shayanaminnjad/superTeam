<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BypassLogic;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\PlayerPiece;
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
        $activePlayer = \intval($this->game->getActivePlayerId());

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
            $board = $this->game->getBoard();
            $this->addActionsDoableByPiece($board, $activePlayer, $board->getPiece($selectablePiecesIds[0])); //this only happens in captain actions
        }

        $this->addActionsAvailableButtons();

        if (count($this->getClientButtons()) == 0)
        {
            $this->addClientButton(\clienttranslate('Pass'), [$this, 'actPass'], [], new ClientButtonParams(ClientButtonColor::ALERT));
        }

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
    public function actBypass() : string
    {
        return BypassTargetSelection::class;
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
        return $this->actPass($playerId);
    }

    private function addActionsDoableByAllPieces() : void
    {
        $board = $this->game->getBoard();
        $activePlayerId = intval($this->game->getActivePlayerId());
        $playerPieces = $board->getPlayerPiecesByOwner($activePlayerId);

        $playerWithBall = null;
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerWithBall))
        {
            if ($playerWithBall->getOwner() === $activePlayerId && $playerWithBall->getRole() === PlayerRole::GoalKeeper->value)
            {
                if (!isset($this->actionsAvailable['Passball'])) {
                    $this->actionsAvailable['Passball']  = [\clienttranslate('Goalie Pass'), [$this, 'actPassBall']];
                }

                return;
            }
        }

        foreach ($playerPieces as $piece)
        {
            $this->addActionsDoableByPiece($board, $activePlayerId, $piece);
        }
    }

    private function addActionsDoableByPiece(Board $board, int $activePlayerId, PlayerPiece $actingPiece) : void
    {
        $playerPieceWithBall = null;
        if($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPieceWithBall))
        {
            if ($playerPieceWithBall->getId() === $actingPiece->getId())
            {
                if (!isset($this->actionsAvailable['Passball'])) {
                    $this->actionsAvailable['Passball']  = [\clienttranslate('Pass ball'), [$this, 'actPassBall']];
                }

                if (!isset($this->actionsAvailable['Shoot'])) {
                    $this->actionsAvailable['Shoot']  = [\clienttranslate('Shoot'), [$this, 'actShootBall']];
                }

                if (!isset($this->actionsAvailable['Dribble']) && !isset($this->actionsAvailable['Bypass']))
                {
                    $playerPowers = PlayerPowers::forPlayer($activePlayerId);

                    if ($playerPowers->bypassesOpponentOnDribble($actingPiece))
                    {
                        if (BypassLogic::getBypassTargets($board, $activePlayerId, $actingPiece->getPosition()))
                        {
                            $this->actionsAvailable['Bypass'] = [\clienttranslate('Bypass'), [$this, 'actBypass']];
                        }
                    }
                    else
                    {
                        $adjacentOpponentPieces = BoardEvaluator::getAdjacentOpponentPieces($board, $board->getBallHex(), $activePlayerId, false);
                        if (count($adjacentOpponentPieces) > 0)
                        {
                            $this->actionsAvailable['Dribble'] = [\clienttranslate('Dribble'), [$this, 'actDribble']];
                        }
                    }
                }
            }
            else if ($playerPieceWithBall->getOwner() !== $activePlayerId)
            {
                if (!isset($this->actionsAvailable['Tackle']))
                {
                    $piecesThatCanTackle = BoardEvaluator::getPiecesThatCanTackle($board, intval($this->game->getActivePlayerId()), $this->game->getInactivePlayerId());

                    if (array_any($piecesThatCanTackle, fn($v) => $v->getId() === $actingPiece->getId()))
                    {
                        $this->actionsAvailable['Tackle']  = [\clienttranslate('Tackle'),[$this, 'actTackle']];
                    }
                }
            }
        }

        if (!isset($this->actionsAvailable['Move']))
        {
            $playerResources = new PlayerResources($activePlayerId);
            if (MovePieceSelectionLogic::canPieceFreeMove($board, $actingPiece, $playerResources->getMorale()))
                $this->actionsAvailable['Move'] = [\clienttranslate('Move'),[$this, 'actMove']];
        }
    }

    private function addActionsAvailableButtons() : void
    {
        foreach ($this->actionsAvailable as [$label, $action]) {
            $this->addClientButton($label, $action);
        }
    }

    private $actionsAvailable = [];
}
