<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\ActionTypes;
use Bga\Games\SuperTeam\BasicTurnLogic;
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
use http\Exception\RuntimeException;
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

        $actionsAvailable = [];
        $board = $this->game->getBoard();
        $inactivePlayerId =  $this->game->getInactivePlayerId();
        if (count($selectablePiecesIds) == 0)
        {
            $actionsAvailable = BasicTurnLogic::getActionsDoableByAllPieces($board, $activePlayer, $inactivePlayerId);
        }
        else
        {
            $actionsAvailable = BasicTurnLogic::getActionsDoableByPiece($board, $board->getPiece($selectablePiecesIds[0]), $activePlayer, $inactivePlayerId); //this only happens in captain actions
        }

        $this->addActionsAvailableButtons($actionsAvailable);

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
        if (!BasicTurnLogic::getMoveAvailabilityInBasic())
            throw new \BgaUserException(clienttranslate("Movement not allowed now"));

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

        ShootTargetSelection::resetTargets($this->game);
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
        BypassLogic::setBypassRollRequirement(true);
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
            throw new \BgaUserException(\clienttranslate("Undo is not possible, invalid starting state!"));
        }
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
        return $this->actPass($playerId);
    }

    private function addActionsAvailableButtons(array $actions) : void
    {
        foreach ($actions as $action) {
            $button = $this->actionTypeToButton($action);
            $this->addClientButton($button[0], $button[1]);
        }
    }

    private function actionTypeToButton(ActionTypes $action) : array
    {
        if ($action === ActionTypes::GoaliePass)
            return [\clienttranslate('Goalie Pass'), [$this, 'actPassBall']];
        else if ($action === ActionTypes::PassBall)
            return [\clienttranslate('Pass ball'), [$this, 'actPassBall']];
        else if ($action === ActionTypes::Shoot)
            return [\clienttranslate('Shoot'), [$this, 'actShootBall']];
        else if ($action === ActionTypes::Bypass)
            return [\clienttranslate('Bypass'), [$this, 'actBypass']];
        else if ($action === ActionTypes::Dribble)
            return [\clienttranslate('Dribble'), [$this, 'actDribble']];
        else if ($action === ActionTypes::Tackle)
            return [\clienttranslate('Tackle'), [$this, 'actTackle']];
        else if ($action === ActionTypes::Move)
            return [\clienttranslate('Move'), [$this, 'actMove']];
        else
            throw new RuntimeException("Unknown action type!");
    }
}
