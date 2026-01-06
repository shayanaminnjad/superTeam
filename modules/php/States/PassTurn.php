<?php

 declare(strict_types=1);
 
 namespace Bga\Games\SuperTeam\States;
 
 use Bga\GameFramework\StateType;
 use Bga\GameFramework\States\GameState;
 use Bga\Games\SuperTeam\Game;
 
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
             updateGameProgression: false,
             initialPrivate: null,
         );
     }
 
     function onEnteringState(int $activePlayerId)
     {
         $playerHand = $this->game->getPlayerHand($activePlayerId);
         $playerHand->drawToFour();
         
         $this->game->notify->player($activePlayerId, "handRefill", "",
         ['playerHand' => $playerHand->getCardsInHand()]);
         
        $this->game->giveExtraTime($activePlayerId);
        $this->game->activeNextPlayer();

        return CardSelection::class;
     }
 }
 