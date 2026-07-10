<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\MoveLogic;
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
        $board = $this->game->getBoard();
        $activePlayerId = intval($this->game->getActivePlayerId());
        $pieceSelectableForMove = MoveLogic::extractFreeMoveMoveablePieces($board, $activePlayerId);

        $playerPieceWithBall = null;
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPieceWithBall)) 
        {
            if ($playerPieceWithBall->getOwner() === $activePlayerId)
            {
                $this->addClientButton('pass ball', [$this, 'actPassBall']);

                if ($playerPieceWithBall->getRole() !== PlayerRole::GoalKeeper->value) 
                {
                    $this->addClientButton('shoot', [$this, 'actShootBall']);

                    if (BoardEvaluator::doesOpponentExistInNeighbours($board, $board->getBallHex(), $activePlayerId, false))
                    {
                        $this->addClientButton('dribble', [$this, 'actDribble']);
                    }
                }
            }
            else //so opponent has the ball!
            {
                $anyoneCanTackle = count(BoardEvaluator::getPiecesThatCanTackle($board, intval($this->game->getActivePlayerId()), $this->game->getInactivePlayerId())) > 0;

                if ($anyoneCanTackle)
                {
                    $this->addClientButton('tackle', [$this, 'actTackle']);
                }
            }
        }
        
        //TODO: what if the other goalie has the ball?
        if (count($pieceSelectableForMove) > 0 &&
            ($playerPieceWithBall == null || $playerPieceWithBall->getRole() !== PlayerRole::GoalKeeper->value)) //FIXME: if opponent's goalie has the ball we should be able to move! 
        {
            $this->addClientButton('move', [$this, 'actMove']);
        }

        return [];
    }

    #[PossibleAction]
    public function actMove(int $activePlayerId): string
    {
        MoveLogic::enforceMovePieceSelectionConstraints($this->game->getBoard(), $activePlayerId, MoveTypes::FREE);

        return PieceSelection::class;
    }

    #[PossibleAction]
    public function actPassBall() : string
    {
        return PassDestSelection::class;
    }
    
    #[PossibleAction]
    public function actShootBall() : string
    {
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
    public function actDebugAddMorale(int $amount) : void
    {
        if ($amount >= 0) {
            $playerResources = new PlayerResources(intval($this->game->getActivePlayerId()));
            $playerResources->setMorale($amount , true);
        }
    }

    #[PossibleAction]
    public function actDebugMoveBall(int $column, int $row) : void
    {
        $selectedHex = new HexID($column, $row);
        $this->game->getBoard()->setBallLocation($selectedHex);

        $this->notify->all('successfulPass', "successful pass", [
            "ballHex" => $selectedHex->convertToArray()
        ]);
    }


    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie
        return '';
    }
}
