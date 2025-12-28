<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\PlayerRole;

class PlayerTurn extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            name: 'playerTurn',
            id: ST_PLAYER_TURN,
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
        $pieceSelectableForMove = $this->game->getSelectablePieces(null);

        //TODO: If the player with ball is the goal keeper, then only pass is possible!

        $activePlayerId = \intval($this->game->getActivePlayerId());

        $board = $this->game->getBoard();
        $playerPieceWithBall = null;
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPieceWithBall)) 
        {
            assert($playerPieceWithBall !== null); // useless, juse supress a warning.

            if ($playerPieceWithBall->getOwner() === $activePlayerId)
            {
                $this->addClientButton('pass ball', [$this, 'actPassBall']);

                if ($playerPieceWithBall->getRole() !== PlayerRole::GoalKeeper->value) 
                {
                    $this->addClientButton('shoot', [$this, 'actShootBall']);

                    if ($board->doesOpponentExistInNeighbours($board->getBallHex(), $activePlayerId, false)) 
                    {
                        $this->addClientButton('dribble', [$this, 'actDribble']);
                    }
                }
            }
            else //so opponent has the ball!
            {
                $anyoneCanTackle = \count($board->getPiecesThatCanTackle(\intval($this->game->getActivePlayerId()), $this->game->getInactivePlayerId())) > 0;

                if ($anyoneCanTackle)
                {
                    $this->addClientButton('tackle', [$this, 'actTackle']);
                }
            }
        }
        
        //TODO: what if the other goalie has the ball?
        if ((\count($pieceSelectableForMove['selectablePieces']) > 0 || \count($pieceSelectableForMove['selectableExhaustedPieces'])) > 0 &&
            ($playerPieceWithBall == null || $playerPieceWithBall->getRole() !== PlayerRole::GoalKeeper->value)) //FIXME: if opponent's goalie has the ball we should be able to move! 
        {
            $this->addClientButton('move', [$this, 'actMove']);
        }

        return [
            '_private' => [
                $activePlayerId => [
                    'playerHand' => $this->game->getPlayerHand($activePlayerId)->getCardsInHand()
                ]
            ]
        ];
    }

    #[PossibleAction]
    public function actMove(): string
    {
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
    public function actDebugAddMorale(int $amount)
    {
        if ($amount >= 0) {
            $this->game->setCurrentPlayerMorale($amount, true);
        }
    }

    #[PossibleAction]
    public function actDebugMoveBall(int $column, int $row)
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
