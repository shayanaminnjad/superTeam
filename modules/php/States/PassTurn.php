<?php

 declare(strict_types=1);
 
 namespace Bga\Games\SuperTeam\States;
 
 use Bga\GameFramework\StateType;
 use Bga\GameFramework\States\GameState;
 use Bga\Games\SuperTeam\Game;
 use Bga\Games\SuperTeam\PlayerPiecesDBManager;
 
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
         $wasDiscardShuffled = $playerHand->drawToFour();

         $this->game->notify->player($activePlayerId, "handRefill", "",
         [
             'playerHand' => $playerHand->getCardsInHand(),
             'wasDiscardShuffled' => $wasDiscardShuffled,
             'deckCardCount' => $playerHand->CountCardsInDeck()
         ]);
         
        $this->game->giveExtraTime($activePlayerId);
        $nextPlayer = $this->game->activeNextPlayer();

        $this->game->DbQuery(PlayerPiecesDBManager::refreshPiecesQuery($nextPlayer));
        $this->game->notify->all("piecesRefreshed", "pieces refreshed", ["playerId" => $nextPlayer]);

        //if ($wasDiscardShuffled)
            //TODO: go to the state that handles halftime, fulltime!

        return CardSelection::class;
     }
 }
 