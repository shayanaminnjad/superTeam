<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * SuperTeam implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * SuperTeam game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: $this->checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

require_once("modules/php/StateMachineConstants.inc.php");

$machinestates = [

    // The initial state. Please do not modify.

    ST_BGA_GAME_SETUP => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => ST_PLAYER_PIECE_SETUP]
    ),

    ST_PLAYER_PIECE_SETUP => [
        "name" => "playerPieceSetup",
        "description" => clienttranslate('${actplayer} must place his player pieces'),
        "descriptionmyturn" => clienttranslate('${you} must place the indicated piece'),
        "type" => "activeplayer",
        "args" => "argPlayerPieceSetup",
        "possibleactions" => [
            // these actions are called from the front with bgaPerformAction, and matched to the function on the game.php file
            "actPlacedPiece", 
            "actDebugDefaultPlacePieces"
        ],
        "transitions" => ["playerPieceSetup" => ST_PLAYER_PIECE_SETUP, "setupComplete" => ST_SETUP_COMPLETE]
    ],

    ST_SETUP_COMPLETE => [
        "name" => "setupComplete",
        "type" => "game",
        "action" => "stSetupComplete",
        "transitions" => ["playerTurn" => ST_BEFORE_PLAYER_TURN, "playerPieceSetup" => ST_PLAYER_PIECE_SETUP]
    ],

    ST_BEFORE_PLAYER_TURN => [
        "name" => "beforePlayerTurn",
        "type" => "game",
        "action" => "stBeforePlayerTurn",
        "transitions" => [""=>ST_PLAYER_TURN]
    ],

    //TODO: add after player turn, here we draw back to player hand size

    //TODO: add half game state. fatigus are removed.

    ST_PLAYER_TURN => [
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must choose an action'),
        "descriptionmyturn" => clienttranslate('${you} must choose an action'),
        "type" => "activeplayer",
        "args" => "argActionSelection",
        "possibleactions" => [
            //(sha) TODO: I can have multiple actions, or a single action with a multiple parameters, I should see
            //which one fits better
            "actActionSelected",
            "actPassForward",
        ],
        "transitions" => ['nextPlayer' => ST_NEXT_PLAYER_TURN, 'playerTurn' => ST_PLAYER_TURN, 'moveAction' => ST_PIECE_SELECTION]
    ],

    ST_PIECE_SELECTION => [
        "name" => "pieceSelection",
        "description" => clienttranslate('${actplayer} is choosing a piece'),
        "descriptionmyturn" => clienttranslate('${you} must choose a piece'),
        "type" => "activeplayer",
        "args" => "argPieceSelection",
        "possibleactions" => [
            "actPieceSelected"
        ],

        "transitions" => ['moveAction' => ST_MOVE_ACTION]
    ],

    ST_MOVE_ACTION => [
        "name" => "moveAction",
        "description" => clienttranslate('${actplayer} is moving a piece'),
        "descriptionmyturn" => clienttranslate('move the selected piece'),
        "type" => "activeplayer",
        "args" => "argMoveAction",
        "possibleactions" => [
            "actPieceMoved",
            "actFinishedMove"
        ],

        "transitions" => ['moveAction' => ST_MOVE_ACTION, 'moveDone'=> ST_MOVE_FINISHED]
    ],

    ST_MOVE_FINISHED=> [
        "name" => "moveFinished",
        "description" => '',
        "type" => "game",
        "action" => "stMoveFinished",
        "transitions" => ["moveDone" => ST_PLAYER_TURN]
    ],

    ST_NEXT_PLAYER_TURN => [
        "name" => "nextPlayer",
        "description" => '',
        "type" => "game",
        "action" => "stNextPlayer",
        "updateGameProgression" => true,
        "transitions" => ["endGame" => ST_END_GAME, "nextPlayer" => ST_BEFORE_PLAYER_TURN]
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    ST_END_GAME => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    ],

];



