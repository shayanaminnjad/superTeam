<?php

 declare(strict_types=1);
 
 namespace Bga\Games\SuperTeam\States;
 
 use Bga\GameFramework\StateType;
 use Bga\GameFramework\States\GameState;
 use Bga\Games\SuperTeam\Game;
 use Bga\Games\SuperTeam\NotificationAdapter;
 use Bga\Games\SuperTeam\PlayerPiecesDBManager;
 use Bga\Games\SuperTeam\TimeTrack;

 class PassTurn extends GameState
 {
     function __construct(
         protected Game $game,
     ) {
         parent::__construct(
             $game,
             id: ST_PASS_TURN,
             name: 'passTurn',
             type: StateType::GAME,
             transitions: [],
             updateGameProgression: true,
             initialPrivate: null,
         );
     }

     function onEnteringState(int $activePlayerId)
     {
         $playerHand = $this->game->getPlayerHand($activePlayerId);
         $wasDiscardShuffled = $playerHand->drawToFour();

         $this->game->notify->player($activePlayerId, "handRefill", "",
         [
             'playerHand' => $playerHand->getCardsInHand(),
             'wasDiscardShuffled' => $wasDiscardShuffled,
         ]);
         
        $this->game->giveExtraTime($activePlayerId);
        $nextPlayer = $this->game->activeNextPlayer();

        $this->game->DbQuery(PlayerPiecesDBManager::refreshPiecesQuery($nextPlayer));
        $this->game->notify->all("piecesRefreshed", "pieces refreshed", ["playerId" => $nextPlayer]);

        if ($wasDiscardShuffled)
        {
            $timeTrackPosition = TimeTrack::AdvanceTimeTrack(true);

            if (TimeTrack::IsHalfTime($timeTrackPosition))
            {
                return HalfTime::class;
            }
            else if (TimeTrack::IsGameFinished($timeTrackPosition))
            {
                return ST_END_GAME;
            }
            else
            {
                return CardSelection::class;
            }
        }
        else
        {
            return CardSelection::class;
        }
     }
 }
 